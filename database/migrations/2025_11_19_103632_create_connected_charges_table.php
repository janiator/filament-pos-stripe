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
        Schema::create('connected_charges', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_charge_id')->unique();
            $table->string('stripe_account_id')->index();
            $table->string('stripe_customer_id')->nullable()->index();
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->integer('amount'); // Amount in cents
            $table->integer('amount_refunded')->default(0);
            $table->string('currency', 3)->default('usd');
            $table->string('status'); // succeeded, pending, failed, refunded, etc.
            $table->string('payment_method')->nullable(); // card, bank_account, etc.
            $table->string('description')->nullable();
            $table->string('failure_code')->nullable();
            $table->string('failure_message')->nullable();
            $table->boolean('captured')->default(true);
            $table->boolean('refunded')->default(false);
            $table->boolean('paid')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->json('outcome')->nullable(); // Stripe outcome object
            $table->string('charge_type')->default('direct'); // direct or destination
            $table->integer('application_fee_amount')->nullable(); // For destination charges
            $table->timestamps();

            $table->index(['stripe_account_id', 'status']);
            $table->index(['stripe_account_id', 'created_at']);
            $table->index(['stripe_customer_id', 'stripe_account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connected_charges');
    }
};
