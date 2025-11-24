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
        Schema::create('connected_payment_intents', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_id')->unique();
            $table->string('stripe_account_id')->index();
            $table->string('stripe_customer_id')->nullable()->index();
            $table->string('stripe_payment_method_id')->nullable()->index();
            $table->integer('amount'); // Amount in cents
            $table->string('currency', 3)->default('usd');
            $table->string('status'); // requires_payment_method, requires_confirmation, requires_action, processing, requires_capture, canceled, succeeded
            $table->string('capture_method')->default('automatic'); // automatic or manual
            $table->string('confirmation_method')->default('automatic'); // automatic or manual
            $table->string('description')->nullable();
            $table->string('receipt_email')->nullable();
            $table->string('statement_descriptor')->nullable();
            $table->string('statement_descriptor_suffix')->nullable();
            $table->json('metadata')->nullable();
            $table->json('payment_method_options')->nullable();
            $table->string('client_secret')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('succeeded_at')->nullable();
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
        Schema::dropIfExists('connected_payment_intents');
    }
};
