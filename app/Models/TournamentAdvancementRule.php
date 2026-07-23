<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentAdvancementRule extends Model
{
    use HasFactory;

    protected $table = 'tournamentadvancementrules';

    protected $primaryKey = 'rule_id';

    public $timestamps = false;

    protected $fillable = [
        'rule_id',
        'stage_id',
        'next_stage_id',
        'rule_type',
        'rule_value',
        'description',
    ];

    public function stage()
    {
        return $this->belongsTo('App\Models\TournamentStage', 'stage_id');
    }

    public function nextStage()
    {
        return $this->belongsTo('App\Models\TournamentStage', 'next_stage_id');
    }

    public function advancements()
    {
        return $this->hasMany('App\Models\TournamentAdvancement', 'rule_id');
    }

    public function isTopN(): bool
    {
        return $this->rule_type === 'top_n';
    }

    public function isBestThird(): bool
    {
        return $this->rule_type === 'best_third';
    }

    public function isManual(): bool
    {
        return $this->rule_type === 'manual';
    }
}
