<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $mainBranch = Shop::first();

        User::create([
            'name' => 'Super Admin',
            'email' => 'super@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'shop_id' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Create owner
        User::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'shop_id' => $mainBranch->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Create admin
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'shop_id' => $mainBranch->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Create managers and cashiers for each shop
        foreach (Shop::all() as $shop) {
            // Manager
            User::create([
                'name' => "Manager {$shop->name}",
                'email' => "manager.{$shop->id}@example.com",
                'password' => Hash::make('password'),
                'role' => 'manager',
                'shop_id' => $shop->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            // Cashiers
            User::factory()
                ->count(2)
                ->cashier()
                ->create([
                    'shop_id' => $shop->id,
                ]);
        }
    }
}
