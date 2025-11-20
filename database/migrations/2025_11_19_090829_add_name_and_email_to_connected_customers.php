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
            $table->string('name')->nullable()->after('stripe_account_id');
            $table->string('email')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stripe_connected_customer_mappings', function (Blueprint $table) {
            $table->dropColumn(['name', 'email']);
        });
    }
};
