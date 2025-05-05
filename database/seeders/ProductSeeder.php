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
        $categories = Category::whereNotNull('parent_id')->get();

        foreach ($categories as $category) {
            // Create 3-5 products per subcategory
            $count = rand(3, 5);

            // Determine if this category contains products that use stock
            $isUsingStock = $category->parent->name !== 'Services';

            $products = Product::factory()
                ->count($count)
                ->create([
                    'category_id' => $category->id,
                    'is_using_stock' => $isUsingStock,
                ]);

            // Create stock for each product in each shop (only for products that use stock)
            if ($isUsingStock) {
                foreach ($products as $product) {
                    foreach (Shop::all() as $shop) {
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
