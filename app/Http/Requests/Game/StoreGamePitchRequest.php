<?php

namespace App\Http\Requests\Game;

use App\Http\Requests\ApiRequest;

class StoreGamePitchRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Step 1 — manual pitch. user_id is NEVER accepted from input.
            'name'      => ['required', 'string', 'max:255'],
            'address'   => ['required', 'string', 'max:1000'],
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
