<?php

namespace App\Http\Requests\Game;

use App\Http\Requests\ApiRequest;

class ChallengeRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Challenge a permanent team (its owner will be notified).
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],

            // …or challenge a USER, who then builds a temporary side by
            // picking their players when accepting.
            'user_id' => ['nullable', 'integer', 'exists:users,id'],

            'message' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $hasTeam = $this->filled('team_id');
            $hasUser = $this->filled('user_id');

            if ($hasTeam === $hasUser) {
                $validator->errors()->add('team_id', 'Provide either team_id or user_id.');
            }
        });
    }
}
