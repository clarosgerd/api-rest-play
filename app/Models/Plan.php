<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'plans';

    protected $fillable = [
        'slug',
        'name',
        'monthly_price_usd',
        'annual_price_usd',
        'monthly_price_bob',
        'annual_price_bob',
        'stripe_price_id_monthly',
        'stripe_price_id_annual',
        'tournament_limit',
        'members_per_group',
        'predictions_per_day',
        'external_leagues',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'monthly_price_usd' => 'decimal:2',
        'annual_price_usd' => 'decimal:2',
        'monthly_price_bob' => 'decimal:2',
        'annual_price_bob' => 'decimal:2',
        'external_leagues' => 'array',
        'is_active' => 'boolean',
    ];

    public function promotions()
    {
        return $this->hasMany(PlanPromotion::class, 'plan_id');
    }

    public function currentPromotion(): ?PlanPromotion
    {
        return $this->promotions()
            ->where('is_active', true)
            ->get()
            ->first(fn (PlanPromotion $promo) => $promo->isCurrentlyActive());
    }

    public function effectiveLimits(): array
    {
        return [
            'tournaments' => $this->tournament_limit,
            'members_per_group' => $this->members_per_group,
            'predictions_per_day' => $this->currentPromotion()?->predictions_per_day ?? $this->predictions_per_day,
            'external_leagues' => $this->external_leagues ?? [],
        ];
    }

    /**
     * @return array{price: float, price_bob: float, stripe_price_id: ?string}
     */
    public function effectiveBilling(string $billing): array
    {
        $promo = $this->currentPromotion();
        $usdColumn = $billing === 'annual' ? 'annual_price_usd' : 'monthly_price_usd';
        $bobColumn = $billing === 'annual' ? 'annual_price_bob' : 'monthly_price_bob';
        $stripeColumn = $billing === 'annual' ? 'stripe_price_id_annual' : 'stripe_price_id_monthly';

        return [
            'price' => (float) ($promo?->{$usdColumn} ?? $this->{$usdColumn}),
            'price_bob' => (float) ($promo?->{$bobColumn} ?? $this->{$bobColumn}),
            'stripe_price_id' => $this->{$stripeColumn},
        ];
    }
}
