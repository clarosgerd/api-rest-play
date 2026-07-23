<?php

namespace App\Filters;

class OddsFilter extends ApiFilter
{
    /**/
    protected $safeParams = [
        'odd_id' => ['eq', 'gt', 'lt', 'gte', 'lte'],
        'match_id' => ['eq'],
        'bet_type' => ['eq'],
        'option' => ['eq'],
        'value' => ['eq'],
        'last_update' => ['eq'],
    ];

    protected $columnMap = [
        'oddid' => 'odd_id',
    ];

    protected $operatorMap = [
        'eq' => '=',
        'gt' => '>',
        'lt' => '<',
        'gte' => '>=',
        'lte' => '<=',
    ];
}
