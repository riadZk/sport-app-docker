<?php

namespace App\Http\Requests\Team;

use App\Http\Requests\ApiRequest;

class AdminBulkInvitePlayerRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled via 'auth:sanctum' + 'admin' middleware
    }

    public function rules(): array
    {
        return [
            'players'                  => 'required|array|min:1',
            'players.*.player_stat_id' => 'required|integer|exists:player_stats,id',
            'message'                  => 'nullable|string|max:500',
        ];
    }
}
