<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\ShopOwner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShopOwnerFactory extends Factory
{
    protected $model = ShopOwner::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->owner(),
            'shop_id' => Shop::factory(),
            'is_primary_owner' => true,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function secondary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary_owner' => false,
        ]);
    }
}
