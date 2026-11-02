<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldBooking extends Model
{
    protected $fillable = [
        'field_id',
        'user_id',
        'team_id',
        'starts_at',
        'ends_at',
        'duration_minutes',
        'status',
        'price_total',
        'currency',
        'field_price_id',
        'notes',
        'cancelled_at',
    ];

    protected $casts = [
        'starts_at'        => 'datetime',
        'ends_at'          => 'datetime',
        'duration_minutes' => 'integer',
        'price_total'      => 'decimal:2',
        'cancelled_at'     => 'datetime',
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function fieldPrice(): BelongsTo
    {
        return $this->belongsTo(FieldPrice::class);
    }

    /**
     * Scope to bookings that block a slot (pending or confirmed).
     */
    public function scopeBlocking(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'confirmed']);
    }
}
