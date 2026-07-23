<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOddsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'match_id' => ['required', 'integer', 'exists:matches,match_id'],
            'bet_type' => ['required', 'string', 'max:50'],
            'option' => ['required', 'string', 'max:100'],
            'value' => ['required', 'numeric', 'min:0'],
            'last_update' => ['nullable', 'date'],
        ];
    }
}
