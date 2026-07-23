<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBetsRequest extends FormRequest
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
                'user_id' => ['required', 'integer', 'exists:users,user_id'],
                'match_id' => ['required', 'integer', 'exists:matches,match_id'],
                'odd_id' => ['required', 'integer', 'exists:odds,odd_id'],
                'amount' => ['required', 'numeric', 'min:0'],
                'potential_payout' => ['nullable', 'numeric', 'min:0'],
                'status' => ['required', 'string', 'in:pending,won,lost,cancelled'],
                'placed_at' => ['nullable', 'date'],
            ];
        } else {
            return [
                'user_id' => ['sometimes', 'required', 'integer', 'exists:users,user_id'],
                'match_id' => ['sometimes', 'required', 'integer', 'exists:matches,match_id'],
                'odd_id' => ['sometimes', 'required', 'integer', 'exists:odds,odd_id'],
                'amount' => ['sometimes', 'required', 'numeric', 'min:0'],
                'potential_payout' => ['nullable', 'numeric', 'min:0'],
                'status' => ['sometimes', 'required', 'string', 'in:pending,won,lost,cancelled'],
                'placed_at' => ['nullable', 'date'],
            ];
        }
    }
}
