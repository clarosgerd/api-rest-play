<?php

namespace Tests\Feature;

use App\Models\AiPrediction;
use App\Models\Matches;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiPredictionControllerTest extends TestCase
{
    use RefreshDatabase;

    private Team $home;

    private Team $away;

    private Tournament $tournament;

    private TournamentStage $stage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->home = Team::create(['name' => 'Home FC', 'country' => 'Spain', 'country_code' => 'ES', 'active' => true]);
        $this->away = Team::create(['name' => 'Away FC', 'country' => 'Spain', 'country_code' => 'ES', 'active' => true]);
        $this->tournament = Tournament::create([
            'name' => 'Liga Test', 'season' => '2026', 'country' => 'Spain', 'country_code' => 'ES',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-01', 'active' => true,
        ]);
        $this->stage = TournamentStage::create(['tournament_id' => $this->tournament->tournament_id, 'name' => 'Regular', 'stage_order' => 1, 'is_knockout' => false]);
    }

    private function makeMatch(string $status): Matches
    {
        return Matches::create([
            'home_team_id' => $this->home->team_id, 'away_team_id' => $this->away->team_id,
            'tournament_id' => $this->tournament->tournament_id, 'stage_id' => $this->stage->stage_id,
            'match_date' => '2026-06-01', 'status' => $status, 'home_score' => 1, 'away_score' => 0,
        ]);
    }

    public function test_show_hides_prediction_before_match_finishes(): void
    {
        $match = $this->makeMatch('scheduled');

        $this->getJson("/api/v1/match/{$match->match_id}/ai-prediction")
            ->assertOk()
            ->assertJson(['data' => null]);

        // Tampoco debe haberse generado ni persistido nada mientras está oculta.
        $this->assertDatabaseMissing('ai_predictions', ['match_id' => $match->match_id]);
    }

    public function test_show_hides_prediction_while_match_is_live(): void
    {
        $match = $this->makeMatch('live');

        $this->getJson("/api/v1/match/{$match->match_id}/ai-prediction")
            ->assertOk()
            ->assertJson(['data' => null]);
    }

    public function test_show_reveals_prediction_once_match_is_finished(): void
    {
        $match = $this->makeMatch('finished');

        $response = $this->getJson("/api/v1/match/{$match->match_id}/ai-prediction")->assertOk();

        $response->assertJsonPath('data.match_id', $match->match_id);
        $response->assertJsonStructure(['data' => ['predicted_result', 'home_win_prob', 'draw_prob', 'away_win_prob', 'confidence']]);
    }

    public function test_show_returns_404_for_unknown_match(): void
    {
        $this->getJson('/api/v1/match/999999/ai-prediction')->assertStatus(404);
    }

    public function test_tournament_endpoint_only_predicts_scheduled_matches(): void
    {
        $scheduled = $this->makeMatch('scheduled');
        Matches::create([
            'home_team_id' => $this->home->team_id, 'away_team_id' => $this->away->team_id,
            'tournament_id' => $this->tournament->tournament_id, 'stage_id' => $this->stage->stage_id,
            'match_date' => '2026-01-01', 'status' => 'finished', 'home_score' => 1, 'away_score' => 0,
        ]);

        $response = $this->getJson("/api/v1/tournament/{$this->tournament->tournament_id}/ai-predictions")->assertOk();

        $ids = collect($response->json('data'))->pluck('match_id');
        $this->assertTrue($ids->contains($scheduled->match_id));
        $this->assertCount(1, $ids);
    }

    public function test_accuracy_endpoint_with_no_evaluated_predictions(): void
    {
        $this->getJson('/api/v1/ai-predictions/accuracy')
            ->assertOk()
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.accuracy', 0);
    }

    public function test_accuracy_endpoint_reflects_evaluated_predictions(): void
    {
        $match = $this->makeMatch('finished');
        AiPrediction::create([
            'match_id' => $match->match_id,
            'predicted_result' => 'home_win',
            'predicted_home_score' => 1,
            'predicted_away_score' => 0,
            'home_win_prob' => 0.6,
            'draw_prob' => 0.25,
            'away_win_prob' => 0.15,
            'confidence' => 0.6,
            'was_correct' => true,
            'model_version' => '2.2',
        ]);

        $this->getJson('/api/v1/ai-predictions/accuracy')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.correct', 1)
            ->assertJsonPath('data.accuracy', 1);
    }
}
