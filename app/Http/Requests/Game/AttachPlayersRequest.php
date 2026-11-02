<?php

namespace App\Http\Requests\Game;

use App\Http\Requests\ApiRequest;
use App\Models\GameTeam;

class AttachPlayersRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Which side to build. The caller must own that side:
            //   - "home" → the game owner (User A)
            //   - "away" → the game owner building both sides himself,
            //              OR the challenged opponent after acceptance.
            'side'              => ['required', 'string', 'in:' . GameTeam::SIDE_HOME . ',' . GameTeam::SIDE_AWAY],

            // "I want to choose players" for the requested side.
            'player_stat_ids'   => ['required', 'array', 'min:1'],
            'player_stat_ids.*' => ['integer', 'distinct', 'exists:player_stats,id'],
            'name'              => ['nullable', 'string', 'max:255'],
            'message'           => ['nullable', 'string', 'max:500'],
        ];
    }
}
