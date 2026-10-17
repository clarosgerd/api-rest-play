<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSupportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    // --- Autorización ---

    public function test_non_admin_cannot_list_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/admin/users')->assertStatus(403);
    }

    public function test_unauthenticated_cannot_list_users(): void
    {
        $this->getJson('/api/v1/admin/users')->assertStatus(401);
    }

    // --- Usuarios ---

    public function test_admin_can_list_and_filter_users_by_email(): void
    {
        $admin = $this->admin();
        User::factory()->create(['email' => 'target@example.com']);
        User::factory()->create(['email' => 'other@example.com']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/users?email=target');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('target@example.com', $response->json('data.0.email'));
    }

    public function test_admin_can_view_user_detail_with_subscription(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        Subscription::create([
            'user_id' => $user->user_id,
            'plan' => 'pro',
            'status' => 'active',
            'monthly_price' => 35,
            'annual_price' => 279,
        ]);

        $response = $this->actingAs($admin)->getJson("/api/v1/admin/users/{$user->user_id}");

        $response->assertOk()
            ->assertJsonPath('data.user_id', $user->user_id)
            ->assertJsonPath('data.subscription.plan', 'pro');
    }

    public function test_admin_can_update_users_tier(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['subscription_tier' => 'free']);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/users/{$user->user_id}", ['subscription_tier' => 'premium'])
            ->assertOk()
            ->assertJsonPath('data.subscription_tier', 'premium');

        $this->assertDatabaseHas('users', ['user_id' => $user->user_id, 'subscription_tier' => 'premium']);
    }

    public function test_admin_can_reset_another_users_password_and_revokes_tokens(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $oldToken = $user->createToken('auth_token')->plainTextToken;

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/admin/users/{$user->user_id}/reset-password");

        $response->assertOk()->assertJsonStructure(['temporary_password']);

        $newPassword = $response->json('temporary_password');
        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password_hash));
        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_admin_can_suspend_and_reactivate_a_user(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $user->createToken('auth_token');

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/users/{$user->user_id}/toggle-suspended")
            ->assertOk();

        $user->refresh();
        $this->assertNotNull($user->suspended_at);
        $this->assertCount(0, $user->tokens);

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/users/{$user->user_id}/toggle-suspended")
            ->assertOk();

        $this->assertNull($user->refresh()->suspended_at);
    }

    public function test_suspended_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'password_hash' => Hash::make('secret123'),
            'suspended_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertStatus(403);
    }

    // --- Suscripciones/pagos ---

    public function test_admin_can_list_subscriptions_filtered_by_status(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        Subscription::create([
            'user_id' => $user->user_id,
            'payment_provider' => 'sip',
            'sip_referencia' => 'SUB-STUCK123',
            'plan' => 'pro',
            'status' => 'pending_payment',
            'monthly_price' => 35,
            'annual_price' => 279,
        ]);
        Subscription::create([
            'user_id' => $user->user_id,
            'plan' => 'premium',
            'status' => 'active',
            'monthly_price' => 69,
            'annual_price' => 559,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/subscriptions?status=pending_payment');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('SUB-STUCK123', $data[0]['sip_referencia']);
    }

    public function test_admin_can_manually_confirm_a_stuck_sip_subscription(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['subscription_tier' => 'free']);
        $subscription = Subscription::create([
            'user_id' => $user->user_id,
            'payment_provider' => 'sip',
            'sip_referencia' => 'SUB-STUCK456',
            'plan' => 'pro',
            'status' => 'pending_payment',
            'is_annual' => false,
            'monthly_price' => 35,
            'annual_price' => 279,
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/admin/subscriptions/{$subscription->subscription_id}/confirm");

        $response->assertOk()->assertJsonPath('data.status', 'active');
        $this->assertEquals('pro', $user->fresh()->subscription_tier);
        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->subscription_id,
            'currency' => 'BOB',
            'status' => 'succeeded',
        ]);
    }

    public function test_confirming_an_already_cancelled_subscription_returns_422(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->user_id,
            'sip_referencia' => 'SUB-CANCELLED1',
            'plan' => 'pro',
            'status' => 'cancelled',
            'monthly_price' => 35,
            'annual_price' => 279,
        ]);

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/subscriptions/{$subscription->subscription_id}/confirm")
            ->assertStatus(422);
    }

    public function test_admin_can_list_all_payments(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->user_id,
            'plan' => 'pro',
            'status' => 'active',
            'monthly_price' => 35,
            'annual_price' => 279,
        ]);
        \App\Models\Payment::create([
            'user_id' => $user->user_id,
            'subscription_id' => $subscription->subscription_id,
            'amount' => 35,
            'currency' => 'BOB',
            'status' => 'succeeded',
            'description' => 'test',
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)->getJson('/api/v1/admin/payments')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
