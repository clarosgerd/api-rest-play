<?php

namespace Tests\Feature;

use App\Models\GroupMembers;
use App\Models\Groups;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Sync manual por el dueño de su torneo público (16/10/2026, pedido real
 * del usuario) — a diferencia de SyncController::sync() (admin-only), esto
 * lo puede disparar el dueño de un grupo ligado al torneo. Se mockea la
 * fachada Artisan para no pegarle de verdad a API-Football/football-data.org
 * desde el test (mismo criterio que el resto de la suite: sqlite en
 * memoria, sin servicios externos reales).
 */
class TournamentSyncByOwnerTest extends TestCase
{
    use RefreshDatabase;

    private function userWithTier(string $tier, string $role = 'user'): User
    {
        return User::create([
            'first_name' => 'Ana', 'last_name' => 'Test', 'email' => 'ana+'.$tier.'-'.$role.'@example.com',
            'password_hash' => bcrypt('secret123'), 'subscription_tier' => $tier, 'role' => $role,
        ]);
    }

    public function test_unrelated_user_cannot_sync_a_tournament(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa Libertadores', 'season' => '2026', 'country' => 'CONMEBOL', 'country_code' => 'CO',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'active' => true,
            'external_id' => 13, 'league_category' => 'conmebol',
        ]);
        Sanctum::actingAs($this->userWithTier('free'));

        $this->postJson("/api/v1/tournament/{$tournament->tournament_id}/sync")->assertStatus(403);
    }

    public function test_group_owner_can_sync_a_conmebol_tournament(): void
    {
        $owner = $this->userWithTier('pro');
        $tournament = Tournament::create([
            'name' => 'Copa Libertadores', 'season' => '2026', 'country' => 'CONMEBOL', 'country_code' => 'CO',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'active' => true,
            'external_id' => 13, 'league_category' => 'conmebol',
        ]);
        $group = Groups::create([
            'name' => 'Mi grupo', 'description' => 'desc', 'creator_id' => $owner->user_id,
            'is_public' => false, 'invitation_code' => 'ABC1234', 'tournament_id' => $tournament->tournament_id,
        ]);
        GroupMembers::create([
            'group_id' => $group->group_id, 'user_id' => $owner->user_id,
            'joined_at' => now(), 'is_admin' => 1, 'status' => 'accepted',
        ]);
        Sanctum::actingAs($owner);

        Artisan::shouldReceive('call')
            ->once()
            ->with('conmebol:sync', ['--competition' => 'libertadores'])
            ->andReturn(0);
        Artisan::shouldReceive('output')->andReturn('ok');

        $this->postJson("/api/v1/tournament/{$tournament->tournament_id}/sync")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_tournament_creator_can_sync_a_european_tournament(): void
    {
        $owner = $this->userWithTier('premium');
        $tournament = Tournament::create([
            'name' => 'Premier League', 'season' => '2026', 'country' => 'England', 'country_code' => 'EN',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'active' => true,
            'external_id' => 2021, 'league_category' => 'european', 'created_by' => $owner->user_id,
        ]);
        Sanctum::actingAs($owner);

        Artisan::shouldReceive('call')
            ->once()
            ->with('football:sync', ['--competition' => '2021', '--type' => 'matches'])
            ->andReturn(0);
        Artisan::shouldReceive('output')->andReturn('ok');

        $this->postJson("/api/v1/tournament/{$tournament->tournament_id}/sync")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_owner_can_sync_a_world_cup_tournament(): void
    {
        $owner = $this->userWithTier('premium');
        $tournament = Tournament::create([
            'name' => 'World Cup 2026', 'season' => '2026', 'country' => 'World', 'country_code' => 'WW',
            'start_date' => '2026-06-11', 'end_date' => '2026-07-19', 'active' => true,
            'external_id' => 1, 'league_category' => 'worldcup', 'created_by' => $owner->user_id,
        ]);
        Sanctum::actingAs($owner);

        Artisan::shouldReceive('call')
            ->once()
            ->with('worldcup:sync', ['--season' => '2026', '--type' => 'matches'])
            ->andReturn(0);
        Artisan::shouldReceive('output')->andReturn('ok');

        $this->postJson("/api/v1/tournament/{$tournament->tournament_id}/sync")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_sync_any_tournament(): void
    {
        $admin = $this->userWithTier('free', 'admin');
        $tournament = Tournament::create([
            'name' => 'Copa Sudamericana', 'season' => '2026', 'country' => 'CONMEBOL', 'country_code' => 'CO',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'active' => true,
            'external_id' => 11, 'league_category' => 'conmebol',
        ]);
        Sanctum::actingAs($admin);

        Artisan::shouldReceive('call')
            ->once()
            ->with('conmebol:sync', ['--competition' => 'sudamericana'])
            ->andReturn(0);
        Artisan::shouldReceive('output')->andReturn('ok');

        $this->postJson("/api/v1/tournament/{$tournament->tournament_id}/sync")->assertOk();
    }

    public function test_private_or_demo_tournament_cannot_be_synced(): void
    {
        $owner = $this->userWithTier('premium');
        $tournament = Tournament::create([
            'name' => 'Campeonato de papás', 'season' => '2026', 'country' => 'N/A', 'country_code' => 'NA',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'active' => true,
            'created_by' => $owner->user_id,
        ]);
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/tournament/{$tournament->tournament_id}/sync")->assertStatus(422);
    }
}
