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
        Schema::create('inventory_cycle_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->date('cycle_date');
            $table->dateTime('period_start')->nullable();
            $table->integer('beginning_stock');
            $table->integer('added');
            $table->integer('deducted');
            $table->integer('non_cash_deducted');
            $table->integer('current_stock');
            $table->integer('staff_input');
            $table->integer('variance');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('product_id');
            $table->index(['product_id', 'cycle_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_cycle_records');
    }
};
