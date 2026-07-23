<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
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

    public function plans()
    {
        $plans = Subscription::getPlans();

        return response()->json([
            'plans' => [
                'pro' => [
                    'name' => 'Pro',
                    'features' => [
                        '5 tournaments',
                        '50 members per group',
                        '20 predictions per day',
                        'CONMEBOL & South American leagues',
                        'Advanced notifications',
                        'Priority scoring',
                    ],
                    'monthly' => $plans['pro']['monthly']['price'],
                    'annual' => $plans['pro']['annual']['price'],
                    'annual_savings' => round(($plans['pro']['monthly']['price'] * 12) - $plans['pro']['annual']['price'], 2),
                ],
                'premium' => [
                    'name' => 'Premium',
                    'features' => [
                        'Unlimited tournaments',
                        'Unlimited members per group',
                        'Unlimited predictions per day',
                        'All leagues worldwide (World Cup, Champions, etc.)',
                        'Complete notifications + analytics',
                        'Real-time scoring',
                        'Tournament creation tools',
                    ],
                    'monthly' => $plans['premium']['monthly']['price'],
                    'annual' => $plans['premium']['annual']['price'],
                    'annual_savings' => round(($plans['premium']['monthly']['price'] * 12) - $plans['premium']['annual']['price'], 2),
                ],
            ],
            'trial_days' => 7,
            'currency' => 'USD',
        ]);
    }

    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'plan' => 'required|in:pro,premium',
            'billing' => 'required|in:monthly,annual',
        ]);

        $user = $request->user();
        $plans = Subscription::getPlans();
        $planConfig = $plans[$validated['plan']][$validated['billing']];
        $priceId = $planConfig['stripe_price_id'];

        try {
            $customerId = $this->getOrCreateStripeCustomer($user);

            $session = \Stripe\Checkout\Session::create([
                'customer' => $customerId,
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'subscription_data' => [
                    'trial_period_days' => 7,
                ],
                'success_url' => env('APP_URL').'/subscription/success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => env('APP_URL').'/subscription/cancel',
                'metadata' => [
                    'user_id' => $user->user_id,
                    'plan' => $validated['plan'],
                    'billing' => $validated['billing'],
                ],
            ]);

            return response()->json([
                'checkout_url' => $session->url,
                'session_id' => $session->id,
            ]);
        } catch (ApiErrorException $e) {
            return response()->json([
                'message' => 'Payment processing failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function cancel(Request $request)
    {
        $user = $request->user();
        $subscription = $user->subscription;

        if (! $subscription || ! $subscription->isActive()) {
            return response()->json(['message' => 'No active subscription to cancel'], 404);
        }

        try {
            if ($subscription->stripe_subscription_id) {
                StripeSubscription::update($subscription->stripe_subscription_id, [
                    'cancel_at_period_end' => true,
                ]);
            }

            $subscription->update([
                'cancelled_at' => Carbon::now(),
            ]);

            return response()->json([
                'message' => 'Subscription will be cancelled at the end of the current period',
                'expires_at' => $subscription->current_period_end,
            ]);
        } catch (ApiErrorException $e) {
            return response()->json([
                'message' => 'Failed to cancel subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
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
