<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sales')->where('payment_method', 'gcash')->update(['payment_method' => 'ewallet']);
        DB::table('exchanges')->where('payment_method', 'gcash')->update(['payment_method' => 'ewallet']);
    }

    public function down(): void
    {
        DB::table('sales')->where('payment_method', 'ewallet')->update(['payment_method' => 'gcash']);
        DB::table('exchanges')->where('payment_method', 'ewallet')->update(['payment_method' => 'gcash']);
    }
};
