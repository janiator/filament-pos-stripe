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
        Schema::table('pos_devices', function (Blueprint $table) {
            $table->foreignId('last_connected_terminal_location_id')
                ->nullable()
                ->after('default_printer_id')
                ->constrained('terminal_locations')
                ->nullOnDelete();
            $table->foreignId('last_connected_terminal_reader_id')
                ->nullable()
                ->after('last_connected_terminal_location_id')
                ->constrained('terminal_readers')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_devices', function (Blueprint $table) {
            $table->dropForeign(['last_connected_terminal_location_id']);
            $table->dropForeign(['last_connected_terminal_reader_id']);
        });
    }
};
