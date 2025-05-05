<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        // Create default customer for walk-in sales
        Customer::create([
            'name' => 'Walk-in Customer',
            'phone' => null,
            'email' => null,
            'address' => null,
            'points' => 0,
            'is_active' => true,
        ]);

        // Create some regular customers
        Customer::factory()->count(20)->create();
    }
}
