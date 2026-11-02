<?php

namespace App\Http\Requests\Team;

use App\Http\Requests\ApiRequest;

class AdminInvitePlayerRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled via 'auth:sanctum' + 'admin' middleware
    }

    public function rules(): array
    {
        return [
            'player_stat_id' => 'required|integer|exists:player_stats,id',
            'message'        => 'nullable|string|max:500',
        ];
    }
}
