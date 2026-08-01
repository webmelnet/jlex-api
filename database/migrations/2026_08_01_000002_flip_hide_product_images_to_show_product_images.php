<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('app_settings')
            ->where('key', 'hide_product_images')
            ->update([
                'key' => 'show_product_images',
                'value' => '0',
                'label' => 'Show product images in the POS and Inventory product lists',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('app_settings')
            ->where('key', 'show_product_images')
            ->update([
                'key' => 'hide_product_images',
                'value' => '0',
                'label' => 'Hide product images throughout the app (POS and Inventory)',
                'updated_at' => now(),
            ]);
    }
};
