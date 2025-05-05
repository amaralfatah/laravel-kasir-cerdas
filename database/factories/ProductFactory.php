<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'sku' => $this->faker->unique()->bothify('??##??##'),
            'category_id' => Category::factory(),
            'purchase_price' => $this->faker->numberBetween(5000, 100000),
            'selling_price' => function (array $attributes) {
                return $attributes['purchase_price'] * (1 + $this->faker->numberBetween(10, 80) / 100);
            },
            'barcode' => $this->faker->unique()->ean13(),
            'description' => $this->faker->paragraph(),
            'images' => null,
            'is_using_stock' => true,
            'is_active' => true,
        ];
    }

    public function service(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_using_stock' => false,
        ]);
    }

    public function goods(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_using_stock' => true,
        ]);
    }
}
