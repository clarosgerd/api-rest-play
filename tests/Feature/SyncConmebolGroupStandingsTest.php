<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\TournamentGroup;
use App\Models\TournamentGroupTeam;
use App\Services\ApiFootballService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Grupos/standings de CONMEBOL vía API-Football (22/10/2026) — dejado
 * armado para cuando haya plan de pago (hoy el free plan responde
 * `errors.plan` para la temporada actual, confirmado a mano contra la API
 * real; solo responde con datos para 2022-2024). `conmebol:sync` nunca
 * pedía standings en absoluto; ahora los pide y, si vienen, los persiste en
 * tournamentgroups/tournamentgroupteams (mismo problema que tenía
 * football-data.org, ver SyncStandingsGroupsTest). Se mockea
 * ApiFootballService para no pegarle a la API real.
 */
class SyncConmebolGroupStandingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_conmebol_sync_persists_groups_when_api_football_returns_standings(): void
    {
        $this->mock(ApiFootballService::class, function ($mock) {
            $mock->shouldReceive('getTeams')->andReturn(['response' => []]);
            $mock->shouldReceive('getFixtures')->andReturn(['response' => []]);
            $mock->shouldReceive('getStandings')->andReturn([
                'response' => [
                    [
                        'league' => [
                            'standings' => [
                                [
                                    ['rank' => 1, 'team' => ['id' => 301, 'name' => 'River Plate', 'logo' => null], 'points' => 16, 'goalsDiff' => 9, 'group' => 'Group A', 'all' => ['played' => 6, 'win' => 5, 'draw' => 1, 'lose' => 0, 'goals' => ['for' => 12, 'against' => 3]]],
                                    ['rank' => 2, 'team' => ['id' => 302, 'name' => 'Boca Juniors', 'logo' => null], 'points' => 10, 'goalsDiff' => 2, 'group' => 'Group A', 'all' => ['played' => 6, 'win' => 3, 'draw' => 1, 'lose' => 2, 'goals' => ['for' => 8, 'against' => 6]]],
                                ],
                                [
                                    ['rank' => 1, 'team' => ['id' => 303, 'name' => 'Flamengo', 'logo' => null], 'points' => 14, 'goalsDiff' => 6, 'group' => 'Group B', 'all' => ['played' => 6, 'win' => 4, 'draw' => 2, 'lose' => 0, 'goals' => ['for' => 10, 'against' => 4]]],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        });

        $this->artisan('conmebol:sync', ['--competition' => 'libertadores', '--season' => '2024'])
            ->assertExitCode(0);

        $tournament = Tournament::where('external_id', 13)->first();
        $this->assertNotNull($tournament);

        $groupA = TournamentGroup::where('name', 'Group A')->first();
        $this->assertNotNull($groupA);
        $this->assertSame('approved', $groupA->status);
        $this->assertSame(1, $groupA->group_order);
        $this->assertSame(2, TournamentGroupTeam::where('group_id', $groupA->group_id)->count());

        $river = TournamentGroupTeam::where('group_id', $groupA->group_id)
            ->whereHas('team', fn ($q) => $q->where('external_id', 301))
            ->first();
        $this->assertNotNull($river);
        $this->assertSame(1, $river->position_final);
        $this->assertSame(16, $river->points);
        $this->assertSame(9, $river->goal_diff);

        $groupB = TournamentGroup::where('name', 'Group B')->first();
        $this->assertSame(2, $groupB->group_order);
        $this->assertSame(1, TournamentGroupTeam::where('group_id', $groupB->group_id)->count());
    }

    public function test_conmebol_sync_does_not_crash_when_free_plan_rejects_the_season(): void
    {
        $this->mock(ApiFootballService::class, function ($mock) {
            $mock->shouldReceive('getTeams')->andReturn(['response' => []]);
            $mock->shouldReceive('getFixtures')->andReturn(['response' => []]);
            $mock->shouldReceive('getStandings')->andReturn([
                'errors' => ['plan' => 'Free plans do not have access to this season, try from 2022 to 2024.'],
                'response' => [],
            ]);
        });

        $this->artisan('conmebol:sync', ['--competition' => 'libertadores', '--season' => '2026'])
            ->assertExitCode(0);

        $this->assertSame(0, TournamentGroup::count());
    }
}
