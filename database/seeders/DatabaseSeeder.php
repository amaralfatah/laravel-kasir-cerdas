<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ShopSeeder::class,
            UserSeeder::class,
            ShopOwnerSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            CustomerSeeder::class,
            PaymentMethodSeeder::class,
            ProductStockSeeder::class,
            TransactionSeeder::class,
            StockMovementSeeder::class,
        ]);
    }
}
