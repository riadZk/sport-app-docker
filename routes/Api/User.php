



<?php
use App\Http\Controllers\Api\User\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    // User profile routes
    Route::prefix('user')->group(function () {

        Route::get('/', [UserController::class, 'profile'])->name('user.profile');
        Route::patch('/', [UserController::class, 'updateInfo'])->name('user.update.info');
        Route::post('/update-picture', [UserController::class, 'updatePicture'])->name('user.update.picture');
        Route::patch('/change-password', [UserController::class, 'changePassword'])->name('user.change.password');

        // Phone change routes
        Route::post('/request-phone-change', [UserController::class, 'requestPhoneChange'])->name('user.request.phone.change');
        Route::patch('/verify-phone-change', [UserController::class, 'verifyPhoneChange'])->name('user.verify.phone.change');

    });
});
