<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change value column to text to support long content
        Schema::table('website_configurations', function (Blueprint $table) {
            $table->text('value')->default(null)->change();
        });

        // Seed About Us defaults
        DB::table('website_configurations')->insert([
            [
                'key'        => 'about_us_title',
                'value'      => 'About Us',
                'label'      => 'About Us Title',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key'        => 'about_us_content',
                'value'      => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.',
                'label'      => 'About Us Content',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('website_configurations')
            ->whereIn('key', ['about_us_title', 'about_us_content'])
            ->delete();

        Schema::table('website_configurations', function (Blueprint $table) {
            $table->string('value')->default('0')->change();
        });
    }
};
