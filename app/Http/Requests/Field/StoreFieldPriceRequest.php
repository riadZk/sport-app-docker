<?php

namespace App\Http\Requests\Field;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class StoreFieldPriceRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pricing_type' => ['required', Rule::in(['hourly', 'daily'])],

            'day_of_week' => [
                'nullable',
                Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
            ],

            'start_time' => ['nullable', 'date_format:H:i', 'required_with:end_time'],
            'end_time'   => ['nullable', 'date_format:H:i', 'required_with:start_time', 'after:start_time'],

            'price'    => ['required', 'numeric', 'min:0'],
            'priority' => ['sometimes', 'integer', 'min:1'],

            'valid_from' => ['nullable', 'date', 'required_with:valid_to'],
            'valid_to'   => ['nullable', 'date', 'required_with:valid_from', 'after_or_equal:valid_from'],

            'min_booking_hours' => ['nullable', 'integer', 'min:1'],
            'max_booking_hours' => [
                'nullable',
                'integer',
                'min:1',
                'gte:min_booking_hours',
            ],

            'slot_duration' => ['sometimes', 'integer', 'min:15', Rule::in([15, 30, 45, 60, 90, 120])],
            'is_active'     => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_time.after'                    => 'The end time must be after the start time.',
            'valid_to.after_or_equal'           => 'The valid_to date must be on or after valid_from.',
            'max_booking_hours.gte'             => 'The max booking hours must be greater than or equal to min booking hours.',
            'start_time.required_with'          => 'start_time is required when end_time is provided.',
            'end_time.required_with'            => 'end_time is required when start_time is provided.',
            'valid_from.required_with'          => 'valid_from is required when valid_to is provided.',
            'valid_to.required_with'            => 'valid_to is required when valid_from is provided.',
            'slot_duration.in'                  => 'Slot duration must be one of: 15, 30, 45, 60, 90, 120 minutes.',
        ];
    }
}
