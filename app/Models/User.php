<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, InteractsWithMedia, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'username',
        'phone_number',
        'phone_verified_at',
        'city',
        'country',
        'email',
        'password',
        'gender',
        'birthday',
        'cin',
        'height',
        'weight'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'media',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'picture_url',
        'has_password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the player stats for the user.
     */
    public function playerStats()
    {
        return $this->hasMany(PlayerStat::class);
    }

    /**
     * Get the game pitches owned by the user.
     */
    public function gamePitches()
    {
        return $this->hasMany(GamePitche::class);
    }

    /**
     * Get the teams owned by the user.
     */
    public function teams()
    {
        return $this->hasMany(Team::class);
    }

    /**
     * Get the roles assigned to the user.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }



    /**
     * Register media collections for the user.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('picture')
            ->useDisk('public')
            ->singleFile();
    }

    /**
     * Register media conversions.
     * All images are converted to WEBP format synchronously.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('webp')
            ->format('webp')
            ->quality(85)
            ->nonQueued()
            ->performOnCollections('picture');
    }

    /**
     * Get the user's picture URL.
     */
    public function getPictureUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('picture', 'webp') ?: null;
    }

    /**
     * Check if user has a password set.
     */
    public function getHasPasswordAttribute(): bool
    {
        return !empty($this->password);
    }
}
