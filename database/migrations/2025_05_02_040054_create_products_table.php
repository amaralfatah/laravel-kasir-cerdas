<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->comment('Product name');
            $table->string('sku', 50)->unique()->comment('Stock Keeping Unit - unique product code');
            $table->foreignId('category_id')->nullable()->constrained()->comment('Product category');
            $table->decimal('purchase_price', 10, 2)->comment('Purchase price');
            $table->decimal('selling_price', 10, 2)->comment('Default selling price');
            $table->string('barcode', 255)->unique()->nullable()->comment('Product barcode');
            $table->text('description')->nullable()->comment('Product description');
            $table->text('images')->nullable()->comment('JSON array of image URLs');
            $table->boolean('is_using_stock')->default(true)->comment('Whether product uses stock management');
            $table->boolean('is_active')->default(true)->comment('Product active status');

            // Additional fields from Kasir Pintar UI
            $table->decimal('weight', 10, 2)->nullable()->comment('Product weight');
            $table->string('unit', 20)->nullable()->comment('Unit of measurement (gram, pcs, etc)');
            $table->decimal('discount_percentage', 5, 2)->nullable()->comment('Default discount percentage');
            $table->string('rack_placement', 50)->nullable()->comment('Location in store shelf/rack');
            $table->text('information')->nullable()->comment('Additional product information');

            $table->timestamps();
            $table->softDeletes()->comment('For soft delete without removing related data');

            // Indexes for search and performance
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
