<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupPool extends Model
{
    use HasFactory;

    protected $table = 'grouppool';

    protected $primaryKey = 'pool_id';

    public $timestamps = false;

    protected $fillable = [
        'pool_id',
        'group_id',
        'user_id',
        'match_id',
        'amount_applied',
        'points_earned',
        'prize_won',
        'status',
        'scored_at',
        'distributed_at',
    ];

    public function group()
    {
        return $this->belongsTo('App\Models\Groups', 'group_id');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'user_id');
    }

    public function match()
    {
        return $this->belongsTo('App\Models\Matches', 'match_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isScored(): bool
    {
        return $this->status === 'scored';
    }

    public function isDistributed(): bool
    {
        return $this->status === 'distributed';
    }
}
