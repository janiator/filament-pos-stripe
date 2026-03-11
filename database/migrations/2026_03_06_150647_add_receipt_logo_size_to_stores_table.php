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
        Schema::table('stores', function (Blueprint $table) {
            $table->unsignedSmallInteger('receipt_logo_max_width_dots')->nullable()->after('logo_path');
            $table->unsignedSmallInteger('receipt_logo_max_height_dots')->nullable()->after('receipt_logo_max_width_dots');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['receipt_logo_max_width_dots', 'receipt_logo_max_height_dots']);
        });
    }
};
