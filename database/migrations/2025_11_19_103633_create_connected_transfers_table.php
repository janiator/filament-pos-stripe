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
        Schema::create('connected_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_transfer_id')->unique();
            $table->string('stripe_account_id')->index();
            $table->string('stripe_charge_id')->nullable()->index();
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->integer('amount'); // Amount in cents
            $table->string('currency', 3)->default('usd');
            $table->string('status'); // paid, pending, in_transit, canceled, failed
            $table->string('destination')->nullable(); // Account ID or bank account
            $table->string('description')->nullable();
            $table->timestamp('arrival_date')->nullable();
            $table->json('metadata')->nullable();
            $table->json('reversals')->nullable();
            $table->integer('reversed_amount')->default(0);
            $table->timestamps();

            $table->index(['stripe_account_id', 'status']);
            $table->index(['stripe_account_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connected_transfers');
    }
};
