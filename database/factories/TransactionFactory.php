<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $shop = Shop::inRandomOrder()->first() ?? Shop::factory()->create();
        $user = User::inRandomOrder()->first() ?? User::factory()->create(['shop_id' => $shop->id]);
        $customer = Customer::inRandomOrder()->first() ?? Customer::factory()->create();
        $paymentMethod = PaymentMethod::inRandomOrder()->first() ?? PaymentMethod::factory()->create();

        $subtotal = $this->faker->randomFloat(2, 10000, 1000000);
        $discount = $this->faker->randomFloat(2, 0, $subtotal * 0.1);
        $tax = $this->faker->randomFloat(2, 0, $subtotal * 0.11);
        $serviceFee = $this->faker->randomFloat(2, 0, 10000);
        $total = $subtotal - $discount + $tax + $serviceFee;

        return [
            'invoice_number' => 'INV-' . strtoupper($this->faker->bothify('??###')),
            'transaction_type' => $this->faker->randomElement(['sale', 'return', 'adjustment']),
            'user_id' => $user->id,
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'service_fee' => $serviceFee,
            'total_amount' => $total,
            'payment_method_id' => $paymentMethod->id,
            'payment_status' => $this->faker->randomElement(['pending', 'partial', 'completed', 'refunded']),
            'notes' => $this->faker->optional(0.7)->sentence(),
            'transaction_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
