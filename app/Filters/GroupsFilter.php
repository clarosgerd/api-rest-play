<?php

namespace App\Filters;

class GroupsFilter extends ApiFilter
{
    /**/
    protected $safeParams = [
        'group_id' => ['eq', 'gt', 'lt', 'gte', 'lte'],
        'tournament_id' => ['eq'],
        'name' => ['eq'],
        'description' => ['eq'],
        'creator_id' => ['eq'],
        'is_public' => ['eq'],
        'invite_code' => ['eq'],
        'max_members' => ['eq'],
        'created_at' => ['eq'],
    ];

    protected $columnMap = [
        'group_id' => 'group_id',
        'tournament_id' => 'tournament_id',
    ];

    protected $operatorMap = [
        'eq' => '=',
        'gt' => '>',
        'lt' => '<',
        'gte' => '>=',
        'lte' => '<=',
    ];
}
