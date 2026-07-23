<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Models\TournamentGroup;
use App\Models\TournamentGroupTeam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TournamentGroupController extends Controller
{
    public function index(Request $request, Tournament $tournament)
    {
        $groups = TournamentGroup::whereHas('stage', function ($q) use ($tournament) {
            $q->where('tournament_id', $tournament->tournament_id);
        })->with(['teams.team', 'stage'])->get();

        return response()->json([
            'tournament_id' => $tournament->tournament_id,
            'groups' => $groups,
        ]);
    }

    public function store(Request $request, Tournament $tournament)
    {
        $validated = $request->validate([
            'stage_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:50'],
            'group_order' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'in:pending,approved,rejected'],
        ]);

        $validated['status'] = $validated['status'] ?? 'pending';
        $validated['created_at'] = now();

        $group = TournamentGroup::create($validated);

        return response()->json([
            'message' => 'Tournament group created successfully',
            'group' => $group,
        ], 201);
    }

    public function show(TournamentGroup $group)
    {
        $group->load(['teams.team', 'stage']);

        return response()->json(['group' => $group]);
    }

    public function update(Request $request, TournamentGroup $group)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:50'],
            'group_order' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'status' => ['sometimes', 'required', 'string', 'in:pending,approved,rejected'],
        ]);

        $validated['updated_at'] = now();

        $group->update($validated);

        return response()->json([
            'message' => 'Tournament group updated successfully',
            'group' => $group,
        ]);
    }

    public function destroy(TournamentGroup $group)
    {
        $group->delete();

        return response()->json([
            'message' => 'Tournament group deleted successfully',
        ]);
    }

    public function addTeams(Request $request, TournamentGroup $group)
    {
        $validated = $request->validate([
            'team_ids' => ['required', 'array', 'min:1'],
            'team_ids.*' => ['integer', 'exists:teams,team_id'],
        ]);

        $added = 0;

        DB::transaction(function () use ($validated, $group, &$added) {
            foreach ($validated['team_ids'] as $teamId) {
                $exists = TournamentGroupTeam::where('group_id', $group->group_id)
                    ->where('team_id', $teamId)
                    ->exists();

                if (! $exists) {
                    TournamentGroupTeam::create([
                        'group_id' => $group->group_id,
                        'team_id' => $teamId,
                        'points' => 0,
                        'goal_diff' => 0,
                        'goals_for' => 0,
                        'goals_against' => 0,
                        'matches_played' => 0,
                        'wins' => 0,
                        'draws' => 0,
                        'losses' => 0,
                    ]);
                    $added++;
                }
            }
        });

        return response()->json([
            'message' => "{$added} team(s) added to group",
            'group_id' => $group->group_id,
        ]);
    }

    public function removeTeam(TournamentGroup $group, int $teamId)
    {
        $deleted = TournamentGroupTeam::where('group_id', $group->group_id)
            ->where('team_id', $teamId)
            ->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Team not found in group'], 404);
        }

        return response()->json(['message' => 'Team removed from group']);
    }

    public function approve(TournamentGroup $group)
    {
        $group->update(['status' => 'approved', 'updated_at' => now()]);

        return response()->json([
            'message' => 'Tournament group approved',
            'group' => $group,
        ]);
    }

    public function reject(TournamentGroup $group)
    {
        $group->update(['status' => 'rejected', 'updated_at' => now()]);

        return response()->json([
            'message' => 'Tournament group rejected',
            'group' => $group,
        ]);
    }
}
