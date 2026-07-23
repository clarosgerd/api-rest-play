<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentGroup;
use App\Models\TournamentGroupTeam;
use App\Models\TournamentStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentGroupTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tournament $tournament;

    private TournamentStage $stage;

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
            'name' => 'Copa Mundial 2026',
            'season' => '2026',
            'country' => 'Global',
            'country_code' => 'GL',
            'start_date' => '2026-06-01',
            'end_date' => '2026-07-15',
            'active' => true,
        ]);

        $this->stage = TournamentStage::create([
            'tournament_id' => $this->tournament->tournament_id,
            'name' => 'Fase de Grupos',
            'stage_order' => 1,
            'is_knockout' => false,
        ]);
    }

    public function test_list_groups_of_tournament(): void
    {
        TournamentGroup::create([
            'stage_id' => $this->stage->stage_id,
            'name' => 'Grupo A',
            'group_order' => 1,
            'status' => 'approved',
        ]);

        $response = $this->getJson("/api/v1/tournament/{$this->tournament->tournament_id}/groups");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'tournament_id',
                'groups' => [
                    [
                        'group_id',
                        'name',
                        'status',
                    ],
                ],
            ]);
    }

    public function test_create_tournament_group(): void
    {
        $response = $this->postJson(
            "/api/v1/tournament/{$this->tournament->tournament_id}/group",
            [
                'stage_id' => $this->stage->stage_id,
                'name' => 'Grupo A',
                'group_order' => 1,
            ],
            ['Authorization' => 'Bearer '.$this->user->createToken('test')->plainTextToken]
        );

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'group' => ['group_id', 'name', 'status'],
            ]);

        $this->assertDatabaseHas('tournamentgroups', [
            'name' => 'Grupo A',
            'status' => 'pending',
        ]);
    }

    public function test_update_tournament_group(): void
    {
        $group = TournamentGroup::create([
            'stage_id' => $this->stage->stage_id,
            'name' => 'Grupo A',
            'group_order' => 1,
            'status' => 'pending',
        ]);

        $response = $this->putJson(
            "/api/v1/tournament-group/{$group->group_id}",
            ['name' => 'Grupo A Updated'],
            ['Authorization' => 'Bearer '.$this->user->createToken('test')->plainTextToken]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('tournamentgroups', ['name' => 'Grupo A Updated']);
    }

    public function test_delete_tournament_group(): void
    {
        $group = TournamentGroup::create([
            'stage_id' => $this->stage->stage_id,
            'name' => 'Grupo A',
            'group_order' => 1,
            'status' => 'pending',
        ]);

        $response = $this->deleteJson(
            "/api/v1/tournament-group/{$group->group_id}",
            [],
            ['Authorization' => 'Bearer '.$this->user->createToken('test')->plainTextToken]
        );

        $response->assertStatus(200);
        $this->assertDatabaseMissing('tournamentgroups', ['group_id' => $group->group_id]);
    }

    public function test_add_teams_to_group(): void
    {
        $group = TournamentGroup::create([
            'stage_id' => $this->stage->stage_id,
            'name' => 'Grupo A',
            'group_order' => 1,
            'status' => 'pending',
        ]);

        $team1 = Team::create(['name' => 'Brasil', 'country' => 'Brazil', 'country_code' => 'BR', 'active' => true]);
        $team2 = Team::create(['name' => 'Argentina', 'country' => 'Argentina', 'country_code' => 'AR', 'active' => true]);

        $response = $this->postJson(
            "/api/v1/tournament-group/{$group->group_id}/teams",
            ['team_ids' => [$team1->team_id, $team2->team_id]],
            ['Authorization' => 'Bearer '.$this->user->createToken('test')->plainTextToken]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('tournamentgroupteams', ['group_id' => $group->group_id, 'team_id' => $team1->team_id]);
        $this->assertDatabaseHas('tournamentgroupteams', ['group_id' => $group->group_id, 'team_id' => $team2->team_id]);
    }

    public function test_remove_team_from_group(): void
    {
        $group = TournamentGroup::create([
            'stage_id' => $this->stage->stage_id,
            'name' => 'Grupo A',
            'group_order' => 1,
            'status' => 'pending',
        ]);

        $team = Team::create(['name' => 'Brasil', 'country' => 'Brazil', 'country_code' => 'BR', 'active' => true]);

        TournamentGroupTeam::create([
            'group_id' => $group->group_id,
            'team_id' => $team->team_id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/tournament-group/{$group->group_id}/teams/{$team->team_id}",
            [],
            ['Authorization' => 'Bearer '.$this->user->createToken('test')->plainTextToken]
        );

        $response->assertStatus(200);
        $this->assertDatabaseMissing('tournamentgroupteams', ['group_id' => $group->group_id, 'team_id' => $team->team_id]);
    }

    public function test_approve_group(): void
    {
        $group = TournamentGroup::create([
            'stage_id' => $this->stage->stage_id,
            'name' => 'Grupo A',
            'group_order' => 1,
            'status' => 'pending',
        ]);

        $response = $this->postJson(
            "/api/v1/tournament-group/{$group->group_id}/approve",
            [],
            ['Authorization' => 'Bearer '.$this->user->createToken('test')->plainTextToken]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('tournamentgroups', ['group_id' => $group->group_id, 'status' => 'approved']);
    }

    public function test_reject_group(): void
    {
        $group = TournamentGroup::create([
            'stage_id' => $this->stage->stage_id,
            'name' => 'Grupo A',
            'group_order' => 1,
            'status' => 'pending',
        ]);

        $response = $this->postJson(
            "/api/v1/tournament-group/{$group->group_id}/reject",
            [],
            ['Authorization' => 'Bearer '.$this->user->createToken('test')->plainTextToken]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('tournamentgroups', ['group_id' => $group->group_id, 'status' => 'rejected']);
    }

    public function test_unauthenticated_user_cannot_create_group(): void
    {
        $response = $this->postJson(
            "/api/v1/tournament/{$this->tournament->tournament_id}/group",
            ['stage_id' => $this->stage->stage_id, 'name' => 'Grupo A']
        );

        $response->assertStatus(401);
    }

    public function test_show_tournament_group(): void
    {
        $group = TournamentGroup::create([
            'stage_id' => $this->stage->stage_id,
            'name' => 'Grupo A',
            'group_order' => 1,
            'status' => 'approved',
        ]);

        $response = $this->getJson("/api/v1/tournament-group/{$group->group_id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'group' => ['group_id', 'name', 'status'],
            ]);
    }
}
