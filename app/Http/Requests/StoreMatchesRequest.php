<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMatchesRequest extends FormRequest
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
            'home_team_id' => ['required'],
            'away_team_id' => ['required'],
            'tournament_id' => ['required'],
            'stage_id' => ['required'],
            'match_date' => ['required'],
            'status' => ['required', Rule::in(['scheduled', 'live', 'finished', 'canceled'])],
            'home_score' => ['required'],
            'away_score' => ['required'],
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'tournament_id' => $this->tournament_id,
            'stage_id' => $this->stage_id,
            'home_team_id' => $this->home_team_id,
            'away_team_id' => $this->away_team_id,
        ]);

    }
}
