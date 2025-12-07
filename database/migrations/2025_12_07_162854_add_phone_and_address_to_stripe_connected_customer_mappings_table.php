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
        Schema::table('stripe_connected_customer_mappings', function (Blueprint $table) {
            // Add phone column (nullable string per Stripe API spec)
            $table->string('phone')->nullable()->after('email');
            
            // Add address column (JSON to store Stripe address object)
            // Stripe address object contains: line1, line2, city, state, postal_code, country
            $table->json('address')->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stripe_connected_customer_mappings', function (Blueprint $table) {
            $table->dropColumn(['phone', 'address']);
        });
    }
};
