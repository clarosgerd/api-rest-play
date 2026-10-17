<?php

namespace Tests\Feature;

use App\Models\GroupMembers;
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

/**
 * Suscripción obligatoria para pronosticar (16/10/2026, pedido real del
 * usuario): un invitado ya no puede pronosticar gratis con el tier free —
 * hace falta cualquier plan pago activo (predictor/pro/premium). Cubre los
 * 2 puntos de entrada reales: POST prediction y POST groupprediction (este
 * último era un bypass sin ningún middleware antes de este cambio).
 */
class PredictionSubscriptionGateTest extends TestCase
{
    use RefreshDatabase;

    private Matches $match;

    private Groups $group;

    protected function setUp(): void
    {
        parent::setUp();

        $home = Team::create(['name' => 'Barcelona', 'country' => 'Spain', 'country_code' => 'ES', 'active' => true]);
        $away = Team::create(['name' => 'Real Madrid', 'country' => 'Spain', 'country_code' => 'ES', 'active' => true]);
        $tournament = Tournament::create([
            'name' => 'La Liga', 'season' => '2025-2026', 'country' => 'Spain', 'country_code' => 'ES',
            'start_date' => '2025-08-01', 'end_date' => '2026-05-31', 'active' => true,
        ]);
        $stage = TournamentStage::create(['tournament_id' => $tournament->tournament_id, 'name' => 'Regular', 'stage_order' => 1, 'is_knockout' => false]);
        $this->match = Matches::create([
            'home_team_id' => $home->team_id, 'away_team_id' => $away->team_id,
            'tournament_id' => $tournament->tournament_id, 'stage_id' => $stage->stage_id,
            'match_date' => Carbon::now()->addDays(3), 'status' => 'scheduled', 'home_score' => 0, 'away_score' => 0,
        ]);
        $this->group = Groups::create([
            'name' => 'Grupo Test', 'description' => 'desc', 'creator_id' => 1,
            'is_public' => true, 'invitation_code' => 'ABC1234',
        ]);
    }

    private function joinedUser(string $tier): User
    {
        $user = User::create([
            'first_name' => 'Ana', 'last_name' => 'Invitada', 'email' => 'ana+'.$tier.'@example.com',
            'password_hash' => bcrypt('secret123'), 'subscription_tier' => $tier,
        ]);
        GroupMembers::create([
            'group_id' => $this->group->group_id, 'user_id' => $user->user_id,
            'joined_at' => Carbon::now(), 'is_admin' => 0, 'status' => 'accepted',
        ]);

        return $user;
    }

    public function test_free_user_cannot_submit_a_prediction(): void
    {
        $user = $this->joinedUser('free');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/prediction', [
            'group_id' => $this->group->group_id, 'match_id' => $this->match->match_id,
            'predicted_home_score' => 2, 'predicted_away_score' => 1,
            'bet_type' => 'exact_score', 'confidence' => 5,
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('current_tier', 'free');
        $response->assertJsonPath('reason', 'subscription_required');
        $this->assertSame(0, Predictions::count());
    }

    public function test_predictor_tier_can_submit_unlimited_predictions(): void
    {
        $user = $this->joinedUser('predictor');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/prediction', [
            'group_id' => $this->group->group_id, 'match_id' => $this->match->match_id,
            'predicted_home_score' => 2, 'predicted_away_score' => 1,
            'predicted_result' => 'home_win', 'bet_type' => 'exact_score', 'confidence' => 5,
        ])->assertStatus(201);

        $this->assertSame(1, Predictions::where('user_id', $user->user_id)->count());
    }

    public function test_pro_and_premium_can_also_submit_predictions(): void
    {
        foreach (['pro', 'premium'] as $tier) {
            $user = $this->joinedUser($tier);
            Sanctum::actingAs($user);

            $match = Matches::create([
                'home_team_id' => $this->match->home_team_id, 'away_team_id' => $this->match->away_team_id,
                'tournament_id' => $this->match->tournament_id, 'stage_id' => $this->match->stage_id,
                'match_date' => Carbon::now()->addDays(5), 'status' => 'scheduled', 'home_score' => 0, 'away_score' => 0,
            ]);

            $this->postJson('/api/v1/prediction', [
                'group_id' => $this->group->group_id, 'match_id' => $match->match_id,
                'predicted_home_score' => 1, 'predicted_away_score' => 0,
                'predicted_result' => 'home_win', 'bet_type' => 'exact_score', 'confidence' => 5,
            ])->assertStatus(201);
        }
    }

    public function test_expired_paid_subscription_is_treated_as_blocked(): void
    {
        $user = $this->joinedUser('pro');
        $user->update(['subscription_expires_at' => Carbon::now()->subDay()]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/prediction', [
            'group_id' => $this->group->group_id, 'match_id' => $this->match->match_id,
            'predicted_home_score' => 2, 'predicted_away_score' => 1,
            'bet_type' => 'exact_score', 'confidence' => 5,
        ])->assertStatus(403);
    }

    /** El bypass real encontrado: groupprediction no tenía NINGÚN middleware antes de este cambio. */
    public function test_groupprediction_also_requires_paid_subscription(): void
    {
        $owner = $this->joinedUser('pro');
        Sanctum::actingAs($owner);
        $prediction = Predictions::create([
            'user_id' => $owner->user_id, 'match_id' => $this->match->match_id,
            'predicted_home_score' => 1, 'predicted_away_score' => 0,
            'predicted_result' => 'home_win', 'bet_type' => 'exact_score', 'confidence' => 5,
            'created_at' => Carbon::now(), 'is_public' => true,
        ]);

        $freeUser = $this->joinedUser('free');
        $secondGroup = Groups::create([
            'name' => 'Otro grupo', 'description' => 'desc', 'creator_id' => $freeUser->user_id,
            'is_public' => true, 'invitation_code' => 'XYZ9999',
        ]);
        Sanctum::actingAs($freeUser);

        $this->postJson('/api/v1/groupprediction', [
            'group_id' => $secondGroup->group_id, 'prediction_id' => $prediction->prediction_id,
        ])->assertStatus(403);
    }
}
