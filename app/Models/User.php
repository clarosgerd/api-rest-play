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

    public function getTierLimits(): array
    {
        return match ($this->subscription_tier) {
            // Predictor (16/10/2026) — pensado para invitados que solo
            // pronostican, no para dueños de torneo/grupo: no puede crear
            // torneos propios ni ser dueño de un grupo (members_per_group=0
            // no tiene sentido como "cupo del grupo que posee" porque no
            // posee ninguno), predicciones ilimitadas porque es su único rol.
            'predictor' => [
                'tournaments' => 0,
                'members_per_group' => 0,
                'predictions_per_day' => -1,
                'external_leagues' => [],
            ],
            'pro' => [
                'tournaments' => 5,
                'members_per_group' => 50,
                'predictions_per_day' => 20,
                'external_leagues' => ['conmebol', 'sudamericana', 'south_america'],
            ],
            'premium' => [
                'tournaments' => -1,
                'members_per_group' => -1,
                'predictions_per_day' => -1,
                'external_leagues' => ['all'],
            ],
            default => [
                'tournaments' => 2,
                'members_per_group' => 10,
                'predictions_per_day' => 5,
                'external_leagues' => [],
            ],
        };
    }
}
