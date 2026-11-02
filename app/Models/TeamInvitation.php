<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamInvitation extends Model
{
    protected $fillable = [
        'team_id',
        'player_stat_id',
        'initiated_by',
        'type',
        'status',
        'message',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function playerStat()
    {
        return $this->belongsTo(PlayerStat::class, 'player_stat_id');
    }

    public function initiatedBy()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
