<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Groups extends Model
{
    /** @use HasFactory<\Database\Factories\GroupsFactory> */
    use HasFactory;

    protected $table = 'groups';

    protected $primaryKey = 'group_id';

    public $timestamps = false;

    protected $fillable = [
        'group_id',
        'tournament_id',
        'name',
        'description',
        'creator_id',
        'is_public',
        'invitation_code',
        'max_members',
        'active',
        'bet_amount',
        'prize_1st_pct',
        'prize_2nd_pct',
        'prize_3rd_pct',
        'pool_active',
        'exact_score_points',
        'match_result_points',
        'correct_goal_points',
        'unique_exact_points',
        'multiplier_dieciseisavos',
        'multiplier_octavos',
        'multiplier_cuartos',
        'multiplier_semifinal',
        'multiplier_final',
        'created_at',
    ];

    protected $casts = [
        'exact_score_points' => 'integer',
        'match_result_points' => 'integer',
        'correct_goal_points' => 'integer',
        'unique_exact_points' => 'integer',
        'multiplier_dieciseisavos' => 'float',
        'multiplier_octavos' => 'float',
        'multiplier_cuartos' => 'float',
        'multiplier_semifinal' => 'float',
        'multiplier_final' => 'float',
    ];

    public function groupmember()
    {
        return $this->hasMany('App\Models\GroupMembers', 'group_id');
    }

    public function groupbet()
    {
        return $this->hasOne('App\Models\GroupBets', 'group_id');
    }

    public function groupprediction()
    {
        return $this->hasOne('App\Models\GroupPredictions', 'group_id');
    }

    public function invitations()
    {
        return $this->hasMany('App\Models\Invitation', 'group_id');
    }

    public function pool()
    {
        return $this->hasMany('App\Models\GroupPool', 'group_id');
    }

    public function tournament()
    {
        return $this->belongsTo('App\Models\Tournament', 'tournament_id');
    }

    public function pendingMembers()
    {
        return $this->hasMany('App\Models\GroupMembers', 'group_id')->where('status', 'pending');
    }

    public function getPrizeDistribution(): array
    {
        return [
            '1st' => $this->prize_1st_pct ?? 60,
            '2nd' => $this->prize_2nd_pct ?? 30,
            '3rd' => $this->prize_3rd_pct ?? 10,
        ];
    }

    public function getStageMultiplier(?string $stageName): float
    {
        if (! $stageName) {
            return 1.0;
        }

        $stageName = strtolower(trim($stageName));

        if (str_contains($stageName, 'dieciseisavos')) {
            return $this->multiplier_dieciseisavos ?? 1.5;
        } elseif (str_contains($stageName, 'octavos')) {
            return $this->multiplier_octavos ?? 2.0;
        } elseif (str_contains($stageName, 'cuartos')) {
            return $this->multiplier_cuartos ?? 3.0;
        } elseif (str_contains($stageName, 'semifinal')) {
            return $this->multiplier_semifinal ?? 5.0;
        } elseif (str_contains($stageName, 'final')) {
            return $this->multiplier_final ?? 10.0;
        }

        return 1.0;
    }
}
