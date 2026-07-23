<?php

namespace Tests\Feature;

use App\Models\GroupMembers;
use App\Models\GroupPredictions;
use App\Models\Groups;
use App\Models\Matches;
use App\Models\PredictionResults;
use App\Models\Predictions;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentStage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ScoringTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Matches $match;

    private Groups $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password_hash' => bcrypt('secret123'),
        ]);

        $home = Team::create([
            'name' => 'Barcelona',
            'country' => 'Spain',
            'country_code' => 'ES',
            'active' => true,
        ]);

        $away = Team::create([
            'name' => 'Real Madrid',
            'country' => 'Spain',
            'country_code' => 'ES',
            'active' => true,
        ]);

        $tournament = Tournament::create([
            'name' => 'La Liga',
            'season' => '2025-2026',
            'country' => 'Spain',
            'country_code' => 'ES',
            'start_date' => '2025-08-01',
            'end_date' => '2026-05-31',
            'active' => true,
        ]);

        $stage = TournamentStage::create([
            'tournament_id' => $tournament->tournament_id,
            'name' => 'Regular Season',
            'stage_order' => 1,
            'is_knockout' => false,
        ]);

        $this->match = Matches::create([
            'home_team_id' => $home->team_id,
            'away_team_id' => $away->team_id,
            'tournament_id' => $tournament->tournament_id,
            'stage_id' => $stage->stage_id,
            'match_date' => Carbon::now()->subDays(1),
            'status' => 'finished',
            'home_score' => 2,
            'away_score' => 1,
        ]);

        $this->group = Groups::create([
            'name' => 'Scoring Group',
            'description' => 'Test',
            'creator_id' => $this->user->user_id,
            'is_public' => true,
            'invitation_code' => 'SCORE1',
        ]);

        GroupMembers::create([
            'group_id' => $this->group->group_id,
            'user_id' => $this->user->user_id,
            'is_admin' => true,
        ]);
    }

    public function test_exact_score(): void
    {
        $prediction = Predictions::create([
            'user_id' => $this->user->user_id,
            'match_id' => $this->match->match_id,
            'predicted_home_score' => 2,
            'predicted_away_score' => 1,
            'predicted_result' => 'home_win',
            'bet_type' => 'exact_score',
            'confidence' => 9,
            'created_at' => Carbon::now(),
            'is_public' => true,
        ]);

        GroupPredictions::create([
            'group_id' => $this->group->group_id,
            'prediction_id' => $prediction->prediction_id,
            'points_awarded' => 0,
        ]);

        Artisan::call('predictions:score', ['--match_id' => $this->match->match_id]);

        $result = PredictionResults::where('prediction_id', $prediction->prediction_id)->first();
        $this->assertNotNull($result);

        $breakdown = json_decode($result->points_breakdown, true);
        $this->assertEquals(10, $breakdown['exact_score']);
        $this->assertEquals(5, $breakdown['match_result']);
        $this->assertEquals(3, $breakdown['correct_goal_home']);
        $this->assertEquals(3, $breakdown['correct_goal_away']);
        $this->assertEquals(15, $breakdown['unique_exact']);
        $this->assertEquals(36, $breakdown['subtotal']);
        $this->assertEquals(36, $breakdown['total']);

        $gp = GroupPredictions::where('prediction_id', $prediction->prediction_id)->first();
        $this->assertEquals(36, $gp->points_awarded);
    }

    public function test_match_result_only(): void
    {
        $prediction = Predictions::create([
            'user_id' => $this->user->user_id,
            'match_id' => $this->match->match_id,
            'predicted_home_score' => 3,
            'predicted_away_score' => 0,
            'predicted_result' => 'home_win',
            'bet_type' => 'match_result',
            'confidence' => 7,
            'created_at' => Carbon::now(),
            'is_public' => true,
        ]);

        GroupPredictions::create([
            'group_id' => $this->group->group_id,
            'prediction_id' => $prediction->prediction_id,
            'points_awarded' => 0,
        ]);

        Artisan::call('predictions:score', ['--match_id' => $this->match->match_id]);

        $result = PredictionResults::where('prediction_id', $prediction->prediction_id)->first();
        $this->assertNotNull($result);

        $breakdown = json_decode($result->points_breakdown, true);
        $this->assertEquals(0, $breakdown['exact_score']);
        $this->assertEquals(5, $breakdown['match_result']);
        $this->assertEquals(0, $breakdown['correct_goal_home']);
        $this->assertEquals(0, $breakdown['correct_goal_away']);
        $this->assertEquals(5, $breakdown['total']);
    }

    public function test_correct_goal_home_only(): void
    {
        $prediction = Predictions::create([
            'user_id' => $this->user->user_id,
            'match_id' => $this->match->match_id,
            'predicted_home_score' => 2,
            'predicted_away_score' => 3,
            'predicted_result' => 'away_win',
            'bet_type' => 'match_result',
            'confidence' => 6,
            'created_at' => Carbon::now(),
            'is_public' => true,
        ]);

        GroupPredictions::create([
            'group_id' => $this->group->group_id,
            'prediction_id' => $prediction->prediction_id,
            'points_awarded' => 0,
        ]);

        Artisan::call('predictions:score', ['--match_id' => $this->match->match_id]);

        $result = PredictionResults::where('prediction_id', $prediction->prediction_id)->first();
        $breakdown = json_decode($result->points_breakdown, true);
        $this->assertEquals(3, $breakdown['correct_goal_home']);
        $this->assertEquals(0, $breakdown['correct_goal_away']);
        $this->assertEquals(3, $breakdown['total']);
    }

    public function test_correct_goal_away_only(): void
    {
        $prediction = Predictions::create([
            'user_id' => $this->user->user_id,
            'match_id' => $this->match->match_id,
            'predicted_home_score' => 0,
            'predicted_away_score' => 1,
            'predicted_result' => 'away_win',
            'bet_type' => 'match_result',
            'confidence' => 6,
            'created_at' => Carbon::now(),
            'is_public' => true,
        ]);

        GroupPredictions::create([
            'group_id' => $this->group->group_id,
            'prediction_id' => $prediction->prediction_id,
            'points_awarded' => 0,
        ]);

        Artisan::call('predictions:score', ['--match_id' => $this->match->match_id]);

        $result = PredictionResults::where('prediction_id', $prediction->prediction_id)->first();
        $breakdown = json_decode($result->points_breakdown, true);
        $this->assertEquals(0, $breakdown['correct_goal_home']);
        $this->assertEquals(3, $breakdown['correct_goal_away']);
        $this->assertEquals(3, $breakdown['total']);
    }

    public function test_correct_goal_both(): void
    {
        $prediction = Predictions::create([
            'user_id' => $this->user->user_id,
            'match_id' => $this->match->match_id,
            'predicted_home_score' => 2,
            'predicted_away_score' => 1,
            'predicted_result' => 'draw',
            'bet_type' => 'exact_score',
            'confidence' => 8,
            'created_at' => Carbon::now(),
            'is_public' => true,
        ]);

        GroupPredictions::create([
            'group_id' => $this->group->group_id,
            'prediction_id' => $prediction->prediction_id,
            'points_awarded' => 0,
        ]);

        Artisan::call('predictions:score', ['--match_id' => $this->match->match_id]);

        $result = PredictionResults::where('prediction_id', $prediction->prediction_id)->first();
        $breakdown = json_decode($result->points_breakdown, true);
        $this->assertEquals(10, $breakdown['exact_score']);
        $this->assertEquals(0, $breakdown['match_result']);
        $this->assertEquals(3, $breakdown['correct_goal_home']);
        $this->assertEquals(3, $breakdown['correct_goal_away']);
        $this->assertEquals(15, $breakdown['unique_exact']);
        $this->assertEquals(31, $breakdown['total']);
    }

    public function test_unique_exact_bonus(): void
    {
        $otherUser = User::create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'password_hash' => bcrypt('secret123'),
        ]);

        GroupMembers::create([
            'group_id' => $this->group->group_id,
            'user_id' => $otherUser->user_id,
            'is_admin' => false,
        ]);

        $myPrediction = Predictions::create([
            'user_id' => $this->user->user_id,
            'match_id' => $this->match->match_id,
            'predicted_home_score' => 2,
            'predicted_away_score' => 1,
            'predicted_result' => 'home_win',
            'bet_type' => 'exact_score',
            'confidence' => 9,
            'created_at' => Carbon::now(),
            'is_public' => true,
        ]);

        GroupPredictions::create([
            'group_id' => $this->group->group_id,
            'prediction_id' => $myPrediction->prediction_id,
            'points_awarded' => 0,
        ]);

        $otherPrediction = Predictions::create([
            'user_id' => $otherUser->user_id,
            'match_id' => $this->match->match_id,
            'predicted_home_score' => 3,
            'predicted_away_score' => 0,
            'predicted_result' => 'home_win',
            'bet_type' => 'match_result',
            'confidence' => 5,
            'created_at' => Carbon::now(),
            'is_public' => true,
        ]);

        GroupPredictions::create([
            'group_id' => $this->group->group_id,
            'prediction_id' => $otherPrediction->prediction_id,
            'points_awarded' => 0,
        ]);

        Artisan::call('predictions:score', ['--match_id' => $this->match->match_id]);

        $myResult = PredictionResults::where('prediction_id', $myPrediction->prediction_id)->first();
        $breakdown = json_decode($myResult->points_breakdown, true);
        $this->assertEquals(15, $breakdown['unique_exact']);
        $this->assertEquals(36, $breakdown['total']);
    }

    public function test_no_unique_when_tied(): void
    {
        $otherUser = User::create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'password_hash' => bcrypt('secret123'),
        ]);

        GroupMembers::create([
            'group_id' => $this->group->group_id,
            'user_id' => $otherUser->user_id,
            'is_admin' => false,
        ]);

        $myPrediction = Predictions::create([
            'user_id' => $this->user->user_id,
            'match_id' => $this->match->match_id,
            'predicted_home_score' => 2,
            'predicted_away_score' => 1,
            'predicted_result' => 'home_win',
            'bet_type' => 'exact_score',
            'confidence' => 9,
            'created_at' => Carbon::now(),
            'is_public' => true,
        ]);

        GroupPredictions::create([
            'group_id' => $this->group->group_id,
            'prediction_id' => $myPrediction->prediction_id,
            'points_awarded' => 0,
        ]);

        $otherPrediction = Predictions::create([
            'user_id' => $otherUser->user_id,
            'match_id' => $this->match->match_id,
            'predicted_home_score' => 2,
            'predicted_away_score' => 1,
            'predicted_result' => 'home_win',
            'bet_type' => 'exact_score',
            'confidence' => 8,
            'created_at' => Carbon::now(),
            'is_public' => true,
        ]);

        GroupPredictions::create([
            'group_id' => $this->group->group_id,
            'prediction_id' => $otherPrediction->prediction_id,
            'points_awarded' => 0,
        ]);

        Artisan::call('predictions:score', ['--match_id' => $this->match->match_id]);

        $myResult = PredictionResults::where('prediction_id', $myPrediction->prediction_id)->first();
        $breakdown = json_decode($myResult->points_breakdown, true);
        $this->assertEquals(0, $breakdown['unique_exact']);
        $this->assertEquals(21, $breakdown['total']);
    }

    public function test_knockout_multiplier(): void
    {
        $stage = TournamentStage::where('name', 'Regular Season')->first();
        $stage->update(['name' => 'Octavos', 'is_knockout' => true]);

        $prediction = Predictions::create([
            'user_id' => $this->user->user_id,
            'match_id' => $this->match->match_id,
            'predicted_home_score' => 2,
            'predicted_away_score' => 1,
            'predicted_result' => 'home_win',
            'bet_type' => 'exact_score',
            'confidence' => 9,
            'created_at' => Carbon::now(),
            'is_public' => true,
        ]);

        GroupPredictions::create([
            'group_id' => $this->group->group_id,
            'prediction_id' => $prediction->prediction_id,
            'points_awarded' => 0,
        ]);

        Artisan::call('predictions:score', ['--match_id' => $this->match->match_id]);

        $result = PredictionResults::where('prediction_id', $prediction->prediction_id)->first();
        $breakdown = json_decode($result->points_breakdown, true);
        $this->assertEquals(2.0, $breakdown['stage_multiplier']);
        $this->assertEquals(72, $breakdown['total']);
    }

    public function test_final_multiplier(): void
    {
        $stage = TournamentStage::where('name', 'Regular Season')->first();
        $stage->update(['name' => 'Final', 'is_knockout' => true]);

        $prediction = Predictions::create([
            'user_id' => $this->user->user_id,
            'match_id' => $this->match->match_id,
            'predicted_home_score' => 2,
            'predicted_away_score' => 1,
            'predicted_result' => 'home_win',
            'bet_type' => 'exact_score',
            'confidence' => 9,
            'created_at' => Carbon::now(),
            'is_public' => true,
        ]);

        GroupPredictions::create([
            'group_id' => $this->group->group_id,
            'prediction_id' => $prediction->prediction_id,
            'points_awarded' => 0,
        ]);

        Artisan::call('predictions:score', ['--match_id' => $this->match->match_id]);

        $result = PredictionResults::where('prediction_id', $prediction->prediction_id)->first();
        $breakdown = json_decode($result->points_breakdown, true);
        $this->assertEquals(10.0, $breakdown['stage_multiplier']);
        $this->assertEquals(360, $breakdown['total']);
    }

    public function test_wrong_prediction(): void
    {
        $prediction = Predictions::create([
            'user_id' => $this->user->user_id,
            'match_id' => $this->match->match_id,
            'predicted_home_score' => 0,
            'predicted_away_score' => 3,
            'predicted_result' => 'away_win',
            'bet_type' => 'match_result',
            'confidence' => 5,
            'created_at' => Carbon::now(),
            'is_public' => true,
        ]);

        GroupPredictions::create([
            'group_id' => $this->group->group_id,
            'prediction_id' => $prediction->prediction_id,
            'points_awarded' => 0,
        ]);

        Artisan::call('predictions:score', ['--match_id' => $this->match->match_id]);

        $result = PredictionResults::where('prediction_id', $prediction->prediction_id)->first();
        $breakdown = json_decode($result->points_breakdown, true);
        $this->assertEquals(0, $breakdown['total']);
    }

    public function test_no_finished_matches(): void
    {
        $this->match->update(['status' => 'scheduled']);

        $exitCode = Artisan::call('predictions:score');
        $this->assertEquals(0, $exitCode);
    }

    public function test_leaderboard(): void
    {
        $prediction = Predictions::create([
            'user_id' => $this->user->user_id,
            'match_id' => $this->match->match_id,
            'predicted_home_score' => 2,
            'predicted_away_score' => 1,
            'predicted_result' => 'home_win',
            'bet_type' => 'exact_score',
            'confidence' => 9,
            'created_at' => Carbon::now(),
            'is_public' => true,
        ]);

        GroupPredictions::create([
            'group_id' => $this->group->group_id,
            'prediction_id' => $prediction->prediction_id,
            'points_awarded' => 0,
        ]);

        PredictionResults::create([
            'prediction_id' => $prediction->prediction_id,
            'points_earned' => 21,
            'points_breakdown' => json_encode([
                'exact_score' => 10,
                'match_result' => 5,
                'correct_goal_home' => 3,
                'correct_goal_away' => 3,
                'unique_exact' => 0,
                'stage_multiplier' => 1,
                'subtotal' => 21,
                'total' => 21,
            ]),
            'processed_at' => Carbon::now(),
        ]);

        $response = $this->getJson("/api/v1/group/{$this->group->group_id}/leaderboard");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'group_id',
                'leaderboard' => [
                    [
                        'user_id',
                        'first_name',
                        'last_name',
                        'total_points',
                        'total_predictions',
                        'correct_predictions',
                        'exact_scores',
                        'unique_predictions',
                    ],
                ],
            ])
            ->assertJson([
                'group_id' => $this->group->group_id,
                'leaderboard' => [
                    [
                        'total_points' => '21',
                        'total_predictions' => 1,
                    ],
                ],
            ]);
    }
}
