<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('app_settings')->insert([
            'key' => 'hide_product_images',
            'value' => '0',
            'label' => 'Hide product images throughout the app (POS and Inventory)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('app_settings')->where('key', 'hide_product_images')->delete();
    }
};
