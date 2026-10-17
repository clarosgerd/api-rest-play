<?php

namespace Tests\Feature;

use App\Models\Matches;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentStage;
use App\Services\MomentumService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MomentumServiceTest extends TestCase
{
    use RefreshDatabase;

    private MomentumService $service;

    private Team $team;

    private Team $opponent;

    private Tournament $tournament;

    private TournamentStage $stage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MomentumService();

        $this->team = Team::create(['name' => 'Flamengo', 'country' => 'Brazil', 'country_code' => 'BR', 'active' => true]);
        $this->opponent = Team::create(['name' => 'Palmeiras', 'country' => 'Brazil', 'country_code' => 'BR', 'active' => true]);
        $this->tournament = Tournament::create([
            'name' => 'Brasileirao', 'season' => '2026', 'country' => 'Brazil', 'country_code' => 'BR',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-01', 'active' => true,
        ]);
        $this->stage = TournamentStage::create(['tournament_id' => $this->tournament->tournament_id, 'name' => 'Regular', 'stage_order' => 1, 'is_knockout' => false]);
    }

    public function test_returns_defaults_when_fewer_than_3_matches(): void
    {
        $this->createMatch(2, 0, '2026-01-01');
        $this->createMatch(1, 0, '2026-01-08');

        $features = $this->service->getFeatures($this->team->team_id, $this->tournament->tournament_id);

        $this->assertSame(0, $features['matches_analyzed']);
        $this->assertSame(0, $features['streak']);
    }

    public function test_winning_streak_counts_consecutive_wins_from_most_recent(): void
    {
        // Orden cronológico: W, W, L, W (la más vieja es la L más antigua)
        $this->createMatch(1, 0, '2026-01-01'); // W
        $this->createMatch(0, 2, '2026-01-08'); // L
        $this->createMatch(2, 0, '2026-01-15'); // W
        $this->createMatch(3, 1, '2026-01-22'); // W (más reciente)

        $features = $this->service->getFeatures($this->team->team_id, $this->tournament->tournament_id);

        // Desde la más reciente: W, W, L, ... -> racha de 2
        $this->assertSame(2, $features['streak']);
        $this->assertSame(4, $features['matches_analyzed']);
    }

    public function test_unbeaten_streak_includes_draws(): void
    {
        $this->createMatch(0, 2, '2026-01-01'); // L (rompe la racha hacia atrás)
        $this->createMatch(1, 1, '2026-01-08'); // D
        $this->createMatch(2, 0, '2026-01-15'); // W (más reciente)

        $features = $this->service->getFeatures($this->team->team_id, $this->tournament->tournament_id);

        $this->assertSame(1, $features['streak']); // solo la W cuenta como racha ganadora
        $this->assertSame(2, $features['unbeaten_streak']); // W + D
    }

    public function test_clean_sheet_percentage(): void
    {
        $this->createMatch(1, 0, '2026-01-01'); // clean sheet
        $this->createMatch(2, 0, '2026-01-08'); // clean sheet
        $this->createMatch(1, 1, '2026-01-15'); // no

        $features = $this->service->getFeatures($this->team->team_id, $this->tournament->tournament_id);

        $this->assertEqualsWithDelta(2 / 3, $features['clean_sheet_pct'], 0.0001);
    }

    public function test_weighted_form_points_favors_the_most_recent_match(): void
    {
        // 3 derrotas antiguas + 1 victoria reciente: la ponderación por
        // recencia debe hacer que el form promedio sea bastante más alto
        // que un simple promedio sin pesos (que daría 0.75 pts/partido).
        $this->createMatch(0, 3, '2026-01-01'); // L
        $this->createMatch(0, 2, '2026-01-08'); // L
        $this->createMatch(0, 1, '2026-01-15'); // L
        $this->createMatch(3, 0, '2026-01-22'); // W (más reciente, peso 0.35)

        $features = $this->service->getFeatures($this->team->team_id, $this->tournament->tournament_id);

        $simpleAverage = 3 / 4;
        $this->assertGreaterThan($simpleAverage, $features['weighted_form_pts']);
    }

    public function test_goal_averages_use_only_the_last_5_matches(): void
    {
        // 6 partidos: el primero (más viejo) con muchos goles no debe entrar al promedio de 5.
        $this->createMatch(10, 0, '2026-01-01');
        $this->createMatch(1, 1, '2026-01-08');
        $this->createMatch(1, 1, '2026-01-15');
        $this->createMatch(1, 1, '2026-01-22');
        $this->createMatch(1, 1, '2026-01-29');
        $this->createMatch(1, 1, '2026-02-05');

        $features = $this->service->getFeatures($this->team->team_id, $this->tournament->tournament_id);

        $this->assertSame(1.0, $features['goals_scored_avg_5']);
        $this->assertSame(6, $features['matches_analyzed']);
    }

    private function createMatch(int $goalsFor, int $goalsAgainst, string $date): Matches
    {
        return Matches::create([
            'home_team_id' => $this->team->team_id, 'away_team_id' => $this->opponent->team_id,
            'tournament_id' => $this->tournament->tournament_id, 'stage_id' => $this->stage->stage_id,
            'match_date' => $date, 'status' => 'finished', 'home_score' => $goalsFor, 'away_score' => $goalsAgainst,
        ]);
    }
}
