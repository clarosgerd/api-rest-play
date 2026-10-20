<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $table = 'subscriptions';

    protected $primaryKey = 'subscription_id';

    protected $fillable = [
        'subscription_id',
        'user_id',
        'stripe_subscription_id',
        'stripe_customer_id',
        'payment_provider',
        'sip_referencia',
        'sip_qr_id',
        'plan',
        'status',
        'monthly_price',
        'annual_price',
        'is_annual',
        'current_period_start',
        'current_period_end',
        'trial_ends_at',
        'cancelled_at',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'annual_price' => 'decimal:2',
        'is_annual' => 'boolean',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'user_id');
    }

    public function payments()
    {
        return $this->hasMany('App\Models\Payment', 'subscription_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trialing']);
    }

    public function isTrial(): bool
    {
        return $this->status === 'trialing' && $this->trial_ends_at?->isFuture();
    }

    public function isPro(): bool
    {
        return $this->plan === 'pro' && $this->isActive();
    }

    public function isPremium(): bool
    {
        return $this->plan === 'premium' && $this->isActive();
    }

    /**
     * Planes administrables por super admin (20/10/2026) — antes hardcodeado
     * acá mismo, ahora lee de la tabla `plans` (mismo shape de salida para no
     * tocar los ~6 lugares que ya consumen esto: GenerarPagoSuscripcionAction,
     * SubscriptionController::plans()/checkout()/webhook, etc). 'free' no
     * tiene precio, así que no aparece acá — solo los planes pagos.
     */
    public static function getPlans(): array
    {
        return Plan::where('slug', '!=', 'free')
            ->where('is_active', true)
            ->get()
            ->mapWithKeys(fn (Plan $plan) => [
                $plan->slug => [
                    'monthly' => $plan->effectiveBilling('monthly'),
                    'annual' => $plan->effectiveBilling('annual'),
                ],
            ])
            ->all();
    }
}
