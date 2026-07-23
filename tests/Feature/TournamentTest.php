<?php

namespace Tests\Feature;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TournamentTest extends TestCase
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

    private function tournamentData(): array
    {
        return [
            'name' => 'Premier League',
            'season' => '2025-2026',
            'country' => 'England',
            'country_code' => 'EN',
            'logo_url' => 'https://example.com/pl.png',
            'flag_url' => 'https://example.com/eng.png',
            'start_date' => '2025-08-01',
            'end_date' => '2026-05-31',
            'actived' => 1,
        ];
    }

    public function test_list_tournaments(): void
    {
        Tournament::create($this->tournamentData());
        Tournament::create(array_merge($this->tournamentData(), ['name' => 'La Liga']));

        $response = $this->getJson('/api/v1/tournament');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_show_tournament(): void
    {
        $tournament = Tournament::create($this->tournamentData());

        $response = $this->getJson("/api/v1/tournament/{$tournament->tournament_id}");

        $response->assertStatus(200);
    }

    public function test_store_tournament_authenticated(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/tournament', $this->tournamentData());

        $response->assertStatus(201);

        $this->assertDatabaseHas('tournaments', ['name' => 'Premier League']);
    }

    public function test_store_tournament_unauthenticated_fails(): void
    {
        $response = $this->postJson('/api/v1/tournament', $this->tournamentData());

        $response->assertStatus(401);
    }

    public function test_update_tournament(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $tournament = Tournament::create($this->tournamentData());

        $response = $this->putJson("/api/v1/tournament/{$tournament->tournament_id}", array_merge($this->tournamentData(), [
            'name' => 'Premier League Updated',
        ]));

        $response->assertStatus(200);

        $this->assertDatabaseHas('tournaments', ['name' => 'Premier League Updated']);
    }

    public function test_delete_tournament(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $tournament = Tournament::create($this->tournamentData());

        $response = $this->deleteJson("/api/v1/tournament/{$tournament->tournament_id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('tournaments', ['tournament_id' => $tournament->tournament_id]);
    }
}
