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
        Schema::create('receipt_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('template_type'); // sales, return, copy, etc.
            $table->text('content'); // The XML template content
            $table->boolean('is_custom')->default(false); // True if customized, false if using default
            $table->string('version')->nullable(); // For tracking template versions
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Ensure one template per type per store (or global if store_id is null)
            $table->unique(['store_id', 'template_type']);
            
            $table->index('template_type');
            $table->index('is_custom');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_templates');
    }
};
