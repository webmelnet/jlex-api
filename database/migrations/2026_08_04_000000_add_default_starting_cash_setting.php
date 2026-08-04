<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('app_settings')->where('key', 'default_starting_cash')->exists()) {
            return;
        }

        DB::table('app_settings')->insert([
            'key' => 'default_starting_cash',
            'value' => '0',
            'label' => 'Default Starting Cash pre-filled when logging a shift\'s Starting Cash',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('app_settings')->where('key', 'default_starting_cash')->delete();
    }
};
