<?php

namespace Tests\Feature;

use App\Models\Matches;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentStage;
use App\Services\EloService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloServiceTest extends TestCase
{
    use RefreshDatabase;

    private EloService $service;

    private Team $home;

    private Team $away;

    private Tournament $tournament;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EloService();

        $this->home = Team::create(['name' => 'Barcelona', 'country' => 'Spain', 'country_code' => 'ES', 'active' => true]);
        $this->away = Team::create(['name' => 'Real Madrid', 'country' => 'Spain', 'country_code' => 'ES', 'active' => true]);
        $this->tournament = Tournament::create([
            'name' => 'La Liga', 'season' => '2025-2026', 'country' => 'Spain', 'country_code' => 'ES',
            'start_date' => '2025-08-01', 'end_date' => '2026-05-31', 'active' => true,
        ]);
    }

    public function test_new_team_starts_at_initial_rating(): void
    {
        $this->assertSame(EloService::INITIAL_RATING, $this->service->getRating($this->home->team_id, null));
        $this->assertSame(EloService::INITIAL_RATING, $this->service->getRating($this->home->team_id, $this->tournament->tournament_id));
    }

    public function test_expected_score_is_50_percent_for_equal_elo(): void
    {
        $this->assertEqualsWithDelta(0.5, $this->service->expectedScore(1500, 1500), 0.0001);
    }

    public function test_expected_score_favors_higher_rated_team(): void
    {
        $expected = $this->service->expectedScore(1700, 1500);
        $this->assertGreaterThan(0.5, $expected);
        $this->assertLessThan(1.0, $expected);
    }

    public function test_home_advantage_increases_expected_score(): void
    {
        $withoutHa = $this->service->expectedScore(1500, 1500, 0);
        $withHa = $this->service->expectedScore(1500, 1500, EloService::HOME_ADVANTAGE);
        $this->assertGreaterThan($withoutHa, $withHa);
    }

    public function test_update_after_match_does_nothing_if_not_finished(): void
    {
        $match = $this->makeMatch('scheduled', 0, 0);
        $this->service->updateAfterMatch($match);

        $this->assertSame(EloService::INITIAL_RATING, $this->service->getRating($this->home->team_id, null));
    }

    public function test_home_win_raises_home_elo_and_lowers_away_elo(): void
    {
        $match = $this->makeMatch('finished', 2, 0);
        $this->service->updateAfterMatch($match);

        $homeElo = $this->service->getRating($this->home->team_id, null);
        $awayElo = $this->service->getRating($this->away->team_id, null);

        $this->assertGreaterThan(EloService::INITIAL_RATING, $homeElo);
        $this->assertLessThan(EloService::INITIAL_RATING, $awayElo);
    }

    public function test_updates_both_global_and_tournament_specific_ratings(): void
    {
        $match = $this->makeMatch('finished', 1, 0);
        $this->service->updateAfterMatch($match);

        $globalElo = $this->service->getRating($this->home->team_id, null);
        $tournamentElo = $this->service->getRating($this->home->team_id, $this->tournament->tournament_id);

        $this->assertGreaterThan(EloService::INITIAL_RATING, $globalElo);
        $this->assertGreaterThan(EloService::INITIAL_RATING, $tournamentElo);
    }

    public function test_bigger_goal_difference_moves_elo_more(): void
    {
        $small = $this->makeMatch('finished', 1, 0);
        $this->service->updateAfterMatch($small);
        $eloAfterSmallWin = $this->service->getRating($this->home->team_id, null);
        $smallDelta = $eloAfterSmallWin - EloService::INITIAL_RATING;

        // Reset with a fresh pair of teams for a blowout win, same starting conditions.
        $home2 = Team::create(['name' => 'Team Big Win', 'country' => 'Spain', 'country_code' => 'ES', 'active' => true]);
        $away2 = Team::create(['name' => 'Team Big Loss', 'country' => 'Spain', 'country_code' => 'ES', 'active' => true]);
        $stage2 = TournamentStage::create(['tournament_id' => $this->tournament->tournament_id, 'name' => 'Regular', 'stage_order' => 1, 'is_knockout' => false]);
        $blowout = Matches::create([
            'home_team_id' => $home2->team_id, 'away_team_id' => $away2->team_id,
            'tournament_id' => $this->tournament->tournament_id, 'stage_id' => $stage2->stage_id,
            'match_date' => '2026-01-10', 'status' => 'finished', 'home_score' => 5, 'away_score' => 0,
        ]);
        $this->service->updateAfterMatch($blowout);
        $eloAfterBlowout = $this->service->getRating($home2->team_id, null);
        $blowoutDelta = $eloAfterBlowout - EloService::INITIAL_RATING;

        $this->assertGreaterThan($smallDelta, $blowoutDelta);
    }

    public function test_draw_moves_elo_towards_the_underdog(): void
    {
        // Give home team a head start so there's an elo gap, then draw.
        $warmUp = $this->makeMatch('finished', 3, 0);
        $this->service->updateAfterMatch($warmUp);
        $homeEloBefore = $this->service->getRating($this->home->team_id, null);
        $awayEloBefore = $this->service->getRating($this->away->team_id, null);
        $this->assertGreaterThan($awayEloBefore, $homeEloBefore);

        $drawStage = TournamentStage::create(['tournament_id' => $this->tournament->tournament_id, 'name' => 'Regular 2', 'stage_order' => 2, 'is_knockout' => false]);
        $draw = Matches::create([
            'home_team_id' => $this->home->team_id, 'away_team_id' => $this->away->team_id,
            'tournament_id' => $this->tournament->tournament_id, 'stage_id' => $drawStage->stage_id,
            'match_date' => '2026-02-01', 'status' => 'finished', 'home_score' => 1, 'away_score' => 1,
        ]);
        $this->service->updateAfterMatch($draw);

        $homeEloAfter = $this->service->getRating($this->home->team_id, null);
        $awayEloAfter = $this->service->getRating($this->away->team_id, null);

        // Favorito empata contra un desfavorito: el favorito pierde puntos, el otro gana.
        $this->assertLessThan($homeEloBefore, $homeEloAfter);
        $this->assertGreaterThan($awayEloBefore, $awayEloAfter);
    }

    public function test_cup_tournament_uses_higher_k_factor_than_league(): void
    {
        $cup = Tournament::create([
            'name' => 'Copa del Rey', 'season' => '2025-2026', 'country' => 'Spain', 'country_code' => 'ES',
            'start_date' => '2025-08-01', 'end_date' => '2026-05-31', 'active' => true,
        ]);
        $stage = TournamentStage::create(['tournament_id' => $cup->tournament_id, 'name' => 'Final', 'stage_order' => 1, 'is_knockout' => true]);

        $cupMatch = Matches::create([
            'home_team_id' => $this->home->team_id, 'away_team_id' => $this->away->team_id,
            'tournament_id' => $cup->tournament_id, 'stage_id' => $stage->stage_id,
            'match_date' => '2026-01-10', 'status' => 'finished', 'home_score' => 1, 'away_score' => 0,
        ]);
        $this->service->updateAfterMatch($cupMatch);
        $cupElo = $this->service->getRating($this->home->team_id, null);

        // Mismo marcador, pero en una liga (K menor) debería mover el Elo menos.
        $home2 = Team::create(['name' => 'Team League', 'country' => 'Spain', 'country_code' => 'ES', 'active' => true]);
        $away2 = Team::create(['name' => 'Team League Away', 'country' => 'Spain', 'country_code' => 'ES', 'active' => true]);
        $leagueStage = TournamentStage::create(['tournament_id' => $this->tournament->tournament_id, 'name' => 'Regular 3', 'stage_order' => 3, 'is_knockout' => false]);
        $leagueMatch = Matches::create([
            'home_team_id' => $home2->team_id, 'away_team_id' => $away2->team_id,
            'tournament_id' => $this->tournament->tournament_id, 'stage_id' => $leagueStage->stage_id,
            'match_date' => '2026-01-10', 'status' => 'finished', 'home_score' => 1, 'away_score' => 0,
        ]);
        $this->service->updateAfterMatch($leagueMatch);
        $leagueElo = $this->service->getRating($home2->team_id, null);

        $cupGain = $cupElo - EloService::INITIAL_RATING;
        $leagueGain = $leagueElo - EloService::INITIAL_RATING;
        $this->assertGreaterThan($leagueGain, $cupGain);
    }

    private function makeMatch(string $status, int $homeScore, int $awayScore): Matches
    {
        $stage = TournamentStage::create(['tournament_id' => $this->tournament->tournament_id, 'name' => 'Regular', 'stage_order' => 1, 'is_knockout' => false]);

        return Matches::create([
            'home_team_id' => $this->home->team_id, 'away_team_id' => $this->away->team_id,
            'tournament_id' => $this->tournament->tournament_id, 'stage_id' => $stage->stage_id,
            'match_date' => '2026-01-01', 'status' => $status, 'home_score' => $homeScore, 'away_score' => $awayScore,
        ]);
    }
}
