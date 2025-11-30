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
        Schema::table('connected_products', function (Blueprint $table) {
            $table->integer('compare_at_price_amount')->nullable()->after('currency'); // Original price for discounts (in cents)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('connected_products', function (Blueprint $table) {
            $table->dropColumn('compare_at_price_amount');
        });
    }
};
