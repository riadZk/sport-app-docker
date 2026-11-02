<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamMember extends Model
{
    protected $fillable = [
        'player_id',
        'team_id',
        'role',
        'position',
        'shirt_number',
        'status',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function playerStat()
    {
        return $this->belongsTo(PlayerStat::class, 'player_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
