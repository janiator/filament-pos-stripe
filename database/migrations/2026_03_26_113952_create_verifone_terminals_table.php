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
        Schema::create('verifone_terminals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('terminal_identifier');
            $table->string('display_name')->nullable();
            $table->string('sale_id')->nullable();
            $table->string('operator_id')->nullable();
            $table->string('site_entity_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('terminal_metadata')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'terminal_identifier']);
            $table->index(['store_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verifone_terminals');
    }
};
