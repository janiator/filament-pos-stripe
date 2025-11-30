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
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->string('stripe_account_id')->index();
            $table->string('stripe_promotion_code_id')->nullable()->unique(); // Stripe Promotion Code ID if synced
            $table->string('title');
            $table->text('description')->nullable();
            
            // Discount type and value
            $table->enum('discount_type', ['percentage', 'fixed_amount'])->default('percentage');
            $table->decimal('discount_value', 10, 2); // Percentage (0-100) or fixed amount in cents
            $table->string('currency', 3)->default('nok');
            
            // Status and dates
            $table->boolean('active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            
            // Conditions (like Shopify automatic discounts)
            $table->enum('customer_selection', ['all', 'specific_customers'])->default('all');
            $table->json('customer_ids')->nullable(); // For specific customers
            $table->enum('minimum_requirement_type', ['none', 'minimum_purchase_amount', 'minimum_quantity'])->default('none');
            $table->integer('minimum_requirement_value')->nullable(); // Amount in cents or quantity
            $table->enum('applicable_to', ['all_products', 'specific_products', 'specific_collections'])->default('all_products');
            $table->json('product_ids')->nullable(); // For specific products
            $table->json('collection_ids')->nullable(); // For specific collections (future feature)
            
            // Usage limits
            $table->integer('usage_limit')->nullable(); // Total usage limit
            $table->integer('usage_limit_per_customer')->nullable(); // Per customer limit
            $table->integer('usage_count')->default(0); // Current usage count
            
            // Priority (higher number = higher priority, applied first)
            $table->integer('priority')->default(0);
            
            // Metadata
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['stripe_account_id', 'active']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
