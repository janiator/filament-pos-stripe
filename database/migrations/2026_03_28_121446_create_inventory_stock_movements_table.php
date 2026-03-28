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
        Schema::create('inventory_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->integer('quantity_delta');
            $table->string('reason', 32);
            $table->foreignId('connected_charge_id')->nullable()->constrained('connected_charges')->nullOnDelete();
            $table->foreignId('pos_event_id')->nullable()->constrained('pos_events')->nullOnDelete();
            $table->string('refund_reference', 128)->nullable();
            $table->string('idempotency_key', 191)->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'created_at']);
            $table->index(['product_variant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_movements');
    }
};
