<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_wholesale_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('min_quantity')->comment('Jumlah minimum untuk harga grosir ini');
            $table->decimal('price', 10, 2)->comment('Harga per unit pada pembelian grosir');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Setiap kombinasi produk, toko, dan jumlah minimum harus unik
            $table->unique(['product_id', 'shop_id', 'min_quantity'], 'idx_unique_wholesale_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_wholesale_prices');
    }
};
