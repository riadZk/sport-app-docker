<?php

namespace App\Http\Requests\Field;

use App\Http\Requests\ApiRequest;

class StoreFieldFormatRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:50'],
            'players_per_team' => ['required', 'integer', 'min:1'],
            'description'      => ['nullable', 'string'],
        ];
    }
}
