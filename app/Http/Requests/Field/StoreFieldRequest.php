<?php

namespace App\Http\Requests\Field;

use App\Http\Requests\ApiRequest;

class StoreFieldRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'venue_id'        => ['required', 'exists:venues,id'],
            'field_format_id' => ['required', 'exists:field_formats,id'],
            'sport_category_id' => ['nullable', 'exists:sport_categories,id'],
            'name'            => ['required', 'string', 'max:100'],
            'surface_type'    => ['required', 'in:grass,artificial,futsal'],
            'width_m'         => ['nullable', 'numeric', 'min:0'],
            'length_m'        => ['nullable', 'numeric', 'min:0'],
            'is_indoor'       => ['sometimes', 'boolean'],
            'is_active'       => ['sometimes', 'boolean'],
            'cover'           => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'gallery'         => ['nullable', 'array', 'max:10'],
            'gallery.*'       => ['image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'images'          => ['nullable', 'array', 'max:10'],
            'images.*'        => ['image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ];
    }
}
