<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Crear grupo propio ahora exige Pro/Premium (20/10/2026, pedido real del
 * usuario) — free/predictor quedan en members_per_group=0. Gap real
 * encontrado al implementar: POST /group no tenía NINGÚN middleware de
 * límites antes de este cambio. Mismo patrón que PredictionSubscriptionGateTest
 * para el gate hermano de predicciones.
 */
class GroupCreationLimitsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithTier(string $tier): User
    {
        $user = User::create([
            'first_name' => 'Ana', 'last_name' => 'Test', 'email' => 'ana+'.$tier.'@example.com',
            'password_hash' => bcrypt('secret123'), 'subscription_tier' => $tier,
        ]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Mi Grupo', 'description' => 'desc',
            'is_public' => true, 'max_members' => 10,
        ], $overrides);
    }

    public function test_free_user_cannot_create_a_group(): void
    {
        $this->userWithTier('free');

        $response = $this->postJson('/api/v1/group', $this->payload());

        $response->assertStatus(403);
        $this->assertDatabaseMissing('groups', ['name' => 'Mi Grupo']);
    }

    public function test_predictor_user_cannot_create_a_group(): void
    {
        $this->userWithTier('predictor');

        $this->postJson('/api/v1/group', $this->payload())->assertStatus(403);
    }

    public function test_pro_user_can_create_a_group(): void
    {
        $this->userWithTier('pro');

        $this->postJson('/api/v1/group', $this->payload())->assertCreated();
    }

    public function test_premium_user_can_create_a_group(): void
    {
        $this->userWithTier('premium');

        $this->postJson('/api/v1/group', $this->payload())->assertCreated();
    }

    /** group/first es la misma acción por otro endpoint — mismo gate. */
    public function test_free_user_cannot_use_group_first_either(): void
    {
        $this->userWithTier('free');

        $this->postJson('/api/v1/group/first', $this->payload())->assertStatus(403);
    }

    public function test_pro_user_can_use_group_first(): void
    {
        $this->userWithTier('pro');

        $this->postJson('/api/v1/group/first', $this->payload())->assertCreated();
    }
}
