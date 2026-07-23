<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroupMembersRequest extends FormRequest
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
                'group_id' => ['required'],
                'user_id' => ['required'],
                'joined_at' => ['required'],
                'is_admin' => ['nullable'],
            ];
        } else {
            return [

                'group_id' => ['sometimes', 'required'],
                'user_id' => ['sometimes', 'required'],
                'joined_at' => ['sometimes', 'required'],
                'is_admin' => ['sometimes', 'nullable'],
            ];
        }
    }

    protected function prepareForValidation()
    {
        $this->merge(['is_admin' => $this->is_admin]);
        // $this->merge(['created_at' => Carbon::now()]);
        // $this->merge(['invitation_code' =>Str::upper(Str::random(7))]);
    }
}
