<?php
// app/Http/Controllers/API/AuthController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\User;
use App\Models\ShopOwner;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return ApiResponse::unauthorized('Invalid credentials');
            }

            // Check if account is active
            if (!$user->is_active) {
                return ApiResponse::forbidden('Your account is not active');
            }

            // Update last login
            $user->last_login = now();
            $user->save();

            // Create token with role as ability
            $token = $user->createToken('auth_token', [$user->role])->plainTextToken;

            $userData = $user->loadMissing('shop')->toArray();

            return ApiResponse::success([
                'user' => $userData,
                'token' => $token
            ], 'Login successful');

        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to login: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Logout
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        try {
            // Revoke the token that was used to authenticate the current request
            $request->user()->currentAccessToken()->delete();

            return ApiResponse::success(null, 'Logout successful');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to logout: ' . $e->getMessage(), 500);
        }
    }
}
