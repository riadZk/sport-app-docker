<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameChallenge extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'game_id',
        'challenging_game_team_id',
        'challenged_game_team_id',
        'challenged_team_id',
        'challenged_user_id',
        'initiated_by',
        'status',
        'message',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** The home Game Team sending the challenge (permanent or temporary). */
    public function challengingGameTeam(): BelongsTo
    {
        return $this->belongsTo(GameTeam::class, 'challenging_game_team_id');
    }

    /** Set when the opponent already answered by materializing their side. */
    public function challengedGameTeam(): BelongsTo
    {
        return $this->belongsTo(GameTeam::class, 'challenged_game_team_id');
    }

    /** Offered option 1: "play as this permanent team". */
    public function challengedTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'challenged_team_id');
    }

    /** Offered option 2: "play as these picked players" (owner decides). */
    public function challengedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'challenged_user_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
