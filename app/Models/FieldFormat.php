<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FieldFormat extends Model
{
    protected $fillable = [
        'name',
        'players_per_team',
        'description',
    ];

    protected $casts = [
        'players_per_team' => 'integer',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(Field::class);
    }
}
