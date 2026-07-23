<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOddsRequest extends FormRequest
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
                'match_id' => ['required', 'integer', 'exists:matches,match_id'],
                'bet_type' => ['required', 'string', 'max:50'],
                'option' => ['required', 'string', 'max:100'],
                'value' => ['required', 'numeric', 'min:0'],
                'last_update' => ['nullable', 'date'],
            ];
        } else {
            return [
                'match_id' => ['sometimes', 'required', 'integer', 'exists:matches,match_id'],
                'bet_type' => ['sometimes', 'required', 'string', 'max:50'],
                'option' => ['sometimes', 'required', 'string', 'max:100'],
                'value' => ['sometimes', 'required', 'numeric', 'min:0'],
                'last_update' => ['nullable', 'date'],
            ];
        }
    }
}
