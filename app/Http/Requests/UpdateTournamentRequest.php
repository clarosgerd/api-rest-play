<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTournamentRequest extends FormRequest
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
                'name' => ['required', 'string', 'max:255'],
                'season' => ['required', 'string'],
                'country' => ['required', 'string'],
                'country_code' => ['required', 'string', 'max:5'],
                'logo_url' => ['required', 'url'],
                'flag_url' => ['required', 'url'],
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after_or_equal:start_date'],
                'actived' => ['required'],
                'is_promotional' => ['nullable', 'boolean'],
                'promotional_tier_required' => ['nullable', 'integer', 'in:0,1,2'],
                'promotional_ends_at' => ['nullable', 'date'],
            ];
        } else {
            return [
                'name' => ['sometimes', 'required', 'string', 'max:255'],
                'season' => ['sometimes', 'required', 'string'],
                'country' => ['sometimes', 'required', 'string'],
                'country_code' => ['sometimes', 'required', 'string', 'max:5'],
                'logo_url' => ['sometimes', 'required', 'url'],
                'flag_url' => ['sometimes', 'required', 'url'],
                'start_date' => ['sometimes', 'required', 'date'],
                'end_date' => ['sometimes', 'required', 'date'],
                'actived' => ['sometimes', 'required'],
                'is_promotional' => ['sometimes', 'boolean'],
                'promotional_tier_required' => ['sometimes', 'integer', 'in:0,1,2'],
                'promotional_ends_at' => ['nullable', 'date'],
            ];
        }
    }

    protected function prepareForValidation()
    {
        if ($this->actived) {
            $this->merge(['active' => $this->actived]);
        }
    }
}
