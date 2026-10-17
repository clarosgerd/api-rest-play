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

        // Suscripción paga obligatoria para pronosticar (16/10/2026, pedido
        // del usuario) — free ya no alcanza, ni siquiera con el cap diario
        // de abajo. Con predictor/pro/premium, predictions_per_day ya es
        // -1/20/-1, así que nadie que pague queda limitado por el cap.
        if (! $user->hasPaidSubscription()) {
            return response()->json([
                'message' => 'Necesitás una suscripción activa (Predictor, Pro o Premium) para pronosticar.',
                // 'reason' distingue este caso de otros 403 de plan (ej.
                // límite de torneos/miembros) para que el frontend pueda
                // mostrar un copy distinto — ver ErrorBanner.tsx.
                'reason' => 'subscription_required',
                'current_tier' => $user->subscription_tier,
                'upgrade_url' => '/api/v1/user/subscription/plans',
            ], 403);
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
