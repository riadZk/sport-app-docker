<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A player roster entry for a specific Game Team in a specific game.
 * Selected players start `pending` and become `joined` only after
 * accepting their game player request.
 */
class GamePlayer extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_JOINED = 'joined';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_LEFT = 'left';

    protected $fillable = [
        'game_id',
        'game_team_id',
        'player_stat_id',
        'status',
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
}
