<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentGroupTeam extends Model
{
    use HasFactory;

    protected $table = 'tournamentgroupteams';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'group_id',
        'team_id',
        'position_final',
        'points',
        'goal_diff',
        'goals_for',
        'goals_against',
        'matches_played',
        'wins',
        'draws',
        'losses',
    ];

    public function group()
    {
        return $this->belongsTo('App\Models\TournamentGroup', 'group_id');
    }

    public function team()
    {
        return $this->belongsTo('App\Models\Team', 'team_id');
    }
}
