<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class SignupRequest extends ApiRequest
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
            // User fields
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255', Rule::unique('users', 'username')->whereNull('deleted_at')],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'phone_number' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone_number')->whereNull('deleted_at'), 'regex:/^\+?[1-9]\d{6,14}$/'],
            'cin' => ['nullable', 'string', Rule::unique('users', 'cin')->whereNull('deleted_at')],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'in:male,female'],
            'birthday' => ['nullable', 'date'],

            // Player stats fields
            'primary_position' => ['required', 'string', 'max:255'],
            'secondary_position' => ['nullable', 'string', 'max:255'],
            'prefered_foot' => ['nullable', 'string', 'in:left,right,both'],
            'match_played' => ['nullable', 'integer', 'min:0'],
            'nbr_goals' => ['nullable', 'integer', 'min:0'],
            'pac' => ['nullable', 'integer', 'min:0', 'max:100'],
            'dribble' => ['nullable', 'integer', 'min:0', 'max:100'],
            'physique' => ['nullable', 'integer', 'min:0', 'max:100'],
            'shoot' => ['nullable', 'integer', 'min:0', 'max:100'],
            'pass' => ['nullable', 'integer', 'min:0', 'max:100'],
            'assists' => ['nullable', 'integer', 'min:0'],
            'defence' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',
            'phone_number.unique' => 'This phone number is already registered.',
            'phone_number.regex' => 'Please enter a valid phone number (e.g., +1234567890).',
            'username.unique' => 'This username is already taken.',
            'cin.unique' => 'This CIN is already registered.',
            'gender.in' => 'Gender must be either male or female.',
            'primary_position.required' => 'Primary position is required.',
            'prefered_foot.in' => 'Preferred foot must be left, right, or both.',
        ];
    }
}
