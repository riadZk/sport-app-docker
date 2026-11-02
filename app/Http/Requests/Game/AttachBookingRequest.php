<?php

namespace App\Http\Requests\Game;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class AttachBookingRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Option A — attach an existing booking (must be owned by the user,
            // verified in the controller/service).
            'booking_id' => ['sometimes', 'nullable', 'integer', 'exists:bookings,id'],

            // Option B — create a booking through the existing booking system.
            'field_id'     => ['required_without:booking_id', 'integer', 'exists:fields,id'],
            'booking_date' => ['required_without:booking_id', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time'   => ['required_without:booking_id', 'date_format:H:i'],
            'end_time'     => ['required_without:booking_id', 'date_format:H:i', 'after:start_time'],
            'total_price'  => ['required_without:booking_id', 'numeric', 'min:0'],
            'note'         => ['nullable', 'string', 'max:1000'],
        ];
    }
}
