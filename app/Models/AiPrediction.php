<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiPrediction extends Model
{
    protected $table = 'ai_predictions';

    protected $primaryKey = 'ai_prediction_id';

    protected $fillable = [
        'match_id',
        'predicted_result',
        'predicted_home_score',
        'predicted_away_score',
        'home_win_prob',
        'draw_prob',
        'away_win_prob',
        'confidence',
        'was_correct',
        'model_version',
    ];

    protected function casts(): array
    {
        return [
            'was_correct' => 'boolean',
        ];
    }

    public function match()
    {
        return $this->belongsTo(Matches::class, 'match_id');
    }
}
