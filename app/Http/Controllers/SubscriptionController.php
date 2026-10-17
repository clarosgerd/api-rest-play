<?php

namespace App\Http\Controllers;

use App\Actions\ConfirmarPagoSuscripcionAction;
use App\Actions\GenerarPagoSuscripcionAction;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SipService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Customer;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;

class SubscriptionController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function show(Request $request)
    {
        $user = $request->user();
        $subscription = $user->subscription;
        $limits = $user->getTierLimits();

        return response()->json([
            'tier' => $user->subscription_tier,
            'is_active' => $user->isSubscriptionActive(),
            'expires_at' => $user->subscription_expires_at,
            'subscription' => $subscription ? [
                'plan' => $subscription->plan,
                'status' => $subscription->status,
                'is_annual' => $subscription->is_annual,
                'current_period_end' => $subscription->current_period_end,
                'trial_ends_at' => $subscription->trial_ends_at,
            ] : null,
            'limits' => [
                'tournaments' => [
                    'max' => $limits['tournaments'],
                    'used' => \App\Models\Tournament::where('external_id', null)->count(),
                ],
                'members_per_group' => [
                    'max' => $limits['members_per_group'],
                ],
                'predictions_per_day' => [
                    'max' => $limits['predictions_per_day'],
                    'used' => $user->predictions()->where('created_at', '>=', Carbon::now()->startOfDay())->count(),
                ],
            ],
        ]);
    }

    /**
     * Precios en Bolivianos vía SIP (16/10/2026) — reemplaza el checkout de
     * Stripe por ahora. El shape de respuesta es el que ya espera el
     * frontend (array `data`, no objeto por clave) — antes nunca matcheaba.
     */
    public function plans()
    {
        $plans = Subscription::getPlans();

        $data = collect(['predictor', 'pro', 'premium'])->map(function (string $plan) use ($plans) {
            $limits = (new User(['subscription_tier' => $plan]))->getTierLimits();

            return [
                'plan' => $plan,
                'monthly_price' => $plans[$plan]['monthly']['price_bob'],
                'annual_price' => $plans[$plan]['annual']['price_bob'],
                'predictions_per_day' => $limits['predictions_per_day'] === -1 ? null : $limits['predictions_per_day'],
                'tournament_limit' => $limits['tournaments'] === -1 ? null : $limits['tournaments'],
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'currency' => 'BOB',
        ]);
    }

    /**
     * Genera el QR de cobro SIP para la suscripción elegida (16/10/2026) —
     * reemplaza el checkout de Stripe por ahora, todo en Bolivianos.
     */
    public function checkout(Request $request, GenerarPagoSuscripcionAction $action)
    {
        $validated = $request->validate([
            'plan' => 'required|in:predictor,pro,premium',
            'billing' => 'required|in:monthly,annual',
        ]);

        try {
            $result = $action->handle($request->user(), $validated['plan'], $validated['billing'] === 'annual');
        } catch (\Throwable $e) {
            Log::error("No se pudo generar el QR de pago de suscripción: {$e->getMessage()}");

            return response()->json(['message' => 'No se pudo generar el código QR de pago.'], 500);
        }

        $subscription = $result['subscription'];
        $qr = $result['qr'];
        $objeto = $qr['objeto'] ?? $qr;

        return response()->json([
            'referencia' => $subscription->sip_referencia,
            'qr_image_base64' => $objeto['imagenQr'] ?? null,
            'monto' => $subscription->is_annual ? (float) $subscription->annual_price : (float) $subscription->monthly_price,
            'moneda' => 'BOB',
            'banco_destino' => $objeto['bancoDestino'] ?? null,
            'cuenta_destino' => $objeto['cuentaDestino'] ?? null,
            'expires_at' => $objeto['fechaVencimiento'] ?? null,
        ], 201);
    }

    /**
     * Estado del pago de suscripción — para el polling del frontend
     * (16/10/2026). Consulta primero el estado local; si sigue pendiente,
     * respalda consultando directamente a SIP (no confiar solo en que el
     * callback llegue a tiempo, mismo criterio que pago_status.php en
     * elascenso/event).
     */
    public function paymentStatus(string $referencia, Request $request, SipService $sip, ConfirmarPagoSuscripcionAction $confirmar)
    {
        $subscription = Subscription::where('sip_referencia', $referencia)
            ->where('user_id', $request->user()->user_id)
            ->firstOrFail();

        if ($subscription->status === 'pending_payment') {
            try {
                $token = $sip->generarToken();
                $accessToken = $token['token'] ?? $token['data']['token'] ?? $token['access_token'] ?? '';
                $estado = $sip->estadoTransaccion($referencia, $accessToken);
                $objeto = $estado['objeto'] ?? $estado;

                if (($objeto['estadoActual'] ?? null) === 'PAGADO') {
                    $subscription = $confirmar->handle($referencia);
                }
            } catch (\Throwable $e) {
                Log::error("No se pudo consultar el estado SIP de {$referencia}: {$e->getMessage()}");
            }
        }

        return response()->json([
            'referencia' => $subscription->sip_referencia,
            'status' => $subscription->status,
        ]);
    }

    /**
     * Callback que invoca SIP al confirmar un pago (16/10/2026). Contrato
     * exacto exigido por la spec de SIP — no se cambia: Basic Auth +
     * respuesta siempre {"codigo":..., "mensaje":...}.
     */
    public function sipCallback(Request $request, ConfirmarPagoSuscripcionAction $confirmar)
    {
        $authorization = $request->header('Authorization', '');

        if (! str_starts_with($authorization, 'Basic ')) {
            return response()->json(['codigo' => '9999', 'mensaje' => 'No autorizado'], 401);
        }

        $decoded = base64_decode(substr($authorization, 6), true);
        [$authUser, $authPassword] = str_contains((string) $decoded, ':')
            ? explode(':', $decoded, 2)
            : ['', ''];

        $validUser = hash_equals((string) config('sip.callback_basic_user'), (string) $authUser);
        $validPassword = hash_equals((string) config('sip.callback_basic_password'), (string) $authPassword);

        if (! $validUser || ! $validPassword) {
            return response()->json(['codigo' => '9999', 'mensaje' => 'No autorizado'], 401);
        }

        $alias = (string) $request->input('alias', '');

        if ($alias === '') {
            return response()->json(['codigo' => '9999', 'mensaje' => 'Se requiere "alias"'], 400);
        }

        try {
            $confirmar->handle($alias);

            return response()->json(['codigo' => '0000', 'mensaje' => 'Registro Exitoso'], 200);
        } catch (\DomainException $e) {
            Log::warning("Callback SIP con alias no resuelto: {$alias} — {$e->getMessage()}");

            return response()->json(['codigo' => '9999', 'mensaje' => $e->getMessage()], 200);
        } catch (\Throwable $e) {
            Log::error("Error procesando el callback SIP de {$alias}: {$e->getMessage()}");

            return response()->json(['codigo' => '9999', 'mensaje' => 'Error interno'], 500);
        }
    }

    /**
     * SIP no tiene concepto de suscripción recurrente (es QR por período) —
     * cancelar solo marca el registro local; el usuario deja de tener plan
     * pago automáticamente cuando `subscription_expires_at` vence
     * (ver User::isSubscriptionActive()).
     */
    public function cancel(Request $request)
    {
        $user = $request->user();
        $subscription = $user->subscription;

        if (! $subscription || ! $subscription->isActive()) {
            return response()->json(['message' => 'No active subscription to cancel'], 404);
        }

        $subscription->update([
            'cancelled_at' => Carbon::now(),
        ]);

        return response()->json([
            'message' => 'Subscription will be cancelled at the end of the current period',
            'expires_at' => $subscription->current_period_end,
        ]);
    }

    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                config('services.stripe.webhook_secret')
            );
        } catch (\UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
            'invoice.payment_succeeded' => $this->handlePaymentSucceeded($event->data->object),
            'invoice.payment_failed' => $this->handlePaymentFailed($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
            default => null,
        };

        return response()->json(['received' => true]);
    }

    private function handleCheckoutCompleted($session): void
    {
        $userId = $session->metadata->user_id ?? null;
        if (! $userId) {
            return;
        }

        $user = User::find($userId);
        if (! $user) {
            return;
        }

        $stripeSubscription = StripeSubscription::retrieve($session->subscription);
        $plan = $session->metadata->plan ?? 'pro';
        $isAnnual = ($session->metadata->billing ?? 'monthly') === 'annual';
        $plans = Subscription::getPlans();

        $subscription = Subscription::create([
            'user_id' => $user->user_id,
            'stripe_subscription_id' => $session->subscription,
            'stripe_customer_id' => $session->customer,
            'plan' => $plan,
            'status' => 'trialing',
            'monthly_price' => $plans[$plan]['monthly']['price'],
            'annual_price' => $plans[$plan]['annual']['price'],
            'is_annual' => $isAnnual,
            'current_period_start' => Carbon::createFromTimestamp($stripeSubscription->current_period_start),
            'current_period_end' => Carbon::createFromTimestamp($stripeSubscription->current_period_end),
            'trial_ends_at' => Carbon::createFromTimestamp($stripeSubscription->trial_end),
        ]);

        $user->update([
            'subscription_tier' => $plan,
            'subscription_expires_at' => Carbon::createFromTimestamp($stripeSubscription->trial_end),
            'stripe_customer_id' => $session->customer,
            'stripe_trial_ends_at' => Carbon::createFromTimestamp($stripeSubscription->trial_end),
        ]);

        $this->recordPayment($user, $subscription, $session->payment_intent, null, 0, 'Trial started');
    }

    private function handlePaymentSucceeded($invoice): void
    {
        $subscriptionId = $invoice->subscription;
        if (! $subscriptionId) {
            return;
        }

        $subscription = Subscription::where('stripe_subscription_id', $subscriptionId)->first();
        if (! $subscription) {
            return;
        }

        $user = $subscription->user;

        $stripeSubscription = StripeSubscription::retrieve($subscriptionId);

        $subscription->update([
            'status' => 'active',
            'current_period_start' => Carbon::createFromTimestamp($stripeSubscription->current_period_start),
            'current_period_end' => Carbon::createFromTimestamp($stripeSubscription->current_period_end),
            'trial_ends_at' => null,
        ]);

        $user->update([
            'subscription_tier' => $subscription->plan,
            'subscription_expires_at' => Carbon::createFromTimestamp($stripeSubscription->current_period_end),
            'stripe_trial_ends_at' => null,
        ]);

        $this->recordPayment($user, $subscription, $invoice->payment_intent, $invoice->id, $invoice->amount_paid / 100, 'Subscription payment');
    }

    private function handlePaymentFailed($invoice): void
    {
        $subscriptionId = $invoice->subscription;
        if (! $subscriptionId) {
            return;
        }

        $subscription = Subscription::where('stripe_subscription_id', $subscriptionId)->first();
        if (! $subscription) {
            return;
        }

        $subscription->update(['status' => 'past_due']);

        $user = $subscription->user;
        try {
            $user->notify(new \App\Notifications\PaymentFailedNotification($subscription));
        } catch (\Exception $e) {
            // continue
        }
    }

    private function handleSubscriptionDeleted($stripeSubscription): void
    {
        $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();
        if (! $subscription) {
            return;
        }

        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => Carbon::now(),
        ]);

        $user = $subscription->user;
        $user->update([
            'subscription_tier' => 'free',
            'subscription_expires_at' => null,
        ]);

        try {
            $user->notify(new \App\Notifications\SubscriptionExpiredNotification($subscription));
        } catch (\Exception $e) {
            // continue
        }
    }

    private function handleSubscriptionUpdated($stripeSubscription): void
    {
        $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();
        if (! $subscription) {
            return;
        }

        $statusMap = [
            'active' => 'active',
            'past_due' => 'past_due',
            'canceled' => 'cancelled',
            'unpaid' => 'past_due',
            'trialing' => 'trialing',
        ];

        $newStatus = $statusMap[$stripeSubscription->status] ?? $subscription->status;

        $subscription->update([
            'status' => $newStatus,
            'current_period_start' => Carbon::createFromTimestamp($stripeSubscription->current_period_start),
            'current_period_end' => Carbon::createFromTimestamp($stripeSubscription->current_period_end),
        ]);

        if ($newStatus === 'active' || $newStatus === 'trialing') {
            $subscription->user->update([
                'subscription_tier' => $subscription->plan,
                'subscription_expires_at' => Carbon::createFromTimestamp($stripeSubscription->current_period_end),
            ]);
        }
    }

    private function getOrCreateStripeCustomer($user): string
    {
        if ($user->stripe_customer_id) {
            return $user->stripe_customer_id;
        }

        $customer = Customer::create([
            'email' => $user->email,
            'name' => $user->first_name.' '.$user->last_name,
            'metadata' => ['user_id' => $user->user_id],
        ]);

        $user->update(['stripe_customer_id' => $customer->id]);

        return $customer->id;
    }

    private function recordPayment($user, $subscription, $paymentIntentId, $invoiceId, $amount, $description): void
    {
        \App\Models\Payment::create([
            'user_id' => $user->user_id,
            'subscription_id' => $subscription->subscription_id,
            'stripe_payment_id' => $paymentIntentId,
            'stripe_invoice_id' => $invoiceId,
            'amount' => $amount,
            'currency' => 'USD',
            'status' => $amount > 0 ? 'succeeded' : 'pending',
            'description' => $description,
            'paid_at' => Carbon::now(),
        ]);
    }
}
