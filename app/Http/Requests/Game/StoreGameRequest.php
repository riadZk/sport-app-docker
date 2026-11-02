<?php

namespace App\Http\Requests\Game;

use App\Http\Requests\ApiRequest;

class StoreGameRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Step 1 — pitch (nullable: user may skip the pitch for now)
            'game_pitch_id' => ['nullable', 'integer', 'exists:game_pitches,id'],
            'booking_id'    => ['nullable', 'integer', 'exists:bookings,id'],

            // STEP 1 — how do you want to play?
            // Option A: "Play with my team" → home_team_id (a permanent team
            // the user is allowed to use — ownership enforced in the service).
            'home_team_id'   => ['nullable', 'integer', 'exists:teams,id'],

            // Option B: "I don't want to play with my team / I want to choose
            // players" → home_players. The system creates the temporary game
            // team automatically (nothing is ever written to `teams`).
            'home_players'   => ['nullable', 'array', 'min:1'],
            'home_players.*' => ['integer', 'distinct', 'exists:player_stats,id'],
            'home_team_name' => ['nullable', 'string', 'max:255'],

            // OPTIONAL — self-organized match: the SAME request may build the
            // AWAY side too (temporary via away_players, or away_team_id).
            // Ownership is enforced in the service: the away side can only be
            // built by the owner while no challenge has assigned it.
            'away_team_id'   => ['nullable', 'integer', 'exists:teams,id'],
            'away_players'   => ['nullable', 'array', 'min:1'],
            'away_players.*' => ['integer', 'distinct', 'exists:player_stats,id'],
            'away_team_name' => ['nullable', 'string', 'max:255'],

            // OPTIONAL — Step 4 in the SAME call: send the challenge to a
            // permanent team (challenge_team_id) or to a user
            // (challenge_user_id). The home side must be chosen; every
            // challenge rule (no self-challenge, ownership) is enforced by
            // GameService::challenge() inside the same transaction.
            'challenge_team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'challenge_user_id' => ['nullable', 'integer', 'exists:users,id'],

            'message'        => ['nullable', 'string', 'max:500'],

            // Step 2 — settings
            'game_date'     => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time'    => ['required', 'date_format:H:i'],
            'duration'      => ['required', 'integer', 'min:1', 'max:300'],
            'game_type'     => ['required', 'in:5v5,6v6,7v7,8v8,9v9,11v11'],
            'gender'        => ['required', 'in:male,female,mixed'],
            'privacy'       => ['required', 'in:public,private'],
        ];
    }

    /**
     * Each side must choose exactly ONE way to play: an existing team OR
     * individually picked players. The home side is mandatory; the away side
     * is optional (self-organized matches).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $hasTeam = $this->filled('home_team_id');
            $hasPlayers = $this->has('home_players') && count(array_filter((array) $this->input('home_players'), fn($v) => $v !== null && $v !== '')) > 0;

            if ($hasTeam && $hasPlayers) {
                $validator->errors()->add('home_team_id', 'Choose either a team or players, not both.');
            }

            if (! $hasTeam && ! $hasPlayers) {
                $validator->errors()->add('home_team_id', 'Choose a team to play with or select players.');
            }

            // Away side (optional): same exclusivity as the home side.
            $hasAwayTeam = $this->filled('away_team_id');
            $hasAwayPlayers = $this->has('away_players') && count(array_filter((array) $this->input('away_players'), fn($v) => $v !== null && $v !== '')) > 0;

            if ($hasAwayTeam && $hasAwayPlayers) {
                $validator->errors()->add('away_team_id', 'Choose either a team or players for the away side, not both.');
            }

            // Challenge (optional): team XOR user — and never together with an
            // owner-built away side (the challenge hands away to the opponent).
            $hasChallenge = $this->filled('challenge_team_id') || $this->filled('challenge_user_id');

            if ($this->filled('challenge_team_id') && $this->filled('challenge_user_id')) {
                $validator->errors()->add('challenge_team_id', 'Challenge either a team or a user, not both.');
            }

            if ($hasChallenge && ($hasAwayTeam || $hasAwayPlayers)) {
                $validator->errors()->add('challenge_team_id', 'Remove the away side fields — the challenge lets the opponent build the away side.');
            }
        });
    }
}
