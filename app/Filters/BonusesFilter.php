<?php

namespace App\Filters;

class BonusesFilter extends ApiFilter
{
    /**/
    protected $safeParams = [
        'bonus_id' => ['eq', 'gt', 'lt', 'gte', 'lte'],
        'user_id' => ['eq'],
        'type' => ['eq'],
        'odd_id' => ['eq'],
        'amount' => ['eq'],
        'expires_at' => ['eq'],
        'is_used' => ['eq'],

    ];

    protected $columnMap = [
        'bonus' => 'bonus_id',
    ];

    protected $operatorMap = [
        'eq' => '=',
        'gt' => '>',
        'lt' => '<',
        'gte' => '>=',
        'lte' => '<=',
    ];
}
