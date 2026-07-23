<?php

namespace App\Filters;

class PredictionResultsFilter extends ApiFilter
{
    /**/
    protected $safeParams = [
        'result_id' => ['eq'],
        'prediction_id' => ['eq'],
        'is_correct' => ['eq'],
        'points_earned' => ['eq', 'gt', 'lt', 'gte', 'lte'],
        'processed_at' => ['eq'],
    ];

    protected $columnMap = [
        'pointsearned' => 'points_earned',
    ];

    protected $operatorMap = [
        'eq' => '=',
        'gt' => '>',
        'lt' => '<',
        'gte' => '>=',
        'lte' => '<=',
    ];
}
