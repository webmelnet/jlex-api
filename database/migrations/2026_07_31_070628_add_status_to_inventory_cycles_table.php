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
        Schema::table('inventory_cycles', function (Blueprint $table) {
            $table->dropUnique(['period']);
            $table->enum('status', ['open', 'closed'])->default('open')->after('period');
            $table->dateTime('closed_at')->nullable()->after('started_at');
            $table->foreignId('closed_by')->nullable()->after('started_by')->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_cycles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn(['status', 'closed_at']);
            $table->unique('period');
        });
    }
};
