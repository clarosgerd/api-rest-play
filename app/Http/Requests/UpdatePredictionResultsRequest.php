<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePredictionResultsRequest extends FormRequest
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
                'prediction_id' => ['required', 'integer', 'exists:predictions,prediction_id'],
                'points_earned' => ['required', 'numeric', 'min:0'],
                'processed_at' => ['nullable', 'date'],
            ];
        } else {
            return [
                'prediction_id' => ['sometimes', 'required', 'integer', 'exists:predictions,prediction_id'],
                'points_earned' => ['sometimes', 'required', 'numeric', 'min:0'],
                'processed_at' => ['nullable', 'date'],
            ];
        }
    }
}
