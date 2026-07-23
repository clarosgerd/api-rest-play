<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePredictionResultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prediction_id' => ['required', 'integer', 'exists:predictions,prediction_id'],
            'points_earned' => ['required', 'numeric', 'min:0'],
            'processed_at' => ['nullable', 'date'],
        ];
    }
}
