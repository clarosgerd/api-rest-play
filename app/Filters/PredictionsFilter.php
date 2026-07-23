<?php

namespace App\Filters;

class PredictionsFilter extends ApiFilter
{
    /**/
    protected $safeParams = [
        'prediction_id' => ['eq'],
        'user_id' => ['eq'],
        'match_id' => ['eq'],
        'predicted_home_score' => ['eq', 'gt', 'lt', 'gte', 'lte'],
        'predicted_away_score' => ['eq', 'gt', 'lt', 'gte', 'lte'],
        'predicted_result' => ['eq'],
        'bet_type' => ['eq'],
        'created_at' => ['eq'],
        'is_public' => ['eq'],
    ];

    protected $columnMap = [
        'predictedhomescore' => 'predicted_home_score',
        'predictedawayscore' => 'predicted_away_score',
    ];

    protected $operatorMap = [
        'eq' => '=',
        'gt' => '>',
        'lt' => '<',
        'gte' => '>=',
        'lte' => '<=',
    ];
}
