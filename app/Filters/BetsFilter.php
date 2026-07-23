<?php

namespace App\Filters;

class BetsFilter extends ApiFilter
{
    /**/
    protected $safeParams = [
        'bet_id' => ['eq', 'gt', 'lt', 'gte', 'lte'],
        'user_id' => ['eq'],
        'match_id' => ['eq'],
        'odd_id' => ['eq'],
        'amount' => ['eq'],
        'potential_payout' => ['eq'],
        'status' => ['eq'],
        'placed_at' => ['eq'],
    ];

    protected $columnMap = [
        'betid' => 'bet_id',
    ];

    protected $operatorMap = [
        'eq' => '=',
        'gt' => '>',
        'lt' => '<',
        'gte' => '>=',
        'lte' => '<=',
    ];
}
