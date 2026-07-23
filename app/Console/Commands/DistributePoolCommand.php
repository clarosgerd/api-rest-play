<?php

namespace App\Console\Commands;

use App\Models\GroupPool;
use App\Models\GroupPredictions;
use App\Models\Groups;
use App\Models\PredictionResults;
use App\Models\Transactions;
use App\Models\User;
use App\Notifications\PoolDistributedNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DistributePoolCommand extends Command
{
    protected $signature = 'pool:distribute {--group_id= : Group ID}';

    protected $description = 'Distribute pool prizes to top scorers in a group';

    public function handle(): int
    {
        $groupId = $this->option('group_id');

        if (! $groupId) {
            $this->error('Usage: pool:distribute --group_id=X');

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

        $poolEntries = GroupPool::where('group_id', $groupId)
            ->where('status', 'pending')
            ->get();

        if ($poolEntries->isEmpty()) {
            $this->error("No pending pool contributions found for group #{$groupId}.");

            return Command::FAILURE;
        }

        $totalPool = $poolEntries->sum('amount_applied');

        $this->info("Total pool: {$totalPool} ({$poolEntries->count()} contributors)");

        $memberScores = $this->getMemberScores($groupId, $poolEntries);

        if ($memberScores->isEmpty()) {
            $this->info('No scores found for pool contributors. Skipping distribution.');

            return Command::SUCCESS;
        }

        $distribution = $this->calculateDistribution($memberScores, $totalPool, $group);

        $this->displayDistribution($distribution, $memberScores);

        DB::transaction(function () use ($distribution, $poolEntries, $groupId) {
            foreach ($distribution as $place => $data) {
                $userId = $data['user_id'];
                $prize = $data['prize'];

                $user = User::find($userId);
                if (! $user) {
                    continue;
                }

                $newBalance = $user->balance + $prize;
                $user->update(['balance' => $newBalance]);

                $poolEntry = $poolEntries->where('user_id', $userId)->first();
                if ($poolEntry) {
                    $poolEntry->update([
                        'status' => 'distributed',
                        'prize_won' => $prize,
                        'distributed_at' => Carbon::now(),
                    ]);
                }

                Transactions::create([
                    'user_id' => $userId,
                    'type' => 'win',
                    'amount' => $prize,
                    'status' => 'completed',
                    'reference' => "Pool prize {$place} for group #{$groupId}",
                    'processed_at' => Carbon::now(),
                ]);
            }

            $poolEntries->where('status', 'pending')->each(function ($entry) {
                $entry->update([
                    'status' => 'scored',
                    'prize_won' => 0,
                    'scored_at' => Carbon::now(),
                ]);
            });
        });

        foreach ($distribution as $data) {
            $winner = User::find($data['user_id']);
            if ($winner && $winner->email) {
                try {
                    $winner->notify(new PoolDistributedNotification(
                        $group,
                        $data['place'],
                        $data['prize'],
                        (int) $totalPool
                    ));
                } catch (\Exception $e) {
                    // Notification failed, continue
                }
            }
        }

        $this->info('Pool distribution complete.');

        return Command::SUCCESS;
    }

    private function getMemberScores(int $groupId, $poolEntries): \Illuminate\Support\Collection
    {
        $userIds = $poolEntries->pluck('user_id')->unique();

        $memberPredictions = GroupPredictions::where('group_id', $groupId)
            ->whereIn('prediction_id', function ($query) use ($userIds) {
                $query->select('prediction_id')
                    ->from('predictions')
                    ->whereIn('user_id', $userIds);
            })
            ->get();

        $resultPoints = PredictionResults::whereIn('prediction_id', $memberPredictions->pluck('prediction_id'))
            ->pluck('points_earned', 'prediction_id');

        $scores = [];

        foreach ($memberPredictions as $mp) {
            $userId = $mp->prediction->user_id ?? null;
            if (! $userId) {
                continue;
            }

            if (! isset($scores[$userId])) {
                $scores[$userId] = ['user_id' => $userId, 'total_points' => 0];
            }

            $scores[$userId]['total_points'] += $resultPoints[$mp->prediction_id] ?? 0;
        }

        return collect($scores)->sortByDesc('total_points')->values();
    }

    private function calculateDistribution(\Illuminate\Support\Collection $memberScores, float $totalPool, Groups $group): array
    {
        $prizes = $group->getPrizeDistribution();
        $distribution = [];
        $sorted = $memberScores->sortByDesc('total_points')->values();

        $placeMap = [
            0 => ['place' => '1st', 'pct_key' => '1st'],
            1 => ['place' => '2nd', 'pct_key' => '2nd'],
            2 => ['place' => '3rd', 'pct_key' => '3rd'],
        ];

        foreach ($placeMap as $index => $placeInfo) {
            if (! isset($sorted[$index])) {
                break;
            }

            $pct = $prizes[$placeInfo['pct_key']] / 100;
            $prize = round($totalPool * $pct, 2);

            $distribution[] = [
                'place' => $placeInfo['place'],
                'user_id' => $sorted[$index]['user_id'],
                'points' => $sorted[$index]['total_points'],
                'percentage' => $prizes[$placeInfo['pct_key']],
                'prize' => $prize,
            ];
        }

        return $distribution;
    }

    private function displayDistribution(array $distribution, \Illuminate\Support\Collection $memberScores): void
    {
        $this->info('');
        $this->info('Pool Distribution:');
        $this->info(str_repeat('-', 60));

        foreach ($distribution as $entry) {
            $user = User::find($entry['user_id']);
            $name = $user ? "{$user->first_name} {$user->last_name}" : "User #{$entry['user_id']}";
            $this->info("  {$entry['place']}: {$name} - {$entry['points']} pts - {$entry['percentage']}% - Prize: {$entry['prize']}");
        }

        $this->info(str_repeat('-', 60));
    }
}
