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
        Schema::create('verifone_terminal_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('verifone_terminal_id')->nullable()->index();
            $table->foreignId('pos_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pos_device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_id');
            $table->string('sale_id');
            $table->string('poiid');
            $table->integer('amount_minor');
            $table->string('currency', 3)->default('NOK');
            $table->string('status')->default('pending');
            $table->string('provider_payment_reference')->nullable();
            $table->string('provider_transaction_id')->nullable();
            $table->string('provider_message')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->json('status_payload')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'service_id']);
            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'poiid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verifone_terminal_payments');
    }
};
