<?php

namespace Tests\Feature;

use App\Models\AiPrediction;
use App\Models\Matches;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentStage;
use App\Services\PredictionEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PredictionEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    private PredictionEngineService $engine;

    private Team $home;

    private Team $away;

    private Tournament $tournament;

    private TournamentStage $stage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(PredictionEngineService::class);

        $this->home = Team::create(['name' => 'Home FC', 'country' => 'Spain', 'country_code' => 'ES', 'active' => true]);
        $this->away = Team::create(['name' => 'Away FC', 'country' => 'Spain', 'country_code' => 'ES', 'active' => true]);
        $this->tournament = Tournament::create([
            'name' => 'Liga Test', 'season' => '2026', 'country' => 'Spain', 'country_code' => 'ES',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-01', 'active' => true,
        ]);
        $this->stage = TournamentStage::create(['tournament_id' => $this->tournament->tournament_id, 'name' => 'Regular', 'stage_order' => 1, 'is_knockout' => false]);
    }

    private function scheduledMatch(): Matches
    {
        return Matches::create([
            'home_team_id' => $this->home->team_id, 'away_team_id' => $this->away->team_id,
            'tournament_id' => $this->tournament->tournament_id, 'stage_id' => $this->stage->stage_id,
            'match_date' => '2026-06-01', 'status' => 'scheduled', 'home_score' => 0, 'away_score' => 0,
        ]);
    }

    public function test_predict_returns_null_for_unknown_match(): void
    {
        $this->assertNull($this->engine->predict(999999));
    }

    public function test_predict_without_any_history_produces_valid_probabilities(): void
    {
        $match = $this->scheduledMatch();

        $result = $this->engine->predict($match->match_id);

        $this->assertNotNull($result);
        $this->assertSame($match->match_id, $result['match_id']);
        $this->assertContains($result['predicted_result'], ['home_win', 'draw', 'away_win']);
        $this->assertEqualsWithDelta(
            1.0,
            $result['home_win_prob'] + $result['draw_prob'] + $result['away_win_prob'],
            0.01,
        );
        $this->assertGreaterThanOrEqual(0.0, $result['confidence']);
        $this->assertLessThanOrEqual(1.0, $result['confidence']);
        $this->assertSame(PredictionEngineService::VERSION, $result['model_version']);
    }

    public function test_predict_persists_an_ai_prediction_row(): void
    {
        $match = $this->scheduledMatch();

        $this->engine->predict($match->match_id);

        $this->assertDatabaseHas('ai_predictions', ['match_id' => $match->match_id]);
        $this->assertSame(1, AiPrediction::where('match_id', $match->match_id)->count());
    }

    public function test_predicting_the_same_match_twice_updates_instead_of_duplicating(): void
    {
        $match = $this->scheduledMatch();

        $this->engine->predict($match->match_id);
        $this->engine->predict($match->match_id);

        $this->assertSame(1, AiPrediction::where('match_id', $match->match_id)->count());
    }

    public function test_strong_home_team_history_tilts_prediction_towards_home_win(): void
    {
        // El equipo local viene de ganar todos sus partidos anteriores por goleada;
        // el visitante viene de perder todos los suyos.
        for ($i = 0; $i < 5; $i++) {
            $this->finishedMatch($this->home, $this->otherOpponent("rival-home-$i"), 4, 0, "2026-0".($i + 1)."-01");
            $this->finishedMatch($this->otherOpponent("rival-away-$i"), $this->away, 4, 0, "2026-0".($i + 1)."-02");
        }

        $match = $this->scheduledMatch();
        $result = $this->engine->predict($match->match_id);

        $this->assertSame('home_win', $result['predicted_result']);
        $this->assertGreaterThan($result['away_win_prob'], $result['home_win_prob']);
    }

    public function test_predict_tournament_only_includes_scheduled_matches(): void
    {
        $scheduled = $this->scheduledMatch();
        $finished = $this->finishedMatch($this->home, $this->away, 1, 0, '2026-01-01');

        $results = $this->engine->predictTournament($this->tournament->tournament_id);

        $matchIds = $results->pluck('match_id')->all();
        $this->assertContains($scheduled->match_id, $matchIds);
        $this->assertNotContains($finished->match_id, $matchIds);
    }

    public function test_evaluate_finished_marks_predictions_correct_or_incorrect(): void
    {
        $match = $this->scheduledMatch();
        $this->engine->predict($match->match_id);
        $prediction = AiPrediction::where('match_id', $match->match_id)->first();

        // Forzamos el resultado real para que coincida exactamente con lo predicho.
        [$homeScore, $awayScore] = match ($prediction->predicted_result) {
            'home_win' => [2, 0],
            'away_win' => [0, 2],
            'draw' => [1, 1],
        };
        $match->update(['status' => 'finished', 'home_score' => $homeScore, 'away_score' => $awayScore]);

        $this->engine->evaluateFinished();

        $prediction->refresh();
        $this->assertTrue($prediction->was_correct);
    }

    public function test_evaluate_finished_marks_incorrect_when_result_differs(): void
    {
        $match = $this->scheduledMatch();
        $this->engine->predict($match->match_id);
        $prediction = AiPrediction::where('match_id', $match->match_id)->first();

        $wrongResult = $prediction->predicted_result === 'home_win' ? [0, 3] : [3, 0];
        $match->update(['status' => 'finished', 'home_score' => $wrongResult[0], 'away_score' => $wrongResult[1]]);

        $this->engine->evaluateFinished();

        $prediction->refresh();
        $this->assertFalse($prediction->was_correct);
    }

    public function test_evaluate_finished_ignores_predictions_already_evaluated(): void
    {
        $match = $this->scheduledMatch();
        $this->engine->predict($match->match_id);
        $prediction = AiPrediction::where('match_id', $match->match_id)->first();
        $prediction->update(['was_correct' => false]);
        $match->update(['status' => 'finished', 'home_score' => 5, 'away_score' => 0]);

        $evaluated = $this->engine->evaluateFinished();

        $this->assertCount(0, $evaluated);
    }

    public function test_accuracy_stats_computed_over_evaluated_predictions(): void
    {
        $this->makeEvaluatedPrediction(true);
        $this->makeEvaluatedPrediction(true);
        $this->makeEvaluatedPrediction(false);

        $stats = $this->engine->accuracyStats();

        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['correct']);
        $this->assertEqualsWithDelta(2 / 3, $stats['accuracy'], 0.0001);
    }

    public function test_accuracy_stats_are_zero_when_nothing_evaluated_yet(): void
    {
        $match = $this->scheduledMatch();
        $this->engine->predict($match->match_id);

        $stats = $this->engine->accuracyStats();

        $this->assertSame(0, $stats['total']);
        $this->assertSame(0, $stats['accuracy']);
    }

    public function test_conmebol_tournament_marks_features_and_applies_altitude(): void
    {
        $bolivia = Team::create(['name' => 'Bolivia Team', 'country' => 'Bolivia', 'country_code' => 'BO', 'active' => true]);
        $brazil = Team::create(['name' => 'Brazil Team', 'country' => 'Brazil', 'country_code' => 'BR', 'active' => true]);
        $copa = Tournament::create([
            'name' => 'Copa Libertadores', 'season' => '2026', 'country' => 'CONMEBOL', 'country_code' => 'CO',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-01', 'active' => true,
        ]);
        $stage = TournamentStage::create(['tournament_id' => $copa->tournament_id, 'name' => 'Grupos', 'stage_order' => 1, 'is_knockout' => false]);
        $match = Matches::create([
            'home_team_id' => $bolivia->team_id, 'away_team_id' => $brazil->team_id,
            'tournament_id' => $copa->tournament_id, 'stage_id' => $stage->stage_id,
            'match_date' => '2026-06-01', 'status' => 'scheduled', 'home_score' => 0, 'away_score' => 0,
        ]);

        $result = $this->engine->predict($match->match_id);

        $this->assertTrue($result['features']['is_conmebol']);
        $this->assertGreaterThan(1.0, $result['features']['altitude_factor']);
    }

    public function test_non_conmebol_tournament_does_not_apply_altitude(): void
    {
        $match = $this->scheduledMatch();

        $result = $this->engine->predict($match->match_id);

        $this->assertFalse($result['features']['is_conmebol']);
        $this->assertSame(1.0, $result['features']['altitude_factor']);
    }

    public function test_striker_factor_boosts_team_with_a_dominant_goalscorer(): void
    {
        $bolivia = Team::create(['name' => 'Bolivia Strikers', 'country' => 'Bolivia', 'country_code' => 'BO', 'active' => true]);
        $brazil = Team::create(['name' => 'Brazil Plain', 'country' => 'Brazil', 'country_code' => 'BR', 'active' => true]);
        $copa = Tournament::create([
            'name' => 'Copa Sudamericana', 'season' => '2026', 'country' => 'CONMEBOL', 'country_code' => 'CO',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-01', 'active' => true,
        ]);
        $stage = TournamentStage::create(['tournament_id' => $copa->tournament_id, 'name' => 'Grupos', 'stage_order' => 1, 'is_knockout' => false]);

        // Goleador muy por encima de la media CONMEBOL.
        Player::create(['team_id' => $bolivia->team_id, 'name' => 'Crack', 'goals' => 20, 'tournament_id' => $copa->tournament_id, 'season' => '2026']);
        Player::create(['team_id' => $brazil->team_id, 'name' => 'Normal', 'goals' => 3, 'tournament_id' => $copa->tournament_id, 'season' => '2026']);

        $match = Matches::create([
            'home_team_id' => $bolivia->team_id, 'away_team_id' => $brazil->team_id,
            'tournament_id' => $copa->tournament_id, 'stage_id' => $stage->stage_id,
            'match_date' => '2026-06-01', 'status' => 'scheduled', 'home_score' => 0, 'away_score' => 0,
        ]);

        $result = $this->engine->predict($match->match_id);

        $this->assertGreaterThan($result['features']['away_striker_factor'], $result['features']['home_striker_factor']);
    }

    private function makeEvaluatedPrediction(bool $wasCorrect): AiPrediction
    {
        $match = $this->finishedMatch($this->home, $this->away, 1, 0, '2026-0'.random_int(1, 9).'-01');

        return AiPrediction::create([
            'match_id' => $match->match_id,
            'predicted_result' => 'home_win',
            'predicted_home_score' => 1,
            'predicted_away_score' => 0,
            'home_win_prob' => 0.5,
            'draw_prob' => 0.25,
            'away_win_prob' => 0.25,
            'confidence' => 0.5,
            'was_correct' => $wasCorrect,
            'model_version' => PredictionEngineService::VERSION,
        ]);
    }

    private function finishedMatch(Team $home, Team $away, int $homeScore, int $awayScore, string $date): Matches
    {
        return Matches::create([
            'home_team_id' => $home->team_id, 'away_team_id' => $away->team_id,
            'tournament_id' => $this->tournament->tournament_id, 'stage_id' => $this->stage->stage_id,
            'match_date' => $date, 'status' => 'finished', 'home_score' => $homeScore, 'away_score' => $awayScore,
        ]);
    }

    private function otherOpponent(string $name): Team
    {
        return Team::create(['name' => $name, 'country' => 'Spain', 'country_code' => 'ES', 'active' => true]);
    }
}
