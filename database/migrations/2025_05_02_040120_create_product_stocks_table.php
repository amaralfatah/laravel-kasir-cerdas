<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Membuat tabel product_stocks untuk menyimpan informasi stok produk per toko.
     * Tabel ini memiliki relasi dengan:
     * - products: untuk mengetahui produk yang stoknya disimpan
     * - shops: untuk mengetahui toko tempat stok berada
     *
     * Tabel ini akan diperbarui ketika terjadi:
     * - Penjualan produk (mengurangi stok)
     * - Pembelian produk (menambah stok)
     * - Stock opname (penyesuaian stok)
     * - Transfer stok antar toko
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->comment('Produk yang stoknya disimpan');
            $table->foreignId('shop_id')->constrained()->comment('Toko tempat stok berada');
            $table->integer('stock')->default(0)->comment('Jumlah stok produk saat ini');
            $table->integer('min_stock')->default(5)->comment('Jumlah minimum stok sebelum perlu restok');
            $table->timestamps();
            $table->softDeletes()->comment('Untuk soft delete stok tanpa menghapus riwayat');

            // Perubahan nama indeks unik dari idx_ menjadi unq_ untuk konsistensi
            $table->unique(['product_id', 'shop_id'], 'unq_product_shop_stock');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('product_stocks');
    }
};
