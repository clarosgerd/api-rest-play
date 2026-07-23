<?php

namespace Tests\Feature;

use App\Models\Matches;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentGroup;
use App\Models\TournamentGroupTeam;
use App\Models\TournamentStage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BracketTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tournament $tournament;

    private TournamentStage $groupStage;

    private TournamentStage $knockoutStage;

    private TournamentGroup $groupA;

    private Team $team1;

    private Team $team2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password_hash' => bcrypt('secret123'),
        ]);

        $this->tournament = Tournament::create([
            'name' => 'Copa Test',
            'season' => '2026',
            'country' => 'Global',
            'country_code' => 'GL',
            'start_date' => '2026-06-01',
            'end_date' => '2026-07-15',
            'active' => true,
        ]);

        $this->groupStage = TournamentStage::create([
            'tournament_id' => $this->tournament->tournament_id,
            'name' => 'Fase de Grupos',
            'stage_order' => 1,
            'is_knockout' => false,
        ]);

        $this->knockoutStage = TournamentStage::create([
            'tournament_id' => $this->tournament->tournament_id,
            'name' => 'Octavos',
            'stage_order' => 2,
            'is_knockout' => true,
        ]);

        $this->groupA = TournamentGroup::create([
            'stage_id' => $this->groupStage->stage_id,
            'name' => 'Grupo A',
            'group_order' => 1,
            'status' => 'approved',
        ]);

        $this->team1 = Team::create(['name' => 'Brasil', 'country' => 'Brazil', 'country_code' => 'BR', 'active' => true]);
        $this->team2 = Team::create(['name' => 'Argentina', 'country' => 'Argentina', 'country_code' => 'AR', 'active' => true]);

        TournamentGroupTeam::create([
            'group_id' => $this->groupA->group_id,
            'team_id' => $this->team1->team_id,
            'points' => 9,
            'goal_diff' => 5,
            'goals_for' => 7,
            'goals_against' => 2,
            'matches_played' => 3,
            'wins' => 3,
            'draws' => 0,
            'losses' => 0,
        ]);

        TournamentGroupTeam::create([
            'group_id' => $this->groupA->group_id,
            'team_id' => $this->team2->team_id,
            'points' => 6,
            'goal_diff' => 2,
            'goals_for' => 5,
            'goals_against' => 3,
            'matches_played' => 3,
            'wins' => 2,
            'draws' => 0,
            'losses' => 1,
        ]);

        Matches::create([
            'home_team_id' => $this->team1->team_id,
            'away_team_id' => $this->team2->team_id,
            'tournament_id' => $this->tournament->tournament_id,
            'stage_id' => $this->groupStage->stage_id,
            'match_date' => Carbon::now()->subDays(1),
            'status' => 'finished',
            'home_score' => 2,
            'away_score' => 1,
        ]);
    }

    public function test_get_full_bracket(): void
    {
        $response = $this->getJson("/api/v1/tournament/{$this->tournament->tournament_id}/bracket");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'tournament_id',
                'tournament_name',
                'bracket' => [
                    [
                        'stage_id',
                        'name',
                        'is_knockout',
                        'stage_order',
                    ],
                ],
            ]);
    }

    public function test_get_stage_bracket(): void
    {
        $response = $this->getJson("/api/v1/tournament/{$this->tournament->tournament_id}/bracket/{$this->groupStage->stage_id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'tournament_id',
                'tournament_name',
                'bracket' => [
                    'stage_id',
                    'name',
                    'is_knockout',
                    'groups' => [
                        [
                            'group_id',
                            'name',
                            'standings',
                            'matches',
                        ],
                    ],
                ],
            ]);
    }

    public function test_bracket_includes_group_standings(): void
    {
        $response = $this->getJson("/api/v1/tournament/{$this->tournament->tournament_id}/bracket/{$this->groupStage->stage_id}");

        $response->assertStatus(200)
            ->assertJson([
                'bracket' => [
                    'groups' => [
                        [
                            'group_id' => $this->groupA->group_id,
                            'standings' => [
                                ['team_id' => $this->team1->team_id, 'points' => 9],
                                ['team_id' => $this->team2->team_id, 'points' => 6],
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function test_bracket_includes_group_matches(): void
    {
        $response = $this->getJson("/api/v1/tournament/{$this->tournament->tournament_id}/bracket/{$this->groupStage->stage_id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'bracket' => [
                    'groups' => [
                        [
                            'matches' => [
                                [
                                    'match_id',
                                    'home' => ['team_id', 'name'],
                                    'away' => ['team_id', 'name'],
                                    'home_score',
                                    'away_score',
                                    'status',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function test_empty_bracket_for_nonexistent_tournament(): void
    {
        $response = $this->getJson('/api/v1/tournament/9999/bracket');

        $response->assertStatus(404);
    }

    public function test_knockout_stage_bracket(): void
    {
        $response = $this->getJson("/api/v1/tournament/{$this->tournament->tournament_id}/bracket/{$this->knockoutStage->stage_id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'bracket' => [
                    'stage_id',
                    'name',
                    'is_knockout',
                    'matches',
                ],
            ]);
    }
}
