<?php

namespace App\Http\Requests\DashboardAdmin;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class UpdatePlayerRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            // User fields
            'first_name'         => ['sometimes', 'string', 'max:255'],
            'last_name'          => ['sometimes', 'string', 'max:255'],
            'username'           => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('users', 'username')->ignore($userId)->whereNull('deleted_at')],
            'email'              => ['sometimes', 'nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)->whereNull('deleted_at')],
            'phone_number'       => ['sometimes', 'nullable', 'string', 'max:20', Rule::unique('users', 'phone_number')->ignore($userId)->whereNull('deleted_at'), 'regex:/^\+?[1-9]\d{6,14}$/'],
            'cin'                => ['sometimes', 'nullable', 'string', Rule::unique('users', 'cin')->ignore($userId)->whereNull('deleted_at')],
            'city'               => ['sometimes', 'nullable', 'string', 'max:255'],
            'country'            => ['sometimes', 'nullable', 'string', 'max:255'],
            'gender'             => ['sometimes', 'nullable', 'string', 'in:male,female'],
            'birthday'           => ['sometimes', 'nullable', 'date'],

            // Player stats fields
            'primary_position'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'secondary_position' => ['sometimes', 'nullable', 'string', 'max:255'],
            'prefered_foot'      => ['sometimes', 'nullable', 'string', 'in:left,right,both'],
            'match_played'       => ['sometimes', 'nullable', 'integer', 'min:0'],
            'nbr_goals'          => ['sometimes', 'nullable', 'integer', 'min:0'],
            'pac'                => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'dribble'            => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'physique'           => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'shoot'              => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'pass'               => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'assists'            => ['sometimes', 'nullable', 'integer', 'min:0'],
            'defence'            => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
