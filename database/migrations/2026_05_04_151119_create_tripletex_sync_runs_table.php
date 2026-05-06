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
        Schema::create('tripletex_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tripletex_integration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('sync_type', 32)->index();
            $table->foreignId('pos_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('store_stripe_payout_id')->nullable()->constrained('store_stripe_payouts')->nullOnDelete();
            $table->string('status', 32)->default('pending')->index();
            $table->string('idempotency_key', 191)->unique();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('tripletex_voucher_id', 64)->nullable()->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'sync_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tripletex_sync_runs');
    }
};
