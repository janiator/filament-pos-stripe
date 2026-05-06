<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_stripe_balance_transactions', function (Blueprint $table) {
            $table->json('fee_details')->nullable()->after('fee');
            $table->json('source_metadata')->nullable()->after('stripe_charge_id');
            $table->string('stripe_payment_intent_id')->nullable()->index()->after('stripe_charge_id');
        });
    }

    public function down(): void
    {
        Schema::table('store_stripe_balance_transactions', function (Blueprint $table) {
            $table->dropColumn(['fee_details', 'source_metadata', 'stripe_payment_intent_id']);
        });
    }
};
