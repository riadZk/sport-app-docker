<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerStat extends Model
{
    use HasFactory;

    //
    protected $fillable = [
        'user_id',
        'sport_category_id',
        'primary_position',
        'secondary_position',
        'prefered_foot',
        'match_played',
        'nbr_goals',
        'pac',
        'dribble',
        'physique',
        'shoot',
        'pass',
        'defence',
        'assists',
    ];

    /**
     * Get the user that owns the player stats.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the sport category associated with the player stats.
     */
    public function sportCategory()
    {
        return $this->belongsTo(SportCategory::class);
    }

    public function teamMemberships()
    {
        return $this->hasMany(TeamMember::class, 'player_id');
    }

    public function invitations()
    {
        return $this->hasMany(TeamInvitation::class, 'player_stat_id');
    }
}
