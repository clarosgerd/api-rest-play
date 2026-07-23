<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGroupPredictionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'group_id' => ['required', 'integer', 'exists:groups,group_id'],
            'prediction_id' => ['required', 'integer', 'exists:predictions,prediction_id'],
            'points_awarded' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
