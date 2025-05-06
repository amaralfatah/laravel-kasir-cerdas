<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Membuat 20 transaksi dengan item transaksi
        Transaction::factory(20)->create()->each(function (Transaction $transaction) {
            // Mendapatkan produk yang memiliki stok di toko transaksi ini
            $availableProducts = ProductStock::where('shop_id', $transaction->shop_id)
                ->where('stock', '>', 0)
                ->pluck('product_id')
                ->toArray();

            // Jika tidak ada produk yang tersedia, skip transaksi ini
            if (empty($availableProducts)) {
                return;
            }

            // Mendapatkan 1-5 produk acak yang tersedia
            $products = Product::whereIn('id', $availableProducts)
                ->inRandomOrder()
                ->take(rand(1, min(5, count($availableProducts))))
                ->get();

            // Total transaksi
            $subtotal = 0;

            // Membuat item transaksi untuk setiap produk
            foreach ($products as $product) {
                // Dapatkan stok produk di toko ini
                $productStock = ProductStock::where('product_id', $product->id)
                    ->where('shop_id', $transaction->shop_id)
                    ->first();

                // Tentukan jumlah yang akan dibeli (tidak lebih dari stok yang tersedia)
                $maxQuantity = min(5, $productStock->stock);
                $quantity = rand(1, $maxQuantity);

                $unitPrice = $product->selling_price;
                $purchasePrice = $product->purchase_price;
                $discountItem = rand(0, 5000) / 100;
                $taxItem = ($unitPrice * $quantity * 0.11);
                $itemSubtotal = ($unitPrice * $quantity) - $discountItem + $taxItem;

                // Membuat item transaksi
                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'purchase_price' => $purchasePrice,
                    'discount_amount' => $discountItem,
                    'tax_amount' => $taxItem,
                    'subtotal' => $itemSubtotal,
                ]);

                // Mengurangi stok produk
                $productStock->stock -= $quantity;
                $productStock->save();

                $subtotal += $itemSubtotal;
            }

            // Memperbarui total transaksi
            $transaction->update([
                'subtotal' => $subtotal,
                'total_amount' => $subtotal + $transaction->service_fee - $transaction->discount_amount,
            ]);
        });
    }
}
