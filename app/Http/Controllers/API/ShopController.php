<?php
// app/Http/Controllers/API/ShopController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ShopOwner;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    /**
     * Get shops associated with the authenticated user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $shops = [];

            // Different logic based on user role
            if ($user->role === 'super_admin') {
                // Super admin can see all shops
                $shops = Shop::all();
            } elseif ($user->role === 'owner') {
                // Owner can only see owned shops
                $shopIds = ShopOwner::where('user_id', $user->id)
                    ->pluck('shop_id')
                    ->toArray();

                $shops = Shop::whereIn('id', $shopIds)->get();
            } else {
                // Other roles can only see their assigned shop
                if ($user->shop_id) {
                    $shops = Shop::where('id', $user->shop_id)->get();
                }
            }

            return ApiResponse::success($shops, 'Shops retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve shops: ' . $e->getMessage(), 500);
        }
    }
}
