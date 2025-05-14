<?php

// routes/api.php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\CustomerController;
use App\Http\Controllers\API\PaymentMethodController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\PurchaseOrderController;
use App\Http\Controllers\API\ShopController;
use App\Http\Controllers\API\ShopManagementController;
use App\Http\Controllers\API\StockController;
use App\Http\Controllers\API\StockOpnameController;
use App\Http\Controllers\API\SupplierController;
use App\Http\Controllers\API\TransactionController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\UserManagementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    // User profile routes
    Route::prefix('users')->group(function () {
        Route::get('/me', [UserController::class, 'me']);
        Route::put('/me', [UserController::class, 'updateProfile']);
    });

    // Shop routes
    Route::get('/shops', [ShopController::class, 'index']);

    // Category routes
    Route::apiResource('categories', CategoryController::class);

    // Product routes
    Route::apiResource('products', ProductController::class);

    // Customer routes
    Route::apiResource('customers', CustomerController::class);
    Route::get('/customers/{id}/transactions', [CustomerController::class, 'transactionHistory']);
    Route::post('/customers/{id}/adjust-points', [CustomerController::class, 'adjustPoints']);

    // Payment methods routes
    Route::apiResource('payment-methods', PaymentMethodController::class);
    Route::post('/payment-methods/calculate-fee', [PaymentMethodController::class, 'calculateFee']);

    // Transaction routes
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{invoiceNumber}', [TransactionController::class, 'show']);
    Route::post('/transactions/{invoiceNumber}/void', [TransactionController::class, 'void']);
    Route::post('/transactions/{invoiceNumber}/payment', [TransactionController::class, 'addPayment']);
    Route::get('/transactions/{invoiceNumber}/receipt', [TransactionController::class, 'receipt']);

    // Stock management routes
    Route::prefix('stock')->group(function () {
        Route::get('/products/{product}', [StockController::class, 'getProductStock']);
        Route::put('/products/{product}', [StockController::class, 'updateStock']);
        Route::post('/transfer/{product}', [StockController::class, 'transferStock']);
        Route::get('/movements/{product}', [StockController::class, 'getStockMovements']);
        Route::get('/low-stock', [StockController::class, 'getLowStockProducts']);
    });

    // Stock Opname routes - NEW
    Route::apiResource('stock-opname', StockOpnameController::class);
    Route::post('/stock-opname/{id}/submit', [StockOpnameController::class, 'submit']);
    Route::post('/stock-opname/{id}/approve', [StockOpnameController::class, 'approve']);
    Route::post('/stock-opname/{id}/cancel', [StockOpnameController::class, 'cancel']);

    // Supplier routes - NEW
    Route::apiResource('suppliers', SupplierController::class);
    Route::get('/suppliers/{id}/purchase-orders', [SupplierController::class, 'purchaseOrders']);

    // Purchase Order routes - NEW
    Route::apiResource('purchase-orders', PurchaseOrderController::class);
    Route::post('/purchase-orders/{id}/order', [PurchaseOrderController::class, 'order']);
    Route::post('/purchase-orders/{id}/receive', [PurchaseOrderController::class, 'receive']);
    Route::post('/purchase-orders/{id}/cancel', [PurchaseOrderController::class, 'cancel']);

    /*
    |--------------------------------------------------------------------------
    | Admin & Owner Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('ability:super_admin,owner,admin')->group(function () {
        // User management
        Route::prefix('user-management')->group(function () {
            Route::get('/users', [UserManagementController::class, 'index']);
            Route::post('/users', [UserManagementController::class, 'store']);
            Route::get('/users/{id}', [UserManagementController::class, 'show']);
            Route::put('/users/{id}', [UserManagementController::class, 'update']);
            Route::delete('/users/{id}', [UserManagementController::class, 'destroy']);

            // Shop ownership management
            Route::post('/assign-ownership', [UserManagementController::class, 'assignShopOwnership']);
            Route::delete('/remove-ownership', [UserManagementController::class, 'removeShopOwnership']);
        });

        // Shop management
        Route::prefix('shop-management')->group(function () {
            Route::get('/shops', [ShopManagementController::class, 'index']);
            Route::post('/shops', [ShopManagementController::class, 'store']);
            Route::get('/shops/{id}', [ShopManagementController::class, 'show']);
            Route::put('/shops/{id}', [ShopManagementController::class, 'update']);
            Route::delete('/shops/{id}', [ShopManagementController::class, 'destroy']);
        });

        // Expense management (to be implemented)
//        Route::apiResource('expenses', ExpenseController::class);
    });

    /*
    |--------------------------------------------------------------------------
    | Cashier Routes
    |--------------------------------------------------------------------------
    */
    Route::middleware('ability:cashier')->group(function () {
        Route::get('/cashier-only', function () {
            return response()->json(['message' => 'Cashier area']);
        });
    });
});
