<?php

namespace App\Filters;

class TournamentFilter extends ApiFilter
{
    /**/
    protected $safeParams = [
        'tournament_id' => ['eq', 'gt', 'lt', 'gte', 'lte'],
        'name' => ['eq'],
        'country' => ['eq'],
        'country_code' => ['eq'],
        'logo_url' => ['eq'],
        'flag_url' => ['eq'],
        'start_date' => ['eq'],
        'end_date' => ['eq'],
        'actived' => ['eq', 'gt', 'lt', 'gte', 'lte', 'ne'],
    ];

    protected $columnMap = [
        'actived' => 'active',
    ];

    protected $operatorMap = [
        'eq' => '=',
        'gt' => '>',
        'lt' => '<',
        'gte' => '>=',
        'lte' => '<=',
        'ne' => '!=',
    ];
}
