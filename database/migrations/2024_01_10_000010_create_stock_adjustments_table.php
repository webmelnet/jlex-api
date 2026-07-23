<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('quantity_before');
            $table->integer('quantity_adjusted');
            $table->integer('quantity_after');
            $table->enum('type', ['increase', 'decrease']);
            $table->enum('reason', [
                'damaged',
                'lost',
                'found',
                'return',
                'correction',
                'expired',
                'other'
            ]);
            $table->text('notes')->nullable();
            $table->dateTime('adjustment_date');
            $table->timestamps();

            $table->index('reference_number');
            $table->index('product_id');
            $table->index('adjustment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
