<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SportCategory extends Model
{
    use HasFactory;

    //

    protected $fillable = [
        'name'
    ];

    /**
     * Get the player stats for the sport category.
     */
    public function playerStats()
    {
        return $this->hasMany(PlayerStat::class);
    }

    /**
     * Get the teams for the sport category.
     */
    public function teams()
    {
        return $this->hasMany(Team::class);
    }
}
