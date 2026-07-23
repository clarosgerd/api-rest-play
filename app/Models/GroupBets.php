<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupBets extends Model
{
    /** @use HasFactory<\Database\Factories\GroupBetsFactory> */
    use HasFactory;

    protected $table = 'groupbets';

    protected $primaryKey = 'group_bet_id';

    public $timestamps = false;

    protected $fillable = [
        'group_bet_id',
        'group_id',
        'bet_id',
        'created_by',
        'created_at',
    ];

    public function createdby()
    {
        return $this->belongsTo('App\Models\User', 'created_by');
    }

    public function group()
    {
        return $this->belongsTo('App\Models\Groups', 'group_id');
    }

    public function bet()
    {
        return $this->belongsTo('App\Models\Bets', 'bet_id');
    }
}
