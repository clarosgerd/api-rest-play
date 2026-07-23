<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTournamentStageRequest extends FormRequest
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
                'tournament_id' => ['required'],
                'name' => ['required'],
                'stage_order' => ['required'],
                'is_knockout' => ['required'],
            ];
        } else {
            return [
                'tournament_id' => ['sometimes', 'required'],
                'name' => ['sometimes', 'required'],
                'stage_order' => ['sometimes', 'required'],
                'is_knockout' => ['sometimes', 'required'],
            ];
        }
    }

    protected function prepareForValidation()
    {

        $this->merge([

            'is_knockout' => $this->is_knockout,

        ]);
    }
}
