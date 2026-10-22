<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\TournamentGroup;
use App\Models\TournamentGroupTeam;
use App\Models\TournamentStage;
use App\Services\FootballDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Persistir grupos/standings reales (22/10/2026) — antes `syncStandings()`
 * solo usaba la respuesta de football-data.org para crear equipos
 * faltantes; el campo `group` y la tabla de posiciones se descartaban sin
 * guardarse en `tournamentgroups`/`tournamentgroupteams`. Se mockea
 * FootballDataService para no pegarle a la API real (mismo criterio que el
 * resto de la suite).
 */
class SyncStandingsGroupsTest extends TestCase
{
    use RefreshDatabase;

    public function test_football_sync_standings_persists_groups_and_their_table(): void
    {
        $tournament = Tournament::create([
            'name' => 'Copa Libertadores',
            'season' => '2026',
            'country' => 'South America',
            'country_code' => 'SA',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'active' => true,
            'external_id' => 2152,
            'league_category' => 'conmebol',
        ]);

        $stage = TournamentStage::create([
            'tournament_id' => $tournament->tournament_id,
            'name' => 'GROUP_STAGE',
            'stage_order' => 1,
            'is_knockout' => false,
        ]);

        $this->mock(FootballDataService::class, function ($mock) {
            $mock->shouldReceive('getStandings')->andReturn([
                'standings' => [
                    [
                        'stage' => 'GROUP_STAGE',
                        'type' => 'TOTAL',
                        'group' => 'GROUP_A',
                        'table' => [
                            ['position' => 1, 'team' => ['id' => 101, 'name' => 'River Plate', 'crest' => null], 'playedGames' => 6, 'won' => 5, 'draw' => 1, 'lost' => 0, 'points' => 16, 'goalsFor' => 12, 'goalsAgainst' => 3, 'goalDifference' => 9],
                            ['position' => 2, 'team' => ['id' => 102, 'name' => 'Boca Juniors', 'crest' => null], 'playedGames' => 6, 'won' => 3, 'draw' => 1, 'lost' => 2, 'points' => 10, 'goalsFor' => 8, 'goalsAgainst' => 6, 'goalDifference' => 2],
                        ],
                    ],
                    [
                        'stage' => 'GROUP_STAGE',
                        'type' => 'HOME',
                        'group' => 'GROUP_A',
                        'table' => [],
                    ],
                    [
                        'stage' => 'GROUP_STAGE',
                        'type' => 'TOTAL',
                        'group' => 'GROUP_B',
                        'table' => [
                            ['position' => 1, 'team' => ['id' => 201, 'name' => 'Flamengo', 'crest' => null], 'playedGames' => 6, 'won' => 4, 'draw' => 2, 'lost' => 0, 'points' => 14, 'goalsFor' => 10, 'goalsAgainst' => 4, 'goalDifference' => 6],
                        ],
                    ],
                ],
            ]);
        });

        $this->artisan('football:sync', ['--type' => 'standings', '--competition' => 2152, '--force' => true])
            ->assertExitCode(0);

        $this->assertSame(2, TournamentGroup::where('stage_id', $stage->stage_id)->count());

        $groupA = TournamentGroup::where('stage_id', $stage->stage_id)->where('name', 'GROUP_A')->first();
        $this->assertNotNull($groupA);
        $this->assertSame('approved', $groupA->status);
        $this->assertSame(1, $groupA->group_order);
        $this->assertSame(2, TournamentGroupTeam::where('group_id', $groupA->group_id)->count());

        $river = TournamentGroupTeam::where('group_id', $groupA->group_id)
            ->whereHas('team', fn ($q) => $q->where('external_id', 101))
            ->first();
        $this->assertNotNull($river);
        $this->assertSame(1, $river->position_final);
        $this->assertSame(16, $river->points);
        $this->assertSame(9, $river->goal_diff);
        $this->assertSame(5, $river->wins);
        $this->assertSame(1, $river->draws);
        $this->assertSame(0, $river->losses);

        $groupB = TournamentGroup::where('stage_id', $stage->stage_id)->where('name', 'GROUP_B')->first();
        $this->assertSame(2, $groupB->group_order);
        $this->assertSame(1, TournamentGroupTeam::where('group_id', $groupB->group_id)->count());
    }

    public function test_group_without_matching_stage_yet_is_skipped_not_created_blindly(): void
    {
        Tournament::create([
            'name' => 'Copa Libertadores',
            'season' => '2026',
            'country' => 'South America',
            'country_code' => 'SA',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'active' => true,
            'external_id' => 2152,
            'league_category' => 'conmebol',
        ]);

        $this->mock(FootballDataService::class, function ($mock) {
            $mock->shouldReceive('getStandings')->andReturn([
                'standings' => [
                    [
                        'stage' => 'GROUP_STAGE',
                        'type' => 'TOTAL',
                        'group' => 'GROUP_A',
                        'table' => [
                            ['position' => 1, 'team' => ['id' => 101, 'name' => 'River Plate'], 'playedGames' => 0, 'won' => 0, 'draw' => 0, 'lost' => 0, 'points' => 0, 'goalsFor' => 0, 'goalsAgainst' => 0, 'goalDifference' => 0],
                        ],
                    ],
                ],
            ]);
        });

        $this->artisan('football:sync', ['--type' => 'standings', '--competition' => 2152, '--force' => true])
            ->assertExitCode(0);

        $this->assertSame(0, TournamentGroup::count());
    }
}
