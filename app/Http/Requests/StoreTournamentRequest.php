<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'season' => ['required', 'string'],
            'country' => ['required', 'string'],
            'country_code' => ['required', 'string', 'max:5'],
            'logo_url' => ['nullable', 'url'],
            'flag_url' => ['nullable', 'url'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'actived' => ['required'],
            'status' => ['nullable', 'string', 'in:draft,published,live,closed'],
            'is_promotional' => ['nullable', 'boolean'],
            'promotional_tier_required' => ['nullable', 'integer', 'in:0,1,2'],
            'promotional_ends_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge(['active' => $this->actived]);
    }
}
