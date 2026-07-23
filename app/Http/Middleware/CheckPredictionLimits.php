<?php

namespace App\Http\Middleware;

use App\Models\Predictions;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPredictionLimits
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $limits = $user->getTierLimits();
        $dailyLimit = $limits['predictions_per_day'];

        if ($dailyLimit === -1) {
            return $next($request);
        }

        $todayPredictions = Predictions::where('user_id', $user->user_id)
            ->where('created_at', '>=', Carbon::now()->startOfDay())
            ->count();

        if ($todayPredictions >= $dailyLimit) {
            return response()->json([
                'message' => 'Daily prediction limit reached',
                'current_tier' => $user->subscription_tier,
                'limit' => $dailyLimit,
                'used' => $todayPredictions,
                'upgrade_url' => '/api/v1/user/subscription/plans',
            ], 403);
        }

        return $next($request);
    }
}
