<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamRequest extends FormRequest
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
        $method = $this->method();
        if ($method == 'PUT') {
            return [
                'name' => ['required'],
                'country' => ['required'],
                'league' => ['required'],
                'logo_url' => ['required'],
                'flag_url' => ['required'],
                'country_code' => ['required'],
                'active' => ['required'],
            ];
        } else {
            return [
                'name' => ['sometimes', 'required'],
                'country' => ['sometimes', 'required'],
                'league' => ['sometimes', 'required'],
                'logo_url' => ['sometimes', 'required'],
                'flag_url' => ['sometimes', 'required'],
                'country_code' => ['sometimes', 'required'],
                'active' => ['sometimes', 'required'],
            ];
        }
    }

    protected function prepareForValidation()
    {
        $this->merge(['active' => $this->active]);
    }
}
