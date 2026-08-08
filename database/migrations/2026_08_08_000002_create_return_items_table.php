<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained('sale_items')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->integer('quantity');
            $table->decimal('price', 10, 2);    // snapshot of sale_item.price at return time
            $table->decimal('subtotal', 10, 2); // price * quantity
            $table->string('return_policy_at_return')->nullable();
            $table->string('policy_warning')->nullable(); // null | 'no_return' | 'window_expired'
            $table->timestamps();

            $table->index('return_id');
            $table->index('product_id');
            $table->index('sale_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
    }
};
