<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Allow the same POS device to be registered on multiple stores (tenants).
     * device_identifier is unique per store, not globally.
     */
    public function up(): void
    {
        Schema::table('pos_devices', function (Blueprint $table) {
            $table->dropUnique(['device_identifier']);
            $table->unique(['store_id', 'device_identifier'], 'pos_devices_store_device_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_devices', function (Blueprint $table) {
            $table->dropUnique('pos_devices_store_device_unique');
            $table->unique('device_identifier');
        });
    }
};
