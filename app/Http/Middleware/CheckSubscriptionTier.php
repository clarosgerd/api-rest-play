<?php

namespace App\Http\Middleware;

use App\Models\Tournament;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionTier
{
    public function handle(Request $request, Closure $next, string ...$requiredTiers): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $tournamentId = $request->input('tournament_id')
            ?? $request->route('tournament')?->tournament_id;

        if ($tournamentId) {
            $tournament = Tournament::find($tournamentId);
            if ($tournament && $tournament->isCurrentlyPromotional()) {
                return $next($request);
            }
        }

        $tierHierarchy = ['free' => 0, 'pro' => 1, 'premium' => 2];
        $userTierLevel = $tierHierarchy[$user->subscription_tier] ?? 0;

        foreach ($requiredTiers as $tier) {
            $requiredLevel = $tierHierarchy[$tier] ?? 0;
            if ($userTierLevel >= $requiredLevel) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'This feature requires a '.ucfirst($requiredTiers[0]).' subscription',
            'current_tier' => $user->subscription_tier,
            'required_tier' => $requiredTiers[0],
            'upgrade_url' => '/api/v1/user/subscription/plans',
        ], 403);
    }
}
