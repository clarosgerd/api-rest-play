<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentGroup extends Model
{
    use HasFactory;

    protected $table = 'tournamentgroups';

    protected $primaryKey = 'group_id';

    public $timestamps = false;

    protected $fillable = [
        'group_id',
        'stage_id',
        'name',
        'group_order',
        'status',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function stage()
    {
        return $this->belongsTo('App\Models\TournamentStage', 'stage_id');
    }

    public function teams()
    {
        return $this->hasMany('App\Models\TournamentGroupTeam', 'group_id');
    }

    public function advancements()
    {
        return $this->hasMany('App\Models\TournamentAdvancement', 'source_group_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
