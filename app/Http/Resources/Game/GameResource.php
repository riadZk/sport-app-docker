<?php

namespace App\Http\Resources\Game;

use App\Models\GameChallenge;
use App\Models\GamePlayer;
use App\Models\GameTeam;
use App\Models\TeamMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class GameResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'user_id'        => $this->user_id,

            // Name + profile of the user who created the game.
            'created_by'     => $this->whenLoaded('user', fn() => $this->user ? [
                'id'          => $this->user->id,
                'name'        => $this->fullName($this->user->first_name, $this->user->last_name),
                'username'    => $this->user->username,
                'picture_url' => $this->user->picture_url,
            ] : null),
            'pitch'          => $this->whenLoaded('pitch', fn() => $this->pitch ? [
                'id'        => $this->pitch->id,
                'name'      => $this->pitch->name,
                'address'   => $this->pitch->address,
                'latitude'  => $this->pitch->latitude,
                'longitude' => $this->pitch->longitude,
            ] : null),
            'booking'        => $this->whenLoaded('booking', fn() => $this->booking ? [
                'id'           => $this->booking->id,
                'reference'    => $this->booking->reference,
                'booking_date' => $this->booking->booking_date?->toDateString(),
                'start_time'   => $this->booking->start_time,
                'end_time'     => $this->booking->end_time,
                'status'       => $this->booking->status,
                'field'        => $this->booking->field?->only(['id', 'name', 'venue_id']),
            ] : null),
            // Average age (years) of all the players in the game.
            'average_age'    => $this->whenLoaded('gameTeams', fn() => $this->averageAge()),
            'game_date'      => $this->game_date?->toDateString(),
            'start_time'     => $this->start_time?->format('H:i'),
            'duration'       => $this->duration,
            'game_type'      => $this->game_type,
            'gender'         => $this->gender,
            'privacy'        => $this->privacy,
            'status'         => $this->status,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
            // Challenges: the game teams live here — home_team / away_team,
            // each with its status, formation and players (name, profile,
            // position, accepted status). While the challenge is pending the
            // away side shows the challenged team's roster.
            'challenges'     => $this->whenLoaded('challenges', fn() => $this->challengesArray()),

            // Prediction voting: tallies per outcome, included only when the
            // relation is loaded (e.g. the dedicated predictions endpoint).
            'predictions'    => $this->whenLoaded('predictions', fn() => $this->predictionTallies()),
        ];
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /** Clean shape for one side of the game (home or away). */
    private function gameTeamArray(GameTeam $gameTeam, ?string $status = 'accepted'): array
    {
        $team = $gameTeam->relationLoaded('team') ? $gameTeam->team : null;

        return [
            'id'         => $gameTeam->id,
            'team_id'    => $team?->id,
            'side'       => $gameTeam->side,
            'type'       => $gameTeam->type,
            'name'       => $gameTeam->name,
            'short_name' => $team?->short_name,
            'city'       => $team?->city,
            'logo_url'   => $team?->logo_url,
            'status'     => $status,
            'formation'  => $team?->formation,
            'players'    => $gameTeam->relationLoaded('players')
                ? $gameTeam->players->map(fn(GamePlayer $gp) => $this->playerArray($gp))->values()
                : [],
        ];
    }

    /** A loaded side by id (the challenge's materialized away side). */
    private function gameTeamById(int $gameTeamId, ?string $status = 'accepted'): ?array
    {
        $gameTeam = $this->gameTeams->firstWhere('id', $gameTeamId);

        return $gameTeam ? $this->gameTeamArray($gameTeam, $status) : null;
    }

    /**
     * The challenge list of the game. If the game has NO challenge at all,
     * a single entry is synthesized from the game teams (home vs away) so
     * the client always receives the same structure.
     */
    private function challengesArray(): array
    {
        if ($this->challenges->isNotEmpty()) {
            return $this->challenges
                ->map(fn(GameChallenge $ch) => $this->challengeArray($ch))
                ->values()
                ->all();
        }

        return [
                'id'           => null,
                'status'       => null,
                'message'      => null,
                'responded_at' => null,
                'home_team'    => $this->sideArray(GameTeam::SIDE_HOME),
                'away_team'    => $this->sideArray(GameTeam::SIDE_AWAY),
        ];
    }

    /** One challenge: the home side vs the away side (teams + players). */
    private function challengeArray(GameChallenge $challenge): array
    {
        return [
            'id'           => $challenge->id,
            'status'       => $challenge->status,
            'message'      => $challenge->message,
            'responded_at' => $challenge->responded_at?->toIso8601String(),
            'home_team'    => $this->sideArray(GameTeam::SIDE_HOME),
            'away_team'    => $this->awayTeamArray($challenge),
        ];
    }

    /**
     * The away side of a challenge:
     *   - accepted → the materialized Game Team with its players;
     *   - pending  → the challenged team's roster (players not yet in the
     *                game, their membership status shows acceptance);
     *   - pending on a user → the user's profile, no players yet.
     */
    private function awayTeamArray(GameChallenge $challenge): ?array
    {
        if ($challenge->challenged_game_team_id) {
            return $this->gameTeamById($challenge->challenged_game_team_id, $challenge->status);
        }

        if ($challenge->relationLoaded('challengedTeam') && $challenge->challengedTeam) {
            $team = $challenge->challengedTeam;
            $members = $team->relationLoaded('activeMembers') ? $team->activeMembers : collect();

            return [
                'id'         => null,
                'team_id'    => $team->id,
                'side'       => GameTeam::SIDE_AWAY,
                'type'       => GameTeam::TYPE_PERMANENT,
                'name'       => $team->name,
                'short_name' => $team->short_name,
                'city'       => $team->city,
                'logo_url'   => $team->logo_url,
                'status'     => $challenge->status,
                'formation'  => $team->formation,
                'players'    => $members->map(fn(TeamMember $member) => [
                    'player_stat_id' => $member->player_id,
                    'name'           => $this->fullName($member->playerStat?->user?->first_name, $member->playerStat?->user?->last_name),
                    'picture_url'    => $member->playerStat?->user?->picture_url,
                    'country'        => $member->playerStat?->user?->country,
                    'position'       => $member->playerStat?->primary_position,
                    'status'         => $member->status
                ])->values()->all(),
            ];
        }

        if ($challenge->relationLoaded('challengedUser') && $challenge->challengedUser) {
            $user = $challenge->challengedUser;

            return [
                'id'         => null,
                'team_id'    => null,
                'side'       => GameTeam::SIDE_AWAY,
                'type'       => GameTeam::TYPE_TEMPORARY,
                'name'       => $this->fullName($user->first_name, $user->last_name),
                'short_name' => null,
                'city'       => $user->city,
                'logo_url'   => $user->picture_url,
                'status'     => $challenge->status,
                'formation'  => null,
                'players'    => [],
            ];
        }

        return null;
    }

    /** The home or away side of THIS game. */
    private function sideArray(string $side): ?array
    {
        $gameTeam = $this->gameTeams->firstWhere('side', $side);

        return $gameTeam ? $this->gameTeamArray($gameTeam) : null;
    }

    /** Clean shape for one roster entry: name, profile, position, status. */
    private function playerArray(GamePlayer $gamePlayer): array
    {
        $user = $gamePlayer->playerStat?->user;

        return [
            'player_stat_id' => $gamePlayer->player_stat_id,
            'name'           => $this->fullName($user?->first_name, $user?->last_name),
            'picture_url'    => $user?->picture_url,
            'country'        => $user?->country,
            'position'       => $gamePlayer->playerStat?->primary_position,
            'status'         => $gamePlayer->status,
            'accepted'       => $gamePlayer->status === GamePlayer::STATUS_JOINED,
        ];
    }

    /** Average age (years) across every player of the game. */
    private function averageAge(): ?float
    {
        $ages = $this->gameTeams
            ->flatMap(fn(GameTeam $gameTeam) => $gameTeam->relationLoaded('players')
                ? $gameTeam->players->map(fn(GamePlayer $gp) => $gp->playerStat?->user?->birthday)
                : collect())
            ->filter()
            ->map(fn($birthday) => Carbon::parse($birthday)->age)
            ->values();

        return $ages->isNotEmpty() ? round((float) $ages->avg(), 1) : null;
    }

    private function fullName(?string $firstName, ?string $lastName): ?string
    {
        return trim(($firstName ?? '') . ' ' . ($lastName ?? '')) ?: null;
    }
}
