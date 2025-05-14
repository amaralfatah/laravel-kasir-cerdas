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
use Illuminate\Support\Facades\DB;
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
        // Your existing login method is good
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
     * Register a new owner account
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        try {
            $validator = validator($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'shop_name' => 'required|string|max:255',
                'shop_address' => 'required|string',
                'shop_phone' => 'nullable|string|max:20',
                'shop_tax_id' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors());
            }

            DB::beginTransaction();

            // Create user with owner role
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'owner',  // Only owner role can self-register
                'is_active' => true,
            ]);

            // Create shop
            $shop = Shop::create([
                'name' => $request->shop_name,
                'address' => $request->shop_address,
                'phone' => $request->shop_phone,
                'tax_id' => $request->shop_tax_id,
                'is_active' => true,
            ]);

            // Assign user as shop owner
            ShopOwner::create([
                'user_id' => $user->id,
                'shop_id' => $shop->id,
                'is_primary_owner' => true,
            ]);

            DB::commit();

            // Create token with owner ability
            $token = $user->createToken('auth_token', ['owner'])->plainTextToken;

            return ApiResponse::success([
                'user' => $user->loadMissing(['ownedShops.shop'])->toArray(),
                'token' => $token
            ], 'Registration successful', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Registration failed: ' . $e->getMessage(), 500);
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
