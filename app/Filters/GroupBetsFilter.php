<?php

namespace App\Filters;

class GroupBetsFilter extends ApiFilter
{
    /**/
    protected $safeParams = [
        'group_bet_id' => ['eq', 'gt', 'lt', 'gte', 'lte'],
        'group_id' => ['eq'],
        'bet_id' => ['eq'],
        'created_by' => ['eq'],
        'created_at' => ['eq'],
    ];

    protected $columnMap = [
        'groupbetid' => 'group_bet_id',
    ];

    protected $operatorMap = [
        'eq' => '=',
        'gt' => '>',
        'lt' => '<',
        'gte' => '>=',
        'lte' => '<=',
    ];
}
