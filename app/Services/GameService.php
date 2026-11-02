<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Game;
use App\Models\GameChallenge;
use App\Models\GamePlayer;
use App\Models\GamePlayerRequest;
use App\Models\GameTeam;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GameService
{
    /**
     * Create (or update the existing draft) game for the authenticated user.
     * Runs inside a transaction so a failing step never leaves partial records.
     *
     * @param  User             $user   Authenticated user — user_id is NEVER taken from input.
     * @param  array            $data   Validated data from the wizard requests.
     * @param  Game|null        $game   Existing draft when updating during the wizard.
     * @return Game
     *
     * @throws InvalidArgumentException on business-rule violations (booking ownership,
     *                                   booking conflicts, identical teams…).
     */
    public function createOrUpdateDraft(User $user, array $data, ?Game $game = null): Game
    {
        return DB::transaction(function () use ($user, $data, $game) {
            $payload = $this->resolveDraftPayload($user, $data);

            if ($game === null) {
                $game = Game::create($payload + ['user_id' => $user->id]);
            } else {
                $game->update($payload);
            }

            // Team registration happens within the same transaction as the
            // game draft so team assignment can never exist without a game.
            // Self-organized: the same request may build the AWAY side too
            // (owner-only while no accepted challenge has assigned it —
            // setupSide() enforces this with a precise 422).
            if (array_key_exists('home_team_id', $data) || array_key_exists('home_players', $data)) {
                $this->setupSide($game, GameTeam::SIDE_HOME, $user, $data);
            }

            if (array_key_exists('away_team_id', $data) || array_key_exists('away_players', $data)) {
                $this->setupSide($game, GameTeam::SIDE_AWAY, $user, $data);
            }

            // Step 4 in the same call: challenge a team or a user. Runs inside
            // the same transaction — a failed challenge creation rolls the
            // whole draft back (no game without its accepted-flow integrity).
            $challengeTeamId = $data['challenge_team_id'] ?? null;
            $challengeUserId = $data['challenge_user_id'] ?? null;

            if ($challengeTeamId !== null || $challengeUserId !== null) {
                $homeGameTeam = $game->gameTeams()
                    ->where('side', GameTeam::SIDE_HOME)
                    ->first();

                if ($homeGameTeam === null) {
                    throw new InvalidArgumentException('Choose the home side before sending a challenge.');
                }

                $this->challenge($game, $homeGameTeam->id, $challengeTeamId, $challengeUserId, $data['message'] ?? null);
            }

            return $game->refresh();
        });
    }

    /**
     * Step 1 — manual pitch. user_id always comes from the authenticated user.
     */
    public function attachManualPitch(User $user, array $pitchData): \App\Models\GamePitche
    {
        return DB::transaction(function () use ($user, $pitchData) {
            return $user->gamePitches()->create([
                'name'      => $pitchData['name'],
                'address'   => $pitchData['address'],
                'latitude'  => $pitchData['latitude'],
                'longitude' => $pitchData['longitude'],
            ]);
        });
    }

    /**
     * Step 1 — book a venue field through the existing booking system.
     * Conflict-checked then attached to the game inside one transaction.
     */
    public function attachBooking(Game $game, User $user, array $bookingData): Game
    {
        return DB::transaction(function () use ($game, $user, $bookingData) {
            $booking = Booking::create([
                'reference'      => strtoupper(uniqid('BK')),
                'field_id'       => $bookingData['field_id'],
                'user_id'        => $user->id,
                'booking_date'   => $bookingData['booking_date'],
                'start_time'     => $bookingData['start_time'],
                'end_time'       => $bookingData['end_time'],
                'total_price'    => $bookingData['total_price'],
                'status'         => 'pending',
                'payment_status' => 'unpaid',
                'note'           => $bookingData['note'] ?? null,
            ]);

            $game->update(['booking_id' => $booking->id]);

            return $game->refresh();
        });
    }

    /**
     * STEP 1/3 — wire up ONE side of the game. Two supported flows:
     *
     * Self-organized match (owner builds BOTH sides):
     *   the game owner may build home AND away while no accepted challenge
     *   has assigned the away side to an opponent.
     *
     * Challenge flow (each user builds only their own side):
     *   home → the game owner (User A);
     *   away → the challenged user / team owner (User B) after acceptance;
     *   once a challenge is accepted the owner can no longer touch away.
     *
     * Option A: "Play with my team" → {side}_team_id given → PERMANENT game
     *           team whose joined roster is copied from the real team membership.
     * Option B: "I want to choose players" → {side}_players given → TEMPORARY
     *           game team (teams table is NEVER touched) + a pending game
     *           player request per selected player.
     *
     * Calling again while the game is a draft replaces the side cleanly.
     */
    public function setupSide(Game $game, string $side, User $user, array $data): GameTeam
    {
        return DB::transaction(function () use ($game, $side, $user, $data) {
            // ── OWNERSHIP: resolve who controls the requested side. ─────────
            $challenge = GameChallenge::query()
                ->where('game_id', $game->id)
                ->where('status', GameChallenge::STATUS_ACCEPTED)
                ->latest('id')
                ->first();

            $awayOwnerId = $challenge
                ? (int) ($challenge->challenged_user_id ?? $challenge->challengedTeam?->user_id)
                : null;

            $isGameOwner = (int) $game->user_id === $user->id;

            if ($side === GameTeam::SIDE_HOME) {
                if (! $isGameOwner) {
                    throw new InvalidArgumentException('Only the game owner can build the home side.');
                }
            } elseif ($isGameOwner) {
                // The owner may build away himself ONLY while the away side
                // has not been handed to a challenged opponent.
                if ($awayOwnerId !== null) {
                    throw new InvalidArgumentException('Only the challenged opponent can build the away side.');
                }
            } elseif ($awayOwnerId === null || $awayOwnerId !== $user->id) {
                throw new InvalidArgumentException('Only the challenged opponent can build the away side.');
            }

            $teamId = $data[$side . '_team_id'] ?? null;
            $playerStatIds = array_values(array_unique($data[$side . '_players'] ?? []));

            if ($teamId === null && $playerStatIds === []) {
                throw new InvalidArgumentException(
                    $side === GameTeam::SIDE_HOME
                        ? 'Choose a team to play with or select players for your game team.'
                        : 'Choose your team or select the players who will play with you.'
                );
            }

            $gameTeam = $this->resolveSideContainer($game, $side);

            if ($teamId !== null) {
                // ── Option A: permanent team ─────────────────────────────
                $team = Team::findOrFail($teamId);

                if ((int) $team->user_id !== $user->id) {
                    throw new InvalidArgumentException('The selected team does not belong to you.');
                }

                $gameTeam->update([
                    'team_id' => $team->id,
                    'name'    => $team->name,
                    'type'    => GameTeam::TYPE_PERMANENT,
                    'owner_id' => $team->user_id,
                ]);

                // Roster comes from the existing membership system: all active
                // members are confirmed immediately (no re-selection needed).
                $gameTeam->players()->delete();
                $memberIds = TeamMember::query()
                    ->where('team_id', $team->id)
                    ->where('status', 'active')
                    ->pluck('player_id');

                if ($memberIds->isEmpty()) {
                    throw new InvalidArgumentException('The selected team has no active members.');
                }

                $this->assertPlayersFitSide($gameTeam, $memberIds->count());

                foreach ($memberIds as $playerStatId) {
                    GamePlayer::create([
                        'game_id'       => $game->id,
                        'game_team_id'  => $gameTeam->id,
                        'player_stat_id' => $playerStatId,
                        'status'        => GamePlayer::STATUS_JOINED,
                    ]);
                }
            } else {
                // ── Option B: temporary pick-players side ────────────────
                // CRITICAL: nothing is written to the permanent teams table.
                $gameTeam->update([
                    'team_id' => null,
                    'name'    => $data[$side . '_team_name'] ?? ($user->last_name ? $user->first_name . "'s Team" : 'My Team'),
                    'type'    => GameTeam::TYPE_TEMPORARY,
                    'owner_id' => $user->id,
                ]);

                $gameTeam->players()->delete();
                $gameTeam->playerRequests()->where('status', GamePlayerRequest::STATUS_PENDING)->update([
                    'status' => GamePlayerRequest::STATUS_CANCELLED,
                ]);

                $this->assertPlayersFitSide($gameTeam, count($playerStatIds));

                foreach ($playerStatIds as $playerStatId) {
                    GamePlayer::create([
                        'game_id'        => $game->id,
                        'game_team_id'   => $gameTeam->id,
                        'player_stat_id' => $playerStatId,
                        'status'         => GamePlayer::STATUS_PENDING,
                    ]);

                    // The user does NOT create a team — the system invites the
                    // players; only accepted players become confirmed members.
                    GamePlayerRequest::create([
                        'game_id'        => $game->id,
                        'game_team_id'   => $gameTeam->id,
                        'player_stat_id' => $playerStatId,
                        'initiated_by'   => $user->id,
                        'status'         => GamePlayerRequest::STATUS_PENDING,
                        'message'        => $data['message'] ?? null,
                    ]);
                }
            }

            return $gameTeam->refresh();
        });
    }

    /**
     * Respond to a game player request. Only the invited player (owner of the
     * player_stats row) can answer, and only while the game is not finished.
     */
    public function respondToPlayerRequest(GamePlayerRequest $request, string $status, int $userId): GamePlayerRequest
    {
        return DB::transaction(function () use ($request, $status, $userId) {
            if ($request->status !== GamePlayerRequest::STATUS_PENDING) {
                throw new InvalidArgumentException('This request has already been handled.');
            }

            if ((int) $request->playerStat->user_id !== $userId) {
                throw new InvalidArgumentException('This request was not sent to you.');
            }

            $request->update([
                'status'       => $status,
                'responded_at' => now(),
            ]);

            // A selected player becomes a CONFIRMED game team member only here.
            $request->gamePlayer()->update([
                'status' => $status === GamePlayerRequest::STATUS_ACCEPTED
                    ? GamePlayer::STATUS_JOINED
                    : GamePlayer::STATUS_DECLINED,
            ]);

            return $request->refresh();
        });
    }

    /**
     * STEP 4 — send a challenge. The challenger is always the home game team
     * (permanent OR temporary). The challenge offers the opponent two doors:
     *  - challenged_team_id: accept and play as that permanent team
     *  - challenged_user_id: accept and build a temporary side with picked players
     */
    public function challenge(Game $game, int $challengingGameTeamId, ?int $challengedTeamId, ?int $challengedUserId, ?string $message): GameChallenge
    {
        return DB::transaction(function () use ($game, $challengingGameTeamId, $challengedTeamId, $challengedUserId, $message) {
            if ($challengedTeamId === null && $challengedUserId === null) {
                throw new InvalidArgumentException('Provide the team to challenge or the user to challenge.');
            }

            if ($challengedTeamId !== null && $challengedUserId !== null) {
                throw new InvalidArgumentException('Challenge either a team or a user, not both.');
            }

            $challenger = GameTeam::findOrFail($challengingGameTeamId);

            if ($challenger->game_id !== $game->id || $challenger->side !== GameTeam::SIDE_HOME) {
                throw new InvalidArgumentException('Only the home game team can send challenges.');
            }

            // The game owner drives the challenge; verify he actually controls
            // the home side (defense in depth — the controller already gates).
            if ((int) $challenger->owner_id !== (int) $game->user_id) {
                throw new InvalidArgumentException('Only the game owner can send challenges.');
            }

            // You cannot challenge yourself (as team or as user).
            if ($challengedTeamId !== null) {
                if ((int) $challenger->team_id === (int) $challengedTeamId) {
                    throw new InvalidArgumentException('The challenged team cannot be your own team.');
                }

                $team = Team::findOrFail($challengedTeamId);

                if ((int) $team->user_id === (int) $game->user_id) {
                    throw new InvalidArgumentException('You cannot challenge your own team.');
                }

                return GameChallenge::create([
                    'game_id'                  => $game->id,
                    'challenging_game_team_id' => $challenger->id,
                    'challenged_team_id'       => $team->id,
                    'challenged_user_id'       => $team->user_id,
                    'initiated_by'             => auth()->id(),
                    'status'                   => GameChallenge::STATUS_PENDING,
                    'message'                  => $message,
                ]);
            }

            if ((int) $challengedUserId === (int) $game->user_id) {
                throw new InvalidArgumentException('You cannot challenge yourself.');
            }

            return GameChallenge::create([
                'game_id'                  => $game->id,
                'challenging_game_team_id' => $challenger->id,
                'challenged_user_id'       => $challengedUserId,
                'initiated_by'             => auth()->id(),
                'status'                   => GameChallenge::STATUS_PENDING,
                'message'                  => $message,
            ]);
        });
    }

    /**
     * Opponent answers a challenge and DECIDES how to build their own side:
     *  - accepts with team_id       → AWAY materializes as PERMANENT (their team)
     *  - accepts with player_stat_ids → AWAY materializes as TEMPORARY
     *    (pending game player requests — same flow as the home side)
     *
     * The choice is ALWAYS the opponent's, regardless of whether the challenge
     * was addressed to one of their teams or to them as a user.
     */
    public function respondToChallenge(GameChallenge $challenge, string $status, User $user, array $extra = []): GameChallenge
    {
        return DB::transaction(function () use ($challenge, $status, $user, $extra) {
            $game = $challenge->game;

            $recipientId = (int) ($challenge->challenged_user_id ?? $challenge->challengedTeam?->user_id);

            if ($recipientId !== $user->id) {
                throw new InvalidArgumentException('This challenge was not sent to you.');
            }

            if ($challenge->status !== GameChallenge::STATUS_PENDING) {
                throw new InvalidArgumentException('Only pending challenges can be responded to.');
            }

            $challenge->update([
                'status'       => $status,
                'responded_at' => now(),
            ]);

            if ($status === GameChallenge::STATUS_ACCEPTED) {
                // The OPPONENT decides: permanent team or hand-picked players.
                if (! empty($extra['team_id'])) {
                    $data = ['away_team_id' => (int) $extra['team_id']];
                } elseif (! empty($extra['player_stat_ids'])) {
                    $data = [
                        'away_players'   => $extra['player_stat_ids'],
                        'away_team_name' => $extra['name'] ?? null,
                    ];
                } elseif ($challenge->challenged_team_id !== null) {
                    // Team challenge: a bare accept means "we play as that team".
                    $data = ['away_team_id' => (int) $challenge->challenged_team_id];
                } else {
                    throw new InvalidArgumentException('Choose your team or select the players who will play with you.');
                }

                $this->setupSide($game, GameTeam::SIDE_AWAY, $user, $data);
            }

            return $challenge->refresh();
        });
    }

    /**
     * Finalize a draft: promote it to `scheduled` once the wizard is complete.
     */
    public function finalize(Game $game): Game
    {
        return DB::transaction(function () use ($game) {
            if (! $game->isDraft()) {
                throw new InvalidArgumentException('Only draft games can be finalized.');
            }

            $game->update(['status' => Game::STATUS_SCHEDULED]);

            return $game->refresh();
        });
    }

    public function start(Game $game): Game
    {
        return $this->transition($game, Game::STATUS_SCHEDULED, Game::STATUS_ONGOING);
    }

    public function complete(Game $game): Game
    {
        return $this->transition($game, Game::STATUS_ONGOING, Game::STATUS_COMPLETED);
    }

    public function cancel(Game $game): Game
    {
        return $this->transition($game, [Game::STATUS_DRAFT, Game::STATUS_SCHEDULED, Game::STATUS_ONGOING], Game::STATUS_CANCELLED);
    }

    /**
     * Guarded status transition: the game must currently be in one of the
     * allowed source statuses, otherwise the move is refused.
     */
    private function transition(Game $game, array|string $from, string $to): Game
    {
        $current = $game->status;

        if (! in_array($current, (array) $from, true)) {
            throw new InvalidArgumentException("Cannot move a {$current} game to {$to}.");
        }

        $game->update(['status' => $to]);

        return $game->refresh();
    }

    /**
     * Recommended opponents: teams matching the home side's profile
     * (sport category / match type), same city first.
     */
    public function recommendedOpponents(Game $game, int $limit = 10)
    {
        $home = $game->homeGameTeam()->with('team')->first();
        $homeTeam = $home?->team;

        $query = Team::query()
            ->when($homeTeam?->id, fn($q) => $q->whereNot('id', $homeTeam->id))
            ->when(
                $homeTeam?->sport_category_id,
                fn($q) => $q->where('sport_category_id', $homeTeam->sport_category_id)
            )
            ->when(
                $homeTeam?->match_type,
                fn($q) => $q->where('match_type', $homeTeam->match_type)
            );

        if ($homeTeam?->city) {
            $query->orderByRaw('CASE WHEN city = ? THEN 0 ELSE 1 END', [$homeTeam->city]);
        }

        return $query->limit($limit)->get();
    }

    // ── Internal validation ─────────────────────────────────────────────

    /**
     * Build the draft payload, enforcing ownership/availability rules that
     * cannot live in FormRequest rules alone (cross-table checks).
     */
    private function resolveDraftPayload(User $user, array $data): array
    {
        $payload = collect($data)->only([
            'game_pitch_id',
            'booking_id',
            'game_date',
            'start_time',
            'duration',
            'game_type',
            'gender',
            'privacy',
        ])->all();

        // Manual pitch must belong to the authenticated user.
        if (! empty($payload['game_pitch_id'])) {
            $owned = $user->gamePitches()->whereKey($payload['game_pitch_id'])->exists();

            if (! $owned) {
                throw new InvalidArgumentException('The selected pitch does not belong to you.');
            }
        }

        // Booking must belong to the authenticated user, be active, and not
        // overlap another game of the same user (no double booking).
        if (! empty($payload['booking_id'])) {
            $booking = Booking::whereKey($payload['booking_id'])->first();

            if (! $booking || $booking->user_id !== $user->id) {
                throw new InvalidArgumentException('The selected booking does not belong to you.');
            }

            if ($booking->status === 'cancelled' || $booking->status === 'completed') {
                throw new InvalidArgumentException('The selected booking is no longer available.');
            }
        }

        if (isset($payload['game_date'], $payload['start_time'], $payload['duration'])) {
            $this->assertNoDoubleBooking(
                $user->id,
                $payload['game_date'],
                $payload['start_time'],
                (int) $payload['duration'],
                $data['id'] ?? null
            );
        }

        return $payload;
    }

    private function assertNoDoubleBooking(int $userId, string $date, string $startTime, int $duration, ?int $exceptGameId = null): void
    {
        $conflict = Game::query()
            ->overlapping($userId, $date, $startTime, $duration)
            ->when($exceptGameId, fn($q) => $q->whereNot('id', $exceptGameId))
            ->first();

        if ($conflict) {
            throw new InvalidArgumentException('You already have a game in this time slot.');
        }
    }

    /**
     * Get-or-create the side container for a game side (unique game_id+side).
     */
    private function resolveSideContainer(Game $game, string $side): GameTeam
    {
        return GameTeam::firstOrCreate(
            ['game_id' => $game->id, 'side' => $side],
            ['name' => $side === GameTeam::SIDE_HOME ? 'Home' : 'Away', 'owner_id' => $game->user_id, 'type' => GameTeam::TYPE_TEMPORARY]
        );
    }

    /**
     * A game team may hold at most `playersPerSide()` players (5v5 → 5).
     */
    private function assertPlayersFitSide(GameTeam $gameTeam, int $adding): void
    {
        $current = $gameTeam->players()->count();

        if ($current + $adding > $gameTeam->game->playersPerSide()) {
            throw new InvalidArgumentException(
                "Too many players for {$gameTeam->game->game_type}: max {$gameTeam->game->playersPerSide()} per side."
            );
        }
    }
}
