<?php

use App\Http\Controllers\Api\PlayerStat\PlayerStatController;
use Illuminate\Support\Facades\Route;

Route::prefix('player-stats')->group(function () {


    Route::middleware('auth:sanctum')->group(function () {

        // Public — no auth required
        Route::get('/', [PlayerStatController::class, 'all'])->name('player-stats.all');

        // Authenticated user's own player stats
        Route::get('/mine', [PlayerStatController::class, 'myStats'])->name('player-stats.my-stats');

        // All active player stats for a specific team
        Route::get('/team/{team}', [PlayerStatController::class, 'teamStats'])->name('player-stats.team');

        // Update position fields only
        Route::patch('/{playerStat}/position', [PlayerStatController::class, 'updatePosition'])->name('player-stats.update-position');
    });
});
