<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['product_id', 'supplier_id']);
        });

        DB::table('products')
            ->whereNotNull('supplier_id')
            ->select('id', 'supplier_id')
            ->orderBy('id')
            ->chunk(500, function ($products) {
                $rows = $products->map(fn ($product) => [
                    'product_id' => $product->id,
                    'supplier_id' => $product->supplier_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->toArray();

                DB::table('product_supplier')->insert($rows);
            });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('brand_id')->constrained()->onDelete('set null');
            $table->index('supplier_id');
        });

        DB::table('product_supplier')
            ->orderBy('product_id')
            ->select('product_id', 'supplier_id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('products')->where('id', $row->product_id)->update(['supplier_id' => $row->supplier_id]);
                }
            });

        Schema::dropIfExists('product_supplier');
    }
};
