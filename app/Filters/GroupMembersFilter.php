<?php

namespace App\Filters;

class GroupMembersFilter extends ApiFilter
{
    /**/
    protected $safeParams = [
        'member_id' => ['eq', 'gt', 'lt', 'gte', 'lte'],
        'group_id' => ['eq'],
        'user_id' => ['eq'],
        'joined_at' => ['eq'],
        'is_admin' => ['eq'],
    ];

    protected $columnMap = [
        'member_id' => 'member_id',
    ];

    protected $operatorMap = [
        'eq' => '=',
        'gt' => '>',
        'lt' => '<',
        'gte' => '>=',
        'lte' => '<=',
    ];
}
