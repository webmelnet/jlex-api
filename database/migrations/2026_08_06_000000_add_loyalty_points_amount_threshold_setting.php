<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('app_settings')->where('key', 'loyalty_points_amount_threshold')->exists()) {
            return;
        }

        DB::table('app_settings')->insert([
            'key' => 'loyalty_points_amount_threshold',
            'value' => '200',
            'label' => 'Purchase amount required per Loyalty Point earned',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('app_settings')->where('key', 'loyalty_points_amount_threshold')->delete();
    }
};
