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
        Schema::create('connected_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_payment_method_id')->unique();
            $table->string('stripe_account_id')->index();
            $table->string('stripe_customer_id')->index();
            $table->string('type'); // card, bank_account, etc.
            $table->string('card_brand')->nullable(); // visa, mastercard, etc.
            $table->string('card_last4')->nullable();
            $table->integer('card_exp_month')->nullable();
            $table->integer('card_exp_year')->nullable();
            $table->string('billing_details_name')->nullable();
            $table->string('billing_details_email')->nullable();
            $table->json('billing_details_address')->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['stripe_account_id', 'stripe_customer_id']);
            $table->index(['stripe_customer_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connected_payment_methods');
    }
};
