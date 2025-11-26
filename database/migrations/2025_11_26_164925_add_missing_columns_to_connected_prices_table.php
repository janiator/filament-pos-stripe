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
        Schema::table('connected_prices', function (Blueprint $table) {
            if (!Schema::hasColumn('connected_prices', 'nickname')) {
                $table->string('nickname')->nullable()->after('metadata');
            }
            if (!Schema::hasColumn('connected_prices', 'recurring_aggregate_usage')) {
                $table->string('recurring_aggregate_usage')->nullable()->after('recurring_usage_type');
            }
            if (!Schema::hasColumn('connected_prices', 'tiers_mode')) {
                $table->string('tiers_mode')->nullable()->after('billing_scheme');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('connected_prices', function (Blueprint $table) {
            $table->dropColumn(['nickname', 'recurring_aggregate_usage', 'tiers_mode']);
        });
    }
};
