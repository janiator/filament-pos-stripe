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
        Schema::table('connected_payment_links', function (Blueprint $table) {
            $table->unsignedInteger('quantity_max')->nullable()->after('metadata');
            $table->unsignedInteger('quantity_sold')->default(0)->after('quantity_max');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('connected_payment_links', function (Blueprint $table) {
            $table->dropColumn(['quantity_max', 'quantity_sold']);
        });
    }
};
