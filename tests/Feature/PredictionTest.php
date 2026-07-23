<?php

namespace Tests\Feature;

use App\Models\GroupMembers;
use App\Models\GroupPredictions;
use App\Models\Groups;
use App\Models\Matches;
use App\Models\Predictions;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentStage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PredictionTest extends TestCase
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
            'match_date' => Carbon::now()->addDays(7),
            'status' => 'scheduled',
            'home_score' => 0,
            'away_score' => 0,
        ]);

        $this->group = Groups::create([
            'name' => 'Predictions Group',
            'description' => 'Test group',
            'creator_id' => $this->user->user_id,
            'is_public' => true,
            'invitation_code' => 'PRED01',
        ]);

        GroupMembers::create([
            'group_id' => $this->group->group_id,
            'user_id' => $this->user->user_id,
            'is_admin' => true,
        ]);
    }

    public function test_store_prediction(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/prediction', [
            'match_id' => $this->match->match_id,
            'group_id' => $this->group->group_id,
            'predicted_home_score' => 2,
            'predicted_away_score' => 1,
            'predicted_result' => 'home_win',
            'bet_type' => 'exact_score',
            'confidence' => 8,
        ]);

        $response->assertStatus(201)
            ->assertJson(['message' => 'Prediction created successfully']);

        $this->assertDatabaseHas('predictions', [
            'user_id' => $this->user->user_id,
            'match_id' => $this->match->match_id,
        ]);

        $this->assertDatabaseHas('grouppredictions', [
            'group_id' => $this->group->group_id,
        ]);
    }

    public function test_store_prediction_unauthenticated_fails(): void
    {
        $response = $this->postJson('/api/v1/prediction', [
            'match_id' => $this->match->match_id,
            'group_id' => $this->group->group_id,
            'predicted_home_score' => 2,
            'predicted_away_score' => 1,
            'predicted_result' => 'home_win',
            'bet_type' => 'exact_score',
            'confidence' => 8,
        ]);

        $response->assertStatus(401);
    }

    public function test_store_prediction_not_group_member_fails(): void
    {
        $other = User::create([
            'first_name' => 'Other',
            'last_name' => 'User',
            'email' => 'other@example.com',
            'password_hash' => bcrypt('secret123'),
        ]);

        Sanctum::actingAs($other);

        $response = $this->postJson('/api/v1/prediction', [
            'match_id' => $this->match->match_id,
            'group_id' => $this->group->group_id,
            'predicted_home_score' => 2,
            'predicted_away_score' => 1,
            'predicted_result' => 'home_win',
            'bet_type' => 'exact_score',
            'confidence' => 8,
        ]);

        $response->assertStatus(403);
    }

    public function test_store_prediction_duplicate_fails(): void
    {
        Sanctum::actingAs($this->user);

        $prediction = Predictions::create([
            'user_id' => $this->user->user_id,
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
            'prediction_id' => $prediction->prediction_id,
            'points_awarded' => 0,
        ]);

        $response = $this->postJson('/api/v1/prediction', [
            'match_id' => $this->match->match_id,
            'group_id' => $this->group->group_id,
            'predicted_home_score' => 1,
            'predicted_away_score' => 0,
            'predicted_result' => 'home_win',
            'bet_type' => 'match_result',
            'confidence' => 5,
        ]);

        $response->assertStatus(409);
    }

    public function test_store_prediction_after_cutoff_fails(): void
    {
        $match = Matches::create([
            'home_team_id' => $this->match->home_team_id,
            'away_team_id' => $this->match->away_team_id,
            'tournament_id' => $this->match->tournament_id,
            'stage_id' => $this->match->stage_id,
            'match_date' => Carbon::now()->addMinutes(15),
            'status' => 'scheduled',
            'home_score' => 0,
            'away_score' => 0,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/prediction', [
            'match_id' => $match->match_id,
            'group_id' => $this->group->group_id,
            'predicted_home_score' => 2,
            'predicted_away_score' => 1,
            'predicted_result' => 'home_win',
            'bet_type' => 'exact_score',
            'confidence' => 8,
        ]);

        $response->assertStatus(422);
    }

    public function test_store_prediction_finished_match_fails(): void
    {
        $match = Matches::create([
            'home_team_id' => $this->match->home_team_id,
            'away_team_id' => $this->match->away_team_id,
            'tournament_id' => $this->match->tournament_id,
            'stage_id' => $this->match->stage_id,
            'match_date' => Carbon::now()->subDays(1),
            'status' => 'finished',
            'home_score' => 2,
            'away_score' => 1,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/prediction', [
            'match_id' => $match->match_id,
            'group_id' => $this->group->group_id,
            'predicted_home_score' => 2,
            'predicted_away_score' => 1,
            'predicted_result' => 'home_win',
            'bet_type' => 'exact_score',
            'confidence' => 8,
        ]);

        $response->assertStatus(422);
    }

    public function test_update_prediction(): void
    {
        Sanctum::actingAs($this->user);

        $prediction = Predictions::create([
            'user_id' => $this->user->user_id,
            'match_id' => $this->match->match_id,
            'predicted_home_score' => 1,
            'predicted_away_score' => 0,
            'predicted_result' => 'home_win',
            'bet_type' => 'match_result',
            'confidence' => 5,
            'created_at' => Carbon::now(),
            'is_public' => true,
        ]);

        $response = $this->putJson("/api/v1/prediction/{$prediction->prediction_id}", [
            'predicted_home_score' => 3,
            'predicted_away_score' => 2,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('predictions', [
            'prediction_id' => $prediction->prediction_id,
            'predicted_home_score' => 3,
        ]);
    }

    public function test_delete_prediction(): void
    {
        Sanctum::actingAs($this->user);

        $prediction = Predictions::create([
            'user_id' => $this->user->user_id,
            'match_id' => $this->match->match_id,
            'predicted_home_score' => 1,
            'predicted_away_score' => 0,
            'predicted_result' => 'home_win',
            'bet_type' => 'match_result',
            'confidence' => 5,
            'created_at' => Carbon::now(),
            'is_public' => true,
        ]);

        $response = $this->deleteJson("/api/v1/prediction/{$prediction->prediction_id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('predictions', ['prediction_id' => $prediction->prediction_id]);
    }
}
