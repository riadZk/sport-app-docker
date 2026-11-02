<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldPrice extends Model
{
    protected $fillable = [
        'field_id',
        'label',
        'pricing_type',
        'day_of_week',
        'start_time',
        'end_time',
        'price',
        'currency',
        'priority',
        'valid_from',
        'valid_to',
        'min_booking_hours',
        'max_booking_hours',
        'slot_duration',
        'is_active',
    ];

    protected $casts = [
        'price'             => 'decimal:2',
        'currency'          => 'string',
        'priority'          => 'integer',
        'min_booking_hours' => 'integer',
        'max_booking_hours' => 'integer',
        'slot_duration'     => 'integer',
        'is_active'         => 'boolean',
        'valid_from'        => 'date',
        'valid_to'          => 'date',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to rules valid on a given calendar date, including global rules
     * (null valid_from / valid_to) and date-bounded seasonal rules.
     */
    public function scopeValidOn(Builder $query, string $date): Builder
    {
        return $query->where(function (Builder $q) use ($date) {
            // global rules (no date restriction)
            $q->whereNull('valid_from')->whereNull('valid_to');
        })->orWhere(function (Builder $q) use ($date) {
            // seasonal/Ramadan rules that cover the date
            $q->whereNotNull('valid_from')
                ->whereNotNull('valid_to')
                ->whereDate('valid_from', '<=', $date)
                ->whereDate('valid_to', '>=', $date);
        });
    }

    /**
     * Scope to rules that match a given day-of-week string (or have no
     * day restriction at all).
     */
    public function scopeForDay(Builder $query, ?string $dayOfWeek): Builder
    {
        return $query->where(function (Builder $q) use ($dayOfWeek) {
            $q->whereNull('day_of_week')
                ->orWhere('day_of_week', $dayOfWeek);
        });
    }

    /**
     * Scope to rules whose time window overlaps the supplied range.
     * Rules with no time window apply at any time.
     */
    public function scopeForTimeRange(Builder $query, string $startTime, string $endTime): Builder
    {
        return $query->where(function (Builder $q) use ($startTime, $endTime) {
            // no time restriction
            $q->whereNull('start_time')->whereNull('end_time');
        })->orWhere(function (Builder $q) use ($startTime, $endTime) {
            // overlapping time window
            $q->whereNotNull('start_time')
                ->whereNotNull('end_time')
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime);
        });
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Whether this rule applies on a weekend (Saturday or Sunday). */
    public function isWeekend(): bool
    {
        return in_array($this->day_of_week, ['saturday', 'sunday']);
    }

    /** Whether this rule is a night-time rule (start_time >= 20:00 or end_time <= 06:00). */
    public function isNightPricing(): bool
    {
        if (! $this->start_time) {
            return false;
        }

        return $this->start_time >= '20:00:00' || $this->end_time <= '06:00:00';
    }

    /** Whether this is a seasonal / Ramadan rule. */
    public function isSeasonal(): bool
    {
        return ! is_null($this->valid_from) && ! is_null($this->valid_to);
    }
}
