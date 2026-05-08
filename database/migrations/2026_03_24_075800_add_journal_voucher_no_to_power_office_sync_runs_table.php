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
        Schema::table('power_office_sync_runs', function (Blueprint $table) {
            $table->bigInteger('journal_voucher_no')->nullable()->after('response_payload')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('power_office_sync_runs', function (Blueprint $table) {
            $table->dropColumn('journal_voucher_no');
        });
    }
};
