<?php

namespace App\Console\Commands;

use App\Models\GroupMembers;
use App\Models\GroupPool;
use App\Models\Groups;
use App\Models\Transactions;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PoolContributionCommand extends Command
{
    protected $signature = 'pool:contribute {--group_id= : Group ID} {--user_id= : User ID}';

    protected $description = 'Contribute to a group pool (place a bet)';

    public function handle(): int
    {
        $groupId = $this->option('group_id');
        $userId = $this->option('user_id');

        if (! $groupId || ! $userId) {
            $this->error('Usage: pool:contribute --group_id=X --user_id=Y');

            return Command::FAILURE;
        }

        $group = Groups::find($groupId);
        if (! $group) {
            $this->error("Group #{$groupId} not found.");

            return Command::FAILURE;
        }

        if (! $group->pool_active) {
            $this->error("Group #{$group->name} does not have pool active.");

            return Command::FAILURE;
        }

        $user = User::find($userId);
        if (! $user) {
            $this->error("User #{$userId} not found.");

            return Command::FAILURE;
        }

        $isMember = GroupMembers::where('group_id', $groupId)
            ->where('user_id', $userId)
            ->first();

        if (! $isMember) {
            $this->error("User #{$userId} is not a member of group #{$groupId}.");

            return Command::FAILURE;
        }

        $betAmount = $group->bet_amount;

        if (! $betAmount || $betAmount <= 0) {
            $this->error("Group #{$group->name} has no bet amount configured.");

            return Command::FAILURE;
        }

        if ($user->balance < $betAmount) {
            $this->error("Insufficient balance. Required: {$betAmount}, Available: {$user->balance}");

            return Command::FAILURE;
        }

        $existingContribution = GroupPool::where('group_id', $groupId)
            ->where('user_id', $userId)
            ->whereIn('status', ['pending', 'scored'])
            ->first();

        if ($existingContribution) {
            $this->error("User #{$userId} already has an active pool contribution in this group.");

            return Command::FAILURE;
        }

        $newBalance = $user->balance - $betAmount;
        $user->update(['balance' => $newBalance]);

        $pool = GroupPool::create([
            'group_id' => $groupId,
            'user_id' => $userId,
            'amount_applied' => $betAmount,
            'status' => 'pending',
        ]);

        Transactions::create([
            'user_id' => $userId,
            'type' => 'bet',
            'amount' => $betAmount,
            'status' => 'completed',
            'reference' => "Pool contribution for group #{$groupId}",
            'processed_at' => Carbon::now(),
        ]);

        $this->info("User #{$userId} contributed {$betAmount} to pool in group #{$group->name}.");

        return Command::SUCCESS;
    }
}
