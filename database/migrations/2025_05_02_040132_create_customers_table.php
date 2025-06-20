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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('phone', 20)->unique()->nullable();
            $table->string('email', 255)->nullable();
            $table->text('address')->nullable();
            $table->integer('points')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('phone', 'idx_customer_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
