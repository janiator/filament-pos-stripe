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
        Schema::table('tripletex_integrations', function (Blueprint $table) {
            $table->boolean('skip_payout_bank_transfer')->default(false)->after('z_report_include_settlement');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tripletex_integrations', function (Blueprint $table) {
            $table->dropColumn('skip_payout_bank_transfer');
        });
    }
};
