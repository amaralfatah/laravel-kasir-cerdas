<?php

// routes/api.php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\ShopManagementController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\ShopController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\UserManagementController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);

    // Protected auth routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// Routes protected with Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // User Profile routes
    Route::prefix('users')->group(function () {
        Route::get('/me', [UserController::class, 'me']);
        Route::put('/me', [UserController::class, 'updateProfile']);
    });

    // Shop routes
    Route::get('/shops', [ShopController::class, 'index']);

    // Category routes
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::post('/', [CategoryController::class, 'store']);
    });

    // Product Routes
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::post('/', [ProductController::class, 'store']);
        Route::get('/{product}', [ProductController::class, 'show']);
        Route::put('/{product}', [ProductController::class, 'update']);
        Route::delete('/{product}', [ProductController::class, 'destroy']);
    });

    // Admin and Owner routes
    Route::middleware('ability:super_admin,owner,admin')->group(function () {
        // User Management Routes (for admins and owners)
        Route::prefix('user-management')->group(function () {
            Route::get('/users', [UserManagementController::class, 'index']);
            Route::post('/users', [UserManagementController::class, 'store']);
            Route::get('/users/{id}', [UserManagementController::class, 'show']);
            Route::put('/users/{id}', [UserManagementController::class, 'update']);
            Route::delete('/users/{id}', [UserManagementController::class, 'destroy']);
            Route::post('/assign-ownership', [UserManagementController::class, 'assignShopOwnership']);
            Route::delete('/remove-ownership', [UserManagementController::class, 'removeShopOwnership']);
        });

        // Shop Management Routes (for super_admin and owners)
        Route::prefix('shop-management')->group(function () {
            Route::get('/shops', [ShopManagementController::class, 'index']);
            Route::post('/shops', [ShopManagementController::class, 'store']);
            Route::get('/shops/{id}', [ShopManagementController::class, 'show']);
            Route::put('/shops/{id}', [ShopManagementController::class, 'update']);
            Route::delete('/shops/{id}', [ShopManagementController::class, 'destroy']);
        });
    });

    // Cashier routes
    Route::middleware('ability:cashier')->group(function () {
        Route::get('/cashier-only', function () {
            return response()->json(['message' => 'Cashier area']);
        });
    });
});
