<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transactions extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionsFactory> */
    use HasFactory;

    protected $table = 'transactions';

    protected $primaryKey = 'transaction_id';

    public $timestamps = false;

    protected $fillable = [
        'transaction_id',
        'user_id',
        'type',
        'amount',
        'status',
        'reference',
        'processed_at',
    ];

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'user_id');
    }
}
