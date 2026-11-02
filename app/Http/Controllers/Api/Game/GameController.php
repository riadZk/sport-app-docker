<?php

namespace App\Http\Controllers\Api\Game;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Game\AttachBookingRequest;
use App\Http\Requests\Game\AttachPlayersRequest;
use App\Http\Requests\Game\ChallengeRequest;
use App\Http\Requests\Game\StoreGamePitchRequest;
use App\Http\Requests\Game\StoreGamePredictionRequest;
use App\Http\Requests\Game\StoreGameRequest;
use App\Http\Requests\Game\UpdateGameRequest;
use App\Http\Resources\Game\GameResource;
use App\Models\Booking;
use App\Models\Game;
use App\Models\GameChallenge;
use App\Models\GamePitche;
use App\Models\GamePrediction;
use App\Models\Team;
use App\Services\GameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use App\Models\PlayerStat;

class GameController extends ApiController
{
    public function __construct(protected GameService $gameService) {}

    /**
     * GET /games — list the authenticated user's games.
     */
    public function index(Request $request): JsonResponse
    {
        $games = Game::query()
            ->where('user_id', $request->user()->id)
            ->with(['pitch', 'booking.field', 'user', 'gameTeams.team', 'gameTeams.players.playerStat.user'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->latest('game_date')
            ->paginate($request->integer('per_page', 15));

        return $this->jsonResponse(GameResource::collection($games), 'Games retrieved successfully.');
    }

    /**
     * POST /games — Step 1+2: create the draft game (wizard entry point).
     * The payload may include the home side choice:
     *   - home_team_id  → "Play with my team"      (permanent game team)
     *   - home_players  → "I want to choose players" (temporary game team + requests)
     */
    public function store(StoreGameRequest $request): JsonResponse
    {
        try {
            $game = $this->gameService->createOrUpdateDraft($request->user(), $request->validated());
        } catch (InvalidArgumentException $e) {
            return $this->jsonResponse(null, $e->getMessage(), 422);
        }

        return $this->jsonResponse(
            new GameResource($game->load(['pitch', 'booking.field', 'user', 'gameTeams.team', 'gameTeams.players.playerStat.user'])),
            'Game draft created successfully.',
            201
        );
    }

    /**
     * GET /games/{game} — private games visible to the owner only.
     */
    public function show(Request $request, Game $game): JsonResponse
    {
        if ($game->privacy === 'private' && ! $game->isOwnedBy($request->user()->id)) {
            return $this->jsonResponse(null, 'Game not found.', 404);
        }

        $game->load([
            'pitch',
            'booking.field',
            'user',
            'gameTeams.team',
            'gameTeams.players.playerStat.user',
            'challenges.challengedTeam.activeMembers.playerStat.user',
            'challenges.challengedUser',
        ]);

        return $this->jsonResponse(
            new GameResource($game),
            'Game retrieved successfully.'
        );
    }

    /**
     * PUT /games/{game} — update a draft (owner only).
     */
    public function update(UpdateGameRequest $request, Game $game): JsonResponse
    {
        if (! $game->isOwnedBy($request->user()->id)) {
            return $this->jsonResponse(null, 'Game not found.', 404);
        }

        if (! $game->isDraft()) {
            return $this->jsonResponse(null, 'Only draft games can be modified.', 422);
        }

        try {
            $game = $this->gameService->createOrUpdateDraft($request->user(), $request->validated(), $game);
        } catch (InvalidArgumentException $e) {
            return $this->jsonResponse(null, $e->getMessage(), 422);
        }

        return $this->jsonResponse(
            new GameResource($game->load(['pitch', 'booking.field', 'user', 'gameTeams.team', 'gameTeams.players.playerStat.user'])),
            'Game updated successfully.'
        );
    }

    /**
     * DELETE /games/{game} — delete a draft (owner only).
     */
    public function destroy(Request $request, Game $game): JsonResponse
    {
        if (! $game->isOwnedBy($request->user()->id)) {
            return $this->jsonResponse(null, 'Game not found.', 404);
        }

        $game->delete();

        return $this->jsonResponse(null, 'Game deleted successfully.');
    }

    // ── Players & Teams ─────────────────────────────────────────────────

    /**
     * GET /games/players — list all players (PlayerStat) with their user
     * info, EXCLUDING the authenticated user's own player stats.
     * Supports pagination via ?page= and ?per_page=.
     */
    public function allPlayers(Request $request): JsonResponse
    {
        $players = PlayerStat::query()
            ->where('user_id', '!=', $request->user()->id)
            ->with(['user:id,first_name,last_name,username,city', 'sportCategory:id,name'])
            ->when($request->filled('sport_category_id'), fn($q) => $q->where('sport_category_id', $request->sport_category_id), fn($q) => $q->where('sport_category_id', 1))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->whereHas('user', fn($uq) => $uq
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%"));
            })
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        // Flatten each player to only the fields the client needs.
        $players->getCollection()->transform(fn($player) => [
            'id'                 => $player->id,
            'primary_position'   => $player->primary_position,
            'secondary_position' => $player->secondary_position,
            'first_name'         => $player->user?->first_name,
            'last_name'          => $player->user?->last_name,
            'username'           => $player->user?->username,
            'city'               => $player->user?->city,
            'picture_url'        => $player->user?->picture_url,
        ]);

        return $this->jsonResponse($players, 'Players retrieved successfully.');
    }


    /**
     * GET /teams/all — list all teams (id, name, logo) EXCLUDING the
     * authenticated user's own teams. Supports pagination via ?page= and ?per_page=.
     *
     * Filters:
     *   ?search=junior      — matches name or short_name
     *   ?match_type=5v5     — one of 5v5,6v6,7v7,8v8,9v9,11v11
     *   ?sport_category_id= — defaults to 1
     */
    public function allTeams(Request $request)
    {
        $teams = Team::query()
            ->where('user_id', '!=', $request->user()->id)
            ->select('id', 'name', 'short_name', 'logo_type', 'default_logo_id', 'city', 'match_type', 'sport_category_id', 'user_id')
            ->with(['sportCategory:id,name'])
            ->withCount('activeMembers')
            ->when($request->filled('sport_category_id'), fn($q) => $q->where('sport_category_id', $request->sport_category_id), fn($q) => $q->where('sport_category_id', 1))
            ->when($request->filled('match_type'), fn($q) => $q->where('match_type', $request->match_type))
            ->when($request->filled('search'), fn($q) => $q->where(fn($w) => $w
                ->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('short_name', 'like', '%' . $request->search . '%')))
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        // Append logo_url for each team
        $teams->getCollection()->transform(fn($team) => [
            'id'                => $team->id,
            'name'              => $team->name,
            'short_name'        => $team->short_name,
            'logo_url'          => $team->logo_url,
            'city'              => $team->city,
            'match_type'        => $team->match_type,
            'members_count'     => $team->active_members_count,
            'sport_category_id' => $team->sport_category_id,
            'sport_category'    => $team->sportCategory?->name,
            'owner_id'          => $team->user_id,
        ]);

        return $this->jsonResponse($teams, 'Teams retrieved successfully');
    }

    // ── Pitch ───────────────────────────────────────────────────────────

    /**
     * POST /games/pitches — create a manual pitch (user_id from auth()).
     */
    public function storePitch(StoreGamePitchRequest $request): JsonResponse
    {
        $pitch = $this->gameService->attachManualPitch($request->user(), $request->validated());

        return $this->jsonResponse([
            'id'        => $pitch->id,
            'name'      => $pitch->name,
            'address'   => $pitch->address,
            'latitude'  => $pitch->latitude,
            'longitude' => $pitch->longitude,
        ], 'Game pitch created successfully.', 201);
    }

    /**
     * GET /games/pitches — list the authenticated user's manual pitches (paginated).
     */
    public function indexPitches(Request $request): JsonResponse
    {
        $pitches = $request->user()
            ->gamePitches()
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return $this->jsonResponse($pitches, 'Game pitches retrieved successfully.');
    }

    /**
     * DELETE /games/pitches/{pitch} — delete a pitch the user created.
     * Only pitches NOT attached to any game can be deleted.
     */
    public function destroyPitch(Request $request, GamePitche $pitch): JsonResponse
    {
        // Only the creator can delete.
        if ($pitch->user_id !== $request->user()->id) {
            return $this->jsonResponse(null, 'Pitch not found.', 404);
        }

        // Cannot delete a pitch that is attached to a game.
        if (Game::where('game_pitch_id', $pitch->id)->exists()) {
            return $this->jsonResponse(null, 'This pitch is attached to a game and cannot be deleted.', 422);
        }

        $pitch->delete();

        return $this->jsonResponse(null, 'Pitch deleted successfully.');
    }

    // ── Booking ─────────────────────────────────────────────────────────

    /**
     * GET /games/bookings/available — the authenticated user's bookings that
     * can still be attached to a game: active (pending/confirmed), not yet
     * used by any game. Supports ?date= and pagination (?page=, ?per_page=).
     */
    public function availableBookings(Request $request): JsonResponse
    {
        $bookings = Booking::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereDoesntHave('game')
            ->with(['field.venue:id,name', 'field.format:id,name,players_per_team'])
            ->when($request->filled('date'), fn($q) => $q->where('booking_date', $request->date))
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->paginate($request->integer('per_page', 15));

        return $this->jsonResponse($bookings, 'Available bookings retrieved successfully.');
    }

    /**
     * POST /games/{game}/booking — attach an existing booking or create one
     * through the existing booking system (conflict-checked, transactional).
     */
    public function attachBooking(AttachBookingRequest $request, Game $game): JsonResponse
    {
        if (! $game->isOwnedBy($request->user()->id) || ! $game->isDraft()) {
            return $this->jsonResponse(null, 'Game not found.', 404);
        }

        $data = $request->validated();

        try {
            if (! empty($data['booking_id'])) {
                $booking = Booking::whereKey($data['booking_id'])->first();

                if (! $booking || $booking->user_id !== $request->user()->id) {
                    return $this->jsonResponse(null, 'The selected booking does not belong to you.', 422);
                }

                $game->update(['booking_id' => $booking->id]);
            } else {
                $game = $this->gameService->attachBooking($game, $request->user(), $data);
            }
        } catch (InvalidArgumentException $e) {
            return $this->jsonResponse(null, $e->getMessage(), 422);
        }

        return $this->jsonResponse(
            new GameResource($game->load(['pitch', 'booking.field', 'user', 'gameTeams.team', 'gameTeams.players.playerStat.user'])),
            'Booking attached successfully.'
        );
    }

    // ── Step 3 — Team / Players (game teams) ────────────────────────────

    /**
     * POST /games/{game}/players — "I want to choose players" for ONE side,
     * selected explicitly with `side: home|away`. Who may build which side:
     *   - home → the game owner (User A)
     *   - away → the game owner building BOTH sides himself (temporary vs
     *            temporary self-organized match), OR the challenged opponent
     *            after accepting the challenge (User B builds his own side).
     * A temporary game team is created by the system (never in `teams`) and a
     * pending request is sent to every selected player.
     */
    public function attachPlayers(AttachPlayersRequest $request, Game $game): JsonResponse
    {
        $userId = $request->user()->id;
        $side = $request->validated('side');
        $isGameOwner = $game->isOwnedBy($userId);

        // Who is allowed to touch the requested side? Strangers get the 404
        // shield; finer-grained rules (e.g. the owner losing away after an
        // accepted challenge) are enforced by the service with a clear 422.
        if ($side === \App\Models\GameTeam::SIDE_HOME) {
            $allowed = $isGameOwner;
        } elseif ($isGameOwner) {
            // The game owner may always aim at the away side — setupSide()
            // refuses with a 422 once a challenge has handed it to an opponent.
            $allowed = true;
        } else {
            // Otherwise only the challenged opponent may build the away side.
            $allowed = \App\Models\GameChallenge::query()
                ->where('game_id', $game->id)
                ->where('status', \App\Models\GameChallenge::STATUS_ACCEPTED)
                ->latest('id')
                ->get()
                ->contains(fn($challenge) => (int) ($challenge->challenged_user_id ?? $challenge->challengedTeam?->user_id) === (int) $userId);
        }

        if (! $allowed) {
            return $this->jsonResponse(null, 'Game not found.', 404);
        }

        try {
            $this->gameService->setupSide($game, $side, $request->user(), [
                $side . '_players'   => $request->validated('player_stat_ids'),
                $side . '_team_name' => $request->validated('name'),
                'message'            => $request->validated('message'),
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->jsonResponse(null, $e->getMessage(), 422);
        }

        return $this->jsonResponse(
            new GameResource($game->load(['pitch', 'booking.field', 'user', 'gameTeams.team', 'gameTeams.players.playerStat.user'])),
            'Players invited successfully. Waiting for their confirmation.'
        );
    }

    /**
     * GET /games/player-requests — requests sent TO me (accept / reject).
     */
    public function myPlayerRequests(Request $request): JsonResponse
    {
        $requests = \App\Models\GamePlayerRequest::query()
            ->whereHas('playerStat', fn($q) => $q->where('user_id', $request->user()->id))
            ->with(['game', 'gameTeam', 'initiatedBy'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->latest()
            ->get();

        return $this->jsonResponse($requests, 'Player requests retrieved successfully.');
    }

    /**
     * POST /games/player-requests/{request}/respond — the invited player
     * accepts or rejects. Only accepted players become confirmed members.
     */
    public function respondToPlayerRequest(Request $request, \App\Models\GamePlayerRequest $player_request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:accepted,rejected'],
        ]);

        try {
            $playerRequest = $this->gameService->respondToPlayerRequest(
                $player_request,
                $data['status'],
                $request->user()->id
            );
        } catch (InvalidArgumentException $e) {
            return $this->jsonResponse(null, $e->getMessage(), 422);
        }

        return $this->jsonResponse($playerRequest, 'Request ' . $data['status'] . ' successfully.');
    }

    /**
     * DELETE /games/{game}/players/{playerStat} — remove a roster entry from
     * my side (cancels the pending request if there is one). The side follows
     * the same ownership rules as attachPlayers: the game owner may remove
     * from home OR away (self-built match), the challenged opponent from away.
     */
    public function detachPlayer(Request $request, Game $game, int $playerStat): JsonResponse
    {
        $userId = $request->user()->id;
        $side = $request->query('side', \App\Models\GameTeam::SIDE_HOME);

        if (! in_array($side, [\App\Models\GameTeam::SIDE_HOME, \App\Models\GameTeam::SIDE_AWAY], true)) {
            return $this->jsonResponse(null, 'Game not found.', 404);
        }

        if ($side === \App\Models\GameTeam::SIDE_AWAY) {
            // Away detach: game owner (self-built away, no accepted challenge)
            // or the challenged opponent.
            $acceptedChallenge = \App\Models\GameChallenge::query()
                ->where('game_id', $game->id)
                ->where('status', \App\Models\GameChallenge::STATUS_ACCEPTED)
                ->latest('id')
                ->first();

            $awayOwnerId = $acceptedChallenge
                ? (int) ($acceptedChallenge->challenged_user_id ?? $acceptedChallenge->challengedTeam?->user_id)
                : null;

            $isGameOwner = $game->isOwnedBy($userId);
            if (! $isGameOwner && $awayOwnerId !== (int) $userId) {
                return $this->jsonResponse(null, 'Game not found.', 404);
            }
            if ($isGameOwner && $awayOwnerId !== null && $awayOwnerId !== (int) $userId) {
                return $this->jsonResponse(null, 'Game not found.', 404);
            }
        } elseif (! $game->isOwnedBy($userId)) {
            return $this->jsonResponse(null, 'Game not found.', 404);
        }

        $gameTeam = $game->gameTeams()->where('side', $side)->first();

        if (! $gameTeam) {
            return $this->jsonResponse(null, 'Game not found.', 404);
        }

        $gameTeam->players()->where('player_stat_id', $playerStat)->delete();
        $gameTeam->playerRequests()->where('player_stat_id', $playerStat)->update([
            'status' => \App\Models\GamePlayerRequest::STATUS_CANCELLED,
        ]);

        return $this->jsonResponse(
            new GameResource($game->load(['pitch', 'booking.field', 'user', 'gameTeams.team', 'gameTeams.players.playerStat.user'])),
            'Player removed successfully.'
        );
    }

    // ── Step 4 — Opponent ───────────────────────────────────────────────

    /**
     * GET /games/{game}/recommended-opponents — teams in the same city /
     * sport category / match type as the home team.
     */
    public function recommendedOpponents(Request $request, Game $game): JsonResponse
    {
        $teams = $this->gameService->recommendedOpponents($game, $request->integer('limit', 10));

        return $this->jsonResponse($teams->map(fn(Team $team) => [
            'id'         => $team->id,
            'name'       => $team->name,
            'short_name' => $team->short_name,
            'city'       => $team->city,
            'match_type' => $team->match_type,
            'logo_url'   => $team->logo_url,
        ]), 'Recommended opponents retrieved successfully.');
    }

    /**
     * POST /games/{game}/opponent/challenge — the HOME game team challenges
     * either a permanent team (team_id) or a user (user_id, who then builds
     * a temporary side by picking players when accepting).
     */
    public function challenge(ChallengeRequest $request, Game $game): JsonResponse
    {
        $validated = $request->validated();

        $homeGameTeam = $game->homeGameTeam()->first();

        if (! $homeGameTeam) {
            return $this->jsonResponse(null, 'You need a home team to send a challenge.', 422);
        }

        try {
            $challenge = $this->gameService->challenge(
                $game,
                $homeGameTeam->id,
                isset($validated['team_id']) ? (int) $validated['team_id'] : null,
                isset($validated['user_id']) ? (int) $validated['user_id'] : null,
                $validated['message'] ?? null
            );
        } catch (InvalidArgumentException $e) {
            return $this->jsonResponse(null, $e->getMessage(), 422);
        }

        return $this->jsonResponse($challenge, 'Challenge sent successfully.', 201);
    }

    /**
     * GET /games/challenges — challenges received by me (as user) or by my teams.
     */
    public function challenges(Request $request): JsonResponse
    {
        $teamIds = Team::where('user_id', $request->user()->id)->pluck('id');

        $challenges = GameChallenge::query()
            ->where(function ($q) use ($request, $teamIds) {
                $q->where('challenged_user_id', $request->user()->id)
                    ->orWhereIn('challenged_team_id', $teamIds);
            })
            ->with(['game', 'challengingGameTeam.team', 'challengedGameTeam', 'challengedTeam', 'challengedUser'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->latest()
            ->get();

        return $this->jsonResponse($challenges, 'Challenges retrieved successfully.');
    }

    /**
     * POST /challenges/{challenge}/respond — accept / reject / cancel.
     * The OPPONENT decides how to build the AWAY side when accepting:
     *   - team_id        → their permanent team (roster copied)
     *   - player_stat_ids → temporary side built from their picked players
     */
    public function respondToChallenge(Request $request, GameChallenge $challenge): JsonResponse
    {
        $recipientId = $challenge->challenged_user_id
            ?? $challenge->challengedTeam?->user_id;

        if ($recipientId !== $request->user()->id) {
            return $this->jsonResponse(null, 'Challenge not found.', 404);
        }

        $data = $request->validate([
            'status'            => ['required', 'in:accepted,rejected,cancelled'],
            'team_id'           => ['nullable', 'integer', 'exists:teams,id'],
            'player_stat_ids'   => ['nullable', 'array', 'min:1'],
            'player_stat_ids.*' => ['integer', 'distinct', 'exists:player_stats,id'],
            'name'              => ['nullable', 'string', 'max:255'],
        ]);

        $hasTeam = ! empty($data['team_id']);
        $hasPlayers = ! empty($data['player_stat_ids']);

        if (
            $data['status'] === 'accepted'
            && $hasTeam === $hasPlayers
            && ! ($hasTeam === false && $challenge->challenged_team_id !== null)
        ) {
            // Accepted without a choice: allowed ONLY for a team challenge
            // (bare accept = "we play as that team"). Otherwise exactly one
            // of team_id / player_stat_ids is required.
            return $this->jsonResponse(null, 'Choose either your team or the players you will play with.', 422);
        }

        try {
            $challenge = $this->gameService->respondToChallenge(
                $challenge,
                $data['status'],
                $request->user(),
                [
                    'team_id'         => $data['team_id'] ?? null,
                    'player_stat_ids' => $data['player_stat_ids'] ?? null,
                    'name'            => $data['name'] ?? null,
                ]
            );
        } catch (InvalidArgumentException $e) {
            return $this->jsonResponse(null, $e->getMessage(), 422);
        }

        return $this->jsonResponse($challenge, 'Challenge updated successfully.');
    }

    // ── Lifecycle ───────────────────────────────────────────────────────

    /**
     * POST /games/{game}/complete — finalize the wizard (draft → scheduled)
     * or mark an ongoing game as completed.
     */
    public function complete(Request $request, Game $game): JsonResponse
    {
        if (! $game->isOwnedBy($request->user()->id)) {
            return $this->jsonResponse(null, 'Game not found.', 404);
        }

        try {
            $game = $game->isDraft()
                ? $this->gameService->finalize($game)
                : $this->gameService->complete($game);
        } catch (InvalidArgumentException $e) {
            return $this->jsonResponse(null, $e->getMessage(), 422);
        }

        return $this->jsonResponse(
            new GameResource($game->load(['pitch', 'booking.field', 'user', 'gameTeams.team', 'gameTeams.players.playerStat.user'])),
            'Game completed successfully.'
        );
    }

    /**
     * POST /games/{game}/cancel — cancel a game (owner only).
     */
    public function cancel(Request $request, Game $game): JsonResponse
    {
        if (! $game->isOwnedBy($request->user()->id)) {
            return $this->jsonResponse(null, 'Game not found.', 404);
        }

        try {
            $game = $this->gameService->cancel($game);
        } catch (InvalidArgumentException $e) {
            return $this->jsonResponse(null, $e->getMessage(), 422);
        }

        return $this->jsonResponse(
            new GameResource($game->load(['pitch', 'booking.field', 'user', 'gameTeams.team', 'gameTeams.players.playerStat.user'])),
            'Game cancelled successfully.'
        );
    }

    // ── Prediction Voting ───────────────────────────────────────────────

    /**
     * POST /games/{game}/predictions — predict the outcome of a game:
     * home_win, draw or away_win. One vote per user; voting again simply
     * changes the prediction. Closed once the game is completed/cancelled.
     */
    public function storePrediction(StoreGamePredictionRequest $request, Game $game): JsonResponse
    {
        if (! $game->isVotingOpen()) {
            return $this->jsonResponse(null, 'Voting is closed for this game.', 422);
        }

        $prediction = GamePrediction::updateOrCreate(
            ['game_id' => $game->id, 'user_id' => $request->user()->id],
            ['prediction' => $request->validated('prediction')]
        );

        $game->setRelation('predictions', $game->predictions()->get());

        return $this->jsonResponse(
            $this->predictionPayload($game, $prediction->fresh()),
            $prediction->wasRecentlyCreated
                ? 'Prediction submitted successfully.'
                : 'Prediction updated successfully.'
        );
    }

    /**
     * GET /games/{game}/predictions — the vote tallies for a game plus the
     * authenticated user's own prediction (if any).
     */
    public function indexPredictions(Request $request, Game $game): JsonResponse
    {
        $game->load('predictions');

        return $this->jsonResponse(
            $this->predictionPayload($game, $game->predictionOf($request->user()->id)),
            'Predictions retrieved successfully.'
        );
    }

    /**
     * DELETE /games/{game}/predictions — withdraw my prediction.
     */
    public function destroyPrediction(Request $request, Game $game): JsonResponse
    {
        $deleted = GamePrediction::query()
            ->where('game_id', $game->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        if (! $deleted) {
            return $this->jsonResponse(null, 'You have not predicted this game.', 404);
        }

        $game->setRelation('predictions', $game->predictions()->get());

        return $this->jsonResponse(
            $this->predictionPayload($game, null),
            'Prediction removed successfully.'
        );
    }

    /** Uniform response body: counts per outcome + my prediction. */
    private function predictionPayload(Game $game, ?GamePrediction $mine): array
    {
        $counts = $game->predictionTallies();

        return [
            'total_votes' => $counts['total'],
            'home_win'    => $counts['home_win'],
            'draw'        => $counts['draw'],
            'away_win'    => $counts['away_win'],
            'my_prediction' => $mine ? [
                'id'         => $mine->id,
                'prediction' => $mine->prediction,
                'created_at' => $mine->created_at,
                'updated_at' => $mine->updated_at,
            ] : null,
        ];
    }
}
