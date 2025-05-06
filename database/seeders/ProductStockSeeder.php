<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Shop;
use Illuminate\Database\Seeder;

class ProductStockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Mendapatkan semua produk dan toko
        $products = Product::all();
        $shops = Shop::all();

        // Memastikan ada data produk dan toko
        if ($products->isEmpty()) {
            Product::factory(10)->create();
            $products = Product::all();
        }

        if ($shops->isEmpty()) {
            Shop::factory(2)->create();
            $shops = Shop::all();
        }

        // Membuat stok untuk setiap produk di setiap toko
        foreach ($products as $product) {
            foreach ($shops as $shop) {
                // Cek apakah kombinasi product_id dan shop_id sudah ada
                $existingStock = ProductStock::where('product_id', $product->id)
                    ->where('shop_id', $shop->id)
                    ->first();

                // Jika belum ada, buat data baru
                if (!$existingStock) {
                    ProductStock::create([
                        'product_id' => $product->id,
                        'shop_id' => $shop->id,
                        'stock' => rand(10, 100),
                        'min_stock' => rand(5, 15),
                    ]);
                }
            }
        }

        // Menambahkan beberapa stok acak tambahan untuk produk yang belum memiliki stok di sebuah toko
        // Mendapatkan 5 produk acak
        $randomProducts = Product::inRandomOrder()->take(5)->get();
        foreach ($randomProducts as $product) {
            // Mendapatkan toko yang belum memiliki stok untuk produk ini
            $existingShopIds = ProductStock::where('product_id', $product->id)
                ->pluck('shop_id')
                ->toArray();

            $availableShops = Shop::whereNotIn('id', $existingShopIds)->get();

            foreach ($availableShops as $shop) {
                ProductStock::create([
                    'product_id' => $product->id,
                    'shop_id' => $shop->id,
                    'stock' => rand(10, 100),
                    'min_stock' => rand(5, 15),
                ]);
            }
        }
    }
}
