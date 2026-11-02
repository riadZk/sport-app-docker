<?php

namespace App\Http\Requests\DashboardAdmin;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class CreatePlayerRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // User fields
            'first_name'         => ['required', 'string', 'max:255'],
            'last_name'          => ['required', 'string', 'max:255'],
            'username'           => ['nullable', 'string', 'max:255', Rule::unique('users', 'username')->whereNull('deleted_at')],
            'email'              => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'phone_number'       => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone_number')->whereNull('deleted_at'), 'regex:/^\+?[1-9]\d{6,14}$/'],
            'cin'                => ['nullable', 'string', Rule::unique('users', 'cin')->whereNull('deleted_at')],
            'city'               => ['nullable', 'string', 'max:255'],
            'country'            => ['nullable', 'string', 'max:255'],
            'gender'             => ['nullable', 'string', 'in:male,female'],
            'birthday'           => ['nullable', 'date'],
            'picture'            => ['nullable', 'image', 'max:2048'],

            // Player stats fields
            'primary_position'   => ['required', 'string', 'max:255'],
            'secondary_position' => ['nullable', 'string', 'max:255'],
            'prefered_foot'      => ['nullable', 'string', 'in:left,right,both'],
            'match_played'       => ['nullable', 'integer', 'min:0'],
            'nbr_goals'          => ['nullable', 'integer', 'min:0'],
            'pac'                => ['nullable', 'integer', 'min:0', 'max:100'],
            'dribble'            => ['nullable', 'integer', 'min:0', 'max:100'],
            'physique'           => ['nullable', 'integer', 'min:0', 'max:100'],
            'shoot'              => ['nullable', 'integer', 'min:0', 'max:100'],
            'pass'               => ['nullable', 'integer', 'min:0', 'max:100'],
            'assists'            => ['nullable', 'integer', 'min:0'],
            'defence'            => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
