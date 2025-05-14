<?php
// app/Http/Controllers/API/ShopManagementController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ShopOwner;
use App\Models\User;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ShopManagementController extends Controller
{
    /**
     * Display a listing of shops
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $query = Shop::query();

            // Apply filters
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Apply access restrictions based on user role
            if ($user->role === 'super_admin') {
                // Super admin can see all shops
            } elseif ($user->role === 'owner') {
                // Owner can only see owned shops
                $shopIds = ShopOwner::where('user_id', $user->id)
                    ->pluck('shop_id')
                    ->toArray();

                $query->whereIn('id', $shopIds);
            } else {
                // Other users can only see their assigned shop
                $query->where('id', $user->shop_id);
            }

            // Eager load relationships
            $query->with(['users' => function($q) {
                $q->where('role', '!=', 'owner'); // Regular staff
            }, 'shopOwners.user']); // Shop owners

            // Pagination
            $perPage = $request->input('per_page', 15);
            $shops = $query->paginate($perPage);

            return ApiResponse::success($shops, 'Shops retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve shops: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created shop
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user();

            // Check permissions: only super_admin and owner can create shops
            if (!in_array($user->role, ['super_admin', 'owner'])) {
                return ApiResponse::forbidden('You do not have permission to create shops');
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'address' => 'required|string',
                'phone' => 'nullable|string|max:20',
                'tax_id' => 'nullable|string|max:255',
                'is_active' => 'boolean',
                'primary_owner_id' => 'nullable|exists:users,id',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors());
            }

            // Begin transaction
            DB::beginTransaction();

            // Create shop
            $shop = Shop::create([
                'name' => $request->name,
                'address' => $request->address,
                'phone' => $request->phone,
                'tax_id' => $request->tax_id,
                'is_active' => $request->input('is_active', true),
            ]);

            // Assign shop ownership
            $ownerId = $request->input('primary_owner_id', $user->id);

            // If primary_owner_id is provided, check if it's a valid owner
            if ($request->has('primary_owner_id')) {
                $ownerUser = User::find($ownerId);

                // Make sure user exists and is/will be an owner
                if (!$ownerUser) {
                    DB::rollBack();
                    return ApiResponse::error('Specified owner does not exist', 404);
                }

                // Update user role to owner if needed
                if ($ownerUser->role !== 'owner') {
                    $ownerUser->role = 'owner';
                    $ownerUser->shop_id = null; // Owners don't have direct shop_id
                    $ownerUser->save();
                }
            }

            // Create shop owner record
            ShopOwner::create([
                'user_id' => $ownerId,
                'shop_id' => $shop->id,
                'is_primary_owner' => true,
                'notes' => $request->input('notes', 'Initial owner'),
            ]);

            DB::commit();

            // Load relationships for response
            $shop->load(['shopOwners.user']);

            return ApiResponse::success($shop, 'Shop created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to create shop: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified shop
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id, Request $request)
    {
        try {
            $user = $request->user();
            $shop = Shop::findOrFail($id);

            // Check permissions
            if ($user->role === 'super_admin') {
                // Super admin can view any shop
            } elseif ($user->role === 'owner') {
                // Owner can only view owned shops
                $isOwner = ShopOwner::where('user_id', $user->id)
                    ->where('shop_id', $id)
                    ->exists();

                if (!$isOwner) {
                    return ApiResponse::forbidden('You do not own this shop');
                }
            } elseif ($user->shop_id !== $id) {
                // Other users can only view their assigned shop
                return ApiResponse::forbidden('You do not have permission to view this shop');
            }

            // Load relationships
            $shop->load([
                'users' => function($q) {
                    $q->where('role', '!=', 'owner');
                },
                'shopOwners.user'
            ]);

            return ApiResponse::success($shop, 'Shop retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve shop: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified shop
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update($id, Request $request)
    {
        try {
            $user = $request->user();
            $shop = Shop::findOrFail($id);

            // Check permissions
            if ($user->role === 'super_admin') {
                // Super admin can update any shop
            } elseif ($user->role === 'owner') {
                // Owner can only update owned shops if they are the primary owner
                $isPrimaryOwner = ShopOwner::where('user_id', $user->id)
                    ->where('shop_id', $id)
                    ->where('is_primary_owner', true)
                    ->exists();

                if (!$isPrimaryOwner) {
                    return ApiResponse::forbidden('You are not the primary owner of this shop');
                }
            } else {
                return ApiResponse::forbidden('You do not have permission to update shops');
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'name' => 'string|max:255',
                'address' => 'string',
                'phone' => 'nullable|string|max:20',
                'tax_id' => 'nullable|string|max:255',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors());
            }

            // Update shop data
            if ($request->has('name')) {
                $shop->name = $request->name;
            }

            if ($request->has('address')) {
                $shop->address = $request->address;
            }

            if ($request->has('phone')) {
                $shop->phone = $request->phone;
            }

            if ($request->has('tax_id')) {
                $shop->tax_id = $request->tax_id;
            }

            if ($request->has('is_active')) {
                $shop->is_active = $request->boolean('is_active');
            }

            $shop->save();

            // Load relationships for response
            $shop->load([
                'users' => function($q) {
                    $q->where('role', '!=', 'owner');
                },
                'shopOwners.user'
            ]);

            return ApiResponse::success($shop, 'Shop updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update shop: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified shop
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id, Request $request)
    {
        try {
            $user = $request->user();
            $shop = Shop::findOrFail($id);

            // Check permissions
            if ($user->role === 'super_admin') {
                // Super admin can delete any shop
            } elseif ($user->role === 'owner') {
                // Owner can only delete owned shops if they are the primary owner
                $isPrimaryOwner = ShopOwner::where('user_id', $user->id)
                    ->where('shop_id', $id)
                    ->where('is_primary_owner', true)
                    ->exists();

                if (!$isPrimaryOwner) {
                    return ApiResponse::forbidden('You are not the primary owner of this shop');
                }
            } else {
                return ApiResponse::forbidden('You do not have permission to delete shops');
            }

            // Begin transaction
            DB::beginTransaction();

            // Delete shop owners
            ShopOwner::where('shop_id', $id)->delete();

            // Update users assigned to this shop
            User::where('shop_id', $id)->update(['shop_id' => null, 'is_active' => false]);

            // Delete shop
            $shop->delete();

            DB::commit();

            return ApiResponse::success(null, 'Shop deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to delete shop: ' . $e->getMessage(), 500);
        }
    }
}
