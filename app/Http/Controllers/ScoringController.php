<?php

namespace App\Http\Controllers;

use App\Models\GroupMembers;
use App\Models\GroupPool;
use App\Models\GroupPredictions;
use App\Models\Groups;
use App\Models\PredictionResults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScoringController extends Controller
{
    public function userDashboard(Request $request, int $groupId, int $userId): JsonResponse
    {
        $group = Groups::findOrFail($groupId);

        $isMember = GroupMembers::where('group_id', $groupId)
            ->where('user_id', $userId)
            ->first();

        if (! $isMember) {
            return response()->json([
                'message' => 'User is not a member of this group',
            ], 404);
        }

        $predictions = GroupPredictions::where('group_id', $groupId)
            ->whereHas('prediction', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->with(['prediction.match.home', 'prediction.match.stage'])
            ->get();

        $totalPoints = 0;
        $exactScores = 0;
        $matchResults = 0;
        $correctGoals = 0;
        $uniqueExact = 0;
        $totalPredictions = $predictions->count();

        $matchBreakdowns = [];

        foreach ($predictions as $gp) {
            $prediction = $gp->prediction;
            if (! $prediction) {
                continue;
            }

            $result = PredictionResults::where('prediction_id', $prediction->prediction_id)->first();

            if (! $result) {
                continue;
            }

            $points = $result->points_earned ?? 0;
            $totalPoints += $points;

            $breakdown = $result->points_breakdown ?? [];

            if (isset($breakdown['exact_score']) && $breakdown['exact_score'] > 0) {
                $exactScores++;
            }
            if (isset($breakdown['match_result']) && $breakdown['match_result'] > 0) {
                $matchResults++;
            }
            if ((isset($breakdown['correct_goal_home']) && $breakdown['correct_goal_home'] > 0)
                || (isset($breakdown['correct_goal_away']) && $breakdown['correct_goal_away'] > 0)) {
                $correctGoals++;
            }
            if (isset($breakdown['unique_exact']) && $breakdown['unique_exact'] > 0) {
                $uniqueExact++;
            }

            $match = $prediction->match;
            $matchBreakdowns[] = [
                'match_id' => $prediction->match_id,
                'match' => $match ? "{$match->home->name} vs {$match->away->name}" : null,
                'home_score' => $match->home_score ?? null,
                'away_score' => $match->away_score ?? null,
                'predicted_home_score' => $prediction->predicted_home_score,
                'predicted_away_score' => $prediction->predicted_away_score,
                'predicted_result' => $prediction->predicted_result,
                'stage' => $match?->stage?->name,
                'points_earned' => $points,
                'breakdown' => $breakdown,
            ];
        }

        $poolWinnings = GroupPool::where('group_id', $groupId)
            ->where('user_id', $userId)
            ->where('status', 'distributed')
            ->sum('prize_won');

        return response()->json([
            'data' => [
                'group' => [
                    'group_id' => $group->group_id,
                    'name' => $group->name,
                ],
                'user' => [
                    'user_id' => $userId,
                ],
                'summary' => [
                    'total_points' => $totalPoints,
                    'total_predictions' => $totalPredictions,
                    'exact_scores' => $exactScores,
                    'match_results' => $matchResults,
                    'correct_goals' => $correctGoals,
                    'unique_exact' => $uniqueExact,
                    'pool_winnings' => $poolWinnings,
                ],
                'matches' => $matchBreakdowns,
            ],
        ]);
    }

    public function groupDashboard(Request $request, int $groupId): JsonResponse
    {
        $group = Groups::findOrFail($groupId);

        $stats = GroupMembers::where('groupmembers.group_id', $groupId)
            ->join('users', 'groupmembers.user_id', '=', 'users.user_id')
            ->leftJoin('grouppredictions', 'groupmembers.group_id', '=', 'grouppredictions.group_id')
            ->leftJoin('predictions', 'grouppredictions.prediction_id', '=', 'predictions.prediction_id')
            ->leftJoin('predictionresults', 'predictions.prediction_id', '=', 'predictionresults.prediction_id')
            ->select(
                'users.user_id',
                'users.first_name',
                'users.last_name',
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
                'users.last_name'
            )
            ->orderByDesc('total_points')
            ->get();

        $poolWinnings = GroupPool::where('group_id', $groupId)
            ->where('status', 'distributed')
            ->select('user_id', DB::raw('SUM(prize_won) as total_winnings'))
            ->groupBy('user_id')
            ->pluck('total_winnings', 'user_id');

        foreach ($stats as $entry) {
            $entry->pool_winnings = $poolWinnings[$entry->user_id] ?? 0;
        }

        $totalPredictions = GroupPredictions::where('group_id', $groupId)->count();
        $scoredPredictions = GroupPredictions::where('group_id', $groupId)
            ->where('points_awarded', '>', 0)
            ->count();

        return response()->json([
            'data' => [
                'group' => [
                    'group_id' => $group->group_id,
                    'name' => $group->name,
                    'bet_amount' => $group->bet_amount,
                    'pool_active' => $group->pool_active,
                ],
                'summary' => [
                    'total_predictions' => $totalPredictions,
                    'scored_predictions' => $scoredPredictions,
                    'members' => $stats->count(),
                ],
                'scoring_rules' => [
                    'exact_score_points' => $group->exact_score_points ?? 10,
                    'match_result_points' => $group->match_result_points ?? 5,
                    'correct_goal_points' => $group->correct_goal_points ?? 3,
                    'unique_exact_points' => $group->unique_exact_points ?? 15,
                    'multipliers' => [
                        'dieciseisavos' => $group->multiplier_dieciseisavos ?? 1.5,
                        'octavos' => $group->multiplier_octavos ?? 2.0,
                        'cuartos' => $group->multiplier_cuartos ?? 3.0,
                        'semifinal' => $group->multiplier_semifinal ?? 5.0,
                        'final' => $group->multiplier_final ?? 10.0,
                    ],
                ],
                'members' => $stats,
            ],
        ]);
    }
}
