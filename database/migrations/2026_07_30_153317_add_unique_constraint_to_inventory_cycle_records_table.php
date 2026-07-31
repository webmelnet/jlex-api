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
        Schema::table('inventory_cycle_records', function (Blueprint $table) {
            $table->unique(['product_id', 'cycle_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_cycle_records', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'cycle_date']);
        });
    }
};
