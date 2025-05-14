<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PriceCategory;
use App\Models\Product;
use App\Models\ShopOwner;
use App\Models\ProductPriceRule;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Models\ProductStock;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Start product query builder
            $productsQuery = Product::query();
            $user = $request->user();

            // Filter products based on user role
            if ($user->role === 'super_admin') {
                // Super admin can see all products
            } elseif ($user->role === 'owner') {
                // Owner can only see products from owned shops
                $ownedShopIds = ShopOwner::where('user_id', $user->id)->pluck('shop_id')->toArray();

                if (!empty($ownedShopIds)) {
                    // Use the forShops scope we added to the Product model
                    $productsQuery->forShops($ownedShopIds);
                }
            } else {
                // Admin, manager, cashier can only see products from their shop
                if ($user->shop_id) {
                    // Use the forShop scope we added to the Product model
                    $productsQuery->forShop($user->shop_id);
                }
            }

            // Apply search filter if provided
            if ($request->has('search')) {
                $searchTerm = $request->input('search');
                $productsQuery->where(function ($query) use ($searchTerm) {
                    $query->where('name', 'like', "%{$searchTerm}%")
                        ->orWhere('barcode', 'like', "%{$searchTerm}%")
                        ->orWhere('sku', 'like', "%{$searchTerm}%");
                });
            }

            // Filter by category if provided
            if ($request->has('category_id')) {
                $productsQuery->where('category_id', $request->input('category_id'));
            }

            // Filter by active status
            if ($request->has('is_active')) {
                $productsQuery->where('is_active', $request->boolean('is_active'));
            }

            // Filter by is_using_stock if provided
            if ($request->has('is_using_stock')) {
                $productsQuery->where('is_using_stock', $request->boolean('is_using_stock'));
            }

            // Sorting
            $sortField = $request->input('sort_by', 'created_at');
            $sortDirection = $request->input('sort_direction', 'desc');
            $validSortFields = ['name', 'selling_price', 'created_at', 'updated_at'];

            if (in_array($sortField, $validSortFields)) {
                $productsQuery->orderBy($sortField, $sortDirection);
            }

            // Eager loading for query optimization
            $productsQuery->with([
                'category',
                'stocks' => function ($query) use ($user) {
                    if ($user->role === 'super_admin') {
                        // Load all stocks for super admin
                    } elseif ($user->role === 'owner') {
                        $ownedShopIds = ShopOwner::where('user_id', $user->id)->pluck('shop_id')->toArray();
                        $query->whereIn('shop_id', $ownedShopIds);
                    } else {
                        $query->where('shop_id', $user->shop_id);
                    }
                },
                'priceRules' => function ($query) use ($user) {
                    if ($user->role === 'super_admin') {
                        // Load all price rules for super admin
                    } elseif ($user->role === 'owner') {
                        $ownedShopIds = ShopOwner::where('user_id', $user->id)->pluck('shop_id')->toArray();
                        $query->where(function ($q) use ($ownedShopIds) {
                            $q->whereIn('shop_id', $ownedShopIds)
                                ->orWhereNull('shop_id');
                        });
                    } else {
                        $query->where(function ($q) use ($user) {
                            $q->where('shop_id', $user->shop_id)
                                ->orWhereNull('shop_id');
                        });
                    }
                }
            ]);

            // Paginate results
            $products = $productsQuery->paginate($request->input('per_page', 10));

            return ApiResponse::success($products, 'Products retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve products: ' . $e->getMessage(), 500);
        }
    }

    public function store(Request $request)
    {
        try {
            // Begin transaction to ensure all operations succeed or fail together
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                // Basic product validation
                'name' => 'required|string|max:255',
                'sku' => 'required|string|max:50|unique:products',
                'purchase_price' => 'required|numeric|min:0',
                'selling_price' => 'required|numeric|min:0',
                'category_id' => 'nullable|exists:categories,id',
                'barcode' => 'nullable|string|max:255|unique:products',
                'description' => 'nullable|string',
                'images' => 'nullable|array',
                'images.*' => 'nullable|file|image|mimes:jpeg,png,jpg,gif|max:2048',
                'is_using_stock' => 'boolean',
                'is_active' => 'boolean',
                'weight' => 'nullable|numeric',
                'unit' => 'nullable|string',
                'discount_percentage' => 'nullable|numeric|min:0|max:100',
                'rack_placement' => 'nullable|string',
                'information' => 'nullable|string',

                // Stock validation
                'stocks' => 'nullable|array',
                'stocks.*.shop_id' => 'required|exists:shops,id',
                'stocks.*.stock' => 'required|numeric|min:0',
                'stocks.*.min_stock' => 'nullable|numeric|min:0',

                // Price types validation - updated to match Kasir Pintar format
                'price_types' => 'nullable|array',
                'price_types.*.name' => 'required|string|max:255',
                'price_types.*.base_price' => 'required|numeric|min:0',

                // Wholesale tiers for each price type
                'price_types.*.tiers' => 'nullable|array',
                'price_types.*.tiers.*.min_quantity' => 'required|integer|min:2',
                'price_types.*.tiers.*.price' => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation failed', 422, $validator->errors());
            }

            // Upload and save images
            $imageUrls = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('products', 'public');
                    $imageUrls[] = asset('storage/' . $path);
                }
            }

            // Create new product
            $product = Product::create([
                'name' => $request->name,
                'sku' => $request->sku,
                'category_id' => $request->category_id,
                'purchase_price' => $request->purchase_price,
                'selling_price' => $request->selling_price,
                'barcode' => $request->barcode,
                'description' => $request->description,
                'images' => $imageUrls,
                'is_using_stock' => $request->input('is_using_stock', true),
                'is_active' => $request->input('is_active', true),
                'weight' => $request->input('weight'),
                'unit' => $request->input('unit'),
                'discount_percentage' => $request->input('discount_percentage'),
                'rack_placement' => $request->input('rack_placement'),
                'information' => $request->input('information'),
            ]);

            $user = $request->user();

            // Create stock records for each provided shop
            if ($request->has('stocks') && is_array($request->stocks) && !empty($request->stocks)) {
                foreach ($request->stocks as $stockData) {
                    ProductStock::create([
                        'product_id' => $product->id,
                        'shop_id' => $stockData['shop_id'],
                        'stock' => $stockData['stock'],
                        'min_stock' => $stockData['min_stock'] ?? 0,
                    ]);
                }
            } else {
                // Default stock handling if user has a shop
                if ($user->shop_id) {
                    ProductStock::create([
                        'product_id' => $product->id,
                        'shop_id' => $user->shop_id,
                        'stock' => $request->input('initial_stock', 0),
                        'min_stock' => $request->input('min_stock', 0),
                    ]);
                } elseif ($user->role === 'owner') {
                    // For owners, create stock in all their shops
                    $ownedShopIds = ShopOwner::where('user_id', $user->id)->pluck('shop_id')->toArray();
                    foreach ($ownedShopIds as $shopId) {
                        ProductStock::create([
                            'product_id' => $product->id,
                            'shop_id' => $shopId,
                            'stock' => $request->input('initial_stock', 0),
                            'min_stock' => $request->input('min_stock', 0),
                        ]);
                    }
                }
            }

            // Handle price types and rules as shown in Kasir Pintar UI
            if ($request->has('price_types')) {
                foreach ($request->price_types as $priceType) {
                    // First, find or create the price category
                    $priceCategory = PriceCategory::firstOrCreate(
                        ['name' => $priceType['name']],
                        ['description' => $priceType['description'] ?? null]
                    );

                    // Create the base price rule (for quantity of 1)
                    ProductPriceRule::create([
                        'product_id' => $product->id,
                        'shop_id' => $request->input('shop_id'), // Can be null for all shops
                        'price_category_id' => $priceCategory->id,
                        'min_quantity' => 1,
                        'price' => $priceType['base_price'],
                    ]);

                    // Create tier-based price rules if provided
                    if (isset($priceType['tiers']) && is_array($priceType['tiers'])) {
                        foreach ($priceType['tiers'] as $tier) {
                            ProductPriceRule::create([
                                'product_id' => $product->id,
                                'shop_id' => $request->input('shop_id'), // Can be null for all shops
                                'price_category_id' => $priceCategory->id,
                                'min_quantity' => $tier['min_quantity'],
                                'price' => $tier['price'],
                            ]);
                        }
                    }
                }
            }
            // Even if no custom price types, create a default "regular" price rule
            else {
                // Find or create the default price category
                $defaultCategory = PriceCategory::firstOrCreate(
                    ['name' => 'Regular'],
                    ['description' => 'Regular retail price']
                );

                // Create default price rule
                ProductPriceRule::create([
                    'product_id' => $product->id,
                    'shop_id' => null, // Apply to all shops
                    'price_category_id' => $defaultCategory->id,
                    'min_quantity' => 1,
                    'price' => $request->selling_price,
                ]);
            }

            // Load related data for response
            $product->load(['stocks', 'category', 'priceRules.priceCategory']);

            // Commit transaction
            DB::commit();

            return ApiResponse::success($product, 'Product created successfully');
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            // If error occurs, delete uploaded images
            if (isset($imageUrls)) {
                foreach ($imageUrls as $url) {
                    $path = str_replace(asset('storage/'), '', $url);
                    Storage::disk('public')->delete($path);
                }
            }

            return ApiResponse::error('Failed to create product: ' . $e->getMessage(), 500);
        }
    }

    public function show(Product $product)
    {
        try {
            $user = request()->user();

            // Load data based on user role
            $product->load(['category']);

            // Load stocks according to user role
            $product->load([
                'stocks' => function ($query) use ($user) {
                    if ($user->role === 'super_admin') {
                        // Load all stocks for super admin
                    } elseif ($user->role === 'owner') {
                        $ownedShopIds = ShopOwner::where('user_id', $user->id)->pluck('shop_id')->toArray();
                        $query->whereIn('shop_id', $ownedShopIds);
                    } else {
                        $query->where('shop_id', $user->shop_id);
                    }
                },
                'priceRules.priceCategory' => function ($query) use ($user) {
                    if ($user->role === 'super_admin') {
                        // Load all price rules for super admin
                    } elseif ($user->role === 'owner') {
                        $ownedShopIds = ShopOwner::where('user_id', $user->id)->pluck('shop_id')->toArray();
                        $query->where(function ($q) use ($ownedShopIds) {
                            $q->whereIn('shop_id', $ownedShopIds)
                                ->orWhereNull('shop_id');
                        });
                    } else {
                        $query->where(function ($q) use ($user) {
                            $q->where('shop_id', $user->shop_id)
                                ->orWhereNull('shop_id');
                        });
                    }
                }
            ]);

            return ApiResponse::success($product, 'Product retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve product: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request, Product $product)
    {
        try {
            // Begin transaction
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'name' => 'string|max:255',
                'sku' => 'string|max:50|unique:products,sku,' . $product->id,
                'category_id' => 'nullable|exists:categories,id',
                'purchase_price' => 'numeric|min:0',
                'selling_price' => 'numeric|min:0',
                'barcode' => 'nullable|string|max:255|unique:products,barcode,' . $product->id,
                'description' => 'nullable|string',
                'images' => 'nullable|array',
                'images.*' => 'nullable|file|image|mimes:jpeg,png,jpg,gif|max:2048',
                'is_using_stock' => 'boolean',
                'is_active' => 'boolean',
                'weight' => 'nullable|numeric',
                'unit' => 'nullable|string',
                'discount_percentage' => 'nullable|numeric|min:0|max:100',
                'rack_placement' => 'nullable|string',
                'information' => 'nullable|string',
                'stocks' => 'nullable|array',
                'stocks.*.shop_id' => 'required|exists:shops,id',
                'stocks.*.stock' => 'required|numeric|min:0',
                'stocks.*.min_stock' => 'nullable|numeric|min:0',

                // Price types validation - updated to match Kasir Pintar format
                'price_types' => 'nullable|array',
                'price_types.*.id' => 'nullable|integer', // For existing price categories
                'price_types.*.name' => 'required|string|max:255',
                'price_types.*.base_price' => 'required|numeric|min:0',

                // Wholesale tiers for each price type
                'price_types.*.tiers' => 'nullable|array',
                'price_types.*.tiers.*.id' => 'nullable|integer', // For existing tiers
                'price_types.*.tiers.*.min_quantity' => 'required|integer|min:2',
                'price_types.*.tiers.*.price' => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation failed', 422, $validator->errors());
            }

            // Upload and save new images if provided
            $imageUrls = $product->images ?? [];
            if ($request->hasFile('images')) {
                // Delete old images
                if ($product->images) {
                    foreach ($product->images as $oldImage) {
                        $path = str_replace(asset('storage/'), '', $oldImage);
                        Storage::disk('public')->delete($path);
                    }
                }

                // Upload new images
                $imageUrls = [];
                foreach ($request->file('images') as $image) {
                    $path = $image->store('products', 'public');
                    $imageUrls[] = asset('storage/' . $path);
                }
            }

            // Update product data
            $product->update(array_merge(
                $request->except(['images', 'stocks', 'price_types']),
                ['images' => $imageUrls]
            ));

            // Update stocks if provided
            if ($request->has('stocks')) {
                foreach ($request->stocks as $stockData) {
                    $product->stocks()->updateOrCreate(
                        ['shop_id' => $stockData['shop_id']],
                        [
                            'stock' => $stockData['stock'],
                            'min_stock' => $stockData['min_stock'] ?? 0,
                        ]
                    );
                }
            }

            // Update price types and rules
            if ($request->has('price_types')) {
                // Get all current price rules to track which ones to delete
                $currentRuleIds = $product->priceRules()->pluck('id')->toArray();
                $updatedRuleIds = [];

                foreach ($request->price_types as $priceType) {
                    // Find or create price category
                    $priceCategory = PriceCategory::firstOrCreate(
                        ['name' => $priceType['name']],
                        ['description' => $priceType['description'] ?? null]
                    );

                    // Update or create base price rule
                    $baseRule = ProductPriceRule::updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'price_category_id' => $priceCategory->id,
                            'min_quantity' => 1,
                            'shop_id' => $request->input('shop_id'),
                        ],
                        [
                            'price' => $priceType['base_price'],
                        ]
                    );

                    $updatedRuleIds[] = $baseRule->id;

                    // Update or create tier rules
                    if (isset($priceType['tiers']) && is_array($priceType['tiers'])) {
                        foreach ($priceType['tiers'] as $tier) {
                            $tierRule = ProductPriceRule::updateOrCreate(
                                [
                                    'product_id' => $product->id,
                                    'price_category_id' => $priceCategory->id,
                                    'min_quantity' => $tier['min_quantity'],
                                    'shop_id' => $request->input('shop_id'),
                                ],
                                [
                                    'price' => $tier['price'],
                                ]
                            );

                            $updatedRuleIds[] = $tierRule->id;
                        }
                    }
                }

                // Delete any price rules that weren't updated
                $rulesToDelete = array_diff($currentRuleIds, $updatedRuleIds);
                if (!empty($rulesToDelete)) {
                    ProductPriceRule::whereIn('id', $rulesToDelete)->delete();
                }
            }

            // Load fresh data for response
            $product = $product->fresh(['category', 'stocks', 'priceRules.priceCategory']);

            // Commit transaction
            DB::commit();

            return ApiResponse::success($product, 'Product updated successfully');
        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();
            return ApiResponse::error('Failed to update product: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Product $product)
    {
        try {
            // Delete product images
            if ($product->images) {
                foreach ($product->images as $image) {
                    $path = str_replace(asset('storage/'), '', $image);
                    Storage::disk('public')->delete($path);
                }
            }

            // Delete price rules
            $product->priceRules()->delete();

            // Delete related stocks
            $product->stocks()->delete();

            // Delete the product (using soft delete if enabled)
            $product->delete();

            return ApiResponse::success(null, 'Product deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete product: ' . $e->getMessage(), 500);
        }
    }
}
