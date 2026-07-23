<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddTeamsTournamentGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'team_ids' => ['required', 'array', 'min:1'],
            'team_ids.*' => ['integer', 'exists:teams,team_id'],
        ];
    }
}
