<?php

namespace App\Http\Controllers;

use App\Actions\ConfirmarPagoSuscripcionAction;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\Request;

/**
 * Panel de soporte para super admin (17/10/2026) — visibilidad y acciones
 * sobre pagos/suscripciones de CUALQUIER usuario. confirm() reusa
 * ConfirmarPagoSuscripcionAction (ya idempotente) para el caso real de
 * soporte: un pago SIP que el usuario ya hizo pero cuyo callback nunca
 * llegó (Subscription.status se queda en 'pending_payment').
 */
class AdminSubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $query = Subscription::with(['user', 'payments']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->orderBy('subscription_id', 'desc')->paginate());
    }

    public function confirm(Subscription $subscription, ConfirmarPagoSuscripcionAction $action)
    {
        try {
            $confirmed = $action->handle($subscription->sip_referencia);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $confirmed]);
    }

    public function payments(Request $request)
    {
        $query = Payment::with(['user', 'subscription']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->orderBy('payment_id', 'desc')->paginate());
    }
}
