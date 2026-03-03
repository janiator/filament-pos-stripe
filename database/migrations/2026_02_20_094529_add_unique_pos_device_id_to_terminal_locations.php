<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'terminal_locations_pos_device_id_unique';

    /**
     * Run the migrations.
     * Ensures each POS device can have at most one terminal location assigned.
     */
    public function up(): void
    {
        // Remove duplicate assignments: keep the location with smallest id per device, clear the rest
        $duplicates = DB::table('terminal_locations')
            ->whereNotNull('pos_device_id')
            ->select('pos_device_id')
            ->groupBy('pos_device_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('pos_device_id');

        foreach ($duplicates as $posDeviceId) {
            $keepId = DB::table('terminal_locations')
                ->where('pos_device_id', $posDeviceId)
                ->orderBy('id')
                ->value('id');
            DB::table('terminal_locations')
                ->where('pos_device_id', $posDeviceId)
                ->where('id', '!=', $keepId)
                ->update(['pos_device_id' => null]);
        }

        // One terminal location per POS device (partial unique: only non-null pos_device_id)
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX '.self::INDEX_NAME.' ON terminal_locations (pos_device_id) WHERE pos_device_id IS NOT NULL');
        } else {
            Schema::table('terminal_locations', function (Blueprint $table) {
                $table->unique('pos_device_id', self::INDEX_NAME);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS '.self::INDEX_NAME);
        } else {
            Schema::table('terminal_locations', function (Blueprint $table) {
                $table->dropUnique(self::INDEX_NAME);
            });
        }
    }
};
