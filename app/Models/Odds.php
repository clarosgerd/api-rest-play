<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Odds extends Model
{
    /** @use HasFactory<\Database\Factories\OddsFactory> */
    use HasFactory;

    protected $table = 'odds';

    protected $primaryKey = 'odd_id';

    protected $fillable = ['odd_id', 'match_id', 'bet_type', 'option', 'value', 'last_update'];

    public $timestamps = false;

    public function match()
    {
        return $this->belongsTo('App\Models\Matches', 'match_id');
    }
}
