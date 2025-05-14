<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $query = Customer::query();

            // Search by name, phone, or email
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Sort by field
            $sortField = $request->input('sort_by', 'created_at');
            $sortDirection = $request->input('sort_direction', 'desc');
            $allowedSortFields = ['name', 'points', 'created_at', 'total_spent'];

            if (in_array($sortField, $allowedSortFields)) {
                if ($sortField === 'total_spent') {
                    // Use withCount and orderByRaw for total_spent
                    $query->withCount(['transactions as total_spent' => function($q) {
                        $q->where('payment_status', 'completed')
                            ->select(\DB::raw('SUM(total_amount)'));
                    }])
                        ->orderByRaw("total_spent {$sortDirection}");
                } else {
                    $query->orderBy($sortField, $sortDirection);
                }
            }

            // Eager load transaction counts
            $query->withCount('transactions');

            // Paginate the results
            $customers = $query->paginate($request->input('per_page', 15));

            return ApiResponse::success($customers, 'Customers retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve customers: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created customer.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'phone' => 'nullable|string|max:20|unique:customers,phone',
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string',
                'points' => 'integer|min:0',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors());
            }

            $customer = Customer::create([
                'name' => $request->name,
                'phone' => $request->phone,
                'email' => $request->email,
                'address' => $request->address,
                'points' => $request->input('points', 0),
                'is_active' => $request->input('is_active', true),
            ]);

            return ApiResponse::success($customer, 'Customer created successfully', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create customer: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified customer.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $customer = Customer::with([
                'transactions' => function($query) {
                    $query->orderBy('transaction_date', 'desc')
                        ->limit(10);
                }
            ])
                ->withCount('transactions')
                ->findOrFail($id);

            // Calculate total spent
            $totalSpent = $customer->transactions()
                ->where('payment_status', 'completed')
                ->sum('total_amount');

            $customerData = $customer->toArray();
            $customerData['total_spent'] = $totalSpent;

            return ApiResponse::success($customerData, 'Customer retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve customer: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified customer.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $customer = Customer::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'string|max:255',
                'phone' => 'nullable|string|max:20|unique:customers,phone,' . $id,
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string',
                'points' => 'integer|min:0',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors());
            }

            $customer->update($request->only([
                'name', 'phone', 'email', 'address', 'points', 'is_active'
            ]));

            return ApiResponse::success($customer, 'Customer updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update customer: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified customer.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $customer = Customer::findOrFail($id);

            // Check if customer has transactions
            $hasTransactions = $customer->transactions()->exists();

            if ($hasTransactions) {
                // Instead of deleting, just mark as inactive
                $customer->is_active = false;
                $customer->save();
                return ApiResponse::success(null, 'Customer marked as inactive (cannot be deleted due to existing transactions)');
            }

            // If no transactions, can be deleted
            $customer->delete();

            return ApiResponse::success(null, 'Customer deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete customer: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get customer transaction history.
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function transactionHistory($id, Request $request)
    {
        try {
            $customer = Customer::findOrFail($id);

            $query = $customer->transactions()
                ->with(['items.product', 'shop', 'paymentMethod'])
                ->orderBy('transaction_date', 'desc');

            // Date filtering
            if ($request->has('start_date')) {
                $query->whereDate('transaction_date', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->whereDate('transaction_date', '<=', $request->end_date);
            }

            // Payment status filtering
            if ($request->has('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            // Transaction type filtering
            if ($request->has('transaction_type')) {
                $query->where('transaction_type', $request->transaction_type);
            }

            // Paginate the results
            $transactions = $query->paginate($request->input('per_page', 15));

            return ApiResponse::success($transactions, 'Customer transaction history retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve customer transaction history: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Adjust customer points.
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function adjustPoints($id, Request $request)
    {
        try {
            $customer = Customer::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'points' => 'required|integer',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors());
            }

            // Only superadmin, owner, and admin can adjust points
            $user = $request->user();
            if (!in_array($user->role, ['super_admin', 'owner', 'admin'])) {
                return ApiResponse::forbidden('You do not have permission to adjust customer points');
            }

            $oldPoints = $customer->points;
            $newPoints = $oldPoints + $request->points;

            // Don't allow negative points
            if ($newPoints < 0) {
                return ApiResponse::error('Cannot reduce points below zero', 422);
            }

            $customer->points = $newPoints;
            $customer->save();

            // Add an audit entry (you may need to create this model)
            // PointsAdjustment::create([
            //     'customer_id' => $customer->id,
            //     'user_id' => $user->id,
            //     'points' => $request->points,
            //     'old_balance' => $oldPoints,
            //     'new_balance' => $newPoints,
            //     'notes' => $request->notes,
            // ]);

            return ApiResponse::success([
                'customer' => $customer,
                'adjustment' => [
                    'old_points' => $oldPoints,
                    'adjustment' => $request->points,
                    'new_points' => $newPoints,
                    'notes' => $request->notes,
                ]
            ], 'Customer points adjusted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to adjust customer points: ' . $e->getMessage(), 500);
        }
    }
}
