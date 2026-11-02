<?php


use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\User\UserController;
use App\Http\Controllers\Api\Player\PlayerController;
use Illuminate\Support\Facades\Route;

require base_path('routes/Api/Team.php');
require base_path('routes/Api/TeamInvitation.php');
require base_path('routes/Api/DashboardAdmin.php');
require base_path('routes/Api/User.php');
require base_path('routes/Api/PlayerStat.php');
require base_path('routes/Api/Venue.php');
require base_path('routes/Api/Field.php');
require base_path('routes/Api/Booking.php');
require base_path('routes/Api/Game.php');
require base_path('routes/Api/Notification.php');

Route::get('/sendWhatsappMessageTest', [AuthController::class, 'sendWhatsappMessageTest']);

Route::middleware('guest')->group(function () {

    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('login_by_email', [AuthController::class, 'login_by_email'])->name('login.by.email');
    Route::post('/signup', [AuthController::class, 'signup'])->name('signup');

    Route::post('verify-login-otp', [AuthController::class, 'verifyLoginOtp'])->name('verify.login.otp');
    Route::post('resend-login-otp', [AuthController::class, 'resendLoginOtp'])->name('resend.login.otp');

    Route::post('verify-signup-otp', [AuthController::class, 'verifySignupOtp'])->name('verify.signup.otp');
    Route::post('resend-signup-otp', [AuthController::class, 'resendSignupOtp'])->name('resend.signup.otp');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
});
