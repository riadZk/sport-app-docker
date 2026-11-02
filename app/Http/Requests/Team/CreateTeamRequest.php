<?php

namespace App\Http\Requests\Team;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class CreateTeamRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
             'sport_category_id' => [
               'required',
               'integer',
                'exists:sport_categories,id',
               Rule::unique('teams')->where(fn ($q) => $q->where('user_id', $this->user()->id)),
              ],

            'name'            => 'required|string|max:255',
            'short_name'      => 'required|string|max:10',
            'city'            => 'required|string|max:100',
            'address'         => 'nullable|string|max:255',
            'primary_color'   => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'secondary_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'match_type'      => 'required|in:5v5,6v6,7v7,8v8,9v9,11v11',
            'formation'       => 'required|string|max:20',

            // Logo — one of the two must be provided
            'logo_type'       => 'required|in:default,upload',
            'default_logo_id' => 'nullable|string|max:255',
            'logo'            => 'required_if:logo_type,upload|nullable|image|mimes:jpeg,png,jpg,webp|max:2048',

            // Optional: invite players during team creation
            'invitation_players'                  => 'nullable|array',
            'invitation_players.*.player_stat_id' => 'required|integer|exists:player_stats,id',
            'invitation_message'                  => 'nullable|string|max:500',
        ];
    }
}
