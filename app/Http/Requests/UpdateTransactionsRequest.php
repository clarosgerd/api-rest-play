<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTransactionsRequest extends FormRequest
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
                'amount' => ['required', 'numeric'],
                'status' => ['required', 'string', 'in:pending,completed,failed,cancelled'],
                'reference' => ['nullable', 'string', 'max:255'],
                'processed_at' => ['nullable', 'date'],
            ];
        } else {
            return [
                'user_id' => ['sometimes', 'required', 'integer', 'exists:users,user_id'],
                'type' => ['sometimes', 'required', 'string', 'max:50'],
                'amount' => ['sometimes', 'required', 'numeric'],
                'status' => ['sometimes', 'required', 'string', 'in:pending,completed,failed,cancelled'],
                'reference' => ['nullable', 'string', 'max:255'],
                'processed_at' => ['nullable', 'date'],
            ];
        }
    }
}
