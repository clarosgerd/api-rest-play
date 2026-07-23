<?php

namespace Tests\Feature;

use App\Models\Matches;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MatchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $home;

    private Team $away;

    private Tournament $tournament;

    private TournamentStage $stage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password_hash' => bcrypt('secret123'),
        ]);

        $this->home = Team::create([
            'name' => 'Barcelona',
            'country' => 'Spain',
            'league' => 'La Liga',
            'country_code' => 'ES',
            'active' => true,
        ]);

        $this->away = Team::create([
            'name' => 'Real Madrid',
            'country' => 'Spain',
            'league' => 'La Liga',
            'country_code' => 'ES',
            'active' => true,
        ]);

        $this->tournament = Tournament::create([
            'name' => 'La Liga',
            'season' => '2025-2026',
            'country' => 'Spain',
            'country_code' => 'ES',
            'start_date' => '2025-08-01',
            'end_date' => '2026-05-31',
            'active' => true,
        ]);

        $this->stage = TournamentStage::create([
            'tournament_id' => $this->tournament->tournament_id,
            'name' => 'Regular Season',
            'stage_order' => 1,
            'is_knockout' => false,
        ]);
    }

    private function matchData(): array
    {
        return [
            'home_team_id' => $this->home->team_id,
            'away_team_id' => $this->away->team_id,
            'tournament_id' => $this->tournament->tournament_id,
            'stage_id' => $this->stage->stage_id,
            'match_date' => '2026-01-15 20:00:00',
            'status' => 'scheduled',
            'home_score' => 0,
            'away_score' => 0,
        ];
    }

    public function test_list_matches(): void
    {
        Matches::create($this->matchData());

        $response = $this->getJson('/api/v1/match');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_show_match(): void
    {
        $match = Matches::create($this->matchData());

        $response = $this->getJson("/api/v1/match/{$match->match_id}");

        $response->assertStatus(200);
    }

    public function test_store_match_authenticated(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/match', $this->matchData());

        $response->assertStatus(201);
        $this->assertDatabaseHas('matches', [
            'home_team_id' => $this->home->team_id,
            'away_team_id' => $this->away->team_id,
        ]);
    }

    public function test_store_match_unauthenticated_fails(): void
    {
        $response = $this->postJson('/api/v1/match', $this->matchData());

        $response->assertStatus(401);
    }

    public function test_update_match(): void
    {
        Sanctum::actingAs($this->user);
        $match = Matches::create($this->matchData());

        $response = $this->putJson("/api/v1/match/{$match->match_id}", array_merge($this->matchData(), [
            'status' => 'finished',
            'home_score' => 2,
            'away_score' => 1,
        ]));

        $response->assertStatus(200);
        $this->assertDatabaseHas('matches', [
            'match_id' => $match->match_id,
            'home_score' => 2,
            'away_score' => 1,
        ]);
    }

    public function test_delete_match(): void
    {
        Sanctum::actingAs($this->user);
        $match = Matches::create($this->matchData());

        $response = $this->deleteJson("/api/v1/match/{$match->match_id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('matches', ['match_id' => $match->match_id]);
    }
}
