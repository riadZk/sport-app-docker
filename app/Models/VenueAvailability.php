<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenueAvailability extends Model
{
    protected $fillable = [
        'venue_id',
        'day_of_week',
        'start_time',
        'end_time',
        'valid_from',
        'valid_to',
        'is_active',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to'   => 'date',
        'is_active'  => 'boolean',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to availability rows valid on a given calendar date, including
     * year-round rows (null window) and date-bounded seasonal rows.
     */
    public function scopeValidOn(Builder $query, string $date): Builder
    {
        return $query
            ->where(fn($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $date))
            ->where(fn($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date));
    }
}
