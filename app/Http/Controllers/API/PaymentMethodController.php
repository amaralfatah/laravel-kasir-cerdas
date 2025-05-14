<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentMethodController extends Controller
{
    /**
     * Display a listing of payment methods.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $paymentMethods = PaymentMethod::all();
            return ApiResponse::success($paymentMethods, 'Payment methods retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve payment methods: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created payment method.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            // Only admin and above can create payment methods
            $user = $request->user();
            if (!in_array($user->role, ['super_admin', 'owner', 'admin'])) {
                return ApiResponse::forbidden('You do not have permission to create payment methods');
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'is_digital' => 'boolean',
                'fee_percentage' => 'numeric|min:0',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors());
            }

            $paymentMethod = PaymentMethod::create([
                'name' => $request->name,
                'is_digital' => $request->input('is_digital', false),
                'fee_percentage' => $request->input('fee_percentage', 0),
                'is_active' => $request->input('is_active', true),
            ]);

            return ApiResponse::success($paymentMethod, 'Payment method created successfully', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create payment method: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified payment method.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $paymentMethod = PaymentMethod::findOrFail($id);

            // Get usage statistics
            $transactionCount = $paymentMethod->transactions()->count();
            $paymentCount = $paymentMethod->transactionPayments()->count();

            return ApiResponse::success([
                'payment_method' => $paymentMethod,
                'stats' => [
                    'transaction_count' => $transactionCount,
                    'payment_count' => $paymentCount,
                ]
            ], 'Payment method retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve payment method: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified payment method.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            // Only admin and above can update payment methods
            $user = $request->user();
            if (!in_array($user->role, ['super_admin', 'owner', 'admin'])) {
                return ApiResponse::forbidden('You do not have permission to update payment methods');
            }

            $paymentMethod = PaymentMethod::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'string|max:255',
                'is_digital' => 'boolean',
                'fee_percentage' => 'numeric|min:0',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors());
            }

            $paymentMethod->update($request->only([
                'name', 'is_digital', 'fee_percentage', 'is_active'
            ]));

            return ApiResponse::success($paymentMethod, 'Payment method updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update payment method: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified payment method (soft delete by deactivation).
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id, Request $request)
    {
        try {
            // Only super_admin and owner can delete payment methods
            $user = $request->user();
            if (!in_array($user->role, ['super_admin', 'owner'])) {
                return ApiResponse::forbidden('You do not have permission to delete payment methods');
            }

            $paymentMethod = PaymentMethod::findOrFail($id);

            // Check if payment method has been used in transactions
            $hasTransactions = $paymentMethod->transactions()->exists() ||
                $paymentMethod->transactionPayments()->exists();

            if ($hasTransactions) {
                // Instead of deleting, just mark as inactive
                $paymentMethod->is_active = false;
                $paymentMethod->save();
                return ApiResponse::success(null, 'Payment method deactivated (cannot be deleted due to existing transactions)');
            }

            // If not used, can delete
            $paymentMethod->delete();

            return ApiResponse::success(null, 'Payment method deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete payment method: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Calculate fee for a given amount.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function calculateFee(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'payment_method_id' => 'required|exists:payment_methods,id',
                'amount' => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors());
            }

            $paymentMethod = PaymentMethod::findOrFail($request->payment_method_id);
            $amount = $request->amount;

            $fee = $paymentMethod->calculateFee($amount);
            $totalWithFee = $amount + $fee;

            return ApiResponse::success([
                'payment_method' => $paymentMethod->name,
                'amount' => $amount,
                'fee_percentage' => $paymentMethod->fee_percentage,
                'fee_amount' => $fee,
                'total_with_fee' => $totalWithFee,
            ], 'Fee calculated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to calculate fee: ' . $e->getMessage(), 500);
        }
    }
}
