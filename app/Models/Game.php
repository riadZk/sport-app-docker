<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Game extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_ONGOING = 'ongoing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'game_pitch_id',
        'booking_id',
        'game_date',
        'start_time',
        'duration',
        'game_type',
        'gender',
        'privacy',
        'status',
    ];

    protected $casts = [
        'game_date' => 'date:Y-m-d',
        'start_time' => 'datetime:H:i',
        'duration' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pitch(): BelongsTo
    {
        return $this->belongsTo(GamePitche::class, 'game_pitch_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * The two sides of the game. Each side is a Game Team: either a
     * PERMANENT team (teams.id) or a TEMPORARY pick-players side that
     * exists only for this game.
     */
    public function gameTeams(): HasMany
    {
        return $this->hasMany(GameTeam::class);
    }

    public function homeGameTeam(): HasOne
    {
        return $this->hasOne(GameTeam::class)->where('side', GameTeam::SIDE_HOME);
    }

    public function awayGameTeam(): HasOne
    {
        return $this->hasOne(GameTeam::class)->where('side', GameTeam::SIDE_AWAY);
    }

    public function challenges(): HasMany
    {
        return $this->hasMany(GameChallenge::class);
    }

    /** Outcome predictions (home_win / draw / away_win) by any user. */
    public function predictions(): HasMany
    {
        return $this->hasMany(GamePrediction::class);
    }

    /** The authenticated-style prediction of a specific user (or null). */
    public function predictionOf(int $userId): ?GamePrediction
    {
        return $this->predictions->firstWhere('user_id', $userId);
    }

    public function pendingChallenges(): HasMany
    {
        return $this->challenges()->where('status', 'pending');
    }

    // ── Scopes ──────────────────────────────────────────────────────────

    /** Public games only (private ones are excluded). */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('privacy', 'public');
    }

    /** Games that have not been cancelled. */
    public function scopeActiveStatus(Builder $query): Builder
    {
        return $query->whereNot('status', self::STATUS_CANCELLED);
    }

    /**
     * Games of a user scheduled at a time overlapping the given window.
     * Overlap math is done in PHP so it works on MySQL and SQLite alike
     * (TIME_TO_SEC is not available on SQLite).
     */
    public function scopeOverlapping(Builder $query, int $userId, string $date, string $startTime, int $durationMinutes): Builder
    {
        $start = date('H:i:s', strtotime($startTime));
        $end = date('H:i:s', strtotime($startTime) + $durationMinutes * 60);

        $candidateIds = static::query()
            ->select('id', 'start_time', 'duration')
            ->where('user_id', $userId)
            ->where('game_date', $date)
            ->whereIn('status', [self::STATUS_DRAFT, self::STATUS_SCHEDULED, self::STATUS_ONGOING])
            ->where('start_time', '<', $end)
            ->get()
            ->filter(fn(self $game) => strtotime($game->start_time) + $game->duration * 60 > strtotime($start))
            ->pluck('id');

        return $query->whereIn('id', $candidateIds->all());
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /** Max players per side derived from the game type (5v5 → 5). */
    public function playersPerSide(): int
    {
        return (int) explode('v', $this->game_type)[0];
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isOwnedBy(int $userId): bool
    {
        return $this->user_id === $userId;
    }

    /** Whether voting is still open (game not finished nor cancelled). */
    public function isVotingOpen(): bool
    {
        return ! in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED], true);
    }

    /** Tallies per outcome: ['home_win' => n, 'draw' => n, 'away_win' => n, 'total' => n]. */
    public function predictionTallies(): array
    {
        $tallies = array_fill_keys(GamePrediction::PREDICTIONS, 0);

        foreach ($this->predictions as $prediction) {
            $tallies[$prediction->prediction]++;
        }

        $tallies['total'] = count($this->predictions);

        return $tallies;
    }
}
