<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        // Create standard payment methods
        $paymentMethods = [
            [
                'name' => 'Cash',
                'is_digital' => false,
                'fee_percentage' => 0.00,
            ],
            [
                'name' => 'Credit Card',
                'is_digital' => true,
                'fee_percentage' => 2.50,
            ],
            [
                'name' => 'Debit Card',
                'is_digital' => true,
                'fee_percentage' => 1.00,
            ],
            [
                'name' => 'Bank Transfer',
                'is_digital' => true,
                'fee_percentage' => 0.50,
            ],
            [
                'name' => 'E-Wallet',
                'is_digital' => true,
                'fee_percentage' => 1.50,
            ],
            [
                'name' => 'QRIS',
                'is_digital' => true,
                'fee_percentage' => 0.70,
            ],
        ];

        foreach ($paymentMethods as $method) {
            PaymentMethod::create($method);
        }
    }
}
