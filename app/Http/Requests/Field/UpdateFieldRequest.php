<?php

namespace App\Http\Requests\Field;

use App\Http\Requests\ApiRequest;

class UpdateFieldRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'venue_id'        => ['sometimes', 'exists:venues,id'],
            'field_format_id' => ['sometimes', 'exists:field_formats,id'],
            'name'            => ['sometimes', 'string', 'max:100'],
            'surface_type'    => ['sometimes', 'in:grass,artificial,futsal'],
            'width_m'         => ['nullable', 'numeric', 'min:0'],
            'length_m'        => ['nullable', 'numeric', 'min:0'],
            'is_indoor'       => ['sometimes', 'boolean'],
            'is_active'       => ['sometimes', 'boolean'],
        ];
    }
}
