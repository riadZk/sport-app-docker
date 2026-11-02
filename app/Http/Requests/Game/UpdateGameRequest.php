<?php

namespace App\Http\Requests\Game;

use App\Http\Requests\ApiRequest;

class UpdateGameRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'game_pitch_id' => ['sometimes', 'nullable', 'integer', 'exists:game_pitches,id'],
            'booking_id'    => ['sometimes', 'nullable', 'integer', 'exists:bookings,id'],

            // Re-choose how to play (same exclusive options as creation).
            'home_team_id'   => ['sometimes', 'nullable', 'integer', 'exists:teams,id'],
            'home_players'   => ['sometimes', 'array', 'min:1'],
            'home_players.*' => ['integer', 'distinct', 'exists:player_stats,id'],
            'home_team_name' => ['sometimes', 'nullable', 'string', 'max:255'],

            // Self-organized match: the away side can also be rebuilt here
            // while no accepted challenge has assigned it (checked in service).
            'away_team_id'   => ['sometimes', 'nullable', 'integer', 'exists:teams,id'],
            'away_players'   => ['sometimes', 'array', 'min:1'],
            'away_players.*' => ['integer', 'distinct', 'exists:player_stats,id'],
            'away_team_name' => ['sometimes', 'nullable', 'string', 'max:255'],

            'message'        => ['sometimes', 'nullable', 'string', 'max:500'],

            'game_date'     => ['sometimes', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time'    => ['sometimes', 'date_format:H:i'],
            'duration'      => ['sometimes', 'integer', 'min:1', 'max:300'],
            'game_type'     => ['sometimes', 'in:5v5,6v6,7v7,8v8,9v9,11v11'],
            'gender'        => ['sometimes', 'in:male,female,mixed'],
            'privacy'       => ['sometimes', 'in:public,private'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->exists('home_team_id') && $this->exists('home_players')) {
                $validator->errors()->add('home_team_id', 'Choose either a team or players, not both.');
            }

            // Away side: same exclusivity as the home side.
            if ($this->exists('away_team_id') && $this->exists('away_players')) {
                $validator->errors()->add('away_team_id', 'Choose either a team or players for the away side, not both.');
            }
        });
    }
}
