<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->comment('Nama produk');
            $table->string('sku', 50)->unique()->comment('Stock Keeping Unit - Kode unik produk');
            $table->foreignId('category_id')->constrained()->comment('Kategori produk');
            $table->decimal('purchase_price', 10, 2)->comment('Harga beli terakhir produk');
            $table->decimal('selling_price', 10, 2)->comment('Harga jual normal produk');
            $table->string('barcode', 255)->unique()->nullable()->comment('Kode barcode produk');
            $table->text('description')->nullable()->comment('Deskripsi produk');
            $table->text('images')->nullable()->comment('JSON array of image URLs - Array URL gambar produk');
            $table->boolean('is_using_stock')->default(true)->comment('Apakah produk menggunakan manajemen stok');
            $table->boolean('is_active')->default(true)->comment('Status aktif produk');
            $table->timestamps();
            $table->softDeletes()->comment('Untuk soft delete produk tanpa menghapus data terkait');

            // Indeks untuk pencarian dan performa
            $table->index('barcode', 'idx_barcode_search');
            $table->index('sku', 'idx_sku_search');
            $table->index('category_id', 'idx_product_category');
            $table->index('name', 'idx_product_name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
