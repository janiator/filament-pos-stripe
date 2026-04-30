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
        Schema::table('power_office_account_mappings', function (Blueprint $table) {
            $table->foreignId('store_id')
                ->nullable()
                ->after('power_office_integration_id')
                ->constrained('stores')
                ->cascadeOnDelete();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('
                UPDATE power_office_account_mappings AS m
                SET store_id = i.store_id
                FROM power_office_integrations AS i
                WHERE m.power_office_integration_id = i.id
                  AND m.store_id IS NULL
            ');
        } else {
            $mappings = DB::table('power_office_account_mappings')->whereNull('store_id')->get();
            foreach ($mappings as $row) {
                $storeId = DB::table('power_office_integrations')
                    ->where('id', $row->power_office_integration_id)
                    ->value('store_id');
                if ($storeId !== null) {
                    DB::table('power_office_account_mappings')
                        ->where('id', $row->id)
                        ->update(['store_id' => $storeId]);
                }
            }
        }

        Schema::table('power_office_account_mappings', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('power_office_account_mappings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_id');
        });
    }
};
