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
        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opname');
            $table->foreignId('product_id')->constrained();
            $table->integer('physical_stock');
            $table->integer('system_stock');
            $table->integer('variance');
            $table->text('notes')->nullable();

            $table->unique(['stock_opname_id', 'product_id'], 'idx_opname_product');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
    }
};
