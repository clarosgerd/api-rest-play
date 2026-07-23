<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGroupMembersRequest extends FormRequest
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
            'group_id' => ['required'],
            'user_id' => ['required'],
            'joined_at' => ['nullable'],
            'is_admin' => ['required'],
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge(['is_admin' => $this->is_admin]);
        // $this->merge(['created_at' => Carbon::now()]);
        // $this->merge(['invitation_code' =>Str::upper(Str::random(7))]);
    }
}
