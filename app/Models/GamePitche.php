<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GamePitche extends Model
{
    //

    protected $fillable = [
        'user_id',
        'name',
        'address',
        'latitude',
        'longitude',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
