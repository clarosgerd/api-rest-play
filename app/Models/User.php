<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens,HasFactory, Notifiable;

    protected $primaryKey = 'user_id';

    public $timestamps = false;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'email',
        'password_hash',
        'balance',
        'registration_date',
        'last_login',
        'verified',
        'role',
        'suspended_at',
        'subscription_tier',
        'subscription_expires_at',
        'stripe_customer_id',
        'stripe_trial_ends_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_hash' => 'hashed',
            'suspended_at' => 'datetime',
            // Bug real encontrado (20/10/2026): sin este cast,
            // isSubscriptionActive() rompía con "Call to a member function
            // isPast() on string" para cualquier usuario con
            // subscription_expires_at seteado (trial/suscripción vieja) —
            // GET /user/subscription (la pantalla de Planes) tiraba 500.
            'subscription_expires_at' => 'datetime',
        ];
    }

    public function bets()
    {
        return $this->hasMany('App\Models\Bets', 'user_id');
    }

    public function groupmembers()
    {
        return $this->hasMany('App\Models\GroupMembers', 'user_id');
    }

    public function predictions()
    {
        return $this->hasMany('App\Models\Predictions', 'user_id');
    }

    public function transaction()
    {
        return $this->hasMany('App\Models\Transactions', 'user_id');
    }

    public function bonuses()
    {
        return $this->hasMany('App\Models\Bonuses', 'user_id');
    }

    public function subscription()
    {
        return $this->hasOne('App\Models\Subscription', 'user_id')->latest();
    }

    public function subscriptions()
    {
        return $this->hasMany('App\Models\Subscription', 'user_id');
    }

    public function payments()
    {
        return $this->hasMany('App\Models\Payment', 'user_id');
    }

    public function isPro(): bool
    {
        return $this->subscription_tier === 'pro' && $this->isSubscriptionActive();
    }

    public function isPremium(): bool
    {
        return $this->subscription_tier === 'premium' && $this->isSubscriptionActive();
    }

    /**
     * Suscripción "predictor" (16/10/2026, pedido del usuario) — pronosticar
     * ahora exige CUALQUIER plan pago activo (predictor/pro/premium), ya no
     * alcanza con free. Separado de isPro()/isPremium() porque esos 2 son
     * exclusivos entre sí, mientras que este chequeo cubre a los 3.
     */
    public function hasPaidSubscription(): bool
    {
        return in_array($this->subscription_tier, ['predictor', 'pro', 'premium'], true)
            && $this->isSubscriptionActive();
    }

    public function isSubscriptionActive(): bool
    {
        if ($this->subscription_tier === 'free') {
            return true;
        }

        if ($this->subscription_expires_at && $this->subscription_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Límites por tier, administrables por super admin (20/10/2026) — antes
     * hardcodeado acá mismo en un match(), ahora lee de la tabla `plans`
     * (mismo shape de salida para no tocar los middlewares/controllers que ya
     * consumen esto). Fallback defensivo a los valores de "free" si por algún
     * motivo la fila no existe (no debería pasar: la migración de `plans`
     * siembra las 4 filas base).
     */
    public function getTierLimits(): array
    {
        $plan = Plan::where('slug', $this->subscription_tier ?? 'free')->first();

        if (! $plan) {
            return [
                'tournaments' => 0,
                'members_per_group' => 0,
                'predictions_per_day' => 0,
                'external_leagues' => [],
            ];
        }

        return $plan->effectiveLimits();
    }
}
