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
        Schema::table('store_stripe_balance_transactions', function (Blueprint $table) {
            $table->string('stripe_payout_id', 64)->nullable()->after('stripe_charge_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_stripe_balance_transactions', function (Blueprint $table) {
            $table->dropColumn('stripe_payout_id');
        });
    }
};
