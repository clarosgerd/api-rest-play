<?php

namespace App\Http\Controllers;

use App\Models\GroupMembers;
use App\Models\GroupPool;
use App\Models\Groups;
use App\Models\Transactions;
use App\Models\User;
use App\Notifications\PoolDistributedNotification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PoolController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $groupId = $request->query('group_id');

        if (! $groupId) {
            return response()->json(['message' => 'group_id is required'], 422);
        }

        $group = Groups::findOrFail($groupId);

        if (! $group->pool_active) {
            return response()->json(['message' => 'Pool is not active for this group'], 422);
        }

        $entries = GroupPool::where('group_id', $groupId)
            ->with('user:user_id,first_name,last_name')
            ->get();

        $totalPool = $entries->where('status', 'pending')->sum('amount_applied');

        return response()->json([
            'data' => $entries,
            'summary' => [
                'total_pool' => $totalPool,
                'contributors' => $entries->where('status', 'pending')->count(),
                'bet_amount' => $group->bet_amount,
                'prize_distribution' => $group->getPrizeDistribution(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group_id' => 'required|integer|exists:groups,group_id',
        ]);

        $user = $request->user();
        $group = Groups::findOrFail($validated['group_id']);

        if (! $group->pool_active) {
            return response()->json([
                'message' => 'Pool is not active for this group',
            ], 422);
        }

        $isMember = GroupMembers::where('group_id', $validated['group_id'])
            ->where('user_id', $user->user_id)
            ->first();

        if (! $isMember) {
            return response()->json([
                'message' => 'You are not a member of this group',
            ], 403);
        }

        $betAmount = $group->bet_amount;

        if (! $betAmount || $betAmount <= 0) {
            return response()->json([
                'message' => 'This group has no bet amount configured',
            ], 422);
        }

        if ($user->balance < $betAmount) {
            return response()->json([
                'message' => "Insufficient balance. Required: {$betAmount}, Available: {$user->balance}",
            ], 422);
        }

        $existingContribution = GroupPool::where('group_id', $validated['group_id'])
            ->where('user_id', $user->user_id)
            ->whereIn('status', ['pending', 'scored'])
            ->first();

        if ($existingContribution) {
            return response()->json([
                'message' => 'You already have an active pool contribution in this group',
            ], 409);
        }

        $pool = DB::transaction(function () use ($validated, $user, $betAmount, $group) {
            $user->update(['balance' => $user->balance - $betAmount]);

            $pool = GroupPool::create([
                'group_id' => $validated['group_id'],
                'user_id' => $user->user_id,
                'amount_applied' => $betAmount,
                'status' => 'pending',
            ]);

            Transactions::create([
                'user_id' => $user->user_id,
                'type' => 'bet',
                'amount' => $betAmount,
                'status' => 'completed',
                'reference' => "Pool contribution for group #{$group->group_id}",
                'processed_at' => Carbon::now(),
            ]);

            return $pool;
        });

        return response()->json([
            'message' => 'Pool contribution successful',
            'data' => $pool->load('user:user_id,first_name,last_name'),
        ], 201);
    }

    public function show(int $groupId): JsonResponse
    {
        $group = Groups::findOrFail($groupId);

        if (! $group->pool_active) {
            return response()->json(['message' => 'Pool is not active for this group'], 422);
        }

        $entries = GroupPool::where('group_id', $groupId)
            ->with('user:user_id,first_name,last_name')
            ->get();

        $totalPool = $entries->where('status', 'pending')->sum('amount_applied');

        $distributed = $entries->where('status', 'distributed')
            ->sortByDesc('prize_won')
            ->values();

        return response()->json([
            'data' => [
                'group' => [
                    'group_id' => $group->group_id,
                    'name' => $group->name,
                    'bet_amount' => $group->bet_amount,
                    'prize_distribution' => $group->getPrizeDistribution(),
                ],
                'total_pool' => $totalPool,
                'contributors' => $entries->where('status', 'pending')->count(),
                'entries' => $entries,
                'winners' => $distributed,
            ],
        ]);
    }

    public function distribute(Request $request, int $groupId): JsonResponse
    {
        $user = $request->user();
        $group = Groups::findOrFail($groupId);

        if ($group->creator_id !== $user->user_id) {
            return response()->json([
                'message' => 'Only the group creator can distribute pool prizes',
            ], 403);
        }

        if (! $group->pool_active) {
            return response()->json([
                'message' => 'Pool is not active for this group',
            ], 422);
        }

        $poolEntries = GroupPool::where('group_id', $groupId)
            ->where('status', 'pending')
            ->get();

        if ($poolEntries->isEmpty()) {
            return response()->json([
                'message' => 'No pending pool contributions found',
            ], 422);
        }

        $totalPool = $poolEntries->sum('amount_applied');
        $memberScores = $this->getMemberScores($groupId, $poolEntries);

        if ($memberScores->isEmpty()) {
            return response()->json([
                'message' => 'No scores found for pool contributors',
            ], 422);
        }

        $distribution = $this->calculateDistribution($memberScores, $totalPool, $group);

        DB::transaction(function () use ($distribution, $poolEntries) {
            foreach ($distribution as $place => $data) {
                $userId = $data['user_id'];
                $prize = $data['prize'];

                $user = User::find($userId);
                if (! $user) {
                    continue;
                }

                $user->update(['balance' => $user->balance + $prize]);

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
                    'reference' => "Pool prize {$place} for group #{$poolEntries->first()->group_id}",
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

        return response()->json([
            'message' => 'Pool prizes distributed successfully',
            'distribution' => $distribution,
        ]);
    }

    private function getMemberScores(int $groupId, $poolEntries): \Illuminate\Support\Collection
    {
        $userIds = $poolEntries->pluck('user_id')->unique();

        $memberPredictions = \App\Models\GroupPredictions::where('group_id', $groupId)
            ->whereIn('prediction_id', function ($query) use ($userIds) {
                $query->select('prediction_id')
                    ->from('predictions')
                    ->whereIn('user_id', $userIds);
            })
            ->get();

        $resultPoints = \App\Models\PredictionResults::whereIn('prediction_id', $memberPredictions->pluck('prediction_id'))
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
        $sorted = $memberScores->sortByDesc('total_points')->values();
        $distribution = [];

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
}
