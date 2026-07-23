<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bonuses extends Model
{
    /** @use HasFactory<\Database\Factories\BonusesFactory> */
    use HasFactory;

    protected $table = 'bonuses';

    protected $primaryKey = 'bonus_id';

    public $timestamps = false;

    protected $fillable = [
        'bonus_id',
        'user_id',
        'type',
        'amount',
        'expires_at',
        'is_used',
    ];

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'user_id');
    }
}
