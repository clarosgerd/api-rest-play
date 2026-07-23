<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkStoreTeamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            '*.name' => ['required'],
            '*.country' => ['required'],
            '*.league' => ['required'],
            '*.logo_url' => ['required'],
            '*.flag_url' => ['required'],
            '*.country_code' => ['required'],
            '*.active' => ['required', Rule::in(['0', '1'])],

        ];
    }

    protected function prepareForValidation()
    {

        $data = [];
        foreach ($this->toArray() as $obj) {
            $obj['name'] = $obj['name'] ?? null;
            $obj['country'] = $obj['country'] ?? null;
            $obj['league'] = $obj['league'] ?? null;
            $obj['logo_url'] = $obj['logo_url'] ?? null;
            $obj['flag_url'] = $obj['flag_url'] ?? null;
            $obj['country_code'] = $obj['country_code'];
            $obj['active'] = $obj['active'];
            $data[] = $obj;
        }
        // dd($data);
        $this->merge($data);
    }
}
