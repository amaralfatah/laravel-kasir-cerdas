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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('shop_id')->constrained();
            $table->integer('quantity')->comment('Positive for incoming, negative for outgoing');
            $table->enum('movement_type', ['purchase', 'sale', 'adjustment', 'return', 'transfer']);
            $table->string('reference_type', 50)->nullable()->comment('Name of related table: "transactions", "purchase_orders", etc.');
            $table->unsignedBigInteger('reference_id')->nullable()->comment('ID in the reference table');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'shop_id', 'created_at'], 'idx_stock_movement_history');
            $table->index(['reference_type', 'reference_id'], 'idx_stock_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
