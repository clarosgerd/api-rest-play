<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentAdvancement extends Model
{
    use HasFactory;

    protected $table = 'tournamentadvancement';

    protected $primaryKey = 'advancement_id';

    public $timestamps = false;

    protected $fillable = [
        'advancement_id',
        'rule_id',
        'team_id',
        'source_group_id',
        'source_position',
        'confirmed',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'confirmed' => 'boolean',
            'confirmed_at' => 'datetime',
        ];
    }

    public function rule()
    {
        return $this->belongsTo('App\Models\TournamentAdvancementRule', 'rule_id');
    }

    public function team()
    {
        return $this->belongsTo('App\Models\Team', 'team_id');
    }

    public function sourceGroup()
    {
        return $this->belongsTo('App\Models\TournamentGroup', 'source_group_id');
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed === true;
    }
}
