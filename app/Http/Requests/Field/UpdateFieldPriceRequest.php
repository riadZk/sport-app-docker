<?php

namespace App\Http\Requests\Field;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class UpdateFieldPriceRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pricing_type' => ['sometimes', Rule::in(['hourly', 'daily'])],

            'day_of_week' => [
                'sometimes',
                'nullable',
                Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
            ],

            'start_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'end_time'   => ['sometimes', 'nullable', 'date_format:H:i', 'after:start_time'],

            'price'    => ['sometimes', 'numeric', 'min:0'],
            'priority' => ['sometimes', 'integer', 'min:1'],

            'valid_from' => ['sometimes', 'nullable', 'date'],
            'valid_to'   => ['sometimes', 'nullable', 'date', 'after_or_equal:valid_from'],

            'min_booking_hours' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_booking_hours' => [
                'sometimes',
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
            'end_time.after'          => 'The end time must be after the start time.',
            'valid_to.after_or_equal' => 'The valid_to date must be on or after valid_from.',
            'max_booking_hours.gte'   => 'The max booking hours must be greater than or equal to min booking hours.',
            'slot_duration.in'        => 'Slot duration must be one of: 15, 30, 45, 60, 90, 120 minutes.',
        ];
    }
}
