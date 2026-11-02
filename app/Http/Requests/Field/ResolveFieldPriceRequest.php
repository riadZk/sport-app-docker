<?php

namespace App\Http\Requests\Field;

use App\Http\Requests\ApiRequest;

class ResolveFieldPriceRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date'       => ['required', 'date_format:Y-m-d', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time'   => ['required', 'date_format:H:i', 'after:start_time'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.after_or_equal'  => 'The booking date must be today or in the future.',
            'end_time.after'       => 'The end time must be after the start time.',
        ];
    }
}
