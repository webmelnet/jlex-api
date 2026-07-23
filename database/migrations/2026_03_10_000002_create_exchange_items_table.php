<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exchange_id')->constrained('exchanges')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('original_sale_item_id')->nullable()->constrained('sale_items')->nullOnDelete();
            $table->enum('type', ['returned', 'replacement']);
            $table->integer('quantity');
            $table->decimal('price', 10, 2);    // unit price
            $table->decimal('subtotal', 10, 2); // price * quantity
            $table->timestamps();

            $table->index('exchange_id');
            $table->index('product_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_items');
    }
};
