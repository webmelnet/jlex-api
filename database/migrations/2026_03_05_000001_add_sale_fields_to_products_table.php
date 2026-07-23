<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('sale_price', 10, 2)->nullable()->after('price');
            $table->enum('sale_mode', ['manual', 'scheduled', 'stock'])->nullable()->after('sale_price');
            $table->timestamp('sale_start_at')->nullable()->after('sale_mode');
            $table->timestamp('sale_end_at')->nullable()->after('sale_start_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['sale_price', 'sale_mode', 'sale_start_at', 'sale_end_at']);
        });
    }
};
