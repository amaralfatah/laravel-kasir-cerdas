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
        Schema::create('stock_opname', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained();
            $table->enum('status', ['draft', 'pending', 'approved', 'canceled'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('conducted_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->date('conducted_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_opnames');
    }
};
