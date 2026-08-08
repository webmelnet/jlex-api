<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE cash_drawer_entries MODIFY COLUMN type ENUM('starting_cash', 'cash_added', 'cash_expense', 'non_cash_expense', 'cash_refund') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE cash_drawer_entries MODIFY COLUMN type ENUM('starting_cash', 'cash_added', 'cash_expense', 'non_cash_expense') NOT NULL");
    }
};
