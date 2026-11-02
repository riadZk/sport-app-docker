<?php

use App\Http\Controllers\Api\Team\TeamController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('teams')->group(function () {

        Route::get('/', [TeamController::class, 'index'])->name('teams.index');
        Route::post('/', [TeamController::class, 'store'])->name('teams.store');
        Route::get('/default-logos', [TeamController::class, 'defaultLogos'])->name('teams.default-logos');
        Route::get('/{team}', [TeamController::class, 'show'])->name('teams.show');
        Route::put('/{team}', [TeamController::class, 'update'])->name('teams.update');
        Route::post('/{team}/logo', [TeamController::class, 'updateLogo'])->name('teams.update-logo');
    });
});
