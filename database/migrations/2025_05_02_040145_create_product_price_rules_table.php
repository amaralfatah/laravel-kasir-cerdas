<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_price_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('shop_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('price_category_id')->constrained()->onDelete('cascade');
            $table->integer('min_quantity')->default(1);
            $table->decimal('price', 10, 2);
            $table->timestamps();

            // Indeks
            $table->index(['product_id', 'shop_id', 'price_category_id', 'min_quantity'], 'idx_product_price_rule');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_rules');
    }
};