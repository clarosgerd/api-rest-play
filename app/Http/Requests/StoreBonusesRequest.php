<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBonusesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,user_id'],
            'type' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:0'],
            'expires_at' => ['nullable', 'date'],
            'is_used' => ['nullable', 'boolean'],
        ];
    }
}
