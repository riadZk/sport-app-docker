<?php

use App\Http\Controllers\Api\Venue\VenueController;
use App\Http\Controllers\Api\Venue\VenueAvailabilityController;
use App\Http\Controllers\Api\Venue\VenueReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('venues')->group(function () {
        Route::get('/map',      [VenueController::class, 'map'])->name('venues.map');
        Route::get('/nearby',   [VenueController::class, 'nearby'])->name('venues.nearby');
        Route::get('/listing',  [VenueController::class, 'listing'])->name('venues.listing');
        Route::get('/',         [VenueController::class, 'index'])->name('venues.index');
        Route::post('/',          [VenueController::class, 'store'])->name('venues.store');
        Route::get('/{venue}',               [VenueController::class, 'show'])->name('venues.show');
        Route::get('/{venue}/fields-by-format', [VenueController::class, 'fieldsByFormat'])->name('venues.fields-by-format.venue');
        Route::get('/{venue}/fields/{field}', [VenueController::class, 'showField'])->name('venues.fields.show');
        Route::put('/{venue}',               [VenueController::class, 'update'])->name('venues.update');
        Route::delete('/{venue}',            [VenueController::class, 'destroy'])->name('venues.destroy');

        // ── Gallery ────────────────────────────────────────────────────────
        Route::delete('/{venue}/gallery/{media}', [VenueController::class, 'deleteGalleryImage'])->name('venues.gallery.destroy');

        // ── Availability & Closures ────────────────────────────────────────
        Route::post('/{venue}/availabilities', [VenueAvailabilityController::class, 'storeAvailability'])->name('venues.availabilities.store');
        Route::post('/{venue}/closures',       [VenueAvailabilityController::class, 'storeClosure'])->name('venues.closures.store');
        Route::get('/fields/{field}/availability', [VenueAvailabilityController::class, 'fieldAvailability'])->name('venues.fields.availability');

        // ── Venue Reviews ──────────────────────────────────────────────────
        Route::prefix('{venue}/reviews')->group(function () {
            Route::get('/summary',     [VenueReviewController::class, 'summary'])->name('venue-reviews.summary');
            Route::get('/',            [VenueReviewController::class, 'index'])->name('venue-reviews.index');
            Route::post('/',           [VenueReviewController::class, 'store'])->name('venue-reviews.store');
            Route::get('/{review}',    [VenueReviewController::class, 'show'])->name('venue-reviews.show');
            Route::put('/{review}',    [VenueReviewController::class, 'update'])->name('venue-reviews.update');
            Route::delete('/{review}', [VenueReviewController::class, 'destroy'])->name('venue-reviews.destroy');
        });
    });
});

