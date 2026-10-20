<?php

namespace App\Http\Middleware;

use App\Models\Groups;
use App\Models\Tournament;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckGroupLimits
{
    public function handle(Request $request, Closure $next, string $limitType = 'members'): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $limits = $user->getTierLimits();

        if ($limitType === 'tournaments') {
            return $this->checkTournamentLimits($request, $next, $user, $limits);
        }

        if ($limitType === 'members') {
            return $this->checkMemberLimits($request, $next, $user, $limits);
        }

        return $next($request);
    }

    private function checkTournamentLimits(
        Request $request,
        Closure $next,
        $user,
        array $limits
    ): Response {
        $tournamentId = $request->input('tournament_id')
            ?? $request->route('tournament')?->tournament_id;

        if ($tournamentId) {
            $tournament = Tournament::find($tournamentId);
            if ($tournament && $tournament->isCurrentlyPromotional()) {
                return $next($request);
            }
        }

        $maxTournaments = $limits['tournaments'];

        if ($maxTournaments === -1) {
            return $next($request);
        }

        // Bug real (20/10/2026): contaba torneos privados GLOBALES, sin
        // filtrar por dueño — el límite por tier nunca funcionó como cupo
        // personal. Se corrige contando solo los del usuario autenticado.
        $tournamentCount = Tournament::where('external_id', null)
            ->where('created_by', $user->user_id)
            ->count();

        if ($tournamentCount >= $maxTournaments) {
            return response()->json([
                'message' => 'Tournament limit reached',
                'current_tier' => $user->subscription_tier,
                'limit' => $maxTournaments,
                'used' => $tournamentCount,
                'upgrade_url' => '/api/v1/user/subscription/plans',
            ], 403);
        }

        return $next($request);
    }

    private function checkMemberLimits(
        Request $request,
        Closure $next,
        $user,
        array $limits
    ): Response {
        $maxMembers = $limits['members_per_group'];

        if ($maxMembers === -1) {
            return $next($request);
        }

        $groupId = $request->input('group_id') ?? $request->route('group')?->group_id;

        // Sin group_id: se está creando un grupo NUEVO, no sumando un
        // miembro a uno existente (20/10/2026) — members_per_group=0 ahora
        // también significa "esta tier no puede tener grupo propio"
        // (free/predictor). Mismo bypass de torneo promocional que ya usa
        // checkTournamentLimits(), para no romper el caso de unirse/crear un
        // grupo dentro de un torneo gratuito promocionado.
        if (! $groupId) {
            $tournamentId = $request->input('tournament_id');
            if ($tournamentId) {
                $tournament = Tournament::find($tournamentId);
                if ($tournament && $tournament->isCurrentlyPromotional()) {
                    return $next($request);
                }
            }

            if ($maxMembers === 0) {
                return response()->json([
                    'message' => 'Tu plan no permite crear un grupo propio',
                    'current_tier' => $user->subscription_tier,
                    'upgrade_url' => '/api/v1/user/subscription/plans',
                ], 403);
            }

            return $next($request);
        }

        $group = Groups::find($groupId);
        if (! $group) {
            return $next($request);
        }

        $currentMembers = $group->groupmember()->count();

        if ($currentMembers >= $maxMembers) {
            return response()->json([
                'message' => 'Group member limit reached',
                'current_tier' => $user->subscription_tier,
                'limit' => $maxMembers,
                'used' => $currentMembers,
                'upgrade_url' => '/api/v1/user/subscription/plans',
            ], 403);
        }

        return $next($request);
    }
}
