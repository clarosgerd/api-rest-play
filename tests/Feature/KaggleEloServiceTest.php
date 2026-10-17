<?php

namespace Tests\Feature;

use App\Services\KaggleEloService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KaggleEloServiceTest extends TestCase
{
    use RefreshDatabase;

    private KaggleEloService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new KaggleEloService();
    }

    private function insertResult(string $home, string $away, int $homeScore, int $awayScore, string $date, string $tournament = 'Friendly', bool $neutral = false): void
    {
        DB::table('kaggle_results')->insert([
            'match_date' => $date, 'home_team' => $home, 'away_team' => $away,
            'home_score' => $homeScore, 'away_score' => $awayScore,
            'tournament' => $tournament, 'city' => 'Test City', 'country' => 'Test Country',
            'neutral' => $neutral, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_unknown_team_starts_at_initial_rating(): void
    {
        $this->assertSame(KaggleEloService::INITIAL_RATING, $this->service->getRating('Nowhereland'));
    }

    public function test_has_both_teams_is_false_without_computed_elo(): void
    {
        $this->assertFalse($this->service->hasBothTeams('Argentina', 'Brazil'));
    }

    public function test_compute_all_replays_matches_chronologically(): void
    {
        $this->insertResult('Argentina', 'Brazil', 2, 0, '2020-01-01', 'Copa America');
        $this->insertResult('Brazil', 'Argentina', 1, 1, '2021-01-01', 'Copa America');

        $count = $this->service->computeAll();

        $this->assertSame(2, $count);
        $this->assertDatabaseHas('kaggle_elo_ratings', ['team_name' => 'Argentina']);
        $this->assertDatabaseHas('kaggle_elo_ratings', ['team_name' => 'Brazil']);

        $argentina = DB::table('kaggle_elo_ratings')->where('team_name', 'Argentina')->first();
        $this->assertSame(2, $argentina->matches_played);
    }

    public function test_winning_team_ends_with_higher_elo_than_losing_team(): void
    {
        $this->insertResult('Chile', 'Peru', 3, 0, '2020-01-01', 'Copa America');
        $this->service->computeAll();

        $chile = DB::table('kaggle_elo_ratings')->where('team_name', 'Chile')->first();
        $peru = DB::table('kaggle_elo_ratings')->where('team_name', 'Peru')->first();

        $this->assertGreaterThan($peru->elo_rating, $chile->elo_rating);
        $this->assertGreaterThan(KaggleEloService::INITIAL_RATING, $chile->elo_rating);
        $this->assertLessThan(KaggleEloService::INITIAL_RATING, $peru->elo_rating);
    }

    public function test_has_both_teams_true_after_compute(): void
    {
        $this->insertResult('Uruguay', 'Paraguay', 1, 0, '2020-01-01', 'Friendly');
        $this->service->computeAll();

        $this->assertTrue($this->service->hasBothTeams('Uruguay', 'Paraguay'));
        $this->assertFalse($this->service->hasBothTeams('Uruguay', 'Nowhereland'));
    }

    public function test_compute_incremental_only_processes_matches_after_last_computed_date(): void
    {
        $this->insertResult('Spain', 'France', 1, 0, '2020-01-01', 'Friendly');
        $this->service->computeAll();
        $eloAfterFirst = $this->service->getRating('Spain');

        // Segunda corrida completa: nuevo servicio, sin cache en memoria.
        $this->insertResult('Spain', 'France', 2, 0, '2020-06-01', 'Friendly');
        $incrementalService = new KaggleEloService();
        $processed = $incrementalService->computeIncremental();

        $this->assertSame(1, $processed); // solo el partido nuevo
        $eloAfterIncremental = $incrementalService->getRating('Spain');
        $this->assertNotEquals($eloAfterFirst, $eloAfterIncremental);
    }

    public function test_compute_incremental_returns_zero_when_nothing_new(): void
    {
        $this->insertResult('Germany', 'Italy', 1, 1, '2020-01-01', 'Friendly');
        $this->service->computeAll();

        $incrementalService = new KaggleEloService();
        $processed = $incrementalService->computeIncremental();

        $this->assertSame(0, $processed);
    }

    public function test_cup_match_moves_elo_more_than_friendly(): void
    {
        $this->insertResult('CupHome', 'CupAway', 2, 0, '2020-01-01', 'World Cup');
        $this->service->computeAll();
        $cupGain = $this->service->getRating('CupHome') - KaggleEloService::INITIAL_RATING;

        $friendlyService = new KaggleEloService();
        DB::table('kaggle_elo_ratings')->truncate();
        $this->insertResult('FriendlyHome', 'FriendlyAway', 2, 0, '2020-01-01', 'Friendly');
        // computeAll() ya truncó y recalculó TODO kaggle_results, así que hace falta
        // limpiar los resultados del caso anterior para aislar el nuevo partido.
        DB::table('kaggle_results')->where('tournament', 'World Cup')->delete();
        $friendlyService->computeAll();
        $friendlyGain = $friendlyService->getRating('FriendlyHome') - KaggleEloService::INITIAL_RATING;

        $this->assertGreaterThan($friendlyGain, $cupGain);
    }

    public function test_match_features_expose_elo_diff_and_expected_score(): void
    {
        $this->insertResult('Ecuador', 'Bolivia', 2, 0, '2020-01-01', 'Copa America');
        $this->service->computeAll();

        $features = $this->service->getMatchFeatures('Ecuador', 'Bolivia');

        $this->assertSame($features['kaggle_home_elo'] - $features['kaggle_away_elo'], $features['kaggle_elo_diff']);
        $this->assertGreaterThan(0.5, $features['home_expected']);
    }
}
