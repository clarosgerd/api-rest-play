<?php

namespace Tests\Feature;

use App\Models\Matches;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentStage;
use App\Services\H2HService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class H2HServiceTest extends TestCase
{
    use RefreshDatabase;

    private H2HService $service;

    private Team $home;

    private Team $away;

    private Tournament $tournament;

    private TournamentStage $stage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new H2HService();

        $this->home = Team::create(['name' => 'Boca', 'country' => 'Argentina', 'country_code' => 'AR', 'active' => true]);
        $this->away = Team::create(['name' => 'River', 'country' => 'Argentina', 'country_code' => 'AR', 'active' => true]);
        $this->tournament = Tournament::create([
            'name' => 'Liga Profesional', 'season' => '2026', 'country' => 'Argentina', 'country_code' => 'AR',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-01', 'active' => true,
        ]);
        $this->stage = TournamentStage::create(['tournament_id' => $this->tournament->tournament_id, 'name' => 'Regular', 'stage_order' => 1, 'is_knockout' => false]);
    }

    public function test_defaults_to_neutral_split_when_no_history(): void
    {
        $features = $this->service->getFeatures($this->home->team_id, $this->away->team_id, null);

        $this->assertSame(0, $features['h2h_matches']);
        $this->assertSame(0.5, $features['h2h_home_win_rate']);
        $this->assertSame(0.25, $features['h2h_draw_pct']);
        $this->assertSame(0.25, $features['h2h_away_win_pct']);
    }

    public function test_counts_wins_regardless_of_who_was_home_that_day(): void
    {
        // Boca le gana a River de local...
        $this->createMatch($this->home, $this->away, 2, 0, '2025-01-01');
        // ...y también le gana jugando de visitante.
        $this->createMatch($this->away, $this->home, 0, 1, '2025-06-01');

        $features = $this->service->getFeatures($this->home->team_id, $this->away->team_id, null);

        $this->assertSame(2, $features['h2h_matches']);
        $this->assertSame(1.0, $features['h2h_home_win_pct']);
        $this->assertSame(0.0, $features['h2h_away_win_pct']);
        $this->assertSame(0.0, $features['h2h_draw_pct']);
    }

    public function test_counts_draws(): void
    {
        $this->createMatch($this->home, $this->away, 1, 1, '2025-01-01');

        $features = $this->service->getFeatures($this->home->team_id, $this->away->team_id, null);

        $this->assertSame(1, $features['h2h_matches']);
        $this->assertSame(1.0, $features['h2h_draw_pct']);
    }

    public function test_ignores_matches_against_other_teams(): void
    {
        $stranger = Team::create(['name' => 'Independiente', 'country' => 'Argentina', 'country_code' => 'AR', 'active' => true]);
        $this->createMatch($this->home, $stranger, 3, 0, '2025-01-01');

        $features = $this->service->getFeatures($this->home->team_id, $this->away->team_id, null);

        $this->assertSame(0, $features['h2h_matches']);
    }

    public function test_ignores_unfinished_matches(): void
    {
        Matches::create([
            'home_team_id' => $this->home->team_id, 'away_team_id' => $this->away->team_id,
            'tournament_id' => $this->tournament->tournament_id, 'stage_id' => $this->stage->stage_id,
            'match_date' => '2026-05-01', 'status' => 'scheduled', 'home_score' => 0, 'away_score' => 0,
        ]);

        $features = $this->service->getFeatures($this->home->team_id, $this->away->team_id, null);

        $this->assertSame(0, $features['h2h_matches']);
    }

    public function test_filters_by_tournament_when_given(): void
    {
        $otherTournament = Tournament::create([
            'name' => 'Copa Argentina', 'season' => '2026', 'country' => 'Argentina', 'country_code' => 'AR',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-01', 'active' => true,
        ]);
        $otherStage = TournamentStage::create(['tournament_id' => $otherTournament->tournament_id, 'name' => 'Final', 'stage_order' => 1, 'is_knockout' => true]);

        $this->createMatch($this->home, $this->away, 2, 0, '2025-01-01'); // en $this->tournament
        Matches::create([
            'home_team_id' => $this->home->team_id, 'away_team_id' => $this->away->team_id,
            'tournament_id' => $otherTournament->tournament_id, 'stage_id' => $otherStage->stage_id,
            'match_date' => '2025-02-01', 'status' => 'finished', 'home_score' => 1, 'away_score' => 1,
        ]);

        $onlyFirst = $this->service->getFeatures($this->home->team_id, $this->away->team_id, $this->tournament->tournament_id);
        $both = $this->service->getFeatures($this->home->team_id, $this->away->team_id, null);

        $this->assertSame(1, $onlyFirst['h2h_matches']);
        $this->assertSame(2, $both['h2h_matches']);
    }

    public function test_goal_diff_is_averaged_from_the_perspective_of_the_queried_home_team(): void
    {
        $this->createMatch($this->home, $this->away, 3, 1, '2025-01-01'); // +2 para home

        $features = $this->service->getFeatures($this->home->team_id, $this->away->team_id, null);

        $this->assertSame(2.0, $features['h2h_home_goal_diff']);
    }

    private function createMatch(Team $home, Team $away, int $homeScore, int $awayScore, string $date): Matches
    {
        return Matches::create([
            'home_team_id' => $home->team_id, 'away_team_id' => $away->team_id,
            'tournament_id' => $this->tournament->tournament_id, 'stage_id' => $this->stage->stage_id,
            'match_date' => $date, 'status' => 'finished', 'home_score' => $homeScore, 'away_score' => $awayScore,
        ]);
    }
}
