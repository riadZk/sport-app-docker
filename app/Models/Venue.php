<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Venue extends Model implements HasMedia
{
    use InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'address',
        'city',
        'country',
        'phone',
        'email',
        'latitude',
        'longitude',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'latitude'  => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_active' => 'boolean',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(Field::class);
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(VenueAvailability::class);
    }

    public function closures(): HasMany
    {
        return $this->hasMany(VenueClosure::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'venue_amenity');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(VenueReview::class);
    }

    public function fieldPrices(): HasManyThrough
    {
        return $this->hasManyThrough(FieldPrice::class, Field::class);
    }

    // ─── Rating helpers ───────────────────────────────────────────────────────

    public function averageRating(): ?float
    {
        $avg = $this->reviews()->avg('rating');

        return $avg !== null ? round((float) $avg, 1) : null;
    }

    public function reviewCount(): int
    {
        return $this->reviews()->count();
    }

    public function ratingBreakdown(): array
    {
        $counts = $this->reviews()
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating')
            ->toArray();

        return array_replace(
            [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            array_map('intval', $counts),
        );
    }

    public function ratingSummary(): array
    {
        return [
            'average'   => $this->averageRating(),
            'count'     => $this->reviewCount(),
            'breakdown' => $this->ratingBreakdown(),
        ];
    }

    // ─── Media ────────────────────────────────────────────────────────────────

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();

        $this->addMediaCollection('cover')->singleFile();

        $this->addMediaCollection('gallery');
    }
}
