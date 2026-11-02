<?php

namespace App\Http\Requests\Team;

use App\Http\Requests\ApiRequest;

class RequestJoinTeamRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is enforced in the controller
    }

    public function rules(): array
    {
        return [
            'player_stat_id' => 'required|integer|exists:player_stats,id',
            'message'        => 'nullable|string|max:500',
        ];
    }
}
