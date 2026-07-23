<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchanges', function (Blueprint $table) {
            $table->id();
            $table->string('exchange_number')->unique();
            $table->foreignId('original_sale_id')->constrained('sales');
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('exchange_date');
            $table->decimal('return_total', 10, 2);       // value of returned items
            $table->decimal('replacement_total', 10, 2);  // value of replacement items
            $table->decimal('amount_due', 10, 2)->default(0); // customer pays this (replacement - return)
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->string('payment_method')->nullable();  // null if no extra payment
            $table->string('ewallet_reference')->nullable();
            $table->enum('status', ['completed', 'cancelled'])->default('completed');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('original_sale_id');
            $table->index('user_id');
            $table->index('exchange_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchanges');
    }
};
