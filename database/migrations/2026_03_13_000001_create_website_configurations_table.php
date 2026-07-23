<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value')->default('0');
            $table->string('label')->nullable();
            $table->timestamps();
        });

        // Seed defaults
        DB::table('website_configurations')->insert([
            ['key' => 'show_price', 'value' => '1', 'label' => 'Show Price', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('website_configurations');
    }
};
