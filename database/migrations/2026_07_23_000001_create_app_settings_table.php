<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value')->default('0');
            $table->string('label')->nullable();
            $table->timestamps();
        });

        // Seed defaults
        DB::table('app_settings')->insert([
            ['key' => 'pos_show_keyboard_shortcuts', 'value' => '1', 'label' => 'Show keyboard shortcuts in the POS Interface', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
