<?php

namespace Tests\Feature;

use App\Models\GroupMembers;
use App\Models\Groups;
use App\Models\Invitation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;

    private Groups $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = User::create([
            'first_name' => 'Creator',
            'last_name' => 'User',
            'email' => 'creator@example.com',
            'password_hash' => bcrypt('secret123'),
        ]);

        $this->group = Groups::create([
            'name' => 'Test Group',
            'description' => 'A test group',
            'creator_id' => $this->creator->user_id,
            'is_public' => true,
            'invitation_code' => 'INVITE1',
            'max_members' => 10,
        ]);

        GroupMembers::create([
            'group_id' => $this->group->group_id,
            'user_id' => $this->creator->user_id,
            'is_admin' => true,
        ]);
    }

    public function test_send_invitation(): void
    {
        Sanctum::actingAs($this->creator);

        $response = $this->postJson('/api/v1/invitation', [
            'group_id' => $this->group->group_id,
            'email' => 'friend@example.com',
        ]);

        $response->assertStatus(201)
            ->assertJson(['message' => 'Invitation sent successfully']);

        $this->assertDatabaseHas('invitations', [
            'email' => 'friend@example.com',
            'status' => 'pending',
        ]);
    }

    public function test_send_invitation_non_creator_fails(): void
    {
        $other = User::create([
            'first_name' => 'Other',
            'last_name' => 'User',
            'email' => 'other@example.com',
            'password_hash' => bcrypt('secret123'),
        ]);
        Sanctum::actingAs($other);

        $response = $this->postJson('/api/v1/invitation', [
            'group_id' => $this->group->group_id,
            'email' => 'someone@example.com',
        ]);

        $response->assertStatus(403);
    }

    public function test_send_duplicate_invitation_fails(): void
    {
        Sanctum::actingAs($this->creator);

        Invitation::create([
            'group_id' => $this->group->group_id,
            'invited_by' => $this->creator->user_id,
            'email' => 'friend@example.com',
            'status' => 'pending',
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $response = $this->postJson('/api/v1/invitation', [
            'group_id' => $this->group->group_id,
            'email' => 'friend@example.com',
        ]);

        $response->assertStatus(409);
    }

    public function test_accept_invitation_by_code(): void
    {
        $invitee = User::create([
            'first_name' => 'Invitee',
            'last_name' => 'User',
            'email' => 'invitee@example.com',
            'password_hash' => bcrypt('secret123'),
        ]);
        Sanctum::actingAs($invitee);

        $response = $this->postJson('/api/v1/invitation/accept-code', [
            'invitation_code' => 'INVITE1',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Successfully joined the group']);

        $this->assertDatabaseHas('groupmembers', [
            'group_id' => $this->group->group_id,
            'user_id' => $invitee->user_id,
        ]);
    }

    public function test_accept_invalid_code_fails(): void
    {
        $invitee = User::create([
            'first_name' => 'Invitee',
            'last_name' => 'User',
            'email' => 'invitee@example.com',
            'password_hash' => bcrypt('secret123'),
        ]);
        Sanctum::actingAs($invitee);

        $response = $this->postJson('/api/v1/invitation/accept-code', [
            'invitation_code' => 'INVALID',
        ]);

        $response->assertStatus(404);
    }

    public function test_accept_already_member_fails(): void
    {
        Sanctum::actingAs($this->creator);

        $response = $this->postJson('/api/v1/invitation/accept-code', [
            'invitation_code' => 'INVITE1',
        ]);

        $response->assertStatus(409);
    }

    public function test_accept_invitation_by_id(): void
    {
        $invitee = User::create([
            'first_name' => 'Invitee',
            'last_name' => 'User',
            'email' => 'invitee@example.com',
            'password_hash' => bcrypt('secret123'),
        ]);

        $invitation = Invitation::create([
            'group_id' => $this->group->group_id,
            'invited_by' => $this->creator->user_id,
            'email' => 'invitee@example.com',
            'status' => 'pending',
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        Sanctum::actingAs($invitee);

        $response = $this->postJson("/api/v1/invitation/{$invitation->invitation_id}/accept");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Successfully joined the group']);
    }

    public function test_accept_wrong_email_fails(): void
    {
        $other = User::create([
            'first_name' => 'Other',
            'last_name' => 'User',
            'email' => 'other@example.com',
            'password_hash' => bcrypt('secret123'),
        ]);

        $invitation = Invitation::create([
            'group_id' => $this->group->group_id,
            'invited_by' => $this->creator->user_id,
            'email' => 'invitee@example.com',
            'status' => 'pending',
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        Sanctum::actingAs($other);

        $response = $this->postJson("/api/v1/invitation/{$invitation->invitation_id}/accept");

        $response->assertStatus(403);
    }

    public function test_cancel_invitation(): void
    {
        $invitation = Invitation::create([
            'group_id' => $this->group->group_id,
            'invited_by' => $this->creator->user_id,
            'email' => 'friend@example.com',
            'status' => 'pending',
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        Sanctum::actingAs($this->creator);

        $response = $this->deleteJson("/api/v1/invitation/{$invitation->invitation_id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Invitation cancelled']);

        $this->assertDatabaseHas('invitations', [
            'invitation_id' => $invitation->invitation_id,
            'status' => 'declined',
        ]);
    }

    public function test_cancel别人的_invitation_fails(): void
    {
        $other = User::create([
            'first_name' => 'Other',
            'last_name' => 'User',
            'email' => 'other@example.com',
            'password_hash' => bcrypt('secret123'),
        ]);

        $invitation = Invitation::create([
            'group_id' => $this->group->group_id,
            'invited_by' => $this->creator->user_id,
            'email' => 'friend@example.com',
            'status' => 'pending',
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        Sanctum::actingAs($other);

        $response = $this->deleteJson("/api/v1/invitation/{$invitation->invitation_id}");

        $response->assertStatus(403);
    }

    public function test_list_pending_invitations(): void
    {
        Invitation::create([
            'group_id' => $this->group->group_id,
            'invited_by' => $this->creator->user_id,
            'email' => 'invitee@example.com',
            'status' => 'pending',
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $invitee = User::create([
            'first_name' => 'Invitee',
            'last_name' => 'User',
            'email' => 'invitee@example.com',
            'password_hash' => bcrypt('secret123'),
        ]);

        Sanctum::actingAs($invitee);

        $response = $this->getJson('/api/v1/invitation');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'invitations');
    }
}
