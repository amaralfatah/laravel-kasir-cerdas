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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('sku', 50)->unique();
            $table->foreignId('category_id')->constrained();
            $table->decimal('purchase_price', 10, 2);
            $table->decimal('selling_price', 10, 2);
            $table->string('barcode', 255)->unique()->nullable();
            $table->text('description')->nullable();
            $table->text('images')->nullable()->comment('JSON array of image URLs');
            $table->boolean('is_using_stock')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('barcode', 'idx_barcode_search');
            $table->index('sku', 'idx_sku_search');
            $table->index('category_id', 'idx_product_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
