<?php
// app/Http/Controllers/API/UserController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Get authenticated user's profile
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        try {
            $user = $request->user()->load(['shop']);

            // If the user is an owner, load their owned shops
            if ($user->role === 'owner') {
                $user->load(['ownedShops' => function($query) {
                    $query->with('shop');
                }]);
            }

            return ApiResponse::success($user, 'User profile retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve user profile: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update authenticated user's profile
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'name' => 'string|max:255',
                'email' => 'email|unique:users,email,' . $user->id,
                'current_password' => 'required_with:new_password',
                'new_password' => 'nullable|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors());
            }

            // Check current password if trying to update password
            if ($request->has('current_password')) {
                if (!Hash::check($request->current_password, $user->password)) {
                    return ApiResponse::error('Current password is incorrect', 422);
                }
            }

            // Update user data
            if ($request->has('name')) {
                $user->name = $request->name;
            }

            if ($request->has('email')) {
                $user->email = $request->email;
            }

            if ($request->has('new_password')) {
                $user->password = Hash::make($request->new_password);
            }

            $user->save();

            return ApiResponse::success($user->fresh(['shop']), 'Profile updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update profile: ' . $e->getMessage(), 500);
        }
    }
}
