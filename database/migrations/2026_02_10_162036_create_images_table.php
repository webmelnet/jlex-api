<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            
            // Polymorphic relationship - can be used for products, categories, users, etc.
            // Note: morphs() automatically creates an index, so no need to add it manually
            $table->morphs('imageable'); // Creates imageable_id, imageable_type AND index
            
            // S3 Storage details
            $table->text('url'); // Full S3 URL
            $table->string('path'); // S3 path (e.g., products/filename.jpg)
            $table->string('filename'); // Stored filename
            $table->string('original_filename')->nullable(); // Original upload filename
            
            // File metadata
            $table->unsignedBigInteger('file_size')->nullable(); // Size in bytes
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            
            // Organization
            $table->boolean('is_primary')->default(false); // Main image flag
            $table->unsignedInteger('position')->default(0); // Display order
            $table->string('alt_text')->nullable(); // SEO alt text
            
            // Optimization metadata (stores upload result details)
            $table->json('metadata')->nullable(); // Stores optimization details, dimensions, etc.
            
            $table->timestamps();
            $table->softDeletes(); // Soft delete support
            
            // Indexes (morphs already creates index for imageable_type, imageable_id)
            $table->index('is_primary');
            $table->index('position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};
