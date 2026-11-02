<?php

namespace App\Http\Requests\Venue;

use App\Http\Requests\ApiRequest;

class StoreVenueClosureRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date'],
            'ends_at'   => ['required', 'date', 'after:starts_at'],
            'reason'    => ['nullable', 'string', 'max:255'],
        ];
    }
}
