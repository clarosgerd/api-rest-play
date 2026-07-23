<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGroupBetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'group_id' => ['required', 'integer', 'exists:groups,group_id'],
            'bet_id' => ['required', 'integer', 'exists:bets,bet_id'],
            'created_by' => ['required', 'integer', 'exists:users,user_id'],
            'created_at' => ['nullable', 'date'],
        ];
    }
}
