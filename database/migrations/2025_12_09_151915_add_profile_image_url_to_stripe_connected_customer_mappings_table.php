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
            $table->string('profile_image_url')->nullable()->after('address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stripe_connected_customer_mappings', function (Blueprint $table) {
            $table->dropColumn('profile_image_url');
        });
    }
};
