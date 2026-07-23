<?php

namespace Tests\Feature;

use App\Models\Groups;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $email = 'john@example.com'): User
    {
        return User::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => $email,
            'password_hash' => bcrypt('secret123'),
        ]);
    }

    public function test_list_groups(): void
    {
        $user = $this->createUser();
        Groups::create([
            'name' => 'Test Group',
            'description' => 'A test group',
            'creator_id' => $user->user_id,
            'is_public' => true,
            'invitation_code' => 'ABC1234',
        ]);

        $response = $this->getJson('/api/v1/group');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_show_group(): void
    {
        $user = $this->createUser();
        $group = Groups::create([
            'name' => 'Test Group',
            'description' => 'A test group',
            'creator_id' => $user->user_id,
            'is_public' => true,
            'invitation_code' => 'ABC1234',
        ]);

        $response = $this->getJson("/api/v1/group/{$group->group_id}");

        $response->assertStatus(200)
            ->assertJson(['data' => ['name' => 'Test Group']]);
    }

    public function test_store_group_authenticated(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/group', [
            'name' => 'My Group',
            'description' => 'Best group ever',
            'creator_id' => $user->user_id,
            'is_public' => true,
            'max_members' => 10,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('groups', ['name' => 'My Group']);
    }

    public function test_store_group_unauthenticated_fails(): void
    {
        $response = $this->postJson('/api/v1/group', [
            'name' => 'My Group',
            'description' => 'Best group',
            'creator_id' => 1,
            'is_public' => true,
        ]);

        $response->assertStatus(401);
    }

    public function test_update_group(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $group = Groups::create([
            'name' => 'Old Name',
            'description' => 'Old desc',
            'creator_id' => $user->user_id,
            'is_public' => true,
            'invitation_code' => 'XYZ7890',
        ]);

        $response = $this->putJson("/api/v1/group/{$group->group_id}", [
            'name' => 'New Name',
            'description' => 'Old desc',
            'creator_id' => $user->user_id,
            'is_public' => true,
            'max_members' => 10,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('groups', ['name' => 'New Name']);
    }

    public function test_delete_group(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $group = Groups::create([
            'name' => 'Delete Me',
            'description' => 'Bye',
            'creator_id' => $user->user_id,
            'is_public' => true,
            'invitation_code' => 'DEL0001',
        ]);

        $response = $this->deleteJson("/api/v1/group/{$group->group_id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('groups', ['group_id' => $group->group_id]);
    }
}
