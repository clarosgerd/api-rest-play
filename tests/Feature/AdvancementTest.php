<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentAdvancement;
use App\Models\TournamentAdvancementRule;
use App\Models\TournamentGroup;
use App\Models\TournamentGroupTeam;
use App\Models\TournamentStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tournament $tournament;

    private TournamentStage $groupStage;

    private TournamentStage $knockoutStage;

    private TournamentGroup $groupA;

    private TournamentGroup $groupB;

    private Team $team1;

    private Team $team2;

    private Team $team3;

    private Team $team4;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password_hash' => bcrypt('secret123'),
        ]);

        $this->tournament = Tournament::create([
            'name' => 'Copa Test',
            'season' => '2026',
            'country' => 'Global',
            'country_code' => 'GL',
            'start_date' => '2026-06-01',
            'end_date' => '2026-07-15',
            'active' => true,
        ]);

        $this->groupStage = TournamentStage::create([
            'tournament_id' => $this->tournament->tournament_id,
            'name' => 'Fase de Grupos',
            'stage_order' => 1,
            'is_knockout' => false,
        ]);

        $this->knockoutStage = TournamentStage::create([
            'tournament_id' => $this->tournament->tournament_id,
            'name' => 'Octavos',
            'stage_order' => 2,
            'is_knockout' => true,
        ]);

        $this->groupA = TournamentGroup::create([
            'stage_id' => $this->groupStage->stage_id,
            'name' => 'Grupo A',
            'group_order' => 1,
            'status' => 'approved',
        ]);

        $this->groupB = TournamentGroup::create([
            'stage_id' => $this->groupStage->stage_id,
            'name' => 'Grupo B',
            'group_order' => 2,
            'status' => 'approved',
        ]);

        $this->team1 = Team::create(['name' => 'Brasil', 'country' => 'Brazil', 'country_code' => 'BR', 'active' => true]);
        $this->team2 = Team::create(['name' => 'Argentina', 'country' => 'Argentina', 'country_code' => 'AR', 'active' => true]);
        $this->team3 = Team::create(['name' => 'Uruguay', 'country' => 'Uruguay', 'country_code' => 'UY', 'active' => true]);
        $this->team4 = Team::create(['name' => 'Chile', 'country' => 'Chile', 'country_code' => 'CL', 'active' => true]);

        TournamentGroupTeam::create(['group_id' => $this->groupA->group_id, 'team_id' => $this->team1->team_id, 'points' => 9, 'goal_diff' => 5, 'goals_for' => 7, 'goals_against' => 2, 'matches_played' => 3, 'wins' => 3, 'draws' => 0, 'losses' => 0]);
        TournamentGroupTeam::create(['group_id' => $this->groupA->group_id, 'team_id' => $this->team2->team_id, 'points' => 6, 'goal_diff' => 2, 'goals_for' => 5, 'goals_against' => 3, 'matches_played' => 3, 'wins' => 2, 'draws' => 0, 'losses' => 1]);
        TournamentGroupTeam::create(['group_id' => $this->groupB->group_id, 'team_id' => $this->team3->team_id, 'points' => 7, 'goal_diff' => 3, 'goals_for' => 6, 'goals_against' => 3, 'matches_played' => 3, 'wins' => 2, 'draws' => 1, 'losses' => 0]);
        TournamentGroupTeam::create(['group_id' => $this->groupB->group_id, 'team_id' => $this->team4->team_id, 'points' => 4, 'goal_diff' => 0, 'goals_for' => 4, 'goals_against' => 4, 'matches_played' => 3, 'wins' => 1, 'draws' => 1, 'losses' => 1]);
    }

    public function test_create_advancement_rule(): void
    {
        $response = $this->postJson(
            "/api/v1/stage/{$this->groupStage->stage_id}/advancement-rule",
            [
                'next_stage_id' => $this->knockoutStage->stage_id,
                'rule_type' => 'top_n',
                'rule_value' => 2,
                'description' => 'Top 2 de cada grupo',
            ],
            ['Authorization' => 'Bearer '.$this->user->createToken('test')->plainTextToken]
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('tournamentadvancementrules', [
            'stage_id' => $this->groupStage->stage_id,
            'rule_type' => 'top_n',
            'rule_value' => 2,
        ]);
    }

    public function test_list_advancement_rules(): void
    {
        TournamentAdvancementRule::create([
            'stage_id' => $this->groupStage->stage_id,
            'next_stage_id' => $this->knockoutStage->stage_id,
            'rule_type' => 'top_n',
            'rule_value' => 2,
        ]);

        $response = $this->getJson("/api/v1/stage/{$this->groupStage->stage_id}/advancement-rules");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'rules' => [
                    ['rule_id', 'rule_type', 'rule_value'],
                ],
            ]);
    }

    public function test_suggest_advancements_top_n(): void
    {
        TournamentAdvancementRule::create([
            'stage_id' => $this->groupStage->stage_id,
            'next_stage_id' => $this->knockoutStage->stage_id,
            'rule_type' => 'top_n',
            'rule_value' => 2,
        ]);

        $response = $this->postJson(
            "/api/v1/stage/{$this->groupStage->stage_id}/advancements/suggest",
            [],
            ['Authorization' => 'Bearer '.$this->user->createToken('test')->plainTextToken]
        );

        $response->assertStatus(200)
            ->assertJsonStructure([
                'suggested' => [
                    ['team_id', 'team_name', 'rule_type'],
                ],
            ]);

        $this->assertDatabaseCount('tournamentadvancement', 4);
    }

    public function test_confirm_advancement(): void
    {
        $rule = TournamentAdvancementRule::create([
            'stage_id' => $this->groupStage->stage_id,
            'next_stage_id' => $this->knockoutStage->stage_id,
            'rule_type' => 'top_n',
            'rule_value' => 1,
        ]);

        $advancement = TournamentAdvancement::create([
            'rule_id' => $rule->rule_id,
            'team_id' => $this->team1->team_id,
            'source_group_id' => $this->groupA->group_id,
            'source_position' => 1,
            'confirmed' => false,
        ]);

        $response = $this->putJson(
            "/api/v1/advancement/{$advancement->advancement_id}/confirm",
            [],
            ['Authorization' => 'Bearer '.$this->user->createToken('test')->plainTextToken]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('tournamentadvancement', [
            'advancement_id' => $advancement->advancement_id,
            'confirmed' => true,
        ]);
    }

    public function test_confirm_all_advancements(): void
    {
        $rule = TournamentAdvancementRule::create([
            'stage_id' => $this->groupStage->stage_id,
            'next_stage_id' => $this->knockoutStage->stage_id,
            'rule_type' => 'top_n',
            'rule_value' => 1,
        ]);

        TournamentAdvancement::create(['rule_id' => $rule->rule_id, 'team_id' => $this->team1->team_id, 'confirmed' => false]);
        TournamentAdvancement::create(['rule_id' => $rule->rule_id, 'team_id' => $this->team3->team_id, 'confirmed' => false]);

        $response = $this->postJson(
            "/api/v1/stage/{$this->groupStage->stage_id}/advancements/confirm-all",
            [],
            ['Authorization' => 'Bearer '.$this->user->createToken('test')->plainTextToken]
        );

        $response->assertStatus(200)
            ->assertJson(['confirmed_count' => 2]);

        $this->assertDatabaseHas('tournamentadvancement', ['team_id' => $this->team1->team_id, 'confirmed' => true]);
        $this->assertDatabaseHas('tournamentadvancement', ['team_id' => $this->team3->team_id, 'confirmed' => true]);
    }

    public function test_list_advanced_teams(): void
    {
        $rule = TournamentAdvancementRule::create([
            'stage_id' => $this->groupStage->stage_id,
            'next_stage_id' => $this->knockoutStage->stage_id,
            'rule_type' => 'top_n',
            'rule_value' => 1,
        ]);

        TournamentAdvancement::create([
            'rule_id' => $rule->rule_id,
            'team_id' => $this->team1->team_id,
            'source_group_id' => $this->groupA->group_id,
            'source_position' => 1,
            'confirmed' => true,
        ]);

        $response = $this->getJson("/api/v1/stage/{$this->groupStage->stage_id}/advancements");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'advancements' => [
                    ['team_id', 'confirmed'],
                ],
            ]);
    }

    public function test_group_standings(): void
    {
        $response = $this->getJson("/api/v1/stage/{$this->groupStage->stage_id}/group/{$this->groupA->group_id}/standings");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'standings' => [
                    ['team_id', 'points', 'goal_diff'],
                ],
            ]);
    }

    public function test_stage_standings(): void
    {
        $response = $this->getJson("/api/v1/stage/{$this->groupStage->stage_id}/standings");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'standings' => [
                    ['group_id', 'group_name', 'standings'],
                ],
            ]);
    }

    public function test_delete_advancement_rule(): void
    {
        $rule = TournamentAdvancementRule::create([
            'stage_id' => $this->groupStage->stage_id,
            'next_stage_id' => $this->knockoutStage->stage_id,
            'rule_type' => 'top_n',
            'rule_value' => 2,
        ]);

        $response = $this->deleteJson(
            "/api/v1/advancement-rule/{$rule->rule_id}",
            [],
            ['Authorization' => 'Bearer '.$this->user->createToken('test')->plainTextToken]
        );

        $response->assertStatus(200);
        $this->assertDatabaseMissing('tournamentadvancementrules', ['rule_id' => $rule->rule_id]);
    }

    public function test_unauthenticated_user_cannot_suggest(): void
    {
        $response = $this->postJson("/api/v1/stage/{$this->groupStage->stage_id}/advancements/suggest");

        $response->assertStatus(401);
    }
}
