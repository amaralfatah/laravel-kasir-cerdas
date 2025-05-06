<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 255)->unique();
            $table->enum('transaction_type', ['sale', 'return', 'adjustment'])->default('sale');
            $table->foreignId('user_id')->constrained();
            $table->foreignId('shop_id')->constrained();
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('service_fee', 10, 2)->default(0.00);
            $table->decimal('total_amount', 10, 2);
            $table->foreignId('payment_method_id')->constrained();
            $table->enum('payment_status', ['pending', 'partial', 'completed', 'refunded'])->default('completed');
            $table->text('notes')->nullable();
            $table->timestamp('transaction_date')->useCurrent();
            $table->timestamps();
            $table->softDeletes();

            $table->index('invoice_number', 'idx_invoice_unique');
            $table->index('transaction_date', 'idx_transaction_date');
            $table->index('customer_id', 'idx_transaction_customer');
            $table->index('shop_id', 'idx_transaction_shop');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
