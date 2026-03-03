<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webflow_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webflow_site_id')->constrained('webflow_sites')->cascadeOnDelete();
            $table->string('webflow_collection_id');
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->json('schema')->nullable();
            $table->json('field_mapping')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['webflow_site_id', 'webflow_collection_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webflow_collections');
    }
};
