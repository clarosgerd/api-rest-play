<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMatchesRequest extends FormRequest
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
        // dd($method);
        if ($method == 'PUT') {
            return [
                'home_team_id' => ['required'],
                'away_team_id' => ['required'],
                'tournament_id' => ['required'],
                'stage_id' => ['required'],
                'match_date' => ['required'],
                'status' => ['required', Rule::in(['scheduled', 'live', 'finished', 'canceled'])],
                'home_score' => ['required'],
                'away_score' => ['required'],

            ];
        } else {
            return [
                'home_team_id' => ['sometimes', 'required'],
                'away_team_id' => ['sometimes', 'required'],
                'tournament_id' => ['sometimes', 'required'],
                'stage_id' => ['sometimes', 'required'],
                'match_date' => ['sometimes', 'required'],
                'status' => ['sometimes', 'required', Rule::in(['scheduled', 'live', 'finished', 'canceled'])],
                'home_score' => ['sometimes', 'required'],
                'away_score' => ['sometimes', 'required'],
            ];
        }
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'status' => $this->status,
            'home_score' => $this->home_score,
            'away_score' => $this->away_score,
        ]);
        // $this->merge(['home_score' => $this->home_score]);
        // $this->merge(['away_score' => $this->away_score]);
    }
}
