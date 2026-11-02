<?php

namespace App\Http\Requests\Booking;

use App\Http\Requests\ApiRequest;

class StoreBookingRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Booking fields
            'field_id'       => ['required', 'integer', 'exists:fields,id'],
            'booking_date'   => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time'     => ['required', 'date_format:H:i'],
            'end_time'       => ['required', 'date_format:H:i', 'after:start_time'],
            'total_price'    => ['required', 'numeric', 'min:0'],
            'note'           => ['nullable', 'string', 'max:1000'],

            // Contact
            'contact'                 => ['required', 'array'],
            'contact.full_name'       => ['required', 'string', 'max:150'],
            'contact.email'           => ['nullable', 'email', 'max:150'],
            'contact.phone'           => ['required', 'string', 'max:30'],
            'contact.national_id'     => ['nullable', 'string', 'max:50'],
        ];
    }
}
