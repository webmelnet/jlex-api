<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_drawer_entries', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['starting_cash', 'cash_added', 'cash_expense', 'non_cash_expense']);
            $table->decimal('amount', 10, 2);
            $table->string('description')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_drawer_entries');
    }
};
