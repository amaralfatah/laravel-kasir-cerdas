<?php

namespace Database\Seeders;

use App\Models\Shop;
use Illuminate\Database\Seeder;

class ShopSeeder extends Seeder
{
    public function run(): void
    {
        // Create main SHOP
        Shop::create([
            'name' => 'Main Shop',
            'address' => 'Main Street, 123',
            'phone' => '021-12345678',
            'tax_id' => '123.456.789-00',
            'is_active' => true,
        ]);

        // Create additional shop for testing
        Shop::factory()->count(2)->create();
    }
}
