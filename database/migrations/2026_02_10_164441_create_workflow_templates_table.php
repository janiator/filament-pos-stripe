<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_templates', function (Blueprint $table) {
            $table->id();

            // Template identification
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();

            // Categorization
            $table->string('category'); // communication, records, integrations, scheduling, custom
            $table->string('icon')->nullable(); // Heroicon name for UI
            $table->string('color')->nullable(); // Filament color name

            // Template definition (same structure as Workflow)
            $table->json('definition');

            // Configurable variables for template instantiation
            // [{ key, label, type, required, default, options }]
            $table->json('variables')->nullable();

            // Metadata
            $table->json('tags')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            // Indexes
            $table->index('category', 'wf_templates_category');
            $table->index(['is_active', 'sort_order'], 'wf_templates_active_sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_templates');
    }
};
