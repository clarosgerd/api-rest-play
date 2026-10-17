<?php

namespace App\Http\Controllers;

use App\Jobs\ScoreMatchPredictions;
use App\Models\GroupMembers;
use App\Models\Groups;
use App\Models\Matches;
use App\Models\Tournament;
use App\Models\TournamentGroup;
use App\Models\TournamentStage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManualMatchController extends Controller
{
    private function canManageResults(Tournament $tournament): bool
    {
        $userId = auth()->id();
        $isAdmin = auth()->user()->role === 'admin';

        if ($isAdmin || $tournament->created_by === $userId) {
            return true;
        }

        $groupIds = Groups::where('tournament_id', $tournament->tournament_id)->pluck('group_id');

        return GroupMembers::whereIn('group_id', $groupIds)
            ->where('user_id', $userId)
            ->where('is_admin', 1)
            ->where('status', 'accepted')
            ->exists();
    }

    public function updateResult(Request $request, int $tournamentId, int $matchId)
    {
        $tournament = Tournament::findOrFail($tournamentId);

        if (! $this->canManageResults($tournament)) {
            return response()->json(['message' => 'No tienes permiso para editar resultados de este torneo.'], 403);
        }

        if (! $tournament->isLive()) {
            return response()->json(['message' => 'Los resultados solo se pueden ingresar en torneos activos.'], 422);
        }

        $request->validate([
            'home_score' => ['required', 'integer', 'min:0'],
            'away_score' => ['required', 'integer', 'min:0'],
        ]);

        $match = Matches::where('match_id', $matchId)
            ->where('tournament_id', $tournamentId)
            ->firstOrFail();

        $match->update([
            'home_score' => $request->home_score,
            'away_score' => $request->away_score,
            'status' => 'finished',
        ]);

        ScoreMatchPredictions::dispatch($match->match_id);

        $this->checkStageCompletion($tournament, $match->stage_id);

        return response()->json([
            'message' => 'Resultado registrado correctamente.',
            'match' => [
                'match_id' => $match->match_id,
                'home_score' => $match->home_score,
                'away_score' => $match->away_score,
                'status' => 'finished',
            ],
        ]);
    }

    public function updateStatus(Request $request, int $tournamentId, int $matchId)
    {
        $tournament = Tournament::findOrFail($tournamentId);

        if (! $this->canManageResults($tournament)) {
            return response()->json(['message' => 'No tienes permiso para editar este torneo.'], 403);
        }

        if (! $tournament->isLive()) {
            return response()->json(['message' => 'Los resultados solo se pueden ingresar en torneos activos.'], 422);
        }

        $request->validate([
            'status' => ['required', 'string', 'in:scheduled,live,finished,canceled'],
        ]);

        $match = Matches::where('match_id', $matchId)
            ->where('tournament_id', $tournamentId)
            ->firstOrFail();

        $match->update(['status' => $request->status]);

        if ($request->status === 'finished') {
            ScoreMatchPredictions::dispatch($match->match_id);
        }

        return response()->json(['message' => 'Estado actualizado.']);
    }

    public function pendingResults(int $tournamentId)
    {
        $tournament = Tournament::findOrFail($tournamentId);

        if (! $this->canManageResults($tournament)) {
            return response()->json(['message' => 'No tienes permiso.'], 403);
        }

        $matches = Matches::where('tournament_id', $tournamentId)
            ->where('status', '!=', 'finished')
            ->with(['home', 'away', 'stage'])
            ->orderBy('match_date')
            ->get();

        $total = Matches::where('tournament_id', $tournamentId)->count();
        $finished = Matches::where('tournament_id', $tournamentId)->where('status', 'finished')->count();

        return response()->json([
            'matches' => $matches,
            'summary' => [
                'total' => $total,
                'finished' => $finished,
                'pending' => $total - $finished,
            ],
        ]);
    }

    private function checkStageCompletion(Tournament $tournament, int $stageId): void
    {
        $pendingMatches = Matches::where('tournament_id', $tournament->tournament_id)
            ->where('stage_id', $stageId)
            ->where('status', '!=', 'finished')
            ->count();

        if ($pendingMatches > 0) {
            return;
        }

        $stage = TournamentStage::find($stageId);
        if (! $stage || $stage->is_knockout) {
            return;
        }

        $groups = TournamentGroup::where('stage_id', $stageId)->where('status', 'pending')->get();
        foreach ($groups as $group) {
            $group->update(['status' => 'approved']);
        }
    }
}
