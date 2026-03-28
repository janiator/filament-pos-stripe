<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('power_office_integrations', function (Blueprint $table) {
            $table->timestamp('onboarding_completed_at')->nullable()->after('last_onboarded_at');
            $table->boolean('sync_enabled')->default(true)->after('auto_sync_on_z_report');
        });

        DB::table('power_office_integrations')->update([
            'onboarding_completed_at' => now(),
            'sync_enabled' => true,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('power_office_integrations', function (Blueprint $table) {
            $table->dropColumn(['onboarding_completed_at', 'sync_enabled']);
        });
    }
};
