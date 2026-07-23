<?php

namespace App\Filters;

class TournamentStagesFilter extends ApiFilter
{
    /**/
    protected $safeParams = [
        'stage_id' => ['eq', 'gt', 'lt', 'gte', 'lte', 'ne'],
        'tournament_id' => ['eq', 'gt', 'lt', 'gte', 'lte', 'ne'],
        'name' => ['eq'],
        'stage_order' => ['eq'],
        'is_knockout' => ['eq'],
    ];

    protected $columnMap = [];

    protected $operatorMap = [
        'eq' => '=',
        'gt' => '>',
        'lt' => '<',
        'gte' => '>=',
        'lte' => '<=',
        'ne' => '!=',
    ];
}
