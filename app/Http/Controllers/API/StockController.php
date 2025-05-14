<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\ShopOwner;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StockController extends Controller
{
    /**
     * Get stock information for a product across all accessible shops
     *
     * @param Request $request
     * @param Product $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProductStock(Request $request, Product $product)
    {
        try {
            $user = $request->user();
            $stockQuery = $product->stocks();

            // Filter based on user role
            if ($user->role === 'super_admin') {
                // Super admin can see stock in all shops
            } elseif ($user->role === 'owner') {
                // Owner can only see stock in owned shops
                $ownedShopIds = ShopOwner::where('user_id', $user->id)->pluck('shop_id')->toArray();
                $stockQuery->whereIn('shop_id', $ownedShopIds);
            } else {
                // Admin, manager, cashier can only see stock in their shop
                $stockQuery->where('shop_id', $user->shop_id);
            }

            // Eager load shop information
            $stockQuery->with('shop');

            // Get stock data
            $stockData = $stockQuery->get();

            return ApiResponse::success($stockData, 'Product stock retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve product stock: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update stock for a product in a specific shop
     *
     * @param Request $request
     * @param Product $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStock(Request $request, Product $product)
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'shop_id' => 'required|exists:shops,id',
                'stock' => 'required|integer|min:0',
                'min_stock' => 'nullable|integer|min:0',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation failed', 422, $validator->errors());
            }

            $user = $request->user();
            $shopId = $request->shop_id;

            // Check if user has access to this shop
            if ($user->role !== 'super_admin') {
                if ($user->role === 'owner') {
                    $hasAccess = ShopOwner::where('user_id', $user->id)
                        ->where('shop_id', $shopId)
                        ->exists();
                } else {
                    $hasAccess = $user->shop_id == $shopId;
                }

                if (!$hasAccess) {
                    return ApiResponse::forbidden('You do not have access to manage stock in this shop');
                }
            }

            // Get current stock
            $stockRecord = ProductStock::where('product_id', $product->id)
                ->where('shop_id', $shopId)
                ->first();

            $oldStock = $stockRecord ? $stockRecord->stock : 0;
            $newStock = $request->stock;
            $stockDifference = $newStock - $oldStock;

            // Update or create stock record
            $stockRecord = ProductStock::updateOrCreate(
                ['product_id' => $product->id, 'shop_id' => $shopId],
                [
                    'stock' => $newStock,
                    'min_stock' => $request->input('min_stock', $stockRecord->min_stock ?? 0),
                ]
            );

            // Create stock movement record for audit trail
            if ($stockDifference !== 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'shop_id' => $shopId,
                    'quantity' => $stockDifference,
                    'movement_type' => 'adjustment',
                    'reference_type' => 'manual_adjustment',
                    'reference_id' => null,
                    'notes' => $request->input('notes', 'Manual stock adjustment'),
                    'user_id' => $user->id,
                ]);
            }

            DB::commit();

            return ApiResponse::success($stockRecord, 'Stock updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to update stock: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Transfer stock between shops
     *
     * @param Request $request
     * @param Product $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function transferStock(Request $request, Product $product)
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'from_shop_id' => 'required|exists:shops,id',
                'to_shop_id' => 'required|exists:shops,id|different:from_shop_id',
                'quantity' => 'required|integer|min:1',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation failed', 422, $validator->errors());
            }

            $user = $request->user();
            $fromShopId = $request->from_shop_id;
            $toShopId = $request->to_shop_id;
            $quantity = $request->quantity;

            // Check if user has access to source shop
            if ($user->role !== 'super_admin') {
                if ($user->role === 'owner') {
                    $hasAccess = ShopOwner::where('user_id', $user->id)
                            ->whereIn('shop_id', [$fromShopId, $toShopId])
                            ->count() === 2;
                } else {
                    $hasAccess = $user->shop_id == $fromShopId;
                }

                if (!$hasAccess) {
                    return ApiResponse::forbidden('You do not have access to transfer stock from this shop');
                }
            }

            // Get current stock in source shop
            $sourceStock = ProductStock::where('product_id', $product->id)
                ->where('shop_id', $fromShopId)
                ->first();

            if (!$sourceStock || $sourceStock->stock < $quantity) {
                return ApiResponse::error('Insufficient stock available for transfer', 422);
            }

            // Update source shop stock
            $sourceStock->decrement('stock', $quantity);

            // Update or create destination shop stock
            $destStock = ProductStock::updateOrCreate(
                ['product_id' => $product->id, 'shop_id' => $toShopId],
                ['stock' => DB::raw('stock + ' . $quantity)]
            );

            // Create stock movement records
            $movementNote = $request->input('notes', 'Stock transfer between shops');

            // Outgoing movement
            StockMovement::create([
                'product_id' => $product->id,
                'shop_id' => $fromShopId,
                'quantity' => -$quantity,
                'movement_type' => 'transfer',
                'reference_type' => 'stock_transfer',
                'reference_id' => null,
                'notes' => $movementNote . ' (Source)',
                'user_id' => $user->id,
            ]);

            // Incoming movement
            $incomingMovement = StockMovement::create([
                'product_id' => $product->id,
                'shop_id' => $toShopId,
                'quantity' => $quantity,
                'movement_type' => 'transfer',
                'reference_type' => 'stock_transfer',
                'reference_id' => null,
                'notes' => $movementNote . ' (Destination)',
                'user_id' => $user->id,
            ]);

            // Update reference IDs to link the movements
            StockMovement::where('id', $incomingMovement->id - 1)
                ->update(['reference_id' => $incomingMovement->id]);
            $incomingMovement->update(['reference_id' => $incomingMovement->id - 1]);

            DB::commit();

            // Get updated stock information for both shops
            $updatedStocks = ProductStock::where('product_id', $product->id)
                ->whereIn('shop_id', [$fromShopId, $toShopId])
                ->with('shop')
                ->get();

            return ApiResponse::success(
                $updatedStocks,
                "Successfully transferred {$quantity} units from shop #{$fromShopId} to shop #{$toShopId}"
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to transfer stock: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get stock movement history for a product
     *
     * @param Request $request
     * @param Product $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStockMovements(Request $request, Product $product)
    {
        try {
            $user = $request->user();
            $movementsQuery = StockMovement::where('product_id', $product->id);

            // Filter based on user role
            if ($user->role === 'super_admin') {
                // Super admin can see all movements
            } elseif ($user->role === 'owner') {
                // Owner can only see movements in owned shops
                $ownedShopIds = ShopOwner::where('user_id', $user->id)->pluck('shop_id')->toArray();
                $movementsQuery->whereIn('shop_id', $ownedShopIds);
            } else {
                // Admin, manager, cashier can only see movements in their shop
                $movementsQuery->where('shop_id', $user->shop_id);
            }

            // Filter by shop
            if ($request->has('shop_id')) {
                $movementsQuery->where('shop_id', $request->shop_id);
            }

            // Filter by movement type
            if ($request->has('movement_type')) {
                $movementsQuery->where('movement_type', $request->movement_type);
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $movementsQuery->whereDate('created_at', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $movementsQuery->whereDate('created_at', '<=', $request->end_date);
            }

            // Eager load relationships
            $movementsQuery->with(['shop', 'user']);

            // Sort by date (newest first)
            $movementsQuery->orderBy('created_at', 'desc');

            // Paginate results
            $perPage = $request->input('per_page', 15);
            $movements = $movementsQuery->paginate($perPage);

            return ApiResponse::success($movements, 'Stock movements retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve stock movements: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get low stock products across all accessible shops
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLowStockProducts(Request $request)
    {
        try {
            $user = $request->user();

            // Start with product stocks query
            $stocksQuery = ProductStock::query()
                ->whereRaw('stock <= min_stock')
                ->with(['product', 'shop']);

            // Filter based on user role
            if ($user->role === 'super_admin') {
                // Super admin can see low stock in all shops
            } elseif ($user->role === 'owner') {
                // Owner can only see low stock in owned shops
                $ownedShopIds = ShopOwner::where('user_id', $user->id)->pluck('shop_id')->toArray();
                $stocksQuery->whereIn('shop_id', $ownedShopIds);
            } else {
                // Admin, manager, cashier can only see low stock in their shop
                $stocksQuery->where('shop_id', $user->shop_id);
            }

            // Filter by shop if provided
            if ($request->has('shop_id')) {
                $stocksQuery->where('shop_id', $request->shop_id);
            }

            // Filter by category if provided
            if ($request->has('category_id')) {
                $stocksQuery->whereHas('product', function($query) use ($request) {
                    $query->where('category_id', $request->category_id);
                });
            }

            // Filter only active products
            $stocksQuery->whereHas('product', function($query) {
                $query->where('is_active', true)
                    ->where('is_using_stock', true);
            });

            // Additional product data
            $stocksQuery->with(['product.category']);

            // Sort by stock level (lowest first)
            $stocksQuery->orderByRaw('(stock / NULLIF(min_stock, 0)) ASC');

            // Paginate results
            $perPage = $request->input('per_page', 15);
            $lowStockProducts = $stocksQuery->paginate($perPage);

            return ApiResponse::success($lowStockProducts, 'Low stock products retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve low stock products: ' . $e->getMessage(), 500);
        }
    }
}
