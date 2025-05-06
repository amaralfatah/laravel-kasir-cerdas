<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ShopOwner;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Mulai query builder produk
            $productsQuery = Product::query();
            $user = $request->user();

            // Filter produk berdasarkan role user
            if ($user->role === 'super_admin') {
                // Super admin dapat melihat semua produk
            } elseif ($user->role === 'owner') {
                // Owner hanya dapat melihat produk dari toko yang dimilikinya
                $ownedShopIds = ShopOwner::where('user_id', $user->id)->pluck('shop_id')->toArray();

                // Filter berdasarkan stocks
                $productsQuery->whereHas('stocks', function ($q) use ($ownedShopIds) {
                    $q->whereIn('shop_id', $ownedShopIds);
                });
            } else {
                // Admin, manager, cashier hanya dapat melihat produk dari toko mereka
                $productsQuery->whereHas('stocks', function ($q) use ($user) {
                    $q->where('shop_id', $user->shop_id);
                });
            }

            // Apply search filter jika disediakan
            if ($request->has('search')) {
                $searchTerm = $request->input('search');
                $productsQuery->where(function ($query) use ($searchTerm) {
                    $query->where('name', 'like', "%{$searchTerm}%")
                        ->orWhere('barcode', 'like', "%{$searchTerm}%")
                        ->orWhere('sku', 'like', "%{$searchTerm}%");
                });
            }

            // Filter berdasarkan kategori jika disediakan
            if ($request->has('category_id')) {
                $productsQuery->where('category_id', $request->input('category_id'));
            }

            // Filter berdasarkan status aktif
            if ($request->has('is_active')) {
                $productsQuery->where('is_active', $request->boolean('is_active'));
            }

            // Filter berdasarkan is_using_stock jika disediakan
            if ($request->has('is_using_stock')) {
                $productsQuery->where('is_using_stock', $request->boolean('is_using_stock'));
            }

            // Sorting
            $sortField = $request->input('sort_by', 'created_at');
            $sortDirection = $request->input('sort_direction', 'desc');
            $validSortFields = ['name', 'selling_price', 'created_at', 'updated_at'];

            if (in_array($sortField, $validSortFields)) {
                $productsQuery->orderBy($sortField, $sortDirection);
            }

            // Eager loading untuk optimasi query
            $productsQuery->with(['category', 'stocks']);

            // Paginate hasil
            $products = $productsQuery->paginate($request->input('per_page', 10));

            return ApiResponse::success($products, 'Products retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve products: ' . $e->getMessage(), 500);
        }
    }
}
