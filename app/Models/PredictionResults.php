<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PredictionResults extends Model
{
    /** @use HasFactory<\Database\Factories\PredictionResultsFactory> */
    use HasFactory;

    protected $table = 'predictionresults';

    protected $primaryKey = 'result_id';

    public $timestamps = false;

    protected $fillable = ['result_id', 'prediction_id', 'points_earned', 'points_breakdown', 'processed_at'];

    protected function casts(): array
    {
        return [
            'points_breakdown' => 'array',
        ];
    }

    public function prediction()
    {
        return $this->belongsTo('App\Models\Predictions', 'prediction_id');
    }
}
