<?php

use App\Http\Controllers\Api\Booking\BookingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('bookings')->group(function () {
    Route::get('/',            [BookingController::class, 'index'])->name('bookings.index');
    Route::post('/',           [BookingController::class, 'store'])->name('bookings.store');
    Route::get('/{booking}',   [BookingController::class, 'show'])->name('bookings.show');
    Route::patch('/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
});
