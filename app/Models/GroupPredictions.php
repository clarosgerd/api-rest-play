<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupPredictions extends Model
{
    /** @use HasFactory<\Database\Factories\GroupPredictionsFactory> */
    use HasFactory;

    protected $table = 'grouppredictions';

    protected $primaryKey = 'group_prediction_id';

    public $timestamps = false;

    protected $fillable = [
        'group_prediction_id',
        'group_id',
        'prediction_id',
        'points_awarded',
    ];

    public function group()
    {
        return $this->belongsTo('App\Models\Groups', 'group_id');
    }

    public function prediction()
    {
        return $this->belongsTo('App\Models\Predictions', 'prediction_id');
    }
}
