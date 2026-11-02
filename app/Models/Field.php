<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Field extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'venue_id',
        'field_format_id',
        'sport_category_id',
        'name',
        'surface_type',
        'width_m',
        'length_m',
        'is_indoor',
        'is_active',
    ];

    protected $casts = [
        'width_m'   => 'decimal:2',
        'length_m'  => 'decimal:2',
        'is_indoor' => 'boolean',
        'is_active' => 'boolean',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function format(): BelongsTo
    {
        return $this->belongsTo(FieldFormat::class, 'field_format_id');
    }

    public function sportCategory(): BelongsTo
    {
        return $this->belongsTo(SportCategory::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(FieldPrice::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(FieldBooking::class);
    }

    // ─── Media ───────────────────────────────────────────────────────────────

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')
            ->singleFile();

        $this->addMediaCollection('gallery');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(400)
            ->height(300)
            ->format('webp')
            ->performOnCollections('cover', 'gallery');

        $this->addMediaConversion('card')
            ->width(800)
            ->height(600)
            ->format('webp')
            ->performOnCollections('cover', 'gallery');
    }
}
