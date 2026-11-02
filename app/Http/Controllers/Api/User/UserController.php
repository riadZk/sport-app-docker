<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\User\RequestPhoneChangeRequest;
use App\Http\Requests\User\UpdateUserInfoRequest;
use App\Http\Requests\User\VerifyPhoneChangeRequest;
use App\Models\User;
use App\Models\WhatsappOtp;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class UserController extends ApiController
{
    protected WhatsappService $whatsappService;

    public function __construct(WhatsappService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    public function profile(Request $request)
    {
        $user = $request->user();
        $user->load('playerStats.sportCategory');

        return $this->jsonResponse($user, 'User profile retrieved successfully', 200);
    }

    public function updateInfo(UpdateUserInfoRequest $request)
    {
        $user = $request->user();

        $user->update($request->validated());

        $user->load('playerStats.sportCategory');

        return $this->jsonResponse($user, 'Profile updated successfully', 200);
    }

    public function updatePicture(Request $request)
    {
        $request->validate([
            'picture' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        try {
            $user = $request->user();

            $user->addMediaFromRequest('picture')
                ->usingFileName('picture_' . now()->timestamp . '.' . $request->file('picture')->getClientOriginalExtension())
                ->toMediaCollection('picture');

            return $this->jsonResponse([
                'picture_url' => $user->picture_url,
            ], 'Picture updated successfully', 200);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 'Failed to update picture: ' . $e->getMessage(), 500);
        }
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        // If user has no password, only require new password
        if (empty($user->password)) {
            $request->validate([
                'password' => 'required|string|min:8|confirmed',
            ]);
        } else {
            // If user has password, require current password
            $request->validate([
                'current_password' => 'required|string',
                'password' => 'required|string|min:8|confirmed',
            ]);

            if (!Hash::check($request->current_password, $user->password)) {
                return $this->jsonResponse(null, 'Current password is incorrect', 400);
            }
        }

        $user->update([
            'password' => $request->password,
        ]);

        $message = empty($user->getOriginal('password')) ? 'Password set successfully' : 'Password changed successfully';

        return $this->jsonResponse(null, $message, 200);
    }


    public function requestPhoneChange(RequestPhoneChangeRequest $request)
    {
        $user = $request->user();
        $rateLimitKey = 'phone_change_request:' . $user->id;

        // Rate limiting: max 3 requests per 15 minutes
        if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return $this->jsonResponse(
                ['retry_after' => $seconds],
                'Too many phone change requests. Please try again in ' . ceil($seconds / 60) . ' minutes.',
                429
            );
        }

        $newPhone = $request->new_phone;

        // Generate OTP using existing WhatsappOtp model
        $otpRecord = WhatsappOtp::generateOtp($newPhone, 5);

        // Send OTP via WhatsApp
        $appName = config('app.name', 'SportApp');
        $message = "🔐 *{$appName} Phone Verification*\n\n"
            . "Your verification code is: *{$otpRecord->otp}*\n\n"
            . "⏰ Valid for 5 minutes.\n"
            . "❌ Do not share this code with anyone.";

        $sent = $this->whatsappService->send($newPhone, $message);

        if (!$sent) {
            $otpRecord->delete();
            return $this->jsonResponse(null, 'Failed to send OTP. Please try again.', 500);
        }

        RateLimiter::hit($rateLimitKey, 900); // 15 minutes

        return $this->jsonResponse(
            ['expires_in' => 300, 'new_phone' => $newPhone],
            'OTP sent to your new phone number. Please verify within 5 minutes.',
            200
        );
    }


    public function verifyPhoneChange(VerifyPhoneChangeRequest $request)
    {
        $user = $request->user();
        $newPhone = $request->new_phone;

        // Double-check phone is still available (race condition protection)
        if (User::where('phone_number', $newPhone)->where('id', '!=', $user->id)->exists()) {
            return $this->jsonResponse(
                null,
                'This phone number is now registered to another account.',
                409
            );
        }

        // Verify OTP using existing WhatsappOtp model
        $result = WhatsappOtp::verifyOtp($newPhone, $request->otp);

        if (!$result['success']) {
            $statusCode = str_contains($result['message'], 'Too many') ? 429 : 400;
            return $this->jsonResponse(null, $result['message'], $statusCode);
        }

        // Update user's phone number
        $oldPhone = $user->phone_number;
        $user->update([
            'phone_number' => $newPhone,
            'phone_verified_at' => now(),
        ]);

        // Send security notification to old phone
        $appName = config('app.name', 'SportApp');
        $this->whatsappService->send(
            $oldPhone,
            "⚠️ *{$appName} Security Alert*\n\n"
                . "Your phone number has been changed to: {$newPhone}\n\n"
                . "If you did not make this change, please contact support immediately."
        );

        return $this->jsonResponse(
            ['phone_number' => $newPhone],
            'Phone number updated successfully.',
            200
        );
    }


}
