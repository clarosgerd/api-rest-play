<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Torneos disponibles por plan + temporada (16/10/2026, pedido real del
 * usuario) — camino 1 del onboarding ("elegir torneo configurado"). Free no
 * ve ligas externas reales, solo demo/promocionales; pro ve CONMEBOL +
 * demo; premium ve todo. "Temporada" = active (ya existente).
 */
class TournamentAvailableForMeTest extends TestCase
{
    use RefreshDatabase;

    private Tournament $demo;

    private Tournament $conmebol;

    private Tournament $european;

    private Tournament $inactive;

    protected function setUp(): void
    {
        parent::setUp();

        // Demo/privado: sin external_id, sin league_category.
        $this->demo = Tournament::create([
            'name' => 'Liga Amistosa Demo', 'season' => '2026', 'country' => 'N/A', 'country_code' => 'NA',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'active' => true,
        ]);

        $this->conmebol = Tournament::create([
            'name' => 'Copa Libertadores', 'season' => '2026', 'country' => 'CONMEBOL', 'country_code' => 'CO',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'active' => true,
            'external_id' => 13, 'league_category' => 'conmebol',
        ]);

        $this->european = Tournament::create([
            'name' => 'Premier League', 'season' => '2026', 'country' => 'England', 'country_code' => 'EN',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'active' => true,
            'external_id' => 2021, 'league_category' => 'european',
        ]);

        // Fuera de temporada: no debe aparecer para NADIE, ni siquiera premium.
        $this->inactive = Tournament::create([
            'name' => 'Liga Vieja', 'season' => '2024', 'country' => 'Spain', 'country_code' => 'ES',
            'start_date' => '2024-01-01', 'end_date' => '2024-12-31', 'active' => false,
            'external_id' => 2014, 'league_category' => 'european',
        ]);
    }

    private function userWithTier(string $tier): User
    {
        $user = User::create([
            'first_name' => 'Ana', 'last_name' => 'Test', 'email' => 'ana+'.$tier.'@example.com',
            'password_hash' => bcrypt('secret123'), 'subscription_tier' => $tier,
        ]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_free_only_sees_demo_tournaments(): void
    {
        $this->userWithTier('free');

        $response = $this->getJson('/api/v1/tournament/available-for-me')->assertOk();
        $ids = collect($response->json('data'))->pluck('tournament_id');

        $this->assertTrue($ids->contains($this->demo->tournament_id));
        $this->assertFalse($ids->contains($this->conmebol->tournament_id));
        $this->assertFalse($ids->contains($this->european->tournament_id));
    }

    public function test_pro_sees_conmebol_plus_demo_but_not_european(): void
    {
        $this->userWithTier('pro');

        $response = $this->getJson('/api/v1/tournament/available-for-me')->assertOk();
        $ids = collect($response->json('data'))->pluck('tournament_id');

        $this->assertTrue($ids->contains($this->demo->tournament_id));
        $this->assertTrue($ids->contains($this->conmebol->tournament_id));
        $this->assertFalse($ids->contains($this->european->tournament_id));
    }

    public function test_premium_sees_everything_in_season(): void
    {
        $this->userWithTier('premium');

        $response = $this->getJson('/api/v1/tournament/available-for-me')->assertOk();
        $ids = collect($response->json('data'))->pluck('tournament_id');

        $this->assertTrue($ids->contains($this->demo->tournament_id));
        $this->assertTrue($ids->contains($this->conmebol->tournament_id));
        $this->assertTrue($ids->contains($this->european->tournament_id));
    }

    public function test_out_of_season_tournament_never_appears(): void
    {
        $this->userWithTier('premium');

        $response = $this->getJson('/api/v1/tournament/available-for-me')->assertOk();
        $ids = collect($response->json('data'))->pluck('tournament_id');

        $this->assertFalse($ids->contains($this->inactive->tournament_id));
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/tournament/available-for-me')->assertStatus(401);
    }
}
