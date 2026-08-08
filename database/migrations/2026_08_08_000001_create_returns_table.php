<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->foreignId('sale_id')->constrained('sales');
            $table->foreignId('user_id')->constrained('users');
            $table->timestamp('return_date');
            $table->decimal('refund_total', 10, 2);
            $table->enum('status', ['completed', 'cancelled'])->default('completed');
            $table->boolean('policy_override')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('sale_id');
            $table->index('user_id');
            $table->index('return_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returns');
    }
};
