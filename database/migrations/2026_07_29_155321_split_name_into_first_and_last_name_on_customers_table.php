<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('id');
            $table->string('last_name')->nullable()->after('first_name');
        });

        DB::table('customers')->orderBy('id')->get(['id', 'name'])->each(function ($customer) {
            $parts = preg_split('/\s+/', trim($customer->name), 2);
            DB::table('customers')->where('id', $customer->id)->update([
                'first_name' => $parts[0] ?? '',
                'last_name' => $parts[1] ?? '',
            ]);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
        });

        DB::table('customers')->orderBy('id')->get(['id', 'first_name', 'last_name'])->each(function ($customer) {
            DB::table('customers')->where('id', $customer->id)->update([
                'name' => trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')),
            ]);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
