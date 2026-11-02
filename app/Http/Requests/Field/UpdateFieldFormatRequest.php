<?php

namespace App\Http\Requests\Field;

use App\Http\Requests\ApiRequest;

class UpdateFieldFormatRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => ['sometimes', 'string', 'max:50'],
            'players_per_team' => ['sometimes', 'integer', 'min:1'],
            'description'      => ['nullable', 'string'],
        ];
    }
}
