<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_cycle_records', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'cycle_date']);
            $table->dropColumn(['cycle_date', 'period_start']);
            $table->foreignId('inventory_cycle_id')->after('id')->constrained()->onDelete('cascade');
        });

        DB::statement('ALTER TABLE inventory_cycle_records MODIFY staff_input INT NULL');
        DB::statement('ALTER TABLE inventory_cycle_records MODIFY variance INT NULL');

        Schema::table('inventory_cycle_records', function (Blueprint $table) {
            $table->unique(['inventory_cycle_id', 'product_id']);
        });

        Schema::rename('inventory_cycle_records', 'inventory_cycle_items');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('inventory_cycle_items', 'inventory_cycle_records');

        Schema::table('inventory_cycle_records', function (Blueprint $table) {
            $table->dropUnique(['inventory_cycle_id', 'product_id']);
        });

        DB::statement('ALTER TABLE inventory_cycle_records MODIFY staff_input INT NOT NULL');
        DB::statement('ALTER TABLE inventory_cycle_records MODIFY variance INT NOT NULL');

        Schema::table('inventory_cycle_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_cycle_id');
            $table->date('cycle_date');
            $table->dateTime('period_start')->nullable();
            $table->unique(['product_id', 'cycle_date']);
        });
    }
};
