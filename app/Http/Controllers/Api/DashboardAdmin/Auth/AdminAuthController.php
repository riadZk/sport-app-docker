<?php

namespace App\Http\Controllers\Api\DashboardAdmin\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Auth\LoginByEmailRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends ApiController
{
    public function login(LoginByEmailRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password) || ! $user->isAdmin()) {
            return $this->jsonResponse(null, 'Invalid credentials', 401);
        }

        $token = $user->createToken('admin_token')->plainTextToken;

        return $this->jsonResponse([
            'user'  => $user,
            'token' => $token,
        ], 'Admin login successful', 200);
    }
}
