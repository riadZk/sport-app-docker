<?php

namespace App\Http\Requests\Venue;

use App\Http\Requests\ApiRequest;

class UpdateVenueReviewRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating'  => ['sometimes', 'integer', 'min:1', 'max:5'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
