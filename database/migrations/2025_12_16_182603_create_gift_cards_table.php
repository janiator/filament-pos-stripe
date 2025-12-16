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
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('code', 32)->unique(); // Unique gift card code
            $table->string('pin', 8)->nullable(); // Optional PIN for security (hashed)
            $table->integer('initial_amount'); // Initial amount in øre
            $table->integer('balance'); // Current balance in øre
            $table->integer('amount_redeemed')->default(0); // Total redeemed in øre
            $table->string('currency', 3)->default('nok');
            $table->enum('status', ['active', 'redeemed', 'expired', 'voided', 'refunded'])->default('active');
            $table->dateTime('purchased_at'); // When gift card was purchased
            $table->dateTime('expires_at')->nullable(); // Optional expiration date
            $table->dateTime('last_used_at')->nullable(); // Last redemption date
            $table->foreignId('purchase_charge_id')->nullable()->constrained('connected_charges')->onDelete('set null'); // Original purchase charge
            $table->foreignId('purchased_by_user_id')->nullable()->constrained('users')->onDelete('set null'); // User who sold the gift card
            $table->foreignId('customer_id')->nullable()->constrained('stripe_connected_customer_mappings')->onDelete('set null'); // Optional: Customer who purchased
            $table->text('notes')->nullable(); // Admin notes
            $table->json('metadata')->nullable(); // Additional metadata
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['store_id', 'status']);
            $table->index(['code']);
            $table->index(['purchased_at']);
            $table->index(['expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gift_cards');
    }
};
