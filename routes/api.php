<?php

// routes/api.php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProductController;
use Illuminate\Support\Facades\Route;

// Route publik
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Route yang dilindungi dengan Sanctum
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Product Routes
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::post('/', [ProductController::class, 'store']);
        Route::get('/{product}', [ProductController::class, 'show']);
        Route::put('/{product}', [ProductController::class, 'update']);
        Route::delete('/{product}', [ProductController::class, 'destroy']);
    });

    // Contoh route khusus untuk role tertentu
    Route::middleware('ability:super_admin,owner,admin')->group(function () {
        Route::get('/admin-only', function () {
            return response()->json(['message' => 'Admin area']);
        });
    });

    // Contoh route untuk kasir
    Route::middleware('ability:cashier')->group(function () {
        Route::get('/cashier-only', function () {
            return response()->json(['message' => 'Cashier area']);
        });
    });
});
