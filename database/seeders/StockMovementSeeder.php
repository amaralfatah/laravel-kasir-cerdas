<?php

namespace Database\Seeders;

use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Database\Seeder;

class StockMovementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Mendapatkan semua stok produk
        $productStocks = ProductStock::all();

        // Memastikan ada data stok produk
        if ($productStocks->isEmpty()) {
            // Jika tidak ada stok produk, panggil ProductStockSeeder
            $this->call(ProductStockSeeder::class);
            $productStocks = ProductStock::all();
        }

        // Membuat pergerakan stok untuk setiap transaksi yang sudah ada
        $transactions = Transaction::all();
        foreach ($transactions as $transaction) {
            $items = TransactionItem::where('transaction_id', $transaction->id)->get();

            foreach ($items as $item) {
                // Buat pergerakan stok untuk penjualan
                StockMovement::create([
                    'product_id' => $item->product_id,
                    'shop_id' => $transaction->shop_id,
                    'quantity' => -$item->quantity, // Negatif karena stok keluar
                    'movement_type' => 'sale',
                    'reference_type' => 'transactions',
                    'reference_id' => $transaction->id,
                    'notes' => 'Penjualan dari transaksi #' . $transaction->invoice_number,
                    'user_id' => $transaction->user_id,
                ]);
            }
        }

        // Untuk setiap stok produk, buat 1-3 pergerakan stok tambahan acak
        foreach ($productStocks as $productStock) {
            $numberOfMovements = rand(1, 3);

            for ($i = 0; $i < $numberOfMovements; $i++) {
                // Pastikan pergerakan ini tidak menyebabkan stok menjadi negatif
                StockMovement::factory()->create([
                    'product_id' => $productStock->product_id,
                    'shop_id' => $productStock->shop_id,
                    'quantity' => rand(1, 20), // Selalu positif untuk menambah stok
                    'movement_type' => 'purchase', // Selalu purchase untuk menambah stok
                ]);

                // Perbarui stok produk
                $productStock->stock += rand(1, 20);
                $productStock->save();
            }
        }
    }
}
