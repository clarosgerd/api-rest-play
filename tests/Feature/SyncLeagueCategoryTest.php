<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Services\ApiFootballService;
use App\Services\FootballDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * league_category poblado al sincronizar (16/10/2026) — reemplaza los
 * rangos de external_id hardcodeados por un campo explícito. Se mockean
 * FootballDataService/ApiFootballService para no pegarle a las APIs
 * externas reales desde el test (mismo criterio que el resto de la suite).
 */
class SyncLeagueCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_football_sync_marks_copa_libertadores_as_conmebol_and_the_rest_as_european(): void
    {
        $this->mock(FootballDataService::class, function ($mock) {
            $mock->shouldReceive('getCompetitions')->andReturn([
                'competitions' => [
                    ['id' => 2152, 'name' => 'Copa Libertadores', 'area' => ['name' => 'South America', 'code' => 'SA']],
                    ['id' => 2021, 'name' => 'Premier League', 'area' => ['name' => 'England', 'code' => 'EN']],
                ],
            ]);
        });

        $this->artisan('football:sync', ['--type' => 'competitions'])->assertExitCode(0);

        $this->assertSame('conmebol', Tournament::where('external_id', 2152)->value('league_category'));
        $this->assertSame('european', Tournament::where('external_id', 2021)->value('league_category'));
    }

    public function test_conmebol_sync_always_marks_tournaments_as_conmebol(): void
    {
        $this->mock(ApiFootballService::class, function ($mock) {
            $mock->shouldReceive('getTeams')->andReturn(['response' => []]);
            $mock->shouldReceive('getFixtures')->andReturn(['response' => []]);
            $mock->shouldReceive('getStandings')->andReturn(['response' => []]);
        });

        $this->artisan('conmebol:sync', ['--competition' => 'libertadores'])->assertExitCode(0);

        $tournament = Tournament::where('external_id', 13)->first();
        $this->assertNotNull($tournament);
        $this->assertSame('conmebol', $tournament->league_category);
    }

    public function test_worldcup_sync_marks_the_tournament_as_worldcup(): void
    {
        $this->mock(ApiFootballService::class, function ($mock) {
            $mock->shouldReceive('getTeams')->andReturn(['response' => []]);
        });

        $this->artisan('worldcup:sync', ['--season' => 2026, '--type' => 'teams'])->assertExitCode(0);

        $tournament = Tournament::where('external_id', 1)->where('season', '2026')->first();
        $this->assertNotNull($tournament);
        $this->assertSame('worldcup', $tournament->league_category);
    }
}
