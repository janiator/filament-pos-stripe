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
        Schema::create('store_stripe_balance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_account_id')->index();
            $table->string('stripe_balance_transaction_id')->unique();
            $table->string('type')->index();
            $table->bigInteger('amount');
            $table->unsignedBigInteger('fee')->default(0);
            $table->bigInteger('net');
            $table->string('currency', 8);
            $table->string('status')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('stripe_charge_id')->nullable()->index();
            $table->unsignedInteger('stripe_created')->nullable()->index();
            $table->timestamp('available_on')->nullable();
            $table->string('reporting_category')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_stripe_balance_transactions');
    }
};
