<?php

namespace App\Filters;

class TeamFilter extends ApiFilter
{
    /**/
    protected $safeParams = [
        'team_id' => ['eq', 'gt', 'lt', 'gte', 'lte'],
        'name' => ['eq'],
        'country' => ['eq'],
        'league' => ['eq'],
        'logo_url' => ['eq'],
        'flag_url' => ['eq'],
        'active' => ['eq', 'gt', 'lt', 'gte', 'lte'],
    ];

    protected $columnMap = [
        'team_id' => 'team_id',
        'active' => 'active',
    ];

    protected $operatorMap = [
        'eq' => '=',
        'gt' => '>',
        'lt' => '<',
        'gte' => '>=',
        'lte' => '<=',
    ];
}
