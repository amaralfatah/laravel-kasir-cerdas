<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductStock>
 */
class ProductStockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $product = Product::inRandomOrder()->first() ?? Product::factory()->create();
        $shop = Shop::inRandomOrder()->first() ?? Shop::factory()->create();

        return [
            'product_id' => $product->id,
            'shop_id' => $shop->id,
            'stock' => $this->faker->numberBetween(5, 100),
            'min_stock' => $this->faker->numberBetween(3, 10),
        ];
    }
}
