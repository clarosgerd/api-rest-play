<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTournamentGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'group_order' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'in:pending,approved,rejected'],
        ];
    }
}
