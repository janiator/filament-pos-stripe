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
        Schema::create('gift_card_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gift_card_id')->constrained()->onDelete('cascade');
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['purchase', 'redemption', 'refund', 'adjustment', 'void']);
            $table->integer('amount'); // Transaction amount in øre (positive for purchase, negative for redemption)
            $table->integer('balance_before'); // Balance before transaction
            $table->integer('balance_after'); // Balance after transaction
            $table->foreignId('charge_id')->nullable()->constrained('connected_charges')->onDelete('set null'); // Related charge
            $table->foreignId('pos_session_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('pos_event_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // User who performed the transaction
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['gift_card_id', 'created_at']);
            $table->index(['store_id', 'type', 'created_at']);
            $table->index(['charge_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gift_card_transactions');
    }
};
