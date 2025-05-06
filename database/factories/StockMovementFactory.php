<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockMovement>
 */
class StockMovementFactory extends Factory
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
        $user = User::inRandomOrder()->first() ?? User::factory()->create(['shop_id' => $shop->id]);
        $movementType = $this->faker->randomElement(['purchase', 'sale', 'adjustment', 'return', 'transfer']);

        // Menentukan jumlah berdasarkan tipe pergerakan
        $quantity = match ($movementType) {
            'purchase', 'return' => $this->faker->numberBetween(1, 20),
            'sale', 'transfer' => -$this->faker->numberBetween(1, 10),
            'adjustment' => $this->faker->numberBetween(-5, 5),
        };

        return [
            'product_id' => $product->id,
            'shop_id' => $shop->id,
            'quantity' => $quantity,
            'movement_type' => $movementType,
            'reference_type' => $this->faker->randomElement(['transactions', 'purchase_orders', null]),
            'reference_id' => $this->faker->numberBetween(1, 100),
            'notes' => $this->faker->optional(0.7)->sentence(),
            'user_id' => $user->id,
        ];
    }
}
