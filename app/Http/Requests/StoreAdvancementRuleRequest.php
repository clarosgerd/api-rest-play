<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdvancementRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'next_stage_id' => ['required', 'integer', 'exists:tournamentstage,stage_id'],
            'rule_type' => ['required', 'string', 'in:top_n,best_third,manual'],
            'rule_value' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
