<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TransactionItem>
 */
class TransactionItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $transaction = Transaction::inRandomOrder()->first() ?? Transaction::factory()->create();
        $product = Product::inRandomOrder()->first() ?? Product::factory()->create();

        $quantity = $this->faker->numberBetween(1, 5);
        $unitPrice = $product->selling_price;
        $purchasePrice = $product->purchase_price;
        $discountItem = $this->faker->randomFloat(2, 0, 5000);
        $taxItem = ($unitPrice * $quantity * 0.11);
        $subtotal = ($unitPrice * $quantity) - $discountItem + $taxItem;

        return [
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'purchase_price' => $purchasePrice,
            'discount_amount' => $discountItem,
            'tax_amount' => $taxItem,
            'subtotal' => $subtotal,
        ];
    }
}
