<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'first_name' => 'sometimes|string|max:255',
            'last_name'  => 'sometimes|string|max:255',
            'username'   => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('users', 'username')->ignore($userId)],
            'email'      => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'city'       => 'sometimes|nullable|string|max:255',
            'country'    => 'sometimes|nullable|string|max:255',
            'gender'     => 'sometimes|nullable|in:male,female,other',
            'birthday'   => 'sometimes|nullable|date|before:today',
            'cin'        => 'sometimes|nullable|string|max:50',
            'height'     => 'sometimes|nullable|numeric|min:50|max:300',
            'weight'     => 'sometimes|nullable|numeric|min:20|max:500',
        ];
    }
}
