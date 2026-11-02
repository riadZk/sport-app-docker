<?php

namespace App\Http\Requests\Venue;

use App\Http\Requests\ApiRequest;

class StoreVenueAvailabilityRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'availabilities'                => ['required', 'array', 'min:1'],
            'availabilities.*.day_of_week'  => ['required', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'availabilities.*.start_time'   => ['required', 'date_format:H:i'],
            'availabilities.*.end_time'     => ['required', 'date_format:H:i', 'after:availabilities.*.start_time'],
            'availabilities.*.valid_from'   => ['nullable', 'date'],
            'availabilities.*.valid_to'     => ['nullable', 'date', 'after_or_equal:availabilities.*.valid_from'],
            'availabilities.*.is_active'    => ['sometimes', 'boolean'],
        ];
    }
}
