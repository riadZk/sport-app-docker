<?php

namespace App\Http\Requests\User;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class RequestPhoneChangeRequest extends ApiRequest
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
     */
    public function rules(): array
    {
        return [
            'new_phone' => [
                'required',
                'string',
                'max:20',
                'regex:/^\+?[1-9]\d{6,14}$/',
                Rule::unique('users', 'phone_number'),
                function ($attribute, $value, $fail) {
                    if ($this->user()->phone_number === $value) {
                        $fail('The new phone number must be different from your current phone number.');
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'new_phone.required' => 'A new phone number is required.',
            'new_phone.regex' => 'Please enter a valid phone number (e.g., +1234567890).',
            'new_phone.unique' => 'This phone number is already registered to another account.',
        ];
    }
}
