<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreGroupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'is_public' => ['required', 'boolean'],
            'invitation_code' => ['nullable', 'string', 'max:10'],
            'max_members' => ['required', 'integer', 'min:2'],
            'bet_amount' => ['nullable', 'numeric', 'min:0'],
            'prize_1st_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'prize_2nd_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'prize_3rd_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pool_active' => ['nullable', 'boolean'],
            'exact_score_points' => ['nullable', 'integer', 'min:0'],
            'match_result_points' => ['nullable', 'integer', 'min:0'],
            'correct_goal_points' => ['nullable', 'integer', 'min:0'],
            'unique_exact_points' => ['nullable', 'integer', 'min:0'],
            'multiplier_dieciseisavos' => ['nullable', 'numeric', 'min:1'],
            'multiplier_octavos' => ['nullable', 'numeric', 'min:1'],
            'multiplier_cuartos' => ['nullable', 'numeric', 'min:1'],
            'multiplier_semifinal' => ['nullable', 'numeric', 'min:1'],
            'multiplier_final' => ['nullable', 'numeric', 'min:1'],
            'created_at' => ['nullable'],
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'is_public' => $this->is_public,
            'created_at' => Carbon::now(),
            'invitation_code' => Str::upper(Str::random(7)),
        ]);
    }
}
