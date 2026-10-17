<?php

namespace App\Actions;

use App\Models\Subscription;
use App\Models\User;
use App\Services\SipService;
use Illuminate\Support\Str;

/**
 * Genera el QR de cobro SIP para una suscripción (reemplaza el checkout de
 * Stripe por ahora). Crea el registro 'pending_payment'; NO activa el plan
 * todavía — eso solo ocurre cuando ConfirmarPagoSuscripcionAction confirma
 * el pago (callback de SIP o polling).
 */
class GenerarPagoSuscripcionAction
{
    public function __construct(private readonly SipService $sip) {}

    /**
     * @return array{subscription: Subscription, qr: array}
     */
    public function handle(User $user, string $plan, bool $isAnnual): array
    {
        // Limpia intentos anteriores que quedaron sin pagar — evita filas
        // huérfanas acumulándose cada vez que el usuario reintenta el
        // checkout sin haber escaneado el QR anterior.
        Subscription::where('user_id', $user->user_id)
            ->where('status', 'pending_payment')
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $plans = Subscription::getPlans();
        $billing = $isAnnual ? 'annual' : 'monthly';
        $planConfig = $plans[$plan][$billing];
        $monto = (float) $planConfig['price_bob'];

        $referencia = 'SUB-'.Str::upper(Str::random(10));

        $subscription = Subscription::create([
            'user_id' => $user->user_id,
            'payment_provider' => 'sip',
            'sip_referencia' => $referencia,
            'plan' => $plan,
            'status' => 'pending_payment',
            'monthly_price' => $plans[$plan]['monthly']['price_bob'],
            'annual_price' => $plans[$plan]['annual']['price_bob'],
            'is_annual' => $isAnnual,
        ]);

        $token = $this->sip->generarToken();
        $accessToken = $token['token'] ?? $token['data']['token'] ?? $token['access_token'] ?? '';

        $qr = $this->sip->generaQr(
            alias: $referencia,
            detalleGlosa: 'Suscripcion '.ucfirst($plan).' Friend Soccer',
            monto: $monto,
            token: $accessToken,
        );

        $subscription->update(['sip_qr_id' => $qr['idQr'] ?? $qr['objeto']['idQr'] ?? null]);

        return [
            'subscription' => $subscription,
            'qr' => $qr,
        ];
    }
}
