<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentStage extends Model
{
    use HasFactory;

    protected $table = 'tournamentstage';

    protected $primaryKey = 'stage_id';

    public $timestamps = false;

    protected $fillable = [
        'stage_id',
        'tournament_id',
        'name',
        'stage_order',
        'is_knockout',
    ];

    public function tournament()
    {
        return $this->belongsTo('App\Models\Tournament', 'tournament_id');
    }

    public function matches()
    {
        return $this->hasMany('App\Models\Matches', 'stage_id');
    }

    public function tournamentGroups()
    {
        return $this->hasMany('App\Models\TournamentGroup', 'stage_id');
    }

    public function advancementRules()
    {
        return $this->hasMany('App\Models\TournamentAdvancementRule', 'stage_id');
    }

    public function nextAdvancementRules()
    {
        return $this->hasMany('App\Models\TournamentAdvancementRule', 'next_stage_id');
    }
}
