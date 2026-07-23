<?php

namespace App\Filters;

class TransactionsFilter extends ApiFilter
{
    /**/
    protected $safeParams = [
        'transaction_id' => ['eq'],
        'user_id' => ['eq'],
        'type' => ['eq'],
        'amount' => ['eq', 'gt', 'lt', 'gte', 'lte'],
        'status' => ['eq'],
        'reference' => ['eq'],
        'processed_at' => ['eq'],
    ];

    protected $columnMap = [
        'amount' => 'amount',
    ];

    protected $operatorMap = [
        'eq' => '=',
        'gt' => '>',
        'lt' => '<',
        'gte' => '>=',
        'lte' => '<=',
    ];
}
