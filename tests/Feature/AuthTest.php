<?php

namespace Tests\Feature;

use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private array $validUserData = [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ];

    public function test_register_success(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->validUserData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['user_id', 'first_name', 'last_name', 'email'],
                'token',
                'token_type',
            ])
            ->assertJson(['token_type' => 'Bearer']);

        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    }

    public function test_register_queues_welcome_email(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/register', $this->validUserData)
            ->assertStatus(201);

        Mail::assertQueued(WelcomeMail::class, function (WelcomeMail $mail) {
            return $mail->user->email === 'john@example.com';
        });
    }

    public function test_register_duplicate_email_fails(): void
    {
        User::create([
            'first_name' => 'Existing',
            'last_name' => 'User',
            'email' => 'john@example.com',
            'password_hash' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/v1/auth/register', $this->validUserData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_missing_fields_fails(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'password']);
    }

    public function test_register_short_password_fails(): void
    {
        $data = $this->validUserData;
        $data['password'] = '123';
        $data['password_confirmation'] = '123';

        $response = $this->postJson('/api/v1/auth/register', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_login_success(): void
    {
        User::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password_hash' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'john@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => ['user_id', 'email'],
                'token',
                'token_type',
            ]);
    }

    public function test_login_wrong_password_fails(): void
    {
        User::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password_hash' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'john@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_nonexistent_user_fails(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(422);
    }

    public function test_logout_success(): void
    {
        $user = User::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password_hash' => bcrypt('secret123'),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully']);
    }

    public function test_logout_unauthenticated_fails(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    }
}
