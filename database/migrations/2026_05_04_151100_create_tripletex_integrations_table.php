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
        Schema::create('tripletex_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('status', 32)->default('not_connected')->index();
            $table->string('environment', 16)->default('test')->index();
            $table->text('consumer_token')->nullable();
            $table->text('employee_token')->nullable();
            $table->string('mapping_basis', 32)->default('vat')->index();
            $table->boolean('sync_enabled')->default(true);
            $table->boolean('auto_sync_on_z_report')->default(true);
            $table->boolean('auto_sync_payouts')->default(false);
            /**
             * When true, Z-report vouchers include fee/payout settlement lines (same as PowerOffice optional lines).
             * When false (default), payout sync posts bank/fees separately to avoid double posting.
             */
            $table->boolean('z_report_include_settlement')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tripletex_integrations');
    }
};
