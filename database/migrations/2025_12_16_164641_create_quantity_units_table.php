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
        Schema::create('quantity_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained('stores')->onDelete('cascade');
            $table->string('stripe_account_id')->nullable()->index();
            $table->string('name'); // e.g., "Piece", "Kilogram", "Meter"
            $table->string('symbol')->nullable(); // e.g., "stk", "kg", "m"
            $table->text('description')->nullable();
            $table->boolean('is_standard')->default(false); // Standard units are pre-seeded
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            // Indexes
            $table->index(['stripe_account_id', 'active']);
            $table->index('is_standard');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quantity_units');
    }
};
