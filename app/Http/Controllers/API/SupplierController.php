<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupplierController extends Controller
{
    /**
     * Display a listing of suppliers.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $query = Supplier::query();

            // Apply search filter
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('contact_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Sort by field
            $sortField = $request->input('sort_by', 'name');
            $sortDirection = $request->input('sort_direction', 'asc');
            $allowedSortFields = ['name', 'contact_name', 'created_at', 'updated_at'];

            if (in_array($sortField, $allowedSortFields)) {
                $query->orderBy($sortField, $sortDirection);
            }

            // Paginate results
            $perPage = $request->input('per_page', 15);
            $suppliers = $query->paginate($perPage);

            return ApiResponse::success($suppliers, 'Suppliers retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve suppliers: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created supplier.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            // Only admin and above can create suppliers
            $user = $request->user();
            if (!in_array($user->role, ['super_admin', 'owner', 'admin'])) {
                return ApiResponse::forbidden('You do not have permission to create suppliers');
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'contact_name' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors());
            }

            $supplier = Supplier::create([
                'name' => $request->name,
                'contact_name' => $request->contact_name,
                'phone' => $request->phone,
                'email' => $request->email,
                'address' => $request->address,
                'is_active' => $request->input('is_active', true),
            ]);

            return ApiResponse::success($supplier, 'Supplier created successfully', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create supplier: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified supplier.
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id, Request $request)
    {
        try {
            $supplier = Supplier::findOrFail($id);

            // Get related statistics if requested
            $withStats = $request->boolean('with_stats', false);
            $data = ['supplier' => $supplier];

            if ($withStats) {
                // Count purchase orders by status
                $poStats = PurchaseOrder::where('supplier_id', $id)
                    ->selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->get()
                    ->pluck('count', 'status')
                    ->toArray();

                // Total amount spent
                $totalSpent = PurchaseOrder::where('supplier_id', $id)
                    ->whereIn('status', ['received', 'partial'])
                    ->sum('total');

                // Recent purchase orders
                $recentOrders = PurchaseOrder::where('supplier_id', $id)
                    ->orderBy('order_date', 'desc')
                    ->limit(5)
                    ->with(['shop'])
                    ->get();

                $data['stats'] = [
                    'po_count' => array_sum($poStats),
                    'po_by_status' => $poStats,
                    'total_spent' => $totalSpent,
                    'recent_orders' => $recentOrders
                ];
            }

            return ApiResponse::success($data, 'Supplier retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve supplier: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified supplier.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            // Only admin and above can update suppliers
            $user = $request->user();
            if (!in_array($user->role, ['super_admin', 'owner', 'admin'])) {
                return ApiResponse::forbidden('You do not have permission to update suppliers');
            }

            $supplier = Supplier::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'string|max:255',
                'contact_name' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors());
            }

            $supplier->update($request->only([
                'name', 'contact_name', 'phone', 'email', 'address', 'is_active'
            ]));

            return ApiResponse::success($supplier, 'Supplier updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update supplier: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified supplier.
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id, Request $request)
    {
        try {
            // Only super_admin and owner can delete suppliers
            $user = $request->user();
            if (!in_array($user->role, ['super_admin', 'owner'])) {
                return ApiResponse::forbidden('You do not have permission to delete suppliers');
            }

            $supplier = Supplier::findOrFail($id);

            // Check if supplier has related purchase orders
            $hasPurchaseOrders = PurchaseOrder::where('supplier_id', $id)->exists();

            if ($hasPurchaseOrders) {
                // Instead of deleting, just mark as inactive
                $supplier->is_active = false;
                $supplier->save();
                return ApiResponse::success(null, 'Supplier marked as inactive (cannot be deleted due to existing purchase orders)');
            }

            // If not used in any purchase orders, it can be deleted
            $supplier->delete();

            return ApiResponse::success(null, 'Supplier deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete supplier: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get purchase orders for a specific supplier.
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function purchaseOrders($id, Request $request)
    {
        try {
            $supplier = Supplier::findOrFail($id);

            $query = PurchaseOrder::where('supplier_id', $id);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('start_date')) {
                $query->whereDate('order_date', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->whereDate('order_date', '<=', $request->end_date);
            }

            // Sort by date (newest first by default)
            $query->orderBy('order_date', $request->input('sort_direction', 'desc'));

            // Eager load relationships
            $query->with(['shop', 'createdBy', 'receivedBy']);

            // Paginate results
            $perPage = $request->input('per_page', 15);
            $purchaseOrders = $query->paginate($perPage);

            return ApiResponse::success([
                'supplier' => $supplier,
                'purchase_orders' => $purchaseOrders
            ], 'Supplier purchase orders retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve supplier purchase orders: ' . $e->getMessage(), 500);
        }
    }
}
