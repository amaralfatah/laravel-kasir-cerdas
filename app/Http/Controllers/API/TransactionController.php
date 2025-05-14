<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionPayment;
use App\Models\Product;
use App\Models\Customer;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    /**
     * Display a listing of transactions.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $query = Transaction::query();

            // Apply user role restrictions
            if ($user->role !== 'super_admin') {
                if ($user->role === 'owner') {
                    $shopIds = $user->ownedShops->pluck('shop_id')->toArray();
                    $query->whereIn('shop_id', $shopIds);
                } else {
                    $query->where('shop_id', $user->shop_id);
                }
            }

            // Date filtering
            if ($request->has('start_date')) {
                $query->whereDate('transaction_date', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->whereDate('transaction_date', '<=', $request->end_date);
            }

            // Customer filtering
            if ($request->has('customer_id')) {
                $query->where('customer_id', $request->customer_id);
            }

            // Payment status filtering
            if ($request->has('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            // Transaction type filtering
            if ($request->has('transaction_type')) {
                $query->where('transaction_type', $request->transaction_type);
            }

            // Search by invoice number
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                });
            }

            // Order by
            $query->orderBy($request->input('sort_by', 'transaction_date'), $request->input('sort_direction', 'desc'));

            // Eager load relationships
            $query->with(['customer', 'paymentMethod', 'user', 'shop', 'payments']);

            // Paginate the results
            $transactions = $query->paginate($request->input('per_page', 15));

            return ApiResponse::success($transactions, 'Transactions retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve transactions: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created transaction.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * Store a newly created transaction.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $user = $request->user();
            $shopId = $request->input('shop_id', $user->shop_id);

            if (!$shopId) {
                return ApiResponse::error('Shop ID is required', 422);
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'customer_id' => 'nullable|exists:customers,id',
                'payment_method_id' => 'required|exists:payment_methods,id',
                'transaction_type' => 'required|in:sale,return,adjustment',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.price_category_id' => 'nullable|exists:price_categories,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_price' => 'nullable|numeric|min:0',
                'items.*.discount_amount' => 'nullable|numeric|min:0',
                'discount_amount' => 'nullable|numeric|min:0',
                'tax_amount' => 'nullable|numeric|min:0',
                'service_fee' => 'nullable|numeric|min:0',
                'payments' => 'required|array|min:1',
                'payments.*.payment_method_id' => 'required|exists:payment_methods,id',
                'payments.*.amount' => 'required|numeric|min:0',
                'payments.*.payment_reference' => 'nullable|string',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors());
            }

            // Calculate transaction amounts
            $subtotal = 0;
            $items = [];

            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);

                // Get price from price rules if not explicitly provided
                if (!isset($item['unit_price']) || $item['unit_price'] == null) {
                    // Pendekatan alternatif tanpa active() dan current() scope
                    $priceRule = $product->priceRules()
                        ->where('price_category_id', $item['price_category_id'] ?? 1)
                        ->where('min_quantity', '<=', $item['quantity'])
                        ->when($shopId, function($query) use ($shopId) {
                            return $query->where(function($q) use ($shopId) {
                                $q->where('shop_id', $shopId)
                                    ->orWhereNull('shop_id');
                            });
                        })
                        ->orderBy('min_quantity', 'desc')
                        ->first();

                    $unitPrice = $priceRule ? $priceRule->price : $product->selling_price;
                } else {
                    $unitPrice = $item['unit_price'];
                }

                $discountAmount = isset($item['discount_amount']) ? $item['discount_amount'] : 0;
                $taxAmount = isset($item['tax_amount']) ? $item['tax_amount'] : 0;
                $itemSubtotal = ($unitPrice * $item['quantity']) - $discountAmount;
                $subtotal += $itemSubtotal;

                $items[] = [
                    'product_id' => $item['product_id'],
                    'price_category_id' => $item['price_category_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'purchase_price' => $product->purchase_price,
                    'discount_amount' => $discountAmount,
                    'tax_amount' => $taxAmount,
                    'subtotal' => $itemSubtotal,
                ];

                // Check stock if it's a sale transaction
                if ($request->transaction_type === 'sale' && $product->is_using_stock) {
                    $stock = ProductStock::where('product_id', $product->id)
                        ->where('shop_id', $shopId)
                        ->first();

                    if (!$stock || $stock->stock < $item['quantity']) {
                        DB::rollBack();
                        return ApiResponse::error("Insufficient stock for product: {$product->name}", 422);
                    }
                }
            }

            // PERBAIKAN: Menggunakan logika yang lebih ketat untuk nilai default
            // Tidak mengandalkan request->input() dengan nilai default
            $discountAmount = 0;
            if ($request->has('discount_amount') && is_numeric($request->discount_amount)) {
                $discountAmount = (float) $request->discount_amount;
            }

            $taxAmount = 0;
            if ($request->has('tax_amount') && is_numeric($request->tax_amount)) {
                $taxAmount = (float) $request->tax_amount;
            }

            $serviceFee = 0;
            if ($request->has('service_fee') && is_numeric($request->service_fee)) {
                $serviceFee = (float) $request->service_fee;
            }

            $totalAmount = $subtotal - $discountAmount + $taxAmount + $serviceFee;

            // Validate total payment amount
            $totalPaymentAmount = 0;
            foreach ($request->payments as $payment) {
                $totalPaymentAmount += $payment['amount'];
            }

            // PERBAIKAN: Menghapus validasi yang melarang pembayaran parsial
            // Sebagai gantinya, tentukan status pembayaran berdasarkan jumlah
            $paymentStatus = 'completed'; // Default
            if ($totalPaymentAmount < $totalAmount) {
                if ($totalPaymentAmount > 0) {
                    $paymentStatus = 'partial';
                } else {
                    $paymentStatus = 'pending';
                }
            }

            // Opsional: Tambahkan validasi minimum pembayaran jika diperlukan
            // Misalnya, minimum 5% dari total harus dibayar
            /*
            $minPaymentRequired = $totalAmount * 0.05; // 5% dari total
            if ($totalPaymentAmount < $minPaymentRequired) {
                DB::rollBack();
                return ApiResponse::error(
                    "Minimum payment required is Rp " . number_format($minPaymentRequired) .
                    " (" . round($minPaymentRequired / $totalAmount * 100) . "% of total)",
                    422
                );
            }
            */

            // Generate invoice number
            $invoiceNumber = Transaction::generateInvoiceNumber($shopId);

            // PERBAIKAN: Memastikan tidak ada nilai NULL yang masuk database
            $insertData = [
                'invoice_number' => $invoiceNumber,
                'transaction_type' => $request->transaction_type,
                'user_id' => $user->id,
                'shop_id' => $shopId,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'service_fee' => $serviceFee,
                'total_amount' => $totalAmount,
                'payment_method_id' => $request->payment_method_id,
                'payment_status' => $paymentStatus,
                'notes' => $request->notes ?? '',
                'transaction_date' => now(),
            ];

            // Cek customer_id
            if ($request->has('customer_id') && $request->customer_id) {
                $insertData['customer_id'] = $request->customer_id;
            }

            // Create transaction
            $transaction = Transaction::create($insertData);

            // Create transaction items
            foreach ($items as $item) {
                $transactionItem = $transaction->items()->create($item);

                // Update stock based on transaction type
                $product = Product::find($item['product_id']);

                if ($product->is_using_stock) {
                    $stockChange = 0;
                    $movementType = '';

                    if ($request->transaction_type === 'sale') {
                        $stockChange = -$item['quantity'];
                        $movementType = 'sale';
                    } elseif ($request->transaction_type === 'return') {
                        $stockChange = $item['quantity'];
                        $movementType = 'return';
                    } elseif ($request->transaction_type === 'adjustment') {
                        // For adjustments, the quantity can be positive or negative
                        $stockChange = $item['quantity'] * ($request->input('is_stock_addition', false) ? 1 : -1);
                        $movementType = 'adjustment';
                    }

                    if ($stockChange != 0) {
                        // Update stock
                        if ($stockChange < 0) {
                            ProductStock::where('product_id', $product->id)
                                ->where('shop_id', $shopId)
                                ->decrement('stock', abs($stockChange));
                        } else {
                            ProductStock::where('product_id', $product->id)
                                ->where('shop_id', $shopId)
                                ->increment('stock', $stockChange);
                        }

                        // Create stock movement record
                        StockMovement::create([
                            'product_id' => $product->id,
                            'shop_id' => $shopId,
                            'quantity' => $stockChange,
                            'movement_type' => $movementType,
                            'reference_type' => 'transaction',
                            'reference_id' => $transaction->id,
                            'notes' => "{$request->transaction_type}: {$invoiceNumber}",
                            'user_id' => $user->id,
                        ]);
                    }
                }
            }

            // Create transaction payments
            foreach ($request->payments as $payment) {
                $transaction->payments()->create([
                    'payment_method_id' => $payment['payment_method_id'],
                    'amount' => $payment['amount'],
                    'payment_reference' => $payment['payment_reference'] ?? null,
                    'payment_date' => now(),
                ]);
            }

            // Update customer points if applicable
            if ($request->customer_id && $request->transaction_type === 'sale' && $paymentStatus === 'completed') {
                // Hanya berikan poin jika pembayaran lunas
                $customer = Customer::find($request->customer_id);
                if ($customer) {
                    // Get points conversion rate (defaulted to 10000)
                    $pointsConversionRate = 10000;

                    $pointsEarned = floor($totalAmount / $pointsConversionRate);

                    if ($pointsEarned > 0) {
                        $customer->increment('points', $pointsEarned);
                    }
                }
            }

            DB::commit();

            // Load relationships for response
            $transaction->load(['items.product', 'customer', 'paymentMethod', 'payments', 'user', 'shop']);

            // Tambahkan informasi pembayaran ke respons
            $responseData = $transaction->toArray();
            $responseData['total_paid'] = $totalPaymentAmount;
            $responseData['remaining_balance'] = $totalAmount - $totalPaymentAmount;

            return ApiResponse::success($responseData, 'Transaction created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to create transaction: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified transaction.
     *
     * @param string $invoiceNumber
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($invoiceNumber, Request $request)
    {
        try {
            $user = $request->user();
            $transaction = Transaction::where('invoice_number', $invoiceNumber)
                ->with(['items.product', 'customer', 'paymentMethod', 'payments', 'user', 'shop'])
                ->firstOrFail();

            // Check user permission
            if ($user->role !== 'super_admin') {
                if ($user->role === 'owner') {
                    $shopIds = $user->ownedShops->pluck('shop_id')->toArray();
                    if (!in_array($transaction->shop_id, $shopIds)) {
                        return ApiResponse::forbidden('You do not have permission to view this transaction');
                    }
                } elseif ($transaction->shop_id !== $user->shop_id) {
                    return ApiResponse::forbidden('You do not have permission to view this transaction');
                }
            }

            return ApiResponse::success($transaction, 'Transaction retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve transaction: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Void a transaction.
     *
     * @param string $invoiceNumber
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function void($invoiceNumber, Request $request)
    {
        try {
            DB::beginTransaction();

            $user = $request->user();
            $transaction = Transaction::where('invoice_number', $invoiceNumber)->firstOrFail();

            // Check user permission
            if ($user->role !== 'super_admin' && $user->role !== 'owner') {
                return ApiResponse::forbidden('You do not have permission to void this transaction');
            }

            if ($user->role === 'owner') {
                $shopIds = $user->ownedShops->pluck('shop_id')->toArray();
                if (!in_array($transaction->shop_id, $shopIds)) {
                    return ApiResponse::forbidden('You do not have permission to void this transaction');
                }
            }

            // Only allow voiding if within 24 hours or user is admin/owner
            $hoursSinceTransaction = now()->diffInHours($transaction->transaction_date);
            if ($hoursSinceTransaction > 24 && !in_array($user->role, ['super_admin', 'owner', 'admin'])) {
                return ApiResponse::forbidden('Transactions can only be voided within 24 hours');
            }

            // Check if transaction can be voided
            if ($transaction->payment_status === 'refunded') {
                return ApiResponse::error('This transaction has already been voided/refunded', 422);
            }

            // Create a reversal transaction if it's a sale or return
            $needsStockReversal = in_array($transaction->transaction_type, ['sale', 'return']);

            if ($needsStockReversal) {
                // Create a reversal transaction
                $reversalType = $transaction->transaction_type === 'sale' ? 'return' : 'sale';

                $reversalTransaction = Transaction::create([
                    'invoice_number' => 'VOID-' . $transaction->invoice_number,
                    'transaction_type' => $reversalType,
                    'user_id' => $user->id,
                    'shop_id' => $transaction->shop_id,
                    'customer_id' => $transaction->customer_id,
                    'subtotal' => $transaction->subtotal,
                    'discount_amount' => $transaction->discount_amount,
                    'tax_amount' => $transaction->tax_amount,
                    'service_fee' => $transaction->service_fee,
                    'total_amount' => $transaction->total_amount,
                    'payment_method_id' => $transaction->payment_method_id,
                    'payment_status' => 'completed',
                    'notes' => 'Automatic reversal for voided transaction ' . $transaction->invoice_number,
                    'transaction_date' => now(),
                ]);

                // Create reversal items and update stock
                foreach ($transaction->items as $item) {
                    $reversalItem = $reversalTransaction->items()->create([
                        'product_id' => $item->product_id,
                        'price_category_id' => $item->price_category_id,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'purchase_price' => $item->purchase_price,
                        'discount_amount' => $item->discount_amount,
                        'tax_amount' => $item->tax_amount,
                        'subtotal' => $item->subtotal,
                    ]);

                    $product = $item->product;

                    if ($product && $product->is_using_stock) {
                        // Reverse the stock movement (opposite of original transaction)
                        $stockChange = $transaction->transaction_type === 'sale' ? $item->quantity : -$item->quantity;

                        if ($stockChange != 0) {
                            // Update stock
                            if ($stockChange < 0) {
                                ProductStock::where('product_id', $product->id)
                                    ->where('shop_id', $transaction->shop_id)
                                    ->decrement('stock', abs($stockChange));
                            } else {
                                ProductStock::where('product_id', $product->id)
                                    ->where('shop_id', $transaction->shop_id)
                                    ->increment('stock', $stockChange);
                            }

                            // Create stock movement record
                            StockMovement::create([
                                'product_id' => $product->id,
                                'shop_id' => $transaction->shop_id,
                                'quantity' => $stockChange,
                                'movement_type' => 'void',
                                'reference_type' => 'transaction',
                                'reference_id' => $reversalTransaction->id,
                                'notes' => "Void reversal for: {$transaction->invoice_number}",
                                'user_id' => $user->id,
                            ]);
                        }
                    }
                }

                // Create reversal payment record
                $reversalTransaction->payments()->create([
                    'payment_method_id' => $transaction->payment_method_id,
                    'amount' => $transaction->total_amount,
                    'payment_reference' => 'Reversal for ' . $transaction->invoice_number,
                    'payment_date' => now(),
                ]);
            }

            // Mark original transaction as refunded
            $transaction->payment_status = 'refunded';
            $transaction->notes = ($transaction->notes ? $transaction->notes . ' | ' : '') . 'VOIDED by ' . $user->name . ' on ' . now();
            $transaction->save();

            // Revert customer points if applicable
            if ($transaction->customer_id && $transaction->transaction_type === 'sale') {
                $pointsConversionRate = SystemSetting::getValue('points_conversion_rate', 'customer', 10000);
                $pointsEarned = floor($transaction->total_amount / $pointsConversionRate);

                if ($pointsEarned > 0) {
                    $transaction->customer->decrement('points', $pointsEarned);
                }
            }

            DB::commit();

            return ApiResponse::success([
                'transaction' => $transaction,
                'reversal_transaction' => $needsStockReversal ? $reversalTransaction : null
            ], 'Transaction voided successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to void transaction: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Add a payment to a transaction with pending or partial status.
     *
     * @param string $invoiceNumber
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addPayment($invoiceNumber, Request $request)
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'payment_method_id' => 'required|exists:payment_methods,id',
                'amount' => 'required|numeric|min:0.01',
                'payment_reference' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ApiResponse::validationError($validator->errors());
            }

            $user = $request->user();
            $transaction = Transaction::where('invoice_number', $invoiceNumber)->firstOrFail();

            // Check user permission
            if ($user->role !== 'super_admin') {
                if ($user->role === 'owner') {
                    $shopIds = $user->ownedShops->pluck('shop_id')->toArray();
                    if (!in_array($transaction->shop_id, $shopIds)) {
                        return ApiResponse::forbidden('You do not have permission to add payment to this transaction');
                    }
                } elseif ($transaction->shop_id !== $user->shop_id) {
                    return ApiResponse::forbidden('You do not have permission to add payment to this transaction');
                }
            }

            // Check if transaction can receive payments
            if (!in_array($transaction->payment_status, ['pending', 'partial'])) {
                return ApiResponse::error('Cannot add payment to a ' . $transaction->payment_status . ' transaction', 422);
            }

            // Calculate remaining balance
            $totalPaid = $transaction->payments()->sum('amount');
            $remainingBalance = $transaction->total_amount - $totalPaid;

            if ($request->amount > $remainingBalance) {
                return ApiResponse::error("Payment amount ({$request->amount}) cannot exceed remaining balance of {$remainingBalance}", 422);
            }

            // Create payment
            $payment = $transaction->payments()->create([
                'payment_method_id' => $request->payment_method_id,
                'amount' => $request->amount,
                'payment_reference' => $request->payment_reference,
                'payment_date' => now(),
            ]);

            // Update transaction status
            $newTotalPaid = $totalPaid + $request->amount;
            $newStatus = 'partial';

            if (abs($newTotalPaid - $transaction->total_amount) < 0.01) {
                $newStatus = 'completed';
            } elseif ($newTotalPaid <= 0) {
                $newStatus = 'pending';
            }

            $transaction->payment_status = $newStatus;
            $transaction->save();

            DB::commit();

            // Reload transaction with payments
            $transaction->load('payments');

            return ApiResponse::success([
                'payment' => $payment,
                'transaction' => $transaction,
                'total_paid' => $newTotalPaid,
                'remaining_balance' => $transaction->total_amount - $newTotalPaid
            ], 'Payment added successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to add payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generate receipt for a transaction.
     *
     * @param string $invoiceNumber
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function receipt($invoiceNumber, Request $request)
    {
        try {
            $user = $request->user();
            $transaction = Transaction::where('invoice_number', $invoiceNumber)
                ->with(['items.product', 'customer', 'paymentMethod', 'payments', 'user', 'shop'])
                ->firstOrFail();

            // Check user permission
            if ($user->role !== 'super_admin') {
                if ($user->role === 'owner') {
                    $shopIds = $user->ownedShops->pluck('shop_id')->toArray();
                    if (!in_array($transaction->shop_id, $shopIds)) {
                        return ApiResponse::forbidden('You do not have permission to access this receipt');
                    }
                } elseif ($transaction->shop_id !== $user->shop_id) {
                    return ApiResponse::forbidden('You do not have permission to access this receipt');
                }
            }

            // Prepare receipt data
            $receiptData = [
                'transaction' => $transaction,
                'shop' => $transaction->shop,
                'items' => $transaction->items,
                'payments' => $transaction->payments,
                'customer' => $transaction->customer,
                'cashier' => $transaction->user,
                'date' => $transaction->transaction_date->format('d M Y H:i'),
                'subtotal' => $transaction->subtotal,
                'discount' => $transaction->discount_amount,
                'tax' => $transaction->tax_amount,
                'service_fee' => $transaction->service_fee,
                'total' => $transaction->total_amount,
                'total_paid' => $transaction->total_paid,
                'remaining' => $transaction->remaining_balance,
                'status' => $transaction->payment_status,
            ];

            return ApiResponse::success($receiptData, 'Receipt generated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to generate receipt: ' . $e->getMessage(), 500);
        }
    }
}
