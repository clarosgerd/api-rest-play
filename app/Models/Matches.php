<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Matches extends Model
{
    /** @use HasFactory<\Database\Factories\MatchesFactory> */
    use HasFactory;

    protected $table = 'matches';

    protected $primaryKey = 'match_id';

    public $timestamps = false;

    protected $fillable = [
        'match_id',
        'home_team_id',
        'away_team_id',
        'tournament_id',
        'stage_id',
        'match_date',
        'status',
        'home_score',
        'away_score',
        'external_id',
    ];

    public function tournament()
    {
        return $this->belongsTo('App\Models\Tournament', 'tournament_id');
    }

    public function stage()
    {
        return $this->belongsTo('App\Models\TournamentStage', 'stage_id');
    }

    public function home()
    {
        return $this->belongsTo('App\Models\Team', 'home_team_id');
    }

    public function away()
    {
        return $this->belongsTo('App\Models\Team', 'away_team_id');
    }

    public function odds()
    {
        return $this->hasMany('App\Models\Odds', 'match_id');
    }

    public function predictions()
    {
        return $this->hasMany('App\Models\Predictions', 'match_id');
    }
}
