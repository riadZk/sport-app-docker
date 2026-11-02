<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Team extends Model implements HasMedia
{
    use InteractsWithMedia, SoftDeletes;

    protected $attributes = [
        'sport_category_id' => 1,
    ];

    protected $fillable = [
        'name',
        'short_name',
        'city',
        'address',
        'logo_type',
        'default_logo_id',
        'primary_color',
        'secondary_color',
        'match_type',
        'formation',
        'user_id',
        'sport_category_id',
    ];

    protected $appends = [
        'logo_url',
    ];

    protected $hidden = [
        'media',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sportCategory()
    {
        return $this->belongsTo(SportCategory::class);
    }

    public function members()
    {
        return $this->hasMany(TeamMember::class);
    }

    public function activeMembers()
    {
        return $this->hasMany(TeamMember::class)->where('status', 'active');
    }

    public function invitations()
    {
        return $this->hasMany(TeamInvitation::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        if ($this->logo_type === 'upload') {
            return $this->getFirstMediaUrl('logo') ?: null;
        }

        return $this->default_logo_id ?: null;
    }
}
