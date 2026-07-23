<?php

namespace App\Http\Controllers;

use App\Models\GroupMembers;
use App\Models\GroupPool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    public function index(Request $request, $groupId)
    {
        $leaderboard = GroupMembers::where('groupmembers.group_id', $groupId)
            ->join('users', 'groupmembers.user_id', '=', 'users.user_id')
            ->leftJoin('grouppredictions', 'groupmembers.group_id', '=', 'grouppredictions.group_id')
            ->leftJoin('predictions', 'grouppredictions.prediction_id', '=', 'predictions.prediction_id')
            ->leftJoin('predictionresults', 'predictions.prediction_id', '=', 'predictionresults.prediction_id')
            ->select(
                'users.user_id',
                'users.first_name',
                'users.last_name',
                'users.email',
                'groupmembers.is_admin',
                'groupmembers.joined_at',
                DB::raw('COALESCE(SUM(predictionresults.points_earned), 0) as total_points'),
                DB::raw('COUNT(DISTINCT predictions.prediction_id) as total_predictions'),
                DB::raw('SUM(CASE WHEN predictionresults.points_earned > 0 THEN 1 ELSE 0 END) as correct_predictions'),
                DB::raw('(SELECT COUNT(*) FROM predictionresults pr
                    JOIN predictions p ON pr.prediction_id = p.prediction_id
                    JOIN grouppredictions gp2 ON p.prediction_id = gp2.prediction_id
                    WHERE gp2.group_id = '.(int) $groupId.'
                    AND p.user_id = users.user_id
                    AND JSON_EXTRACT(pr.points_breakdown, "$.exact_score") > 0) as exact_scores'),
                DB::raw('(SELECT COUNT(*) FROM predictionresults pr
                    JOIN predictions p ON pr.prediction_id = p.prediction_id
                    JOIN grouppredictions gp2 ON p.prediction_id = gp2.prediction_id
                    WHERE gp2.group_id = '.(int) $groupId.'
                    AND p.user_id = users.user_id
                    AND JSON_EXTRACT(pr.points_breakdown, "$.unique_exact") > 0) as unique_predictions'),
            )
            ->groupBy(
                'users.user_id',
                'users.first_name',
                'users.last_name',
                'users.email',
                'groupmembers.is_admin',
                'groupmembers.joined_at'
            )
            ->orderByDesc('total_points')
            ->orderByDesc('correct_predictions')
            ->get();

        $poolWinnings = GroupPool::where('group_id', $groupId)
            ->where('status', 'distributed')
            ->select('user_id', DB::raw('SUM(prize_won) as total_winnings'))
            ->groupBy('user_id')
            ->pluck('total_winnings', 'user_id');

        foreach ($leaderboard as $entry) {
            $entry->pool_winnings = $poolWinnings[$entry->user_id] ?? 0;
        }

        return response()->json([
            'group_id' => $groupId,
            'leaderboard' => $leaderboard,
        ]);
    }
}
