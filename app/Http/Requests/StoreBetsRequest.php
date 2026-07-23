<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,user_id'],
            'match_id' => ['required', 'integer', 'exists:matches,match_id'],
            'odd_id' => ['required', 'integer', 'exists:odds,odd_id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'potential_payout' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'string', 'in:pending,won,lost,cancelled'],
            'placed_at' => ['nullable', 'date'],
        ];
    }
}
