<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    use HasFactory;

    protected $table = 'players';

    protected $primaryKey = 'player_id';

    public $timestamps = false;

    protected $fillable = [
        'player_id',
        'team_id',
        'name',
        'first_name',
        'last_name',
        'position',
        'nationality',
        'birth_date',
        'age',
        'photo_url',
        'external_id',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id');
    }
}
