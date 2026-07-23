<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionsRequest extends FormRequest
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
            'amount' => ['required', 'numeric'],
            'status' => ['required', 'string', 'in:pending,completed,failed,cancelled'],
            'reference' => ['nullable', 'string', 'max:255'],
            'processed_at' => ['nullable', 'date'],
        ];
    }
}
