<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanPromotion extends Model
{
    protected $table = 'plan_promotions';

    protected $fillable = [
        'plan_id',
        'name',
        'monthly_price_usd',
        'annual_price_usd',
        'monthly_price_bob',
        'annual_price_bob',
        'predictions_per_day',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'monthly_price_usd' => 'decimal:2',
        'annual_price_usd' => 'decimal:2',
        'monthly_price_bob' => 'decimal:2',
        'annual_price_bob' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }
}
