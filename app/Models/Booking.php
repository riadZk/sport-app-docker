<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    protected $fillable = [
        'reference',
        'field_id',
        'user_id',
        'booking_date',
        'start_time',
        'end_time',
        'total_price',
        'status',
        'payment_status',
        'note',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'total_price'  => 'decimal:2',
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): HasOne
    {
        return $this->hasOne(BookingContact::class);
    }

    /**
     * The game this booking is attached to (games.booking_id), if any.
     */
    public function game(): HasOne
    {
        return $this->hasOne(Game::class);
    }

    /**
     * Scope: bookings that block a time slot (not cancelled/completed).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'confirmed']);
    }

    /**
     * Scope: bookings that overlap with a given time window on the same field/date.
     * Overlap condition: existing.start < new.end AND existing.end > new.start
     */
    public function scopeOverlapping(Builder $query, string $fieldId, string $date, string $startTime, string $endTime): Builder
    {
        return $query
            ->where('field_id', $fieldId)
            ->where('booking_date', $date)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);
    }
}
