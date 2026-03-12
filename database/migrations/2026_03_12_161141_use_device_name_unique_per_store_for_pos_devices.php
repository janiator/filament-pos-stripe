<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Use device_name as the unique key per store for POS device identity.
     * Android device_identifier is not guaranteed unique, so we no longer
     * enforce uniqueness on it; device_name (e.g. "POS 4", "POS 6") is.
     */
    public function up(): void
    {
        Schema::table('pos_devices', function (Blueprint $table) {
            $table->dropUnique('pos_devices_store_device_unique');
        });

        // Resolve any duplicate (store_id, device_name) by appending id to device_name
        $duplicates = DB::table('pos_devices')
            ->select('store_id', 'device_name')
            ->groupBy('store_id', 'device_name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            $rows = DB::table('pos_devices')
                ->where('store_id', $dup->store_id)
                ->where('device_name', $dup->device_name)
                ->orderBy('id')
                ->get();
            $first = true;
            foreach ($rows as $row) {
                if (! $first) {
                    DB::table('pos_devices')
                        ->where('id', $row->id)
                        ->update(['device_name' => $row->device_name.'_'.$row->id]);
                }
                $first = false;
            }
        }

        Schema::table('pos_devices', function (Blueprint $table) {
            $table->unique(['store_id', 'device_name'], 'pos_devices_store_device_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_devices', function (Blueprint $table) {
            $table->dropUnique('pos_devices_store_device_name_unique');
        });

        Schema::table('pos_devices', function (Blueprint $table) {
            $table->unique(['store_id', 'device_identifier'], 'pos_devices_store_device_unique');
        });
    }
};
