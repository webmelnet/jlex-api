<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('app_settings')->insert([
            'key' => 'pos_enable_phone_order',
            'value' => '0',
            'label' => 'Enable Phone Order option in the POS Interface',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('app_settings')->where('key', 'pos_enable_phone_order')->delete();
    }
};
