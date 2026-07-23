<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateGroupsRequest extends FormRequest
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
                'description' => ['required'],
                'creator_id' => ['required'],
                'is_public' => ['required'],
                'invitation_code' => ['nullable'],
                'max_members' => ['required'],
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
        } else {
            return [
                'name' => ['sometimes', 'required'],
                'description' => ['sometimes', 'required'],
                'creator_id' => ['sometimes', 'required'],
                'is_public' => ['sometimes', 'required'],
                'invitation_code' => ['sometimes', 'nullable'],
                'max_members' => ['sometimes', 'required'],
                'bet_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'prize_1st_pct' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
                'prize_2nd_pct' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
                'prize_3rd_pct' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
                'pool_active' => ['sometimes', 'nullable', 'boolean'],
                'exact_score_points' => ['sometimes', 'nullable', 'integer', 'min:0'],
                'match_result_points' => ['sometimes', 'nullable', 'integer', 'min:0'],
                'correct_goal_points' => ['sometimes', 'nullable', 'integer', 'min:0'],
                'unique_exact_points' => ['sometimes', 'nullable', 'integer', 'min:0'],
                'multiplier_dieciseisavos' => ['sometimes', 'nullable', 'numeric', 'min:1'],
                'multiplier_octavos' => ['sometimes', 'nullable', 'numeric', 'min:1'],
                'multiplier_cuartos' => ['sometimes', 'nullable', 'numeric', 'min:1'],
                'multiplier_semifinal' => ['sometimes', 'nullable', 'numeric', 'min:1'],
                'multiplier_final' => ['sometimes', 'nullable', 'numeric', 'min:1'],
                'created_at' => ['sometimes', 'nullable'],
            ];
        }
    }

    protected function prepareForValidation()
    {
        $this->merge(['is_public' => $this->is_public]);
        // $this->merge(['created_at' => Carbon::now()]);
        // $this->merge(['invitation_code' =>Str::upper(Str::random(7))]);
    }
}
