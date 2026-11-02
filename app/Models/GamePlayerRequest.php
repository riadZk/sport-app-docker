<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Invitation/request sent to an individually selected player for a Game Team.
 * The player is NOT a confirmed member of the game side until they accept.
 */
class GamePlayerRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'game_id',
        'game_team_id',
        'player_stat_id',
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

    public function gameTeam(): BelongsTo
    {
        return $this->belongsTo(GameTeam::class);
    }

    public function playerStat(): BelongsTo
    {
        return $this->belongsTo(PlayerStat::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /** The roster entry this request controls (pending → joined/declined). */
    public function gamePlayer(): HasOne
    {
        return $this->hasOne(GamePlayer::class, 'game_team_id', 'game_team_id')
            ->where('player_stat_id', $this->player_stat_id);
    }
}
