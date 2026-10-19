<?php

namespace App\Actions;

use App\Models\Payment;
use App\Models\Subscription;
use App\Notifications\SubscriptionActivatedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Confirma el pago SIP de una suscripción — llamado por el callback de SIP
 * o por el polling de estado si detecta el pago antes de que llegue el
 * callback. Idempotente: SIP puede reintentar el callback varias veces.
 */
class ConfirmarPagoSuscripcionAction
{
    public function handle(string $sipReferencia): Subscription
    {
        $subscription = Subscription::where('sip_referencia', $sipReferencia)->first();

        if (! $subscription) {
            throw new \DomainException("No existe ninguna suscripción con la referencia {$sipReferencia}.");
        }

        if ($subscription->status === 'active') {
            return $subscription;
        }

        if ($subscription->status !== 'pending_payment') {
            throw new \DomainException("Esta suscripción ya no está pendiente de pago (estado: {$subscription->status}).");
        }

        $periodStart = Carbon::now();
        $periodEnd = $subscription->is_annual ? $periodStart->copy()->addYear() : $periodStart->copy()->addMonth();

        $subscription->update([
            'status' => 'active',
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
        ]);

        $user = $subscription->user;
        $user->update([
            'subscription_tier' => $subscription->plan,
            'subscription_expires_at' => $periodEnd,
        ]);

        Payment::create([
            'user_id' => $user->user_id,
            'subscription_id' => $subscription->subscription_id,
            'amount' => $subscription->is_annual ? $subscription->annual_price : $subscription->monthly_price,
            'currency' => 'BOB',
            'status' => 'succeeded',
            'description' => 'Suscripción '.ucfirst($subscription->plan).' confirmada por SIP',
            'paid_at' => $periodStart,
        ]);

        // No puede tirar excepción acá: esta Action la invoca tanto el
        // webhook de SIP (que debe responder siempre el contrato exacto
        // {"codigo":"0000",...}) como el polling — un fallo de correo no
        // debe revertir ni bloquear la confirmación del pago.
        try {
            $user->notify(new SubscriptionActivatedNotification($subscription));
        } catch (\Throwable $e) {
            Log::error("No se pudo notificar la activación de la suscripción {$subscription->subscription_id}: {$e->getMessage()}");
        }

        return $subscription->refresh();
    }
}
