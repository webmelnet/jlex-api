<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_queues', function (Blueprint $table) {
            $table->foreignId('editing_by_user_id')->nullable()->after('claimed_at')->constrained('users')->onDelete('set null');
            $table->timestamp('editing_started_at')->nullable()->after('editing_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_queues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('editing_by_user_id');
            $table->dropColumn('editing_started_at');
        });
    }
};
