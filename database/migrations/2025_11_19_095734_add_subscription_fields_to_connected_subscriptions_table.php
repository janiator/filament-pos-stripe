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
        Schema::table('connected_subscriptions', function (Blueprint $table) {
            $table->timestamp('current_period_start')->nullable()->after('ends_at');
            $table->timestamp('current_period_end')->nullable()->after('current_period_start');
            $table->timestamp('billing_cycle_anchor')->nullable()->after('current_period_end');
            $table->boolean('cancel_at_period_end')->default(false)->after('billing_cycle_anchor');
            $table->string('collection_method')->nullable()->after('cancel_at_period_end');
            $table->string('currency', 3)->nullable()->after('collection_method');
            $table->json('metadata')->nullable()->after('currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('connected_subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'current_period_start',
                'current_period_end',
                'billing_cycle_anchor',
                'cancel_at_period_end',
                'collection_method',
                'currency',
                'metadata',
            ]);
        });
    }
};
