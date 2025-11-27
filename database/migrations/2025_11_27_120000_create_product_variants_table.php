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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connected_product_id')->constrained('connected_products')->onDelete('cascade');
            $table->string('stripe_account_id')->index();
            $table->string('stripe_price_id')->nullable()->index(); // Link to ConnectedPrice
            $table->string('sku')->nullable()->index();
            $table->string('barcode')->nullable()->index();
            
            // Variant options (e.g., Size: Large, Color: Red)
            $table->string('option1_name')->nullable();
            $table->string('option1_value')->nullable();
            $table->string('option2_name')->nullable();
            $table->string('option2_value')->nullable();
            $table->string('option3_name')->nullable();
            $table->string('option3_value')->nullable();
            
            // Pricing
            $table->integer('price_amount')->nullable(); // In cents
            $table->string('currency', 3)->default('nok');
            $table->integer('compare_at_price_amount')->nullable(); // Original price for discounts
            
            // Physical properties
            $table->integer('weight_grams')->nullable();
            $table->boolean('requires_shipping')->default(true);
            $table->boolean('taxable')->default(true);
            
            // Inventory (optional - can be null if not tracking)
            $table->integer('inventory_quantity')->nullable();
            $table->string('inventory_policy')->default('deny'); // 'deny' or 'continue'
            $table->string('inventory_management')->nullable(); // e.g., 'shopify'
            
            // Variant-specific image
            $table->string('image_url')->nullable();
            
            // Metadata
            $table->json('metadata')->nullable();
            
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            // Indexes
            $table->index(['connected_product_id', 'stripe_account_id']);
            $table->unique(['sku', 'stripe_account_id'], 'unique_sku_per_account');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};

