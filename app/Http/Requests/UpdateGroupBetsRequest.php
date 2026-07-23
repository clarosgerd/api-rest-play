<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroupBetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $method = $this->method();
        if ($method == 'PUT') {
            return [
                'group_id' => ['required', 'integer', 'exists:groups,group_id'],
                'bet_id' => ['required', 'integer', 'exists:bets,bet_id'],
                'created_by' => ['required', 'integer', 'exists:users,user_id'],
            ];
        } else {
            return [
                'group_id' => ['sometimes', 'required', 'integer', 'exists:groups,group_id'],
                'bet_id' => ['sometimes', 'required', 'integer', 'exists:bets,bet_id'],
                'created_by' => ['sometimes', 'required', 'integer', 'exists:users,user_id'],
            ];
        }
    }
}
