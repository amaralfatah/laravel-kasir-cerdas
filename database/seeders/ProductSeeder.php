<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Shop;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Create products for each subcategory
        // Gunakan eager loading untuk mengoptimalkan query dan memastikan parent terload
        $categories = Category::whereNotNull('parent_id')->with('parent')->get();
        $shops = Shop::all(); // Load semua toko di awal untuk efisiensi

        foreach ($categories as $category) {
            // Create 3-5 products per subcategory
            $count = rand(3, 5);

            // Pengecekan yang lebih aman dengan null check
            $isUsingStock = true;
            if ($category->parent && $category->parent->name === 'Services') {
                $isUsingStock = false;
            }

            $products = Product::factory()
                ->count($count)
                ->create([
                    'category_id' => $category->id,
                    'is_using_stock' => $isUsingStock,
                ]);

            // Create stock for each product in each shop (only for products that use stock)
            if ($isUsingStock) {
                foreach ($products as $product) { // Fix: Menambahkan loop products yang hilang
                    foreach ($shops as $shop) {
                        ProductStock::create([
                            'product_id' => $product->id,
                            'shop_id' => $shop->id,
                            'stock' => rand(10, 100),
                            'min_stock' => rand(5, 20),
                        ]);
                    }
                }
            }
        }
    }
}
