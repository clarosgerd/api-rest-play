<?php

namespace Tests\Feature;

use App\Models\Groups;
use App\Models\User;
use App\Notifications\GroupCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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

    public function test_store_group_without_creator_id_uses_authenticated_user(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/group', [
            'name' => 'No Creator Id Sent',
            'description' => 'Reproduce bug: creator_id field is required',
            'is_public' => true,
            'max_members' => 10,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('groups', [
            'name' => 'No Creator Id Sent',
            'creator_id' => $user->user_id,
        ]);
    }

    public function test_store_group_cannot_spoof_creator_id(): void
    {
        $user = $this->createUser();
        $other = $this->createUser('other@example.com');
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/group', [
            'name' => 'Spoofed Owner',
            'description' => 'desc',
            'creator_id' => $other->user_id,
            'is_public' => true,
            'max_members' => 10,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('groups', [
            'name' => 'Spoofed Owner',
            'creator_id' => $user->user_id,
        ]);
    }

    public function test_insert_first_creates_group_and_admin_membership_without_creator_id(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/group/first', [
            'name' => 'My First Group',
            'description' => 'Best group ever',
            'is_public' => '1',
            'max_members' => '20',
        ]);

        // 201, no 200: ahora se devuelve el $group recién creado en memoria
        // (wasRecentlyCreated=true), no un refetch vía Groups::all()->last().
        $response->assertStatus(201);
        $this->assertDatabaseHas('groups', [
            'name' => 'My First Group',
            'creator_id' => $user->user_id,
        ]);

        $group = Groups::where('name', 'My First Group')->first();
        $this->assertDatabaseHas('groupmembers', [
            'group_id' => $group->group_id,
            'user_id' => $user->user_id,
            'is_admin' => 1,
        ]);
    }

    public function test_store_group_sends_invitation_code_email(): void
    {
        Notification::fake();
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/group', [
            'name' => 'My Group',
            'description' => 'Best group ever',
            'is_public' => true,
            'max_members' => 10,
        ])->assertStatus(201);

        $group = Groups::where('name', 'My Group')->first();

        Notification::assertSentTo(
            $user,
            GroupCreatedNotification::class,
            fn (GroupCreatedNotification $notification) => $notification->group->is($group)
                && $notification->group->invitation_code === $group->invitation_code
        );
    }

    public function test_insert_first_sends_invitation_code_email(): void
    {
        Notification::fake();
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/group/first', [
            'name' => 'My First Group',
            'description' => 'Best group ever',
            'is_public' => '1',
            'max_members' => '20',
        ])->assertStatus(201);

        $group = Groups::where('name', 'My First Group')->first();

        Notification::assertSentTo(
            $user,
            GroupCreatedNotification::class,
            fn (GroupCreatedNotification $notification) => $notification->group->is($group)
        );
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
