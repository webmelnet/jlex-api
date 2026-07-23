<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('website_configurations')->insert([
            [
                'key'        => 'find_us_address',
                'value'      => 'Tara St., Masbate City, Masbate, Philippines',
                'label'      => 'Store Address',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key'        => 'find_us_phone',
                'value'      => '+63 977 832 8484',
                'label'      => 'Store Phone',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key'        => 'find_us_map_embed',
                'value'      => 'https://maps.google.com/maps?q=12.3664005,123.6220172&z=16&output=embed',
                'label'      => 'Google Maps Embed URL',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('website_configurations')
            ->whereIn('key', ['find_us_address', 'find_us_phone', 'find_us_map_embed'])
            ->delete();
    }
};
