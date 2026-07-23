<?php

namespace App\Http\Controllers;

use App\Models\GroupMembers;
use App\Models\GroupPredictions;
use App\Models\Matches;
use App\Models\Predictions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PredictionsController extends Controller
{
    public function index(Request $request)
    {
        $filter = new \App\Filters\PredictionsFilter;
        $queryItems = $filter->transform($request);

        $query = Predictions::with(['match', 'user']);

        if (count($queryItems) > 0) {
            $query->where($queryItems);
        }

        if ($request->has('group_id')) {
            $query->whereHas('grouppredictions', function ($q) use ($request) {
                $q->where('group_id', $request->group_id);
            });
        }

        return response()->json($query->paginate()->appends($request->query()));
    }

    public function show(Predictions $prediction)
    {
        $prediction->load(['match', 'user', 'grouppredictions.group']);

        return response()->json(['data' => $prediction]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'match_id' => 'required|integer|exists:matches,match_id',
            'group_id' => 'required|integer|exists:groups,group_id',
            'predicted_home_score' => 'required|integer|min:0',
            'predicted_away_score' => 'required|integer|min:0',
            'predicted_result' => 'required|in:home_win,away_win,draw',
            'bet_type' => 'required|in:exact_score,match_result,both_teams_score',
            'confidence' => 'required|integer|min:1|max:10',
        ]);

        $user = $request->user();
        $match = Matches::findOrFail($validated['match_id']);

        // Validate match is still scheduled
        if ($match->status !== 'scheduled') {
            return response()->json([
                'message' => 'Cannot predict on a match that is not scheduled',
            ], 422);
        }

        // Validate 30-minute window before match
        $matchTime = Carbon::parse($match->match_date);
        $now = Carbon::now();

        if ($now->greaterThan($matchTime->subMinutes(30))) {
            return response()->json([
                'message' => 'Predictions are closed. The match starts in less than 30 minutes.',
            ], 422);
        }

        // Validate user is member of the group
        $isMember = GroupMembers::where('group_id', $validated['group_id'])
            ->where('user_id', $user->user_id)
            ->first();

        if (! $isMember) {
            return response()->json([
                'message' => 'You are not a member of this group',
            ], 403);
        }

        // Prevent duplicate predictions per user per match per group
        $existingPrediction = Predictions::where('user_id', $user->user_id)
            ->where('match_id', $validated['match_id'])
            ->whereHas('grouppredictions', function ($q) use ($validated) {
                $q->where('group_id', $validated['group_id']);
            })
            ->first();

        if ($existingPrediction) {
            return response()->json([
                'message' => 'You have already predicted this match in this group',
                'existing_prediction' => $existingPrediction,
            ], 409);
        }

        // Create prediction and link to group in a transaction
        $prediction = DB::transaction(function () use ($validated, $user) {
            $prediction = Predictions::create([
                'user_id' => $user->user_id,
                'match_id' => $validated['match_id'],
                'predicted_home_score' => $validated['predicted_home_score'],
                'predicted_away_score' => $validated['predicted_away_score'],
                'predicted_result' => $validated['predicted_result'],
                'bet_type' => $validated['bet_type'],
                'confidence' => $validated['confidence'],
                'created_at' => Carbon::now(),
                'is_public' => true,
            ]);

            GroupPredictions::create([
                'group_id' => $validated['group_id'],
                'prediction_id' => $prediction->prediction_id,
                'points_awarded' => 0,
            ]);

            return $prediction;
        });

        return response()->json([
            'message' => 'Prediction created successfully',
            'prediction' => $prediction,
        ], 201);
    }

    public function update(Request $request, Predictions $prediction)
    {
        $user = $request->user();

        // Only the owner can update their prediction
        if ($prediction->user_id !== $user->user_id) {
            return response()->json([
                'message' => 'You can only update your own predictions',
            ], 403);
        }

        $match = Matches::findOrFail($prediction->match_id);

        // Cannot update if match is no longer scheduled
        if ($match->status !== 'scheduled') {
            return response()->json([
                'message' => 'Cannot update prediction after the match has started',
            ], 422);
        }

        // Validate 30-minute window
        $matchTime = Carbon::parse($match->match_date);
        $now = Carbon::now();

        if ($now->greaterThan($matchTime->subMinutes(30))) {
            return response()->json([
                'message' => 'Cannot update. The match starts in less than 30 minutes.',
            ], 422);
        }

        $validated = $request->validate([
            'predicted_home_score' => 'sometimes|integer|min:0',
            'predicted_away_score' => 'sometimes|integer|min:0',
            'predicted_result' => 'sometimes|in:home_win,away_win,draw',
            'bet_type' => 'sometimes|in:exact_score,match_result,both_teams_score',
            'confidence' => 'sometimes|integer|min:1|max:10',
        ]);

        $prediction->update($validated);

        return response()->json([
            'message' => 'Prediction updated successfully',
            'prediction' => $prediction,
        ]);
    }

    public function destroy(Request $request, Predictions $prediction)
    {
        $user = $request->user();

        if ($prediction->user_id !== $user->user_id) {
            return response()->json([
                'message' => 'You can only delete your own predictions',
            ], 403);
        }

        $match = Matches::findOrFail($prediction->match_id);

        if ($match->status !== 'scheduled') {
            return response()->json([
                'message' => 'Cannot delete prediction after the match has started',
            ], 422);
        }

        $prediction->delete();

        return response()->json([
            'message' => 'Prediction deleted successfully',
        ]);
    }
}
