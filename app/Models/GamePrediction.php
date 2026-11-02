<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's prediction for the outcome of a game:
 * home_win (home team wins), draw or away_win (away team wins).
 * One row per user per game — voting again updates the prediction.
 */
class GamePrediction extends Model
{
    public const PREDICTION_HOME_WIN = 'home_win';
    public const PREDICTION_DRAW = 'draw';
    public const PREDICTION_AWAY_WIN = 'away_win';

    public const PREDICTIONS = [
        self::PREDICTION_HOME_WIN,
        self::PREDICTION_DRAW,
        self::PREDICTION_AWAY_WIN,
    ];

    protected $fillable = [
        'game_id',
        'user_id',
        'prediction',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isHomeWin(): bool
    {
        return $this->prediction === self::PREDICTION_HOME_WIN;
    }

    public function isDraw(): bool
    {
        return $this->prediction === self::PREDICTION_DRAW;
    }

    public function isAwayWin(): bool
    {
        return $this->prediction === self::PREDICTION_AWAY_WIN;
    }
}
