<?php

namespace Tests\Feature;

use App\Models\GroupMembers;
use App\Models\GroupPool;
use App\Models\GroupPredictions;
use App\Models\Groups;
use App\Models\Matches;
use App\Models\PredictionResults;
use App\Models\Predictions;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentStage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PoolTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Groups $group;

    private Matches $match;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password_hash' => bcrypt('secret123'),
            'balance' => 100.00,
        ]);

        $home = Team::create(['name' => 'Team A', 'country' => 'X', 'country_code' => 'XX', 'active' => true]);
        $away = Team::create(['name' => 'Team B', 'country' => 'Y', 'country_code' => 'YY', 'active' => true]);

        $tournament = Tournament::create([
            'name' => 'Test Cup', 'season' => '2026', 'country' => 'Z', 'country_code' => 'ZZ',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'active' => true,
        ]);

        $stage = TournamentStage::create([
            'tournament_id' => $tournament->tournament_id, 'name' => 'Regular', 'stage_order' => 1, 'is_knockout' => false,
        ]);

        $this->match = Matches::create([
            'home_team_id' => $home->team_id, 'away_team_id' => $away->team_id,
            'tournament_id' => $tournament->tournament_id, 'stage_id' => $stage->stage_id,
            'match_date' => Carbon::now()->subDays(1), 'status' => 'finished', 'home_score' => 2, 'away_score' => 1,
        ]);

        $this->group = Groups::create([
            'name' => 'Pool Group', 'description' => 'Test', 'creator_id' => $this->user->user_id,
            'is_public' => true, 'invitation_code' => 'POOL1',
            'bet_amount' => 10.00, 'prize_1st_pct' => 60, 'prize_2nd_pct' => 30, 'prize_3rd_pct' => 10,
            'pool_active' => true,
        ]);

        GroupMembers::create([
            'group_id' => $this->group->group_id, 'user_id' => $this->user->user_id, 'is_admin' => true,
        ]);
    }

    public function test_create_group_with_pool(): void
    {
        $this->assertEquals(10.00, $this->group->bet_amount);
        $this->assertEquals(60, $this->group->prize_1st_pct);
        $this->assertEquals(30, $this->group->prize_2nd_pct);
        $this->assertEquals(10, $this->group->prize_3rd_pct);
        $this->assertTrue($this->group->pool_active);
    }

    public function test_get_prize_distribution(): void
    {
        $prizes = $this->group->getPrizeDistribution();

        $this->assertEquals(60, $prizes['1st']);
        $this->assertEquals(30, $prizes['2nd']);
        $this->assertEquals(10, $prizes['3rd']);
    }

    public function test_pool_contribution(): void
    {
        Artisan::call('pool:contribute', [
            '--group_id' => $this->group->group_id,
            '--user_id' => $this->user->user_id,
        ]);

        $this->assertDatabaseHas('grouppool', [
            'group_id' => $this->group->group_id,
            'user_id' => $this->user->user_id,
            'amount_applied' => 10.00,
            'status' => 'pending',
        ]);

        $this->user->refresh();
        $this->assertEquals(90.00, $this->user->balance);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->user_id,
            'type' => 'bet',
            'amount' => 10.00,
        ]);
    }

    public function test_pool_insufficient_balance(): void
    {
        $this->user->update(['balance' => 5.00]);

        Artisan::call('pool:contribute', [
            '--group_id' => $this->group->group_id,
            '--user_id' => $this->user->user_id,
        ]);

        $this->assertDatabaseMissing('grouppool', [
            'group_id' => $this->group->group_id,
            'user_id' => $this->user->user_id,
        ]);
    }

    public function test_pool_not_member(): void
    {
        $otherUser = User::create([
            'first_name' => 'Jane', 'last_name' => 'Smith', 'email' => 'jane@example.com',
            'password_hash' => bcrypt('secret123'), 'balance' => 100.00,
        ]);

        Artisan::call('pool:contribute', [
            '--group_id' => $this->group->group_id,
            '--user_id' => $otherUser->user_id,
        ]);

        $this->assertDatabaseMissing('grouppool', [
            'group_id' => $this->group->group_id,
            'user_id' => $otherUser->user_id,
        ]);
    }

    public function test_pool_group_not_active(): void
    {
        $this->group->update(['pool_active' => false]);

        Artisan::call('pool:contribute', [
            '--group_id' => $this->group->group_id,
            '--user_id' => $this->user->user_id,
        ]);

        $this->assertDatabaseMissing('grouppool', [
            'group_id' => $this->group->group_id,
            'user_id' => $this->user->user_id,
        ]);
    }

    public function test_pool_duplicate_contribution_blocked(): void
    {
        GroupPool::create([
            'group_id' => $this->group->group_id, 'user_id' => $this->user->user_id,
            'amount_applied' => 10.00, 'status' => 'pending',
        ]);

        Artisan::call('pool:contribute', [
            '--group_id' => $this->group->group_id,
            '--user_id' => $this->user->user_id,
        ]);

        $count = GroupPool::where('group_id', $this->group->group_id)
            ->where('user_id', $this->user->user_id)
            ->count();
        $this->assertEquals(1, $count);
    }

    public function test_pool_distribution(): void
    {
        $user1 = $this->user;
        $user2 = User::create([
            'first_name' => 'Jane', 'last_name' => 'Smith', 'email' => 'jane@example.com',
            'password_hash' => bcrypt('secret123'), 'balance' => 100.00,
        ]);

        GroupMembers::create([
            'group_id' => $this->group->group_id, 'user_id' => $user2->user_id, 'is_admin' => false,
        ]);

        $pred1 = Predictions::create([
            'user_id' => $user1->user_id, 'match_id' => $this->match->match_id,
            'predicted_home_score' => 2, 'predicted_away_score' => 1, 'predicted_result' => 'home_win',
            'bet_type' => 'exact_score', 'confidence' => 9, 'created_at' => Carbon::now(), 'is_public' => true,
        ]);

        GroupPredictions::create([
            'group_id' => $this->group->group_id, 'prediction_id' => $pred1->prediction_id, 'points_awarded' => 0,
        ]);

        PredictionResults::create([
            'prediction_id' => $pred1->prediction_id, 'points_earned' => 36,
            'points_breakdown' => json_encode(['total' => 36]), 'processed_at' => Carbon::now(),
        ]);

        $pred2 = Predictions::create([
            'user_id' => $user2->user_id, 'match_id' => $this->match->match_id,
            'predicted_home_score' => 3, 'predicted_away_score' => 0, 'predicted_result' => 'home_win',
            'bet_type' => 'match_result', 'confidence' => 5, 'created_at' => Carbon::now(), 'is_public' => true,
        ]);

        GroupPredictions::create([
            'group_id' => $this->group->group_id, 'prediction_id' => $pred2->prediction_id, 'points_awarded' => 0,
        ]);

        PredictionResults::create([
            'prediction_id' => $pred2->prediction_id, 'points_earned' => 5,
            'points_breakdown' => json_encode(['total' => 5]), 'processed_at' => Carbon::now(),
        ]);

        GroupPool::create([
            'group_id' => $this->group->group_id, 'user_id' => $user1->user_id, 'amount_applied' => 10.00, 'status' => 'pending',
        ]);

        GroupPool::create([
            'group_id' => $this->group->group_id, 'user_id' => $user2->user_id, 'amount_applied' => 10.00, 'status' => 'pending',
        ]);

        Artisan::call('pool:distribute', ['--group_id' => $this->group->group_id]);

        $user1->refresh();
        $user2->refresh();

        $distributed = GroupPool::where('group_id', $this->group->group_id)
            ->where('status', 'distributed')
            ->get();

        $this->assertEquals(2, $distributed->count());

        $topWinner = $distributed->sortByDesc('prize_won')->first();
        $this->assertEquals(12.00, $topWinner->prize_won);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user1->user_id, 'type' => 'win',
        ]);
    }
}
