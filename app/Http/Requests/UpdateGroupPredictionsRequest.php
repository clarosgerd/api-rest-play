<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroupPredictionsRequest extends FormRequest
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
                'group_id' => ['required', 'integer', 'exists:groups,group_id'],
                'prediction_id' => ['required', 'integer', 'exists:predictions,prediction_id'],
                'points_awarded' => ['nullable', 'numeric', 'min:0'],
            ];
        } else {
            return [
                'group_id' => ['sometimes', 'required', 'integer', 'exists:groups,group_id'],
                'prediction_id' => ['sometimes', 'required', 'integer', 'exists:predictions,prediction_id'],
                'points_awarded' => ['nullable', 'numeric', 'min:0'],
            ];
        }
    }
}
