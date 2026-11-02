<?php

namespace App\Http\Requests\Team;

use App\Http\Requests\ApiRequest;

class UpdateTeamRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => 'sometimes|string|max:255',
            'short_name'      => 'sometimes|string|max:10',
            'city'            => 'sometimes|string|max:100',
            'address'         => 'sometimes|nullable|string|max:255',
            'primary_color'   => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'match_type'      => 'sometimes|in:5v5,6v6,7v7,8v8,9v9,11v11',
            'formation'       => 'sometimes|string|max:20',
        ];
    }
}
