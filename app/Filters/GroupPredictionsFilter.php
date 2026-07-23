<?php

namespace App\Filters;

class GroupPredictionsFilter extends ApiFilter
{
    /**/
    protected $safeParams = [
        'group_prediction_id' => ['eq'],
        'group_id' => ['eq'],
        'prediction_id' => ['eq'],
        'points_awarded' => ['eq', 'gt', 'lt', 'gte', 'lte'],
    ];

    protected $columnMap = [
        'pointsawarded' => 'points_awarded',
    ];

    protected $operatorMap = [
        'eq' => '=',
        'gt' => '>',
        'lt' => '<',
        'gte' => '>=',
        'lte' => '<=',
    ];
}
