<?php

namespace App\Http\Controllers;

use App\Models\GroupMembers;
use App\Models\GroupPredictions;
use App\Models\Groups;
use App\Models\Matches;
use App\Models\Predictions;
use App\Models\User;
use App\Services\PredictionEngineService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiCompetitorController extends Controller
{
    public function __construct(
        protected PredictionEngineService $engine,
    ) {}

    public function toggle(Groups $group): JsonResponse
    {
        $aiUser = User::where('email', 'franco.chelo@soccerapi.ai')->first();

        if (! $aiUser) {
            return response()->json(['message' => 'AI competitor user not found'], 500);
        }

        $enabled = ! $group->ai_competitor_enabled;

        DB::transaction(function () use ($group, $aiUser, $enabled) {
            $group->update(['ai_competitor_enabled' => $enabled]);

            if ($enabled) {
                $this->enableAiCompetitor($group, $aiUser);
            } else {
                $this->disableAiCompetitor($group, $aiUser);
            }
        });

        return response()->json([
            'data' => [
                'ai_competitor_enabled' => $enabled,
                'message' => $enabled
                    ? '¡Franco (Chelo) se unió al grupo! Los miembros ahora compiten contra la IA.'
                    : 'Franco (Chelo) se retiró del grupo.',
            ],
        ]);
    }

    private function enableAiCompetitor(Groups $group, User $aiUser): void
    {
        // 1. Add AI user as group member if not already
        $existing = GroupMembers::where('group_id', $group->group_id)
            ->where('user_id', $aiUser->user_id)
            ->first();

        if (! $existing) {
            GroupMembers::create([
                'group_id' => $group->group_id,
                'user_id' => $aiUser->user_id,
                'joined_at' => Carbon::now(),
                'is_admin' => 0,
                'status' => 'accepted',
            ]);
        }

        // 2. Generate predictions for upcoming matches in the group's tournament
        if ($group->tournament_id) {
            $this->generatePredictions($group, $aiUser);
        }
    }

    private function disableAiCompetitor(Groups $group, User $aiUser): void
    {
        // Remove AI user from group members
        GroupMembers::where('group_id', $group->group_id)
            ->where('user_id', $aiUser->user_id)
            ->delete();

        // Optionally: remove AI predictions from the group
        // We keep them for historical scoring but remove the link to avoid
        // showing them in the group context going forward
        $aiPredictions = Predictions::where('user_id', $aiUser->user_id)
            ->whereHas('grouppredictions', fn ($q) => $q->where('group_id', $group->group_id))
            ->get();

        foreach ($aiPredictions as $prediction) {
            GroupPredictions::where('group_id', $group->group_id)
                ->where('prediction_id', $prediction->prediction_id)
                ->delete();
            $prediction->delete();
        }
    }

    /**
     * Regenerate AI predictions for the group (used after toggle-enable).
     */
    public function regenerate(Groups $group): JsonResponse
    {
        if (! $group->ai_competitor_enabled) {
            return response()->json(['message' => 'AI competitor not enabled for this group'], 422);
        }

        $aiUser = User::where('email', 'franco.chelo@soccerapi.ai')->first();
        if (! $aiUser) {
            return response()->json(['message' => 'AI competitor user not found'], 500);
        }

        // Remove old AI predictions for this group
        $oldPredictions = Predictions::where('user_id', $aiUser->user_id)
            ->whereHas('grouppredictions', fn ($q) => $q->where('group_id', $group->group_id))
            ->get();

        foreach ($oldPredictions as $p) {
            GroupPredictions::where('group_id', $group->group_id)
                ->where('prediction_id', $p->prediction_id)
                ->delete();
            $p->delete();
        }

        // Regenerate
        if ($group->tournament_id) {
            $count = $this->generatePredictions($group, $aiUser);
        }

        return response()->json([
            'data' => [
                'message' => "Franco (Chelo) actualizó sus predicciones ({$count} partidos).",
            ],
        ]);
    }

    private function generatePredictions(Groups $group, User $aiUser): int
    {
        $matches = Matches::where('tournament_id', $group->tournament_id)
            ->where('status', 'scheduled')
            ->with(['home', 'away', 'tournament', 'stage'])
            ->get();

        $count = 0;

        foreach ($matches as $match) {
            // Skip if AI already predicted this match in this group
            $alreadyPredicted = Predictions::where('user_id', $aiUser->user_id)
                ->where('match_id', $match->match_id)
                ->whereHas('grouppredictions', fn ($q) => $q->where('group_id', $group->group_id))
                ->exists();

            if ($alreadyPredicted) {
                continue;
            }

            $result = $this->engine->predict($match->match_id);

            if (! $result) {
                continue;
            }

            DB::transaction(function () use ($aiUser, $group, $match, $result) {
                $prediction = Predictions::create([
                    'user_id' => $aiUser->user_id,
                    'match_id' => $match->match_id,
                    'predicted_home_score' => $result['predicted_home_score'],
                    'predicted_away_score' => $result['predicted_away_score'],
                    'predicted_result' => $result['predicted_result'],
                    'bet_type' => 'exact_score',
                    'confidence' => 5,
                    'created_at' => Carbon::now(),
                    'is_public' => true,
                ]);

                GroupPredictions::create([
                    'group_id' => $group->group_id,
                    'prediction_id' => $prediction->prediction_id,
                    'points_awarded' => 0,
                ]);
            });

            $count++;
        }

        return $count;
    }

    /**
     * Get the AI prediction for a specific match in a group.
     * Only revealed after the match finishes.
     */
    public function prediction(Groups $group, int $matchId): JsonResponse
    {
        $aiUser = User::where('email', 'franco.chelo@soccerapi.ai')->first();

        if (! $aiUser) {
            return response()->json(['data' => null]);
        }

        $match = Matches::find($matchId);

        // Only reveal prediction after the match is finished
        if (! $match || $match->status !== 'finished') {
            return response()->json(['data' => null]);
        }

        $prediction = Predictions::where('user_id', $aiUser->user_id)
            ->where('match_id', $matchId)
            ->whereHas('grouppredictions', fn ($q) => $q->where('group_id', $group->group_id))
            ->first();

        if (! $prediction) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'prediction_id' => $prediction->prediction_id,
                'match_id' => $prediction->match_id,
                'predicted_home_score' => $prediction->predicted_home_score,
                'predicted_away_score' => $prediction->predicted_away_score,
                'predicted_result' => $prediction->predicted_result,
                'bet_type' => $prediction->bet_type,
            ],
        ]);
    }

    /**
     * Show AI competitor status for a group.
     */
    public function status(Groups $group): JsonResponse
    {
        $aiUser = User::where('email', 'franco.chelo@soccerapi.ai')->first();

        $isMember = false;
        $predictionsCount = 0;

        if ($aiUser) {
            $isMember = GroupMembers::where('group_id', $group->group_id)
                ->where('user_id', $aiUser->user_id)
                ->exists();

            if ($isMember) {
                $predictionsCount = Predictions::where('user_id', $aiUser->user_id)
                    ->whereHas('grouppredictions', fn ($q) => $q->where('group_id', $group->group_id))
                    ->count();
            }
        }

        return response()->json([
            'data' => [
                'ai_competitor_enabled' => (bool) $group->ai_competitor_enabled,
                'is_member' => $isMember,
                'predictions_count' => $predictionsCount,
                'name' => 'Franco (Chelo)',
            ],
        ]);
    }
}
