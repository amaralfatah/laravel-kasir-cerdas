<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Membuat tabel untuk mencatat semua pergerakan stok produk.
     * Tabel ini berfungsi sebagai audit trail untuk semua perubahan stok, baik itu
     * dari pembelian, penjualan, penyesuaian, retur, atau transfer antar toko.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->comment('Referensi ke produk yang stoknya berubah');
            $table->foreignId('shop_id')->constrained()->comment('Toko tempat pergerakan stok terjadi');
            $table->integer('quantity')->comment('Positif untuk penambahan stok, negatif untuk pengurangan stok');
            $table->enum('movement_type', ['purchase', 'sale', 'adjustment', 'return', 'transfer'])->comment('Tipe pergerakan stok');
            $table->string('reference_type', 50)->nullable()->comment('Nama tabel terkait: "transactions", "purchase_orders", dll.');
            $table->unsignedBigInteger('reference_id')->nullable()->comment('ID pada tabel referensi');
            $table->text('notes')->nullable()->comment('Catatan tambahan terkait pergerakan stok');
            $table->foreignId('user_id')->constrained()->comment('Pengguna yang melakukan perubahan stok');
            $table->timestamps(); // Standarisasi penggunaan timestamps
            $table->softDeletes(); // Menambahkan soft delete

            $table->index(['product_id', 'shop_id', 'created_at'], 'idx_stock_movement_history');
            $table->index(['reference_type', 'reference_id'], 'idx_stock_reference');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
