<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkStoreMatchesRequest extends FormRequest
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

            '*.home_team_id' => ['required', 'integer'],
            '*.away_team_id' => ['required', 'integer'],
            '*.tournament_id' => ['required'],
            '*.stage_id' => ['required'],
            '*.match_date' => ['required'],
            '*.status' => ['required', Rule::in(['scheduled', 'live', 'finished', 'canceled'])],
            '*.home_score' => ['required'],
            '*.away_score' => ['required'],

        ];
    }

    protected function prepareForValidation()
    {

        $data = [];
        foreach ($this->toArray() as $obj) {
            $obj['home_team_id'] = $obj['home_team_id'];
            $obj['away_team_id'] = $obj['away_team_id'] ?? null;
            $obj['tournament_id'] = $obj['tournament_id'] ?? null;
            $obj['stage_id'] = $obj['stage_id'];
            $obj['match_date'] = $obj['match_date'];
            $obj['status'] = $obj['status'];
            $obj['home_score'] = $obj['home_score'];
            $obj['away_score'] = $obj['away_score'];
            $data[] = $obj;
        }
        // dd($data);
        $this->merge($data);
    }
}
