<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePredictionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'match_id' => ['required', 'integer', 'exists:matches,match_id'],
            'group_id' => ['required', 'integer', 'exists:groups,group_id'],
            'predicted_home_score' => ['required', 'integer', 'min:0'],
            'predicted_away_score' => ['required', 'integer', 'min:0'],
            'predicted_result' => ['required', 'string', 'in:home_win,away_win,draw'],
            'bet_type' => ['required', 'string', 'in:exact_score,match_result,both_teams_score'],
            'confidence' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }
}
