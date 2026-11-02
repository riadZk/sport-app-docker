<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Game Team is the per-game container for one side of a match.
 *
 * PERMANENT side: team_id points to an existing teams row (roster copied from
 * the real team membership). TEMPORARY side: team_id is NULL — the side was
 * built by individually picking players and exists ONLY for this game; it is
 * never inserted into `teams` and never appears in "My Teams".
 */
class GameTeam extends Model
{
    public const TYPE_PERMANENT = 'permanent';
    public const TYPE_TEMPORARY = 'temporary';

    public const SIDE_HOME = 'home';
    public const SIDE_AWAY = 'away';

    protected $fillable = [
        'game_id',
        'team_id',
        'owner_id',
        'name',
        'type',
        'side',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** The underlying permanent team (null for temporary sides). */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(GamePlayer::class);
    }

    public function joinedPlayers(): HasMany
    {
        return $this->players()->where('status', GamePlayer::STATUS_JOINED);
    }

    public function playerRequests(): HasMany
    {
        return $this->hasMany(GamePlayerRequest::class);
    }

    public function challengesSent(): HasMany
    {
        return $this->hasMany(GameChallenge::class, 'challenging_game_team_id');
    }

    public function isPermanent(): bool
    {
        return $this->type === self::TYPE_PERMANENT;
    }

    public function isTemporary(): bool
    {
        return $this->type === self::TYPE_TEMPORARY;
    }

    public function isOwnedBy(int $userId): bool
    {
        return $this->owner_id === $userId;
    }
}
