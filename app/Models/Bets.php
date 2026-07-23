<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bets extends Model
{
    /** @use HasFactory<\Database\Factories\BetsFactory> */
    use HasFactory;

    protected $table = 'bets';

    protected $primaryKey = 'bet_id';

    public $timestamps = false;

    protected $fillable = [
        'bet_id',
        'user_id',
        'match_id',
        'odd_id',
        'amount',
        'potential_payout',
        'status',
        'placed_at',
    ];

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'user_id');
    }

    public function groupbets()
    {
        return $this->hasMany('App\Models\GroupBets', 'bet_id');
    }
}
