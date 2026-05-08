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
        Schema::create('store_stripe_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_account_id')->index();
            $table->string('stripe_payout_id')->unique();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 8);
            $table->string('status')->index();
            $table->timestamp('arrival_date')->nullable()->index();
            $table->string('method')->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->string('statement_descriptor')->nullable();
            $table->boolean('automatic')->default(true);
            $table->unsignedInteger('stripe_created')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_stripe_payouts');
    }
};
