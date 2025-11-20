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
        Schema::create('connected_prices', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_price_id')->unique();
            $table->string('stripe_account_id')->index();
            $table->string('stripe_product_id')->index();
            $table->integer('unit_amount')->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('type')->default('one_time');
            $table->string('recurring_interval')->nullable();
            $table->integer('recurring_interval_count')->nullable();
            $table->string('recurring_usage_type')->nullable();
            $table->string('recurring_aggregate_usage')->nullable();
            $table->boolean('active')->default(true);
            $table->json('metadata')->nullable();
            $table->string('nickname')->nullable();
            $table->string('billing_scheme')->nullable();
            $table->string('tiers_mode')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connected_prices');
    }
};
