<?php

namespace App\Filters;

class MatchesFilter extends ApiFilter
{
    /**/
    protected $safeParams = [
        'match_id' => ['eq', 'gt', 'lt', 'gte', 'lte'],
        'home_team_id' => ['eq'],
        'away_team_id' => ['eq'],
        'tournament_id' => ['eq'],
        'stage_id' => ['eq'],
        'match_date' => ['eq', 'gt', 'lt', 'gte', 'lte'],
        'status' => ['eq'],
        'home_score' => ['eq'],
        'away_score' => ['eq'],
    ];

    protected $columnMap = [
        'match_id' => 'match_id',
    ];

    protected $operatorMap = [
        'eq' => '=',
        'gt' => '>',
        'lt' => '<',
        'gte' => '>=',
        'lte' => '<=',
    ];
}
