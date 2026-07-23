<?php

namespace Tests\Feature;

use App\Models\Bets;
use App\Models\GroupBets;
use App\Models\GroupMembers;
use App\Models\Groups;
use App\Models\Matches;
use App\Models\Odds;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentStage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OddsBetsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Matches $match;

    private Groups $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password_hash' => bcrypt('secret123'),
        ]);

        $home = Team::create([
            'name' => 'Barcelona',
            'country' => 'Spain',
            'country_code' => 'ES',
            'active' => true,
        ]);

        $away = Team::create([
            'name' => 'Real Madrid',
            'country' => 'Spain',
            'country_code' => 'ES',
            'active' => true,
        ]);

        $tournament = Tournament::create([
            'name' => 'La Liga',
            'season' => '2025-2026',
            'country' => 'Spain',
            'country_code' => 'ES',
            'start_date' => '2025-08-01',
            'end_date' => '2026-05-31',
            'active' => true,
        ]);

        $stage = TournamentStage::create([
            'tournament_id' => $tournament->tournament_id,
            'name' => 'Regular Season',
            'stage_order' => 1,
            'is_knockout' => false,
        ]);

        $this->match = Matches::create([
            'home_team_id' => $home->team_id,
            'away_team_id' => $away->team_id,
            'tournament_id' => $tournament->tournament_id,
            'stage_id' => $stage->stage_id,
            'match_date' => Carbon::now()->addDays(3),
            'status' => 'scheduled',
            'home_score' => 0,
            'away_score' => 0,
        ]);

        $this->group = Groups::create([
            'name' => 'Betting Group',
            'description' => 'Test',
            'creator_id' => $this->user->user_id,
            'is_public' => true,
            'invitation_code' => 'BET0001',
        ]);

        GroupMembers::create([
            'group_id' => $this->group->group_id,
            'user_id' => $this->user->user_id,
            'is_admin' => true,
        ]);
    }

    // --- ODDS ---

    public function test_list_odds(): void
    {
        Odds::create([
            'match_id' => $this->match->match_id,
            'bet_type' => '1X2',
            'option' => 'Home Win',
            'value' => 1.85,
            'last_update' => Carbon::now(),
        ]);

        $response = $this->getJson('/api/v1/odds');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_store_odd(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/odds', [
            'match_id' => $this->match->match_id,
            'bet_type' => '1X2',
            'option' => 'Home Win',
            'value' => 1.85,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('odds', [
            'match_id' => $this->match->match_id,
            'option' => 'Home Win',
        ]);
    }

    public function test_store_odd_validation_fails(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/odds', [
            'match_id' => $this->match->match_id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bet_type', 'option', 'value']);
    }

    public function test_update_odd(): void
    {
        Sanctum::actingAs($this->user);

        $odd = Odds::create([
            'match_id' => $this->match->match_id,
            'bet_type' => '1X2',
            'option' => 'Home Win',
            'value' => 1.85,
            'last_update' => Carbon::now(),
        ]);

        $response = $this->putJson("/api/v1/odds/{$odd->odd_id}", [
            'match_id' => $this->match->match_id,
            'bet_type' => '1X2',
            'option' => 'Home Win',
            'value' => 2.10,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('odds', ['odd_id' => $odd->odd_id, 'value' => 2.10]);
    }

    public function test_delete_odd(): void
    {
        Sanctum::actingAs($this->user);

        $odd = Odds::create([
            'match_id' => $this->match->match_id,
            'bet_type' => '1X2',
            'option' => 'Home Win',
            'value' => 1.85,
            'last_update' => Carbon::now(),
        ]);

        $response = $this->deleteJson("/api/v1/odds/{$odd->odd_id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('odds', ['odd_id' => $odd->odd_id]);
    }

    // --- BETS ---

    public function test_list_bets(): void
    {
        $odd = Odds::create([
            'match_id' => $this->match->match_id,
            'bet_type' => '1X2',
            'option' => 'Home Win',
            'value' => 1.85,
            'last_update' => Carbon::now(),
        ]);

        Bets::create([
            'user_id' => $this->user->user_id,
            'match_id' => $this->match->match_id,
            'odd_id' => $odd->odd_id,
            'amount' => 10.00,
            'potential_payout' => 18.50,
            'status' => 'pending',
            'placed_at' => Carbon::now(),
        ]);

        $response = $this->getJson('/api/v1/bets');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_store_bet(): void
    {
        Sanctum::actingAs($this->user);

        $odd = Odds::create([
            'match_id' => $this->match->match_id,
            'bet_type' => '1X2',
            'option' => 'Home Win',
            'value' => 1.85,
            'last_update' => Carbon::now(),
        ]);

        $response = $this->postJson('/api/v1/bets', [
            'user_id' => $this->user->user_id,
            'match_id' => $this->match->match_id,
            'odd_id' => $odd->odd_id,
            'amount' => 10.00,
            'potential_payout' => 18.50,
            'status' => 'pending',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('bets', [
            'user_id' => $this->user->user_id,
            'amount' => 10.00,
        ]);
    }

    public function test_store_bet_validation_fails(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/bets', [
            'user_id' => $this->user->user_id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['match_id', 'odd_id', 'amount', 'status']);
    }

    public function test_delete_bet(): void
    {
        Sanctum::actingAs($this->user);

        $odd = Odds::create([
            'match_id' => $this->match->match_id,
            'bet_type' => '1X2',
            'option' => 'Home Win',
            'value' => 1.85,
            'last_update' => Carbon::now(),
        ]);

        $bet = Bets::create([
            'user_id' => $this->user->user_id,
            'match_id' => $this->match->match_id,
            'odd_id' => $odd->odd_id,
            'amount' => 10.00,
            'potential_payout' => 18.50,
            'status' => 'pending',
            'placed_at' => Carbon::now(),
        ]);

        $response = $this->deleteJson("/api/v1/bets/{$bet->bet_id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('bets', ['bet_id' => $bet->bet_id]);
    }

    // --- GROUP BETS ---

    public function test_store_group_bet(): void
    {
        Sanctum::actingAs($this->user);

        $odd = Odds::create([
            'match_id' => $this->match->match_id,
            'bet_type' => '1X2',
            'option' => 'Home Win',
            'value' => 1.85,
            'last_update' => Carbon::now(),
        ]);

        $bet = Bets::create([
            'user_id' => $this->user->user_id,
            'match_id' => $this->match->match_id,
            'odd_id' => $odd->odd_id,
            'amount' => 10.00,
            'potential_payout' => 18.50,
            'status' => 'pending',
            'placed_at' => Carbon::now(),
        ]);

        $response = $this->postJson('/api/v1/groupbets', [
            'group_id' => $this->group->group_id,
            'bet_id' => $bet->bet_id,
            'created_by' => $this->user->user_id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('groupbets', [
            'group_id' => $this->group->group_id,
            'bet_id' => $bet->bet_id,
        ]);
    }

    public function test_list_groupbets(): void
    {
        $odd = Odds::create([
            'match_id' => $this->match->match_id,
            'bet_type' => '1X2',
            'option' => 'Home Win',
            'value' => 1.85,
            'last_update' => Carbon::now(),
        ]);

        $bet = Bets::create([
            'user_id' => $this->user->user_id,
            'match_id' => $this->match->match_id,
            'odd_id' => $odd->odd_id,
            'amount' => 10.00,
            'potential_payout' => 18.50,
            'status' => 'pending',
            'placed_at' => Carbon::now(),
        ]);

        GroupBets::create([
            'group_id' => $this->group->group_id,
            'bet_id' => $bet->bet_id,
            'created_by' => $this->user->user_id,
            'created_at' => Carbon::now(),
        ]);

        $response = $this->getJson('/api/v1/groupbets');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
}
