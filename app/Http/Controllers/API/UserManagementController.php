<?php
// app/Http/Controllers/API/UserManagementController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Shop;
use App\Models\ShopOwner;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class UserManagementController extends Controller
{
    /**
     * Get list of users (based on user's access level)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $query = User::query();

            // Filter by role
            if ($request->has('role')) {
                $query->where('role', $request->role);
            }

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Filter by shop
            if ($request->has('shop_id')) {
                $shopId = $request->shop_id;
                $query->where(function($q) use ($shopId) {
                    $q->where('shop_id', $shopId)
                        ->orWhereHas('ownedShops', function($q) use ($shopId) {
                            $q->where('shop_id', $shopId);
                        });
                });
            }

            // Apply access restrictions based on user role
            if ($user->role === 'super_admin') {
                // Super admin can see all users
            } elseif ($user->role === 'owner') {
                // Owner can only see users in their shops
                $ownedShopIds = ShopOwner::where('user_id', $user->id)
                    ->pluck('shop_id')
                    ->toArray();

                $query->where(function($q) use ($ownedShopIds, $user) {
                    $q->whereIn('shop_id', $ownedShopIds)
                        ->orWhereHas('ownedShops', function($q) use ($ownedShopIds) {
                            $q->whereIn('shop_id', $ownedShopIds);
                        })
                        ->orWhere('id', $user->id); // Include self
                });
            } else {
                // Admin/manager can only see users in their shop
                $query->where('shop_id', $user->shop_id);
            }

            // Eager load relationships
            $query->with(['shop', 'ownedShops.shop']);

            // Paginate results
            $perPage = $request->input('per_page', 15);
            $users = $query->paginate($perPage);

            return ApiResponse::success($users, 'Users retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve users: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create a new user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user();

            // Super admin can create any type of user
            // Owner can create admin, manager, cashier
            // Others cannot create users

            // Check if user has permission
            $allowedRoles = [];

            if ($user->role === 'super_admin') {
                $allowedRoles = ['super_admin', 'owner', 'admin', 'manager', 'cashier'];
            } elseif ($user->role === 'owner') {
                $allowedRoles = ['admin', 'manager', 'cashier'];
            } else {
                return ApiResponse::forbidden('You do not have permission to create users');
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
                'role' => 'required|string|in:' . implode(',', $allowedRoles),
                'shop_id' => 'required_unless:role,super_admin,owner|exists:shops,id|nullable',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors());
            }

            // If owner is creating a user, verify they own the shop
            if ($user->role === 'owner' && $request->filled('shop_id')) {
                $shopOwner = ShopOwner::where('user_id', $user->id)
                    ->where('shop_id', $request->shop_id)
                    ->first();

                if (!$shopOwner) {
                    return ApiResponse::forbidden('You do not own this shop');
                }
            }

            DB::beginTransaction();

            // Create user
            $newUser = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'shop_id' => in_array($request->role, ['admin', 'manager', 'cashier']) ? $request->shop_id : null,
                'is_active' => $request->input('is_active', true),
            ]);

            // If creating an owner, check if shop_id was provided to assign ownership
            if ($request->role === 'owner' && $request->filled('shop_id')) {
                ShopOwner::create([
                    'user_id' => $newUser->id,
                    'shop_id' => $request->shop_id,
                    'is_primary_owner' => $request->input('is_primary_owner', false),
                    'notes' => $request->input('notes', null),
                ]);
            }

            DB::commit();

            // Load relationships for response
            $newUser->load(['shop', 'ownedShops.shop']);

            return ApiResponse::success($newUser, 'User created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to create user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified user
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id, Request $request)
    {
        try {
            $authUser = $request->user();

            // Get user with relationships
            $user = User::with(['shop', 'ownedShops.shop'])->findOrFail($id);

            // Check permission to view this user
            if ($authUser->role === 'super_admin') {
                // Super admin can view any user
            } elseif ($authUser->role === 'owner') {
                // Owners can view users in their shops
                $ownedShopIds = ShopOwner::where('user_id', $authUser->id)
                    ->pluck('shop_id')
                    ->toArray();

                $canView = $user->id === $authUser->id || // Can view self
                    in_array($user->shop_id, $ownedShopIds) || // Can view users in owned shops
                    ShopOwner::where('user_id', $user->id)
                        ->whereIn('shop_id', $ownedShopIds)
                        ->exists(); // Can view owners of same shops

                if (!$canView) {
                    return ApiResponse::forbidden('You do not have permission to view this user');
                }
            } else {
                // Others can only view users in their shop
                if ($user->shop_id !== $authUser->shop_id && $user->id !== $authUser->id) {
                    return ApiResponse::forbidden('You do not have permission to view this user');
                }
            }

            return ApiResponse::success($user, 'User retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified user
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update($id, Request $request)
    {
        try {
            $authUser = $request->user();
            $user = User::findOrFail($id);

            // Check permission to update this user
            if ($authUser->role === 'super_admin') {
                // Super admin can update any user
            } elseif ($authUser->role === 'owner') {
                // Owners can update users in their shops, except super_admins and other owners
                if ($user->role === 'super_admin') {
                    return ApiResponse::forbidden('You cannot update a super admin');
                }

                if ($user->role === 'owner' && $user->id !== $authUser->id) {
                    $sharedShops = ShopOwner::where('user_id', $authUser->id)
                        ->whereIn('shop_id', function($query) use ($user) {
                            $query->select('shop_id')
                                ->from('shop_owners')
                                ->where('user_id', $user->id);
                        })->exists();

                    if (!$sharedShops) {
                        return ApiResponse::forbidden('You cannot update an owner of another shop');
                    }
                }

                if ($user->role !== 'owner' && $user->shop_id) {
                    $ownsShop = ShopOwner::where('user_id', $authUser->id)
                        ->where('shop_id', $user->shop_id)
                        ->exists();

                    if (!$ownsShop) {
                        return ApiResponse::forbidden('You do not own this user\'s shop');
                    }
                }
            } else {
                // Others cannot update users
                return ApiResponse::forbidden('You do not have permission to update users');
            }

            // Validate request
            $rules = [
                'name' => 'string|max:255',
                'email' => 'string|email|max:255|unique:users,email,' . $id,
                'is_active' => 'boolean',
            ];

            // Only super_admin and self can change password
            if ($authUser->role === 'super_admin' || $authUser->id === $user->id) {
                $rules['password'] = 'string|min:8|nullable';
            }

            // Only super_admin can change role
            if ($authUser->role === 'super_admin') {
                $rules['role'] = 'string|in:super_admin,owner,admin,manager,cashier';
                $rules['shop_id'] = 'exists:shops,id|nullable';
            }

            // Only owner and super_admin can change shop_id
            if (in_array($authUser->role, ['super_admin', 'owner']) && $user->role !== 'owner' && $user->role !== 'super_admin') {
                $rules['shop_id'] = 'exists:shops,id|nullable';
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors());
            }

            // Begin transaction for update
            DB::beginTransaction();

            // Update user
            if ($request->has('name')) {
                $user->name = $request->name;
            }

            if ($request->has('email')) {
                $user->email = $request->email;
            }

            if ($request->has('password') && ($authUser->role === 'super_admin' || $authUser->id === $user->id)) {
                $user->password = Hash::make($request->password);
            }

            if ($request->has('is_active')) {
                $user->is_active = $request->boolean('is_active');
            }

            if ($request->has('role') && $authUser->role === 'super_admin') {
                $user->role = $request->role;

                // Adjust shop_id based on role
                if (in_array($request->role, ['super_admin', 'owner'])) {
                    $user->shop_id = null;
                } elseif ($request->has('shop_id')) {
                    $user->shop_id = $request->shop_id;
                }
            } elseif ($request->has('shop_id') && in_array($authUser->role, ['super_admin', 'owner'])
                && $user->role !== 'owner' && $user->role !== 'super_admin') {

                // If an owner is changing shop_id, verify they own the shop
                if ($authUser->role === 'owner') {
                    $ownsShop = ShopOwner::where('user_id', $authUser->id)
                        ->where('shop_id', $request->shop_id)
                        ->exists();

                    if (!$ownsShop) {
                        DB::rollBack();
                        return ApiResponse::forbidden('You do not own this shop');
                    }
                }

                $user->shop_id = $request->shop_id;
            }

            $user->save();
            DB::commit();

            // Load relationships for response
            $user->load(['shop', 'ownedShops.shop']);

            return ApiResponse::success($user, 'User updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to update user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified user
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id, Request $request)
    {
        try {
            $authUser = $request->user();
            $user = User::findOrFail($id);

            // Cannot delete self
            if ($user->id === $authUser->id) {
                return ApiResponse::forbidden('You cannot delete yourself');
            }

            // Check permissions (same as before)
            if ($authUser->role === 'super_admin') {
                if ($user->role === 'super_admin') {
                    return ApiResponse::forbidden('Super admin cannot be deleted');
                }
            } elseif ($authUser->role === 'owner') {
                if (in_array($user->role, ['super_admin', 'owner'])) {
                    return ApiResponse::forbidden('You cannot delete a super admin or owner');
                }

                $ownsShop = ShopOwner::where('user_id', $authUser->id)
                    ->where('shop_id', $user->shop_id)
                    ->exists();

                if (!$ownsShop) {
                    return ApiResponse::forbidden('You do not own this user\'s shop');
                }
            } else {
                return ApiResponse::forbidden('You do not have permission to delete users');
            }

            // Instead of deleting, deactivate the user
            $user->is_active = false;
            $user->email = $user->email . '.deleted.' . time(); // Prevent email reuse
            $user->save();

            // Delete related shop ownerships if any
            ShopOwner::where('user_id', $user->id)->delete();

            return ApiResponse::success(null, 'User deactivated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Assign shop ownership to a user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignShopOwnership(Request $request)
    {
        try {
            $authUser = $request->user();

            // Validate request
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'shop_id' => 'required|exists:shops,id',
                'is_primary_owner' => 'boolean',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors());
            }

            // Get the user to be assigned
            $user = User::findOrFail($request->user_id);

            // Check permissions
            if ($authUser->role === 'super_admin') {
                // Super admin can assign any shop to any user with owner role
            } elseif ($authUser->role === 'owner') {
                // Owners can assign shops they own
                $ownsShop = ShopOwner::where('user_id', $authUser->id)
                    ->where('shop_id', $request->shop_id)
                    ->where('is_primary_owner', true)
                    ->exists();

                if (!$ownsShop) {
                    return ApiResponse::forbidden('You are not the primary owner of this shop');
                }
            } else {
                return ApiResponse::forbidden('You do not have permission to assign shop ownership');
            }

            // Check if user is appropriate role
            if ($user->role !== 'owner') {
                // Update user role to owner
                $user->role = 'owner';
                $user->shop_id = null; // Owners don't have a direct shop_id
                $user->save();
            }

            // Check if this ownership already exists
            $existingOwnership = ShopOwner::where('user_id', $request->user_id)
                ->where('shop_id', $request->shop_id)
                ->first();

            if ($existingOwnership) {
                $existingOwnership->is_primary_owner = $request->input('is_primary_owner', false);
                $existingOwnership->notes = $request->input('notes');
                $existingOwnership->save();

                return ApiResponse::success($existingOwnership, 'Shop ownership updated successfully');
            }

            // Create new ownership
            $shopOwner = ShopOwner::create([
                'user_id' => $request->user_id,
                'shop_id' => $request->shop_id,
                'is_primary_owner' => $request->input('is_primary_owner', false),
                'notes' => $request->input('notes', null),
            ]);

            return ApiResponse::success($shopOwner, 'Shop ownership assigned successfully', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to assign shop ownership: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove shop ownership from a user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeShopOwnership(Request $request)
    {
        try {
            $authUser = $request->user();

            // Validate request
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'shop_id' => 'required|exists:shops,id',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors());
            }

            // Find the ownership record
            $shopOwner = ShopOwner::where('user_id', $request->user_id)
                ->where('shop_id', $request->shop_id)
                ->first();

            if (!$shopOwner) {
                return ApiResponse::notFound('Shop ownership record not found');
            }

            // Check permissions
            if ($authUser->role === 'super_admin') {
                // Super admin can remove any shop ownership
            } elseif ($authUser->role === 'owner') {
                // Owners can only remove ownerships from their shops if they are primary owners
                $isPrimaryOwner = ShopOwner::where('user_id', $authUser->id)
                    ->where('shop_id', $request->shop_id)
                    ->where('is_primary_owner', true)
                    ->exists();

                if (!$isPrimaryOwner) {
                    return ApiResponse::forbidden('You are not the primary owner of this shop');
                }

                // Primary owners cannot remove themselves
                if ($authUser->id === $request->user_id && $shopOwner->is_primary_owner) {
                    return ApiResponse::forbidden('Primary owners cannot remove themselves');
                }
            } else {
                return ApiResponse::forbidden('You do not have permission to remove shop ownership');
            }

            // Delete the ownership
            $shopOwner->delete();

            // Check if user has any remaining ownerships
            $hasOtherOwnerships = ShopOwner::where('user_id', $request->user_id)->exists();

            // If no other ownerships and not super_admin, update user role to admin
            if (!$hasOtherOwnerships) {
                $user = User::find($request->user_id);
                if ($user && $user->role === 'owner') {
                    $user->role = 'admin';
                    $user->shop_id = $request->shop_id; // Assign to the shop they were previously an owner of
                    $user->save();
                }
            }

            return ApiResponse::success(null, 'Shop ownership removed successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to remove shop ownership: ' . $e->getMessage(), 500);
        }
    }
}
