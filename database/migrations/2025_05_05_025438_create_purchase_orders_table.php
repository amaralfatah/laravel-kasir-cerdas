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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 50)->unique();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('shop_id')->constrained();
            $table->decimal('total', 10, 2);
            $table->enum('status', ['draft', 'ordered', 'partial', 'received', 'canceled'])->default('draft');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->date('order_date');
            $table->timestamps();

            $table->index('po_number', 'idx_po_number');
            $table->index('order_date', 'idx_po_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
