<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBonusesRequest extends FormRequest
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
                'type' => ['required', 'string', 'max:50'],
                'amount' => ['required', 'numeric', 'min:0'],
                'expires_at' => ['nullable', 'date'],
                'is_used' => ['nullable', 'boolean'],
            ];
        } else {
            return [
                'user_id' => ['sometimes', 'required', 'integer', 'exists:users,user_id'],
                'type' => ['sometimes', 'required', 'string', 'max:50'],
                'amount' => ['sometimes', 'required', 'numeric', 'min:0'],
                'expires_at' => ['nullable', 'date'],
                'is_used' => ['nullable', 'boolean'],
            ];
        }
    }
}
