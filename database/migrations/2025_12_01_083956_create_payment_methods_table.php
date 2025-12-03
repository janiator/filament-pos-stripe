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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('name'); // Display name (e.g., "Kontant", "Kort", "Mobile Pay")
            $table->string('code')->index(); // Internal code (e.g., "cash", "card", "mobile")
            $table->enum('provider', ['stripe', 'cash', 'other'])->default('other');
            $table->string('provider_method')->nullable(); // Stripe payment method type (e.g., "card_present", "card")
            $table->boolean('enabled')->default(true);
            $table->integer('sort_order')->default(0); // For ordering in UI
            $table->json('config')->nullable(); // Provider-specific configuration
            $table->string('saf_t_payment_code')->nullable(); // SAF-T payment code (PredefinedBasicID-12)
            $table->string('saf_t_event_code')->nullable(); // SAF-T event code (PredefinedBasicID-13)
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'enabled']);
            $table->index(['store_id', 'code']);
            $table->unique(['store_id', 'code']); // Each store can only have one payment method per code
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
