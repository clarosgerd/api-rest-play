<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    //
    use HasFactory;

    protected $table = 'teams';

    protected $primaryKey = 'team_id';

    public $timestamps = false;

    protected $fillable = [
        'team_id',
        'name',
        'country',
        'league',
        'logo_url',
        'flag_url',
        'country_code',
        'active',
        'external_id',
    ];

    public function hometeam()
    {
        return $this->hasMany('App\Models\Matches', 'home_team_id');
    }

    public function awayteam()
    {
        return $this->hasMany('App\Models\Matches', 'away_team_id');
    }
}
