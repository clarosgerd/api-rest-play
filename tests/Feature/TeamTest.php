<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        return User::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password_hash' => bcrypt('secret123'),
        ]);
    }

    private function teamData(): array
    {
        return [
            'name' => 'Barcelona',
            'country' => 'Spain',
            'league' => 'La Liga',
            'logo_url' => 'https://example.com/barca.png',
            'flag_url' => 'https://example.com/spain.png',
            'country_code' => 'ES',
            'active' => 1,
        ];
    }

    public function test_list_teams(): void
    {
        Team::create($this->teamData());
        Team::create(array_merge($this->teamData(), ['name' => 'Real Madrid']));

        $response = $this->getJson('/api/v1/team');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_show_team(): void
    {
        $team = Team::create($this->teamData());

        $response = $this->getJson("/api/v1/team/{$team->team_id}");

        $response->assertStatus(200);
    }

    public function test_store_team_authenticated(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/team', $this->teamData());

        $response->assertStatus(201);

        $this->assertDatabaseHas('teams', ['name' => 'Barcelona']);
    }

    public function test_store_team_unauthenticated_fails(): void
    {
        $response = $this->postJson('/api/v1/team', $this->teamData());

        $response->assertStatus(401);
    }

    public function test_update_team(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $team = Team::create($this->teamData());

        $response = $this->putJson("/api/v1/team/{$team->team_id}", array_merge($this->teamData(), [
            'name' => 'FC Barcelona',
        ]));

        $response->assertStatus(200);
        $this->assertDatabaseHas('teams', ['name' => 'FC Barcelona']);
    }

    public function test_delete_team(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $team = Team::create($this->teamData());

        $response = $this->deleteJson("/api/v1/team/{$team->team_id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('teams', ['team_id' => $team->team_id]);
    }

    public function test_filter_teams_by_name(): void
    {
        Team::create($this->teamData());
        Team::create(array_merge($this->teamData(), ['name' => 'Real Madrid']));

        $response = $this->getJson('/api/v1/team?name[eq]=Barcelona');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
}
