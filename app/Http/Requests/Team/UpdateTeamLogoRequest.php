<?php

namespace App\Http\Requests\Team;

use App\Http\Requests\ApiRequest;

class UpdateTeamLogoRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'logo_type'       => 'required|in:default,upload',
            'default_logo_id' => 'nullable|string|max:255',
            'logo'            => 'required_if:logo_type,upload|nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ];
    }
}
