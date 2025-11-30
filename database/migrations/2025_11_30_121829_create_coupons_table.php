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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->string('stripe_account_id')->index();
            $table->string('stripe_coupon_id')->nullable()->unique(); // Stripe Coupon ID
            $table->string('stripe_promotion_code_id')->nullable()->unique(); // Stripe Promotion Code ID (if code is set)
            
            // Coupon code (unique per store)
            $table->string('code')->index();
            
            // Discount type and value
            $table->enum('discount_type', ['percentage', 'fixed_amount'])->default('percentage');
            $table->decimal('discount_value', 10, 2); // Percentage (0-100) or fixed amount in cents
            $table->string('currency', 3)->default('nok');
            
            // Duration
            $table->enum('duration', ['once', 'repeating', 'forever'])->default('once');
            $table->integer('duration_in_months')->nullable(); // For repeating duration
            
            // Status and dates
            $table->boolean('active')->default(true);
            $table->timestamp('redeem_by')->nullable(); // Expiration date
            
            // Usage limits
            $table->integer('max_redemptions')->nullable(); // Total usage limit
            $table->integer('times_redeemed')->default(0); // Current usage count
            
            // Conditions
            $table->integer('minimum_amount')->nullable(); // Minimum purchase amount in cents
            $table->string('minimum_amount_currency', 3)->nullable();
            
            // Metadata
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->unique(['code', 'stripe_account_id'], 'unique_code_per_account');
            $table->index(['stripe_account_id', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
