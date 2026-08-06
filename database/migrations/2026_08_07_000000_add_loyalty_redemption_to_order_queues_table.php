<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_queues', function (Blueprint $table) {
            $table->unsignedInteger('loyalty_points_used')->default(0)->after('customer_type');
            $table->decimal('loyalty_discount', 10, 2)->default(0)->after('loyalty_points_used');
        });
    }

    public function down(): void
    {
        Schema::table('order_queues', function (Blueprint $table) {
            $table->dropColumn(['loyalty_points_used', 'loyalty_discount']);
        });
    }
};
