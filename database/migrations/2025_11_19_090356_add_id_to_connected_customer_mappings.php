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
            if (!Schema::hasColumn('stripe_connected_customer_mappings', 'id')) {
                $table->id()->first();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stripe_connected_customer_mappings', function (Blueprint $table) {
            if (Schema::hasColumn('stripe_connected_customer_mappings', 'id')) {
                $table->dropColumn('id');
            }
        });
    }
};
