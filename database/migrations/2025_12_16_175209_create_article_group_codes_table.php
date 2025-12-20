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
        Schema::create('article_group_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained('stores')->onDelete('cascade');
            $table->string('stripe_account_id')->nullable()->index();
            $table->string('code', 10); // e.g., "04001", "04003" - PredefinedBasicID-04
            $table->string('name'); // e.g., "Uttak av behandlingstjenester"
            $table->text('description')->nullable();
            $table->decimal('default_vat_percent', 5, 2)->nullable()->comment('Default VAT percentage for this code');
            $table->boolean('is_standard')->default(false); // Standard SAF-T codes are pre-seeded
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            // Indexes
            $table->index(['stripe_account_id', 'active']);
            $table->index('is_standard');
            $table->index('code');
            // Unique constraint: code must be unique per stripe_account_id (or globally if null)
            $table->unique(['code', 'stripe_account_id'], 'article_group_codes_code_stripe_account_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_group_codes');
    }
};
