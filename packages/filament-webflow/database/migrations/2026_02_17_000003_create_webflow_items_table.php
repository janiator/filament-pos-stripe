<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webflow_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webflow_collection_id')->constrained('webflow_collections')->cascadeOnDelete();
            $table->string('webflow_item_id')->nullable();
            $table->json('field_data')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->boolean('is_draft')->default(false);
            $table->timestamp('webflow_created_at')->nullable();
            $table->timestamp('webflow_updated_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['webflow_collection_id', 'webflow_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webflow_items');
    }
};
