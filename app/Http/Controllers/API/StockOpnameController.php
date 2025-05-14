<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\ShopOwner;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StockOpnameController extends Controller
{
    /**
     * Display a listing of stock opnames.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $query = StockOpname::query();

            // Apply filters based on user role
            if ($user->role === 'super_admin') {
                // Super admin can see all stock opnames
            } elseif ($user->role === 'owner') {
                // Owner can only see stock opnames in owned shops
                $ownedShopIds = ShopOwner::where('user_id', $user->id)->pluck('shop_id')->toArray();
                $query->whereIn('shop_id', $ownedShopIds);
            } else {
                // Admin, manager, cashier can only see stock opnames in their shop
                $query->where('shop_id', $user->shop_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->whereDate('conducted_at', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->whereDate('conducted_at', '<=', $request->end_date);
            }

            // Filter by shop
            if ($request->has('shop_id') && $user->role === 'super_admin') {
                $query->where('shop_id', $request->shop_id);
            }

            // Sort by date (newest first)
            $query->orderBy('conducted_at', 'desc');

            // Eager load relationships
            $query->with(['shop', 'conductedBy', 'approvedBy']);

            // Paginate results
            $perPage = $request->input('per_page', 15);
            $stockOpnames = $query->paginate($perPage);

            return ApiResponse::success($stockOpnames, 'Stock opnames retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve stock opnames: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created stock opname.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'shop_id' => 'required|exists:shops,id',
                'notes' => 'nullable|string',
                'conducted_at' => 'nullable|date',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.physical_stock' => 'required|integer|min:0',
                'items.*.notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors());
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
                    return ApiResponse::forbidden('You do not have access to create stock opname in this shop');
                }
            }

            // Create stock opname
            $stockOpname = StockOpname::create([
                'shop_id' => $shopId,
                'status' => 'draft',
                'notes' => $request->notes,
                'conducted_by' => $user->id,
                'conducted_at' => $request->input('conducted_at', now()->toDateString()),
            ]);

            // Process items
            foreach ($request->items as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);

                // Get current system stock
                $productStock = ProductStock::where('product_id', $product->id)
                    ->where('shop_id', $shopId)
                    ->first();

                $systemStock = $productStock ? $productStock->stock : 0;
                $physicalStock = $itemData['physical_stock'];
                $variance = $physicalStock - $systemStock;

                // Create stock opname item
                $stockOpname->items()->create([
                    'product_id' => $product->id,
                    'physical_stock' => $physicalStock,
                    'system_stock' => $systemStock,
                    'variance' => $variance,
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            DB::commit();

            // Load relationships for response
            $stockOpname->load(['shop', 'conductedBy', 'items.product']);

            return ApiResponse::success($stockOpname, 'Stock opname created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to create stock opname: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified stock opname.
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id, Request $request)
    {
        try {
            $user = $request->user();
            $stockOpname = StockOpname::with(['shop', 'conductedBy', 'approvedBy', 'items.product'])->findOrFail($id);

            // Check if user has access to this stock opname
            if ($user->role !== 'super_admin') {
                if ($user->role === 'owner') {
                    $hasAccess = ShopOwner::where('user_id', $user->id)
                        ->where('shop_id', $stockOpname->shop_id)
                        ->exists();
                } else {
                    $hasAccess = $user->shop_id == $stockOpname->shop_id;
                }

                if (!$hasAccess) {
                    return ApiResponse::forbidden('You do not have access to view this stock opname');
                }
            }

            return ApiResponse::success($stockOpname, 'Stock opname retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve stock opname: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified stock opname.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $user = $request->user();
            $stockOpname = StockOpname::findOrFail($id);

            // Check if user has access to this stock opname
            if ($user->role !== 'super_admin') {
                if ($user->role === 'owner') {
                    $hasAccess = ShopOwner::where('user_id', $user->id)
                        ->where('shop_id', $stockOpname->shop_id)
                        ->exists();
                } else {
                    $hasAccess = $user->shop_id == $stockOpname->shop_id;
                }

                if (!$hasAccess) {
                    return ApiResponse::forbidden('You do not have access to update this stock opname');
                }
            }

            // Can only update stock opname if it's in draft status
            if ($stockOpname->status !== 'draft') {
                return ApiResponse::error('Stock opname can only be updated in draft status', 422);
            }

            $validator = Validator::make($request->all(), [
                'notes' => 'nullable|string',
                'conducted_at' => 'nullable|date',
                'items' => 'array',
                'items.*.id' => 'nullable|exists:stock_opname_items,id,stock_opname_id,' . $id,
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.physical_stock' => 'required|integer|min:0',
                'items.*.notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors());
            }

            // Update stock opname
            $stockOpname->update([
                'notes' => $request->input('notes', $stockOpname->notes),
                'conducted_at' => $request->input('conducted_at', $stockOpname->conducted_at),
            ]);

            // Process items if provided
            if ($request->has('items')) {
                foreach ($request->items as $itemData) {
                    $product = Product::findOrFail($itemData['product_id']);

                    // Get current system stock
                    $productStock = ProductStock::where('product_id', $product->id)
                        ->where('shop_id', $stockOpname->shop_id)
                        ->first();

                    $systemStock = $productStock ? $productStock->stock : 0;
                    $physicalStock = $itemData['physical_stock'];
                    $variance = $physicalStock - $systemStock;

                    // Update or create stock opname item
                    if (isset($itemData['id'])) {
                        // Update existing item
                        StockOpnameItem::where('id', $itemData['id'])
                            ->where('stock_opname_id', $id)
                            ->update([
                                'physical_stock' => $physicalStock,
                                'system_stock' => $systemStock,
                                'variance' => $variance,
                                'notes' => $itemData['notes'] ?? null,
                            ]);
                    } else {
                        // Create new item
                        $stockOpname->items()->create([
                            'product_id' => $product->id,
                            'physical_stock' => $physicalStock,
                            'system_stock' => $systemStock,
                            'variance' => $variance,
                            'notes' => $itemData['notes'] ?? null,
                        ]);
                    }
                }
            }

            DB::commit();

            // Load relationships for response
            $stockOpname->load(['shop', 'conductedBy', 'items.product']);

            return ApiResponse::success($stockOpname, 'Stock opname updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to update stock opname: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Submit stock opname for approval.
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function submit($id, Request $request)
    {
        try {
            DB::beginTransaction();

            $user = $request->user();
            $stockOpname = StockOpname::findOrFail($id);

            // Check if user has access to this stock opname
            if ($user->role !== 'super_admin') {
                if ($user->role === 'owner') {
                    $hasAccess = ShopOwner::where('user_id', $user->id)
                        ->where('shop_id', $stockOpname->shop_id)
                        ->exists();
                } else {
                    $hasAccess = $user->shop_id == $stockOpname->shop_id;
                }

                if (!$hasAccess) {
                    return ApiResponse::forbidden('You do not have access to submit this stock opname');
                }
            }

            // Can only submit stock opname if it's in draft status
            if ($stockOpname->status !== 'draft') {
                return ApiResponse::error('Stock opname can only be submitted in draft status', 422);
            }

            // Check if stock opname has items
            if ($stockOpname->items()->count() === 0) {
                return ApiResponse::error('Stock opname has no items', 422);
            }

            // Update status to pending
            $stockOpname->status = 'pending';
            $stockOpname->save();

            DB::commit();

            // Load relationships for response
            $stockOpname->load(['shop', 'conductedBy', 'items.product']);

            return ApiResponse::success($stockOpname, 'Stock opname submitted for approval');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to submit stock opname: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Approve stock opname and adjust stock.
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function approve($id, Request $request)
    {
        try {
            DB::beginTransaction();

            $user = $request->user();
            $stockOpname = StockOpname::with('items.product')->findOrFail($id);

            // Only admin, owner, or super_admin can approve
            if (!in_array($user->role, ['super_admin', 'owner', 'admin'])) {
                return ApiResponse::forbidden('Only admin or higher can approve stock opname');
            }

            // Check if user has access to this stock opname
            if ($user->role !== 'super_admin') {
                if ($user->role === 'owner') {
                    $hasAccess = ShopOwner::where('user_id', $user->id)
                        ->where('shop_id', $stockOpname->shop_id)
                        ->exists();
                } else {
                    $hasAccess = $user->shop_id == $stockOpname->shop_id;
                }

                if (!$hasAccess) {
                    return ApiResponse::forbidden('You do not have access to approve this stock opname');
                }
            }

            // Can only approve stock opname if it's in pending status
            if ($stockOpname->status !== 'pending') {
                return ApiResponse::error('Stock opname can only be approved in pending status', 422);
            }

            // Validate selective approvals if provided
            $approvalsProvided = $request->has('approvals');
            $approvedItems = [];

            if ($approvalsProvided) {
                $validator = Validator::make($request->all(), [
                    'approvals' => 'required|array',
                    'approvals.*' => 'required|exists:stock_opname_items,id,stock_opname_id,' . $id,
                ]);

                if ($validator->fails()) {
                    return ApiResponse::validationError($validator->errors());
                }

                $approvedItems = $request->approvals;
            }

            // Update stocks based on variances
            foreach ($stockOpname->items as $item) {
                // Skip if selective approvals provided and this item is not approved
                if ($approvalsProvided && !in_array($item->id, $approvedItems)) {
                    continue;
                }

                if ($item->variance != 0) {
                    // Update product stock
                    $productStock = ProductStock::where('product_id', $item->product_id)
                        ->where('shop_id', $stockOpname->shop_id)
                        ->first();

                    if (!$productStock) {
                        // Create new stock record if it doesn't exist
                        $productStock = ProductStock::create([
                            'product_id' => $item->product_id,
                            'shop_id' => $stockOpname->shop_id,
                            'stock' => $item->physical_stock,
                            'min_stock' => 0,
                        ]);
                    } else {
                        // Update existing stock
                        $productStock->stock = $item->physical_stock;
                        $productStock->save();
                    }

                    // Create stock movement record
                    StockMovement::create([
                        'product_id' => $item->product_id,
                        'shop_id' => $stockOpname->shop_id,
                        'quantity' => $item->variance,
                        'movement_type' => 'adjustment',
                        'reference_type' => 'stock_opname',
                        'reference_id' => $stockOpname->id,
                        'notes' => 'Stock adjustment from stock opname #' . $stockOpname->id,
                        'user_id' => $user->id,
                    ]);
                }
            }

            // Update stock opname status
            $stockOpname->status = 'approved';
            $stockOpname->approved_by = $user->id;
            $stockOpname->save();

            DB::commit();

            // Load relationships for response
            $stockOpname->load(['shop', 'conductedBy', 'approvedBy', 'items.product']);

            return ApiResponse::success($stockOpname, 'Stock opname approved and stock adjusted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to approve stock opname: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cancel stock opname.
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancel($id, Request $request)
    {
        try {
            $user = $request->user();
            $stockOpname = StockOpname::findOrFail($id);

            // Check if user has access to this stock opname
            if ($user->role !== 'super_admin') {
                if ($user->role === 'owner') {
                    $hasAccess = ShopOwner::where('user_id', $user->id)
                        ->where('shop_id', $stockOpname->shop_id)
                        ->exists();
                } else {
                    $hasAccess = $user->shop_id == $stockOpname->shop_id;
                }

                if (!$hasAccess) {
                    return ApiResponse::forbidden('You do not have access to cancel this stock opname');
                }
            }

            // Can only cancel stock opname if it's not already approved or canceled
            if ($stockOpname->status === 'approved' || $stockOpname->status === 'canceled') {
                return ApiResponse::error('Cannot cancel stock opname with status: ' . $stockOpname->status, 422);
            }

            // Update status to canceled
            $stockOpname->status = 'canceled';
            $stockOpname->save();

            return ApiResponse::success($stockOpname, 'Stock opname canceled successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to cancel stock opname: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified stock opname.
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id, Request $request)
    {
        try {
            $user = $request->user();
            $stockOpname = StockOpname::findOrFail($id);

            // Check if user has access to this stock opname
            if ($user->role !== 'super_admin') {
                if ($user->role === 'owner') {
                    $hasAccess = ShopOwner::where('user_id', $user->id)
                        ->where('shop_id', $stockOpname->shop_id)
                        ->exists();
                } else {
                    $hasAccess = $user->shop_id == $stockOpname->shop_id;
                }

                if (!$hasAccess) {
                    return ApiResponse::forbidden('You do not have access to delete this stock opname');
                }
            }

            // Can only delete stock opname if it's in draft or canceled status
            if (!in_array($stockOpname->status, ['draft', 'canceled'])) {
                return ApiResponse::error('Can only delete stock opname in draft or canceled status', 422);
            }

            // Delete stock opname items
            $stockOpname->items()->delete();

            // Delete stock opname
            $stockOpname->delete();

            return ApiResponse::success(null, 'Stock opname deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete stock opname: ' . $e->getMessage(), 500);
        }
    }
}
