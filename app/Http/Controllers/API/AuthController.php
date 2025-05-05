<?php
// app/Http/Controllers/AuthController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\User;
use App\Models\Branch;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
                return ApiResponse::unauthorized('Kredensial tidak valid');
            }

// Cek status akun aktif
            if (!$user->is_active) {
                return ApiResponse::forbidden('Akun Anda tidak aktif');
            }

// Update last login
            $user->last_login = now();
            $user->save();

// Buat token dengan role sebagai ability
            $token = $user->createToken('auth_token', [$user->role])->plainTextToken;

            return ApiResponse::success([
                'user' => $user,
                'token' => $token
            ], 'Login berhasil');

        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal melakukan login: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Register owner dengan shop
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
                'shop_name' => 'required|string|max:255',
                'shop_address' => 'required|string',
                'shop_phone' => 'nullable|string|max:20',
                'shop_tax_id' => 'nullable|string|max:255',
            ]);

// Buat shop baru
            $shop = Shop::create([
                'name' => $request->shop_name,
                'address' => $request->shop_address,
                'phone' => $request->shop_phone,
                'tax_id' => $request->shop_tax_id,
                'is_active' => true,
            ]);

// Buat user owner baru
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'owner',
                'shop_id' => $shop->id,
                'is_active' => true,
            ]);

// Buat token untuk owner baru
            $token = $user->createToken('auth_token', ['owner'])->plainTextToken;

            return ApiResponse::success([
                'user' => $user,
                'shop' => $shop,
                'token' => $token
            ], 'Owner berhasil terdaftar', 201);

        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal membuat owner: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Info User
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        try {
            return ApiResponse::success(
                $request->user()->load('shop'),
                'Data user berhasil dimuat'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal memuat data user: ' . $e->getMessage(), 500);
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
            $request->user()->currentAccessToken()->delete();

            return ApiResponse::success(null, 'Logout berhasil');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal logout: ' . $e->getMessage(), 500);
        }
    }
}
