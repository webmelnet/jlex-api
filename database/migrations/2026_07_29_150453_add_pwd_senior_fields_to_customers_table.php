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
        Schema::table('customers', function (Blueprint $table) {
            $table->enum('customer_category', ['regular', 'pwd', 'senior'])->default('regular')->after('name');
            $table->string('id_number')->nullable()->after('customer_category');
            $table->date('id_expiry_date')->nullable()->after('id_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['customer_category', 'id_number', 'id_expiry_date']);
        });
    }
};
