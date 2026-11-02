<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LoginByEmailRequest;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Http\Requests\Auth\SignupRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\WhatsappService;
use App\Notifications\WelcomeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
class AuthController extends ApiController
{
    public function __construct(
        protected WhatsappService $whatsappService,
        protected NotificationService $notificationService,
    ) {}


    public function login(LoginRequest $request)
    {
        $user = User::where('phone_number', $request->phone_number)->first();

        if (!$user) {
            return $this->jsonResponse(null, 'User not found', 404);
        }

        if (!$user->hasRole('player')) {
            return $this->jsonResponse(null, 'Only players can log in through this endpoint.', 403);
        }

        if (!$user->phone_verified_at) {
            return $this->jsonResponse(null, 'Phone number is not verified. Please complete registration first.', 403);
        }

        $result = $this->whatsappService->sendOtp($request->phone_number);

        return $this->jsonResponse(
            ['phone_number' => $request->phone_number],
            $result['message'],
            $result['success'] ? 200 : 400
        );
    }


    public function resendLoginOtp(ResendOtpRequest $request)
    {
        $user = User::where('phone_number', $request->phone_number)->first();

        if (!$user) {
            return $this->jsonResponse(null, 'User not found', 404);
        }

        if (!$user->phone_verified_at) {
            return $this->jsonResponse(null, 'Phone number is not verified. Please complete registration first.', 403);
        }

        $result = $this->whatsappService->sendOtp($request->phone_number);

        return $this->jsonResponse(
            ['phone_number' => $request->phone_number],
            $result['message'],
            $result['success'] ? 200 : 400
        );
    }


    public function verifySignupOtp(VerifyOtpRequest $request)
    {
        $user = User::where('phone_number', $request->phone_number)->first();

        if (!$user) {
            return $this->jsonResponse(null, 'User not found', 404);
        }

        if ($user->phone_verified_at) {
            return $this->jsonResponse(null, 'Phone number is already verified.', 400);
        }

        $result = $this->whatsappService->verifyOtp($request->phone_number, $request->otp);

        if ($result['success']) {
            $user->update(['phone_verified_at' => now()]);

            // Send welcome notification (Reverb + WhatsApp)
            $this->notificationService->send(
                $user,
                new WelcomeNotification($user->first_name),
            );
        }

        return $this->jsonResponse($result['success'] ? $user : null, $result['message'], $result['success'] ? 200 : 400);
    }


    public function resendSignupOtp(ResendOtpRequest $request)
    {
        $user = User::where('phone_number', $request->phone_number)->first();

        if (!$user) {
            return $this->jsonResponse(null, 'User not found', 404);
        }

        if ($user->phone_verified_at) {
            return $this->jsonResponse(null, 'Phone number is already verified.', 400);
        }

        $result = $this->whatsappService->sendOtp($request->phone_number);

        return $this->jsonResponse(
            ['phone_number' => $request->phone_number],
            $result['message'],
            $result['success'] ? 200 : 400
        );
    }


    public function verifyLoginOtp(VerifyOtpRequest $request)
    {
        $user = User::where('phone_number', $request->phone_number)->first();

        if (!$user) {
            return $this->jsonResponse(null, 'User not found', 404);
        }

        if (!$user->hasRole('player')) {
            return $this->jsonResponse(null, 'Only players can log in through this endpoint.', 403);
        }

        $result = $this->whatsappService->verifyOtp($request->phone_number, $request->otp);

        if (!$result['success']) {
            return $this->jsonResponse(null, $result['message'], 400);
        }

        // Update phone_verified_at if not already verified
        if (!$user->phone_verified_at) {
            $user->update(['phone_verified_at' => now()]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->jsonResponse(['user' => $user, 'token' => $token], 'Login successful', 200);
    }


    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->jsonResponse(null, 'Logged out successfully', 200);
    }


    public function signup(SignupRequest $request)
    {
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'username' => $request->username,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'city' => $request->city,
            'country' => $request->country,
            'gender' => $request->gender,
            'birthday' => $request->birthday,
            'cin' => $request->cin,
        ]);

        // Every user registered through this endpoint is a player
        $playerRole = Role::where('name', 'player')->first();
        if ($playerRole) {
            $user->roles()->attach($playerRole->id);
        }

        // Create player stats with sport_category_id = 1
        $user->playerStats()->create([
            'sport_category_id' => 1,
            'primary_position' => $request->primary_position,
            'secondary_position' => $request->secondary_position,
            'prefered_foot' => $request->prefered_foot,
            'match_played' => $request->match_played ?? 0,
            'nbr_goals' => $request->nbr_goals ?? 0,
            'pac' => $request->pac ?? 0,
            'dribble' => $request->dribble ?? 0,
            'physique' => $request->physique ?? 0,
            'shoot' => $request->shoot ?? 0,
            'pass' => $request->pass ?? 0,
            'assists' => $request->assists ?? 0,
            'defence' => $request->defence ?? 0,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        // Send OTP for phone verification if phone number is provided
        $otpResult = null;
        if ($request->phone_number) {
            $otpResult = $this->whatsappService->sendOtp($request->phone_number);
        }

        return $this->jsonResponse([
            'user' => $user,
            'token' => $token,
            'otp_sent' => $otpResult['success'] ?? false,
            'otp_message' => $otpResult['message'] ?? 'No phone number provided for OTP verification',
        ], 'User registered successfully', 201);
    }


    public function login_by_email(LoginByEmailRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->jsonResponse(null, 'Invalid credentials', 401);
        }

        if (!$user->hasRole('player')) {
            return $this->jsonResponse(null, 'Only players can log in through this endpoint.', 403);
        }

        if (!$user->phone_verified_at) {
            return $this->jsonResponse(null, 'Phone number is not verified. Please complete registration first.', 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->jsonResponse(['user' => $user, 'token' => $token], 'Login successful', 200);
    }


}




