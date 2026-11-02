<?php

namespace App\Http\Requests\Game;

use App\Http\Requests\ApiRequest;
use App\Models\GamePrediction;
use Illuminate\Validation\Rule;

class StoreGamePredictionRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prediction' => ['required', Rule::in(GamePrediction::PREDICTIONS)],
        ];
    }
}
