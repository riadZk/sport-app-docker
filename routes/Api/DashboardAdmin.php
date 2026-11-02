<?php

use App\Http\Controllers\Api\DashboardAdmin\Auth\AdminAuthController;
use App\Http\Controllers\Api\DashboardAdmin\Booking\AdminBookingController;
use App\Http\Controllers\Api\DashboardAdmin\Field\AdminFieldController;
use App\Http\Controllers\Api\DashboardAdmin\Player\AdminPlayerController;
use App\Http\Controllers\Api\DashboardAdmin\Team\AdminTeamController;
use App\Http\Controllers\Api\DashboardAdmin\Venue\AdminAmenityController;
use App\Http\Controllers\Api\DashboardAdmin\Venue\AdminVenueController;
use Illuminate\Support\Facades\Route;

// Public admin route (no auth required)
Route::post('admin/login', [AdminAuthController::class, 'login'])->name('admin.login');

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {

    // Players
    Route::get('/players', [AdminPlayerController::class, 'getPlayers']);
    Route::get('/players/{id}', [AdminPlayerController::class, 'detailPlayer']);
    Route::post('/players', [AdminPlayerController::class, 'createPlayer']);
    Route::post('/players/{id}', [AdminPlayerController::class, 'updatePlayer']);
    Route::post('/players/{id}/picture', [AdminPlayerController::class, 'updatePlayerPicture']);
    Route::delete('/players/{id}', [AdminPlayerController::class, 'deletePlayer']);

    // Teams
    Route::get('/teams', [AdminTeamController::class, 'getTeams']);
    Route::get('/teams/{id}', [AdminTeamController::class, 'detailTeam']);
    Route::post('/teams', [AdminTeamController::class, 'store']);
    Route::post('/teams/{id}', [AdminTeamController::class, 'updateTeam']);
    Route::post('/teams/{id}/logo', [AdminTeamController::class, 'updateTeamLogo']);
    Route::post('/teams/{id}/invitations', [AdminTeamController::class, 'sendInvitation']);
    Route::post('/teams/{id}/invitations/bulk', [AdminTeamController::class, 'bulkInvite']);
    Route::delete('/teams/{id}/members/{memberId}', [AdminTeamController::class, 'deleteMember']);
    Route::delete('/teams/{id}', [AdminTeamController::class, 'deleteTeam']);

    // Venues
    Route::get('/venues/listing', [AdminVenueController::class, 'listing'])->name('admin.venues.listing');
    Route::get('/venues/{id}', [AdminVenueController::class, 'detailVenue']);
    Route::post('/venues', [AdminVenueController::class, 'store']);
    Route::put('/venues/{id}', [AdminVenueController::class, 'updateVenue']);
    Route::post('/venues/{id}/logo', [AdminVenueController::class, 'updateLogo']);
    Route::post('/venues/{id}/cover', [AdminVenueController::class, 'updateCover']);
    Route::post('/venues/{id}/gallery', [AdminVenueController::class, 'updateGallery']);
    Route::put('/venues/{id}/amenities', [AdminVenueController::class, 'updateAmenities']);
    Route::get('/venues/{id}/fields', [AdminVenueController::class, 'getFieldsByFormat']);
    Route::put('/venues/{id}/fields', [AdminVenueController::class, 'updateFields']);
    Route::delete('/venues/{id}', [AdminVenueController::class, 'deleteVenue']);
    Route::get('/amenities', [AdminAmenityController::class, 'getAmenities']);
    Route::get('/field-formats', [AdminAmenityController::class, 'getFieldFormats']);

    // Bookings
    Route::get('/bookings', [AdminBookingController::class, 'listing']);
    Route::get('/bookings/{id}', [AdminBookingController::class, 'detail']);
});
