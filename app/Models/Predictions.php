<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Predictions extends Model
{
    /** @use HasFactory<\Database\Factories\PredictionsFactory> */
    use HasFactory;

    protected $table = 'predictions';

    protected $primaryKey = 'prediction_id';

    protected $fillable = ['prediction_id', 'user_id', 'match_id', 'predicted_home_score', 'predicted_away_score', 'predicted_result', 'bet_type', 'confidence', 'created_at', 'is_public'];

    public $timestamps = false;

    public function match()
    {
        return $this->belongsTo('App\Models\Matches', 'match_id');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User', 'user_id');
    }

    public function grouppredictions()
    {
        return $this->hasMany('App\Models\GroupPredictions', 'prediction_id');
    }
}
