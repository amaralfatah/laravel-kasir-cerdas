<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
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
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'sku' => 'required|string|max:50|unique:products',
                'category_id' => 'nullable|exists:categories,id',
                'purchase_price' => 'required|numeric|min:0',
                'selling_price' => 'required|numeric|min:0',
                'barcode' => 'nullable|string|max:255|unique:products',
                'description' => 'nullable|string',
                'images' => 'nullable|array',
                'images.*' => 'nullable|file|image|mimes:jpeg,png,jpg,gif|max:2048',
                'is_using_stock' => 'boolean',
                'is_active' => 'boolean',
                'stocks' => 'nullable|array',
                'stocks.*.shop_id' => 'required|exists:shops,id',
                'stocks.*.stock' => 'required|numeric|min:0',
                'stocks.*.min_stock' => 'nullable|numeric|min:0',
                'price_rules' => 'nullable|array',
                'price_rules.*.price_category_id' => 'required|exists:price_categories,id',
                'price_rules.*.shop_id' => 'nullable|exists:shops,id',
                'price_rules.*.min_quantity' => 'required|integer|min:1',
                'price_rules.*.price' => 'required|numeric|min:0'
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation failed', 422, $validator->errors());
            }

            $user = $request->user();

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
            ]);

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
                // If no stocks are provided, create default stock for user's shop
                // Only applicable for non-owner and non-super_admin users with a shop_id
                if ($user->role !== 'super_admin' && $user->role !== 'owner' && $user->shop_id) {
                    ProductStock::create([
                        'product_id' => $product->id,
                        'shop_id' => $user->shop_id,
                        'stock' => 0,
                        'min_stock' => 0,
                    ]);
                } else if ($user->role === 'owner') {
                    // If owner has shops, create stock for the first owned shop
                    $ownedShopId = ShopOwner::where('user_id', $user->id)
                        ->orderBy('is_primary_owner', 'desc')
                        ->value('shop_id');

                    if ($ownedShopId) {
                        ProductStock::create([
                            'product_id' => $product->id,
                            'shop_id' => $ownedShopId,
                            'stock' => 0,
                            'min_stock' => 0,
                        ]);
                    }
                }
            }

            // Create price rules if provided
            if ($request->has('price_rules') && is_array($request->price_rules)) {
                foreach ($request->price_rules as $ruleData) {
                    ProductPriceRule::create([
                        'product_id' => $product->id,
                        'shop_id' => $ruleData['shop_id'] ?? null,
                        'price_category_id' => $ruleData['price_category_id'],
                        'min_quantity' => $ruleData['min_quantity'],
                        'price' => $ruleData['price'],
                    ]);
                }
            }

            // Load related data for response
            $product->load(['stocks', 'category', 'priceRules.priceCategory']);

            return ApiResponse::success($product, 'Product created successfully');
        } catch (\Exception $e) {
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
                'stocks' => 'nullable|array',
                'stocks.*.shop_id' => 'required|exists:shops,id',
                'stocks.*.stock' => 'required|numeric|min:0',
                'stocks.*.min_stock' => 'nullable|numeric|min:0',
                'price_rules' => 'nullable|array',
                'price_rules.*.id' => 'nullable|exists:product_price_rules,id',
                'price_rules.*.price_category_id' => 'required|exists:price_categories,id',
                'price_rules.*.shop_id' => 'nullable|exists:shops,id',
                'price_rules.*.min_quantity' => 'required|integer|min:1',
                'price_rules.*.price' => 'required|numeric|min:0'
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
                $request->except(['images', 'stocks', 'price_rules']),
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

            // Update price rules if provided
            if ($request->has('price_rules')) {
                foreach ($request->price_rules as $ruleData) {
                    if (isset($ruleData['id'])) {
                        // Update existing rule
                        ProductPriceRule::where('id', $ruleData['id'])
                            ->where('product_id', $product->id)
                            ->update([
                                'price_category_id' => $ruleData['price_category_id'],
                                'shop_id' => $ruleData['shop_id'] ?? null,
                                'min_quantity' => $ruleData['min_quantity'],
                                'price' => $ruleData['price']
                            ]);
                    } else {
                        // Create new rule
                        ProductPriceRule::create([
                            'product_id' => $product->id,
                            'price_category_id' => $ruleData['price_category_id'],
                            'shop_id' => $ruleData['shop_id'] ?? null,
                            'min_quantity' => $ruleData['min_quantity'],
                            'price' => $ruleData['price']
                        ]);
                    }
                }
            }

            // Load fresh data for response
            $product = $product->fresh(['category', 'stocks', 'priceRules.priceCategory']);

            return ApiResponse::success($product, 'Product updated successfully');
        } catch (\Exception $e) {
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
