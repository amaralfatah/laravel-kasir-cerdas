<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\ShopOwner;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseOrderController extends Controller
{
    /**
     * Display a listing of purchase orders.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $query = PurchaseOrder::query();

            // Apply filters based on user role
            if ($user->role === 'super_admin') {
                // Super admin can see all purchase orders
            } elseif ($user->role === 'owner') {
                // Owner can only see purchase orders in owned shops
                $ownedShopIds = ShopOwner::where('user_id', $user->id)->pluck('shop_id')->toArray();
                $query->whereIn('shop_id', $ownedShopIds);
            } else {
                // Admin, manager, cashier can only see purchase orders in their shop
                $query->where('shop_id', $user->shop_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by supplier
            if ($request->has('supplier_id')) {
                $query->where('supplier_id', $request->supplier_id);
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->whereDate('order_date', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->whereDate('order_date', '<=', $request->end_date);
            }

            // Filter by po_number
            if ($request->has('search')) {
                $query->where('po_number', 'like', '%' . $request->search . '%');
            }

            // Sort by date (newest first)
            $query->orderBy('order_date', 'desc');

            // Eager load relationships
            $query->with(['supplier', 'shop', 'createdBy', 'receivedBy']);

            // Paginate results
            $perPage = $request->input('per_page', 15);
            $purchaseOrders = $query->paginate($perPage);

            return ApiResponse::success($purchaseOrders, 'Purchase orders retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve purchase orders: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created purchase order.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'supplier_id' => 'required|exists:suppliers,id',
                'shop_id' => 'required|exists:shops,id',
                'order_date' => 'required|date',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_price' => 'required|numeric|min:0',
                'notes' => 'nullable|string',
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
                    return ApiResponse::forbidden('You do not have access to create purchase orders for this shop');
                }
            }

            // Calculate total
            $total = 0;
            foreach ($request->items as $item) {
                $total += $item['quantity'] * $item['unit_price'];
            }

            // Generate PO Number
            $poNumber = PurchaseOrder::generatePoNumber();

            // Create purchase order
            $purchaseOrder = PurchaseOrder::create([
                'po_number' => $poNumber,
                'supplier_id' => $request->supplier_id,
                'shop_id' => $shopId,
                'total' => $total,
                'status' => 'draft',
                'created_by' => $user->id,
                'notes' => $request->notes,
                'order_date' => $request->order_date,
            ]);

            // Create purchase order items
            foreach ($request->items as $itemData) {
                $purchaseOrder->items()->create([
                    'product_id' => $itemData['product_id'],
                    'quantity' => $itemData['quantity'],
                    'received_quantity' => 0,
                    'unit_price' => $itemData['unit_price'],
                ]);
            }

            DB::commit();

            // Load relationships for response
            $purchaseOrder->load(['supplier', 'shop', 'createdBy', 'items.product']);

            return ApiResponse::success($purchaseOrder, 'Purchase order created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to create purchase order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified purchase order.
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id, Request $request)
    {
        try {
            $user = $request->user();
            $purchaseOrder = PurchaseOrder::with(['supplier', 'shop', 'createdBy', 'receivedBy', 'items.product'])->findOrFail($id);

            // Check if user has access to this purchase order
            if ($user->role !== 'super_admin') {
                if ($user->role === 'owner') {
                    $hasAccess = ShopOwner::where('user_id', $user->id)
                        ->where('shop_id', $purchaseOrder->shop_id)
                        ->exists();
                } else {
                    $hasAccess = $user->shop_id == $purchaseOrder->shop_id;
                }

                if (!$hasAccess) {
                    return ApiResponse::forbidden('You do not have access to view this purchase order');
                }
            }

            return ApiResponse::success($purchaseOrder, 'Purchase order retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve purchase order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified purchase order.
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
            $purchaseOrder = PurchaseOrder::findOrFail($id);

            // Check if user has access to this purchase order
            if ($user->role !== 'super_admin') {
                if ($user->role === 'owner') {
                    $hasAccess = ShopOwner::where('user_id', $user->id)
                        ->where('shop_id', $purchaseOrder->shop_id)
                        ->exists();
                } else {
                    $hasAccess = $user->shop_id == $purchaseOrder->shop_id;
                }

                if (!$hasAccess) {
                    return ApiResponse::forbidden('You do not have access to update this purchase order');
                }
            }

            // Can only update purchase order if it's in draft status
            if ($purchaseOrder->status !== 'draft') {
                return ApiResponse::error('Purchase order can only be updated in draft status', 422);
            }

            $validator = Validator::make($request->all(), [
                'supplier_id' => 'exists:suppliers,id',
                'order_date' => 'date',
                'items' => 'array',
                'items.*.id' => 'nullable|exists:purchase_order_items,id,po_id,' . $id,
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_price' => 'required|numeric|min:0',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors());
            }

            // Update purchase order
            if ($request->has('supplier_id')) {
                $purchaseOrder->supplier_id = $request->supplier_id;
            }
            if ($request->has('order_date')) {
                $purchaseOrder->order_date = $request->order_date;
            }
            if ($request->has('notes')) {
                $purchaseOrder->notes = $request->notes;
            }

            // Process items if provided
            if ($request->has('items')) {
                // Calculate new total
                $total = 0;
                $currentItemIds = [];

                foreach ($request->items as $itemData) {
                    $subtotal = $itemData['quantity'] * $itemData['unit_price'];
                    $total += $subtotal;

                    if (isset($itemData['id'])) {
                        // Update existing item
                        $item = PurchaseOrderItem::where('id', $itemData['id'])
                            ->where('po_id', $id)
                            ->first();

                        if ($item) {
                            $item->product_id = $itemData['product_id'];
                            $item->quantity = $itemData['quantity'];
                            $item->unit_price = $itemData['unit_price'];
                            $item->save();
                            $currentItemIds[] = $item->id;
                        }
                    } else {
                        // Create new item
                        $item = $purchaseOrder->items()->create([
                            'product_id' => $itemData['product_id'],
                            'quantity' => $itemData['quantity'],
                            'received_quantity' => 0,
                            'unit_price' => $itemData['unit_price'],
                        ]);
                        $currentItemIds[] = $item->id;
                    }
                }

                // Delete items not in the update request
                PurchaseOrderItem::where('po_id', $id)
                    ->whereNotIn('id', $currentItemIds)
                    ->delete();

                // Update total
                $purchaseOrder->total = $total;
            }

            $purchaseOrder->save();

            DB::commit();

            // Load relationships for response
            $purchaseOrder->load(['supplier', 'shop', 'createdBy', 'items.product']);

            return ApiResponse::success($purchaseOrder, 'Purchase order updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to update purchase order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Change purchase order status to ordered.
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function order($id, Request $request)
    {
        try {
            DB::beginTransaction();

            $user = $request->user();
            $purchaseOrder = PurchaseOrder::findOrFail($id);

            // Check if user has access to this purchase order
            if ($user->role !== 'super_admin') {
                if ($user->role === 'owner') {
                    $hasAccess = ShopOwner::where('user_id', $user->id)
                        ->where('shop_id', $purchaseOrder->shop_id)
                        ->exists();
                } else {
                    $hasAccess = $user->shop_id == $purchaseOrder->shop_id;
                }

                if (!$hasAccess) {
                    return ApiResponse::forbidden('You do not have access to order this purchase order');
                }
            }

            // Can only order purchase order if it's in draft status
            if ($purchaseOrder->status !== 'draft') {
                return ApiResponse::error('Purchase order can only be ordered if it is in draft status', 422);
            }

            // Check if purchase order has items
            if ($purchaseOrder->items()->count() === 0) {
                return ApiResponse::error('Purchase order has no items', 422);
            }

            // Update status to ordered
            $purchaseOrder->status = 'ordered';
            $purchaseOrder->save();

            DB::commit();

            // Load relationships for response
            $purchaseOrder->load(['supplier', 'shop', 'createdBy', 'items.product']);

            return ApiResponse::success($purchaseOrder, 'Purchase order status changed to ordered');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to update purchase order status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Receive items from a purchase order.
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function receive($id, Request $request)
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'items' => 'required|array|min:1',
                'items.*.id' => 'required|exists:purchase_order_items,id,po_id,' . $id,
                'items.*.received_quantity' => 'required|integer|min:0',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors());
            }

            $user = $request->user();
            $purchaseOrder = PurchaseOrder::findOrFail($id);

            // Check if user has access to this purchase order
            if ($user->role !== 'super_admin') {
                if ($user->role === 'owner') {
                    $hasAccess = ShopOwner::where('user_id', $user->id)
                        ->where('shop_id', $purchaseOrder->shop_id)
                        ->exists();
                } else {
                    $hasAccess = $user->shop_id == $purchaseOrder->shop_id;
                }

                if (!$hasAccess) {
                    return ApiResponse::forbidden('You do not have access to receive items for this purchase order');
                }
            }

            // Can only receive items if in ordered or partial status
            if (!in_array($purchaseOrder->status, ['ordered', 'partial'])) {
                return ApiResponse::error('Can only receive items if purchase order is in ordered or partial status', 422);
            }

            $allItemsReceived = true;
            $anyItemsReceived = false;

            // Process received items
            foreach ($request->items as $itemData) {
                $item = PurchaseOrderItem::where('id', $itemData['id'])
                    ->where('po_id', $id)
                    ->firstOrFail();

                $additionalQuantity = $itemData['received_quantity'] - $item->received_quantity;

                // Skip if no additional items received
                if ($additionalQuantity <= 0) {
                    if ($item->received_quantity < $item->quantity) {
                        $allItemsReceived = false;
                    }
                    continue;
                }

                $anyItemsReceived = true;

                // Update received quantity
                $item->received_quantity = $itemData['received_quantity'];
                $item->save();

                // Check if this item is fully received
                if ($item->received_quantity < $item->quantity) {
                    $allItemsReceived = false;
                }

                // Update product stock
                $product = Product::find($item->product_id);
                if ($product->is_using_stock) {
                    // Find or create product stock
                    $productStock = ProductStock::firstOrCreate(
                        [
                            'product_id' => $item->product_id,
                            'shop_id' => $purchaseOrder->shop_id
                        ],
                        [
                            'stock' => 0,
                            'min_stock' => 0
                        ]
                    );

                    // Increment stock
                    $productStock->increment('stock', $additionalQuantity);

                    // Update product purchase price
                    $product->purchase_price = $item->unit_price;
                    $product->save();

                    // Create stock movement record
                    StockMovement::create([
                        'product_id' => $item->product_id,
                        'shop_id' => $purchaseOrder->shop_id,
                        'quantity' => $additionalQuantity,
                        'movement_type' => 'purchase',
                        'reference_type' => 'purchase_order',
                        'reference_id' => $purchaseOrder->id,
                        'notes' => 'Received from PO: ' . $purchaseOrder->po_number,
                        'user_id' => $user->id,
                    ]);
                }
            }

            // Update purchase order status
            if ($anyItemsReceived) {
                if ($allItemsReceived) {
                    $purchaseOrder->status = 'received';
                } else {
                    $purchaseOrder->status = 'partial';
                }

                // Set received_by if it's first time receiving
                if (!$purchaseOrder->received_by) {
                    $purchaseOrder->received_by = $user->id;
                }

                // Update notes if provided
                if ($request->has('notes')) {
                    $purchaseOrder->notes = $request->notes;
                }

                $purchaseOrder->save();
            }

            DB::commit();

            // Load relationships for response
            $purchaseOrder->load(['supplier', 'shop', 'createdBy', 'receivedBy', 'items.product']);

            return ApiResponse::success($purchaseOrder, 'Items received successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to receive items: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cancel purchase order.
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancel($id, Request $request)
    {
        try {
            $user = $request->user();
            $purchaseOrder = PurchaseOrder::findOrFail($id);

            // Check if user has access to this purchase order
            if ($user->role !== 'super_admin') {
                if ($user->role === 'owner') {
                    $hasAccess = ShopOwner::where('user_id', $user->id)
                        ->where('shop_id', $purchaseOrder->shop_id)
                        ->exists();
                } else {
                    $hasAccess = $user->shop_id == $purchaseOrder->shop_id;
                }

                if (!$hasAccess) {
                    return ApiResponse::forbidden('You do not have access to cancel this purchase order');
                }
            }

            // Can only cancel if not received or already canceled
            if (in_array($purchaseOrder->status, ['received', 'canceled'])) {
                return ApiResponse::error('Cannot cancel purchase order with status: ' . $purchaseOrder->status, 422);
            }

            // Update status to canceled
            $purchaseOrder->status = 'canceled';
            $purchaseOrder->save();

            return ApiResponse::success($purchaseOrder, 'Purchase order canceled successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to cancel purchase order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified purchase order.
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id, Request $request)
    {
        try {
            $user = $request->user();
            $purchaseOrder = PurchaseOrder::findOrFail($id);

            // Check if user has access to this purchase order
            if ($user->role !== 'super_admin') {
                if ($user->role === 'owner') {
                    $hasAccess = ShopOwner::where('user_id', $user->id)
                        ->where('shop_id', $purchaseOrder->shop_id)
                        ->exists();
                } else {
                    $hasAccess = $user->shop_id == $purchaseOrder->shop_id;
                }

                if (!$hasAccess) {
                    return ApiResponse::forbidden('You do not have access to delete this purchase order');
                }
            }

            // Can only delete if in draft or canceled status
            if (!in_array($purchaseOrder->status, ['draft', 'canceled'])) {
                return ApiResponse::error('Can only delete purchase order in draft or canceled status', 422);
            }

            // Delete purchase order items
            $purchaseOrder->items()->delete();

            // Delete purchase order
            $purchaseOrder->delete();

            return ApiResponse::success(null, 'Purchase order deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete purchase order: ' . $e->getMessage(), 500);
        }
    }
}
