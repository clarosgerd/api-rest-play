<?php

namespace Tests\Feature;

use App\Models\Groups;
use App\Models\Matches;
use App\Models\Predictions;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiCompetitorControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $home;

    private Team $away;

    private Tournament $tournament;

    private TournamentStage $stage;

    private Groups $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'first_name' => 'Ana', 'last_name' => 'Gestora', 'email' => 'ana@example.com',
            'password_hash' => bcrypt('secret123'),
        ]);

        $this->home = Team::create(['name' => 'Home FC', 'country' => 'Spain', 'country_code' => 'ES', 'active' => true]);
        $this->away = Team::create(['name' => 'Away FC', 'country' => 'Spain', 'country_code' => 'ES', 'active' => true]);
        $this->tournament = Tournament::create([
            'name' => 'Liga Test', 'season' => '2026', 'country' => 'Spain', 'country_code' => 'ES',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-01', 'active' => true,
        ]);
        $this->stage = TournamentStage::create(['tournament_id' => $this->tournament->tournament_id, 'name' => 'Regular', 'stage_order' => 1, 'is_knockout' => false]);
        $this->group = Groups::create([
            'name' => 'Grupo Test', 'description' => 'desc', 'creator_id' => $this->user->user_id,
            'is_public' => true, 'invitation_code' => 'ABC1234', 'tournament_id' => $this->tournament->tournament_id,
        ]);
    }

    private function authenticate(): void
    {
        Sanctum::actingAs($this->user);
    }

    private function franco(): User
    {
        // Creado por la migración 2026_07_29_210000_create_ai_competitor_user —
        // RefreshDatabase corre todas las migraciones, así que ya debería existir.
        return User::where('email', 'franco.chelo@soccerapi.ai')->first();
    }

    public function test_franco_user_exists_from_the_migration(): void
    {
        $this->assertNotNull($this->franco());
    }

    public function test_endpoints_require_authentication(): void
    {
        // Sin authenticate(): ninguna request de este test lleva token.
        $this->postJson("/api/v1/group/{$this->group->group_id}/ai-competitor")->assertStatus(401);
        $this->getJson("/api/v1/group/{$this->group->group_id}/ai-competitor")->assertStatus(401);
    }

    public function test_status_when_not_enabled(): void
    {
        $this->authenticate();

        $this->getJson("/api/v1/group/{$this->group->group_id}/ai-competitor")
            ->assertOk()
            ->assertJson(['data' => [
                'ai_competitor_enabled' => false,
                'is_member' => false,
                'predictions_count' => 0,
                'name' => 'Franco (Chelo)',
            ]]);
    }

    public function test_toggle_enables_and_adds_franco_as_group_member(): void
    {
        $this->authenticate();

        $response = $this->postJson("/api/v1/group/{$this->group->group_id}/ai-competitor")->assertOk();

        $response->assertJsonPath('data.ai_competitor_enabled', true);
        $this->assertDatabaseHas('groupmembers', [
            'group_id' => $this->group->group_id,
            'user_id' => $this->franco()->user_id,
            'status' => 'accepted',
        ]);
        $this->assertTrue($this->group->refresh()->ai_competitor_enabled);
    }

    public function test_toggle_generates_predictions_for_scheduled_matches(): void
    {
        $this->authenticate();
        $scheduled = $this->makeMatch('scheduled');
        $this->makeMatch('finished'); // no debe generar predicción para este

        $this->postJson("/api/v1/group/{$this->group->group_id}/ai-competitor")->assertOk();

        $franco = $this->franco();
        $this->assertDatabaseHas('predictions', [
            'user_id' => $franco->user_id,
            'match_id' => $scheduled->match_id,
        ]);
        $this->assertSame(1, Predictions::where('user_id', $franco->user_id)->count());
    }

    public function test_toggle_again_disables_and_removes_franco_from_group(): void
    {
        $this->authenticate();

        $this->postJson("/api/v1/group/{$this->group->group_id}/ai-competitor")->assertOk();
        $response = $this->postJson("/api/v1/group/{$this->group->group_id}/ai-competitor")->assertOk();

        $response->assertJsonPath('data.ai_competitor_enabled', false);
        $this->assertDatabaseMissing('groupmembers', [
            'group_id' => $this->group->group_id,
            'user_id' => $this->franco()->user_id,
        ]);
        $this->assertFalse($this->group->refresh()->ai_competitor_enabled);
    }

    public function test_disabling_removes_francos_predictions_from_the_group(): void
    {
        $this->authenticate();
        $this->makeMatch('scheduled');
        $this->postJson("/api/v1/group/{$this->group->group_id}/ai-competitor")->assertOk();
        $this->assertSame(1, Predictions::where('user_id', $this->franco()->user_id)->count());

        $this->postJson("/api/v1/group/{$this->group->group_id}/ai-competitor")->assertOk();

        $this->assertSame(0, Predictions::where('user_id', $this->franco()->user_id)->count());
    }

    public function test_status_reflects_membership_and_predictions_count_once_enabled(): void
    {
        $this->authenticate();
        $this->makeMatch('scheduled');
        $this->postJson("/api/v1/group/{$this->group->group_id}/ai-competitor")->assertOk();

        $this->getJson("/api/v1/group/{$this->group->group_id}/ai-competitor")
            ->assertOk()
            ->assertJsonPath('data.ai_competitor_enabled', true)
            ->assertJsonPath('data.is_member', true)
            ->assertJsonPath('data.predictions_count', 1);
    }

    public function test_regenerate_fails_when_not_enabled(): void
    {
        $this->authenticate();

        $this->postJson("/api/v1/group/{$this->group->group_id}/ai-competitor/regenerate")
            ->assertStatus(422);
    }

    public function test_regenerate_replaces_old_predictions(): void
    {
        $this->authenticate();
        $this->makeMatch('scheduled');
        $this->postJson("/api/v1/group/{$this->group->group_id}/ai-competitor")->assertOk();
        $firstPredictionId = Predictions::where('user_id', $this->franco()->user_id)->value('prediction_id');

        $this->postJson("/api/v1/group/{$this->group->group_id}/ai-competitor/regenerate")->assertOk();

        $this->assertDatabaseMissing('predictions', ['prediction_id' => $firstPredictionId]);
        $this->assertSame(1, Predictions::where('user_id', $this->franco()->user_id)->count());
    }

    public function test_prediction_endpoint_hides_result_before_match_finishes(): void
    {
        $this->authenticate();
        $match = $this->makeMatch('scheduled');
        $this->postJson("/api/v1/group/{$this->group->group_id}/ai-competitor")->assertOk();

        $this->getJson("/api/v1/group/{$this->group->group_id}/ai-competitor/prediction/{$match->match_id}")
            ->assertOk()
            ->assertJson(['data' => null]);
    }

    public function test_prediction_endpoint_reveals_result_once_match_finishes(): void
    {
        $this->authenticate();
        $match = $this->makeMatch('scheduled');
        $this->postJson("/api/v1/group/{$this->group->group_id}/ai-competitor")->assertOk();

        $match->update(['status' => 'finished', 'home_score' => 1, 'away_score' => 0]);

        $this->getJson("/api/v1/group/{$this->group->group_id}/ai-competitor/prediction/{$match->match_id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['prediction_id', 'match_id', 'predicted_home_score', 'predicted_away_score', 'predicted_result', 'bet_type']]);
    }

    private function makeMatch(string $status): Matches
    {
        return Matches::create([
            'home_team_id' => $this->home->team_id, 'away_team_id' => $this->away->team_id,
            'tournament_id' => $this->tournament->tournament_id, 'stage_id' => $this->stage->stage_id,
            'match_date' => '2026-06-01', 'status' => $status, 'home_score' => 0, 'away_score' => 0,
        ]);
    }
}
