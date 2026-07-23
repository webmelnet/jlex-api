<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('ewallet_reference')->nullable()->after('payment_method');
            $table->string('ewallet_screenshot')->nullable()->after('ewallet_reference');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['ewallet_reference', 'ewallet_screenshot']);
        });
    }
};
