<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePredictionsRequest extends FormRequest
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
                'match_id' => ['required', 'integer', 'exists:matches,match_id'],
                'predicted_home_score' => ['required', 'integer', 'min:0'],
                'predicted_away_score' => ['required', 'integer', 'min:0'],
                'predicted_result' => ['required', 'string', 'in:home_win,away_win,draw'],
                'bet_type' => ['required', 'string', 'in:exact_score,match_result,both_teams_score'],
                'confidence' => ['required', 'integer', 'min:1', 'max:10'],
            ];
        } else {
            return [
                'match_id' => ['sometimes', 'required', 'integer', 'exists:matches,match_id'],
                'predicted_home_score' => ['sometimes', 'required', 'integer', 'min:0'],
                'predicted_away_score' => ['sometimes', 'required', 'integer', 'min:0'],
                'predicted_result' => ['sometimes', 'required', 'string', 'in:home_win,away_win,draw'],
                'bet_type' => ['sometimes', 'required', 'string', 'in:exact_score,match_result,both_teams_score'],
                'confidence' => ['sometimes', 'required', 'integer', 'min:1', 'max:10'],
            ];
        }
    }
}
