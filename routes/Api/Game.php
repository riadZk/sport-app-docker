<?php

use App\Http\Controllers\Api\Game\GameController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('games')->group(function () {
    // Static segments first so they never collide with /{game} binding.
    Route::get('/challenges',             [GameController::class, 'challenges'])->name('games.challenges.index');
    Route::post('/challenges/{challenge}/respond', [GameController::class, 'respondToChallenge'])->name('games.challenges.respond');
    Route::get('/players', [GameController::class, 'allPlayers'])->name('games.players.index');
    Route::get('/teams', [GameController::class, 'allTeams'])->name('games.teams.index');

    Route::get('/player-requests',        [GameController::class, 'myPlayerRequests'])->name('games.player-requests.index');
    Route::post('/player-requests/{player_request}/respond', [GameController::class, 'respondToPlayerRequest'])->name('games.player-requests.respond');
    Route::get('/pitches',                [GameController::class, 'indexPitches'])->name('games.pitches.index');
    Route::post('/pitches',               [GameController::class, 'storePitch'])->name('games.pitches.store');
    Route::delete('/pitches/{pitch}',     [GameController::class, 'destroyPitch'])->name('games.pitches.destroy');
    Route::get('/bookings/available',     [GameController::class, 'availableBookings'])->name('games.bookings.available');

    // CRUD
    Route::get('/',                       [GameController::class, 'index'])->name('games.index');
    Route::post('/',                      [GameController::class, 'store'])->name('games.store');
    Route::get('/{game}',                 [GameController::class, 'show'])->name('games.show');
    Route::put('/{game}',                 [GameController::class, 'update'])->name('games.update');
    Route::delete('/{game}',              [GameController::class, 'destroy'])->name('games.destroy');

    // Step 1 — booking
    Route::post('/{game}/booking',        [GameController::class, 'attachBooking'])->name('games.booking.attach');

    // Step 3 — game teams & players (pick-players flow for the away side)
    Route::post('/{game}/players',        [GameController::class, 'attachPlayers'])->name('games.players.attach');
    Route::delete('/{game}/players/{playerStat}', [GameController::class, 'detachPlayer'])->name('games.players.detach');

    // Step 4 — opponent
    Route::get('/{game}/recommended-opponents', [GameController::class, 'recommendedOpponents'])->name('games.opponents.recommended');
    Route::post('/{game}/opponent/challenge',   [GameController::class, 'challenge'])->name('games.opponent.challenge');

    // Lifecycle
    Route::post('/{game}/complete',       [GameController::class, 'complete'])->name('games.complete');
    Route::post('/{game}/cancel',         [GameController::class, 'cancel'])->name('games.cancel');

    // Prediction voting (home_win / draw / away_win)
    Route::post('/{game}/predictions',    [GameController::class, 'storePrediction'])->name('games.predictions.store');
    Route::get('/{game}/predictions',     [GameController::class, 'indexPredictions'])->name('games.predictions.index');
    Route::delete('/{game}/predictions',  [GameController::class, 'destroyPrediction'])->name('games.predictions.destroy');
});
