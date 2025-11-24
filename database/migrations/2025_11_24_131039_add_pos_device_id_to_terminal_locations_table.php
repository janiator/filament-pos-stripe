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
        Schema::table('terminal_locations', function (Blueprint $table) {
            $table->foreignId('pos_device_id')
                ->nullable()
                ->after('store_id')
                ->constrained('pos_devices')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('terminal_locations', function (Blueprint $table) {
            $table->dropForeign(['pos_device_id']);
            $table->dropColumn('pos_device_id');
        });
    }
};
