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
        Schema::create('power_office_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('status', 32)->default('not_connected')->index();
            $table->string('environment', 16)->default('dev')->index();
            $table->text('client_key')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('mapping_basis', 32)->default('vat')->index();
            $table->boolean('auto_sync_on_z_report')->default(true);
            $table->timestamp('last_onboarded_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('settings')->nullable();
            $table->string('onboarding_state_token', 64)->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('power_office_integrations');
    }
};
