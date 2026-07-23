<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $table = 'payments';

    protected $primaryKey = 'payment_id';

    protected $fillable = [
        'payment_id',
        'user_id',
        'subscription_id',
        'stripe_payment_id',
        'stripe_invoice_id',
        'amount',
        'currency',
        'status',
        'description',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'user_id');
    }

    public function subscription()
    {
        return $this->belongsTo('App\Models\Subscription', 'subscription_id');
    }
}
