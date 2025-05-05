<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement(['Cash', 'Credit Card', 'Debit Card', 'Bank Transfer', 'E-Wallet', 'QRIS']),
            'is_digital' => $this->faker->boolean(80),
            'fee_percentage' => $this->faker->randomFloat(2, 0, 3),
            'is_active' => true,
        ];
    }

    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Cash',
            'is_digital' => false,
            'fee_percentage' => 0,
        ]);
    }

    public function digital(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_digital' => true,
        ]);
    }
}
