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
        Schema::create('pos_line_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('pos_session_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('correction_type')->default('feilslag'); // Type: feilslag, etc.
            $table->integer('quantity_reduction')->default(0); // Quantity reduced (only reductions count)
            $table->integer('amount_reduction')->default(0); // Amount reduced in øre
            $table->text('reason')->nullable(); // Reason for correction
            $table->json('original_item_data')->nullable(); // Snapshot of original item
            $table->json('corrected_item_data')->nullable(); // Snapshot of corrected item
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['store_id', 'pos_session_id']);
            $table->index(['pos_session_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_line_corrections');
    }
};
