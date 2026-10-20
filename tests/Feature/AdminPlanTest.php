<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * CRUD de planes/promocionales para super admin (20/10/2026, pedido real del
 * usuario) — reemplaza Subscription::getPlans()/User::getTierLimits()
 * hardcodeados. Las 4 filas base (free/predictor/pro/premium) ya existen tras
 * la migración (seed), así que estos tests parten de ellas en vez de crearlas.
 */
class AdminPlanTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_non_admin_cannot_list_plans(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/admin/plans')->assertStatus(403);
    }

    public function test_unauthenticated_cannot_list_plans(): void
    {
        $this->getJson('/api/v1/admin/plans')->assertStatus(401);
    }

    public function test_migration_seeds_the_4_base_plans(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/plans')->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug');

        $this->assertEqualsCanonicalizing(['free', 'predictor', 'pro', 'premium'], $slugs->all());
    }

    public function test_admin_can_create_a_new_plan(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/plans', [
            'slug' => 'vip',
            'name' => 'VIP',
            'monthly_price_bob' => 99,
            'tournament_limit' => -1,
            'members_per_group' => -1,
            'predictions_per_day' => -1,
            'external_leagues' => ['all'],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('plans', ['slug' => 'vip', 'name' => 'VIP']);
    }

    public function test_cannot_create_a_plan_with_duplicate_slug(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/api/v1/admin/plans', [
            'slug' => 'pro',
            'name' => 'Otro Pro',
            'tournament_limit' => 1,
            'members_per_group' => 1,
            'predictions_per_day' => 1,
        ])->assertStatus(422);
    }

    /** Retirar un plan (is_active=false) no debe romper a quien ya lo tiene. */
    public function test_admin_can_deactivate_a_plan_without_affecting_existing_subscribers(): void
    {
        $admin = $this->admin();
        $pro = Plan::where('slug', 'pro')->firstOrFail();
        $subscriber = User::factory()->create(['subscription_tier' => 'pro']);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/plans/{$pro->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        // getTierLimits() resuelve por slug directamente, no por is_active —
        // un suscripto viejo sigue viendo sus límites normales.
        $this->assertEquals(5, $subscriber->fresh()->getTierLimits()['tournaments']);

        // Pero ya no aparece en el catálogo de planes pagos a la venta.
        $this->assertArrayNotHasKey('pro', \App\Models\Subscription::getPlans());
    }

    public function test_admin_can_update_plan_limits(): void
    {
        $admin = $this->admin();
        $pro = Plan::where('slug', 'pro')->firstOrFail();

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/plans/{$pro->id}", ['tournament_limit' => 8])
            ->assertOk()
            ->assertJsonPath('data.tournament_limit', 8);

        $subscriber = User::factory()->create(['subscription_tier' => 'pro']);
        $this->assertEquals(8, $subscriber->getTierLimits()['tournaments']);
    }

    public function test_admin_can_create_and_apply_a_promotion(): void
    {
        $admin = $this->admin();
        $predictor = Plan::where('slug', 'predictor')->firstOrFail();

        $response = $this->actingAs($admin)->postJson("/api/v1/admin/plans/{$predictor->id}/promotions", [
            'name' => 'Lanzamiento',
            'monthly_price_bob' => 5,
            'starts_at' => now()->subDay()->toDateTimeString(),
            'ends_at' => now()->addWeek()->toDateTimeString(),
        ]);

        $response->assertCreated();

        $plans = \App\Models\Subscription::getPlans();
        $this->assertEquals(5, $plans['predictor']['monthly']['price_bob']);
    }

    public function test_expired_promotion_does_not_apply(): void
    {
        $admin = $this->admin();
        $predictor = Plan::where('slug', 'predictor')->firstOrFail();

        $this->actingAs($admin)->postJson("/api/v1/admin/plans/{$predictor->id}/promotions", [
            'name' => 'Ya vencida',
            'monthly_price_bob' => 5,
            'ends_at' => now()->subDay()->toDateTimeString(),
        ])->assertCreated();

        $plans = \App\Models\Subscription::getPlans();
        $this->assertEquals(14, $plans['predictor']['monthly']['price_bob']);
    }

    /**
     * Un plan nuevo creado por el admin debe aparecer en el catálogo público
     * de planes sin deploy (20/10/2026) — antes `SubscriptionController::
     * plans()` hardcodeaba `['predictor', 'pro', 'premium']`.
     */
    public function test_a_newly_created_plan_appears_in_the_public_plans_listing(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson('/api/v1/admin/plans', [
            'slug' => 'vip', 'name' => 'VIP', 'monthly_price_bob' => 99,
            'tournament_limit' => -1, 'members_per_group' => -1, 'predictions_per_day' => -1,
        ])->assertCreated();

        $user = User::factory()->create();
        $response = $this->actingAs($user)->getJson('/api/v1/user/subscription/plans')->assertOk();

        $plans = collect($response->json('data'))->keyBy('plan');
        $this->assertTrue($plans->has('vip'));
        $this->assertEquals('VIP', $plans['vip']['name']);
        $this->assertEquals(99, $plans['vip']['monthly_price']);
    }

    public function test_user_can_checkout_a_newly_created_plan(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson('/api/v1/admin/plans', [
            'slug' => 'vip', 'name' => 'VIP', 'monthly_price_bob' => 99,
            'tournament_limit' => -1, 'members_per_group' => -1, 'predictions_per_day' => -1,
        ])->assertCreated();

        Http::fake([
            '*autenticacion*' => Http::response(['token' => 'fake-jwt-token'], 200),
            '*generaQr*' => Http::response(['objeto' => ['imagenQr' => 'qr', 'idQr' => 'QR-1']], 200),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user)
            ->postJson('/api/v1/user/subscription/checkout', ['plan' => 'vip', 'billing' => 'monthly'])
            ->assertStatus(201);

        $this->assertDatabaseHas('subscriptions', ['user_id' => $user->user_id, 'plan' => 'vip']);
    }

    public function test_admin_can_update_a_promotion(): void
    {
        $admin = $this->admin();
        $predictor = Plan::where('slug', 'predictor')->firstOrFail();
        $promo = $predictor->promotions()->create([
            'name' => 'Lanzamiento', 'monthly_price_bob' => 5, 'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/plans/{$predictor->id}/promotions/{$promo->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $plans = \App\Models\Subscription::getPlans();
        $this->assertEquals(14, $plans['predictor']['monthly']['price_bob']);
    }
}
