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
        Schema::table('pos_devices', function (Blueprint $table) {
            $table->foreignId('terminal_location_id')
                ->nullable()
                ->after('default_printer_id')
                ->constrained('terminal_locations')
                ->nullOnDelete();
        });

        DB::table('terminal_locations')
            ->whereNotNull('pos_device_id')
            ->orderBy('id')
            ->get(['id', 'pos_device_id'])
            ->each(function (object $location): void {
                DB::table('pos_devices')
                    ->where('id', $location->pos_device_id)
                    ->whereNull('terminal_location_id')
                    ->update([
                        'terminal_location_id' => $location->id,
                        'updated_at' => now(),
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_devices', function (Blueprint $table) {
            $table->dropForeign(['terminal_location_id']);
            $table->dropColumn('terminal_location_id');
        });
    }
};
