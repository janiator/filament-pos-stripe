<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Prevents duplicate quantity units: dedupes existing rows then adds a unique
     * constraint so concurrent seeder runs (e.g. double-click) cannot insert duplicates.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            // Delete duplicate rows, keeping the one with the smallest id per (store_id, stripe_account_id, name, symbol).
            DB::statement('
                DELETE FROM quantity_units a
                USING quantity_units b
                WHERE a.store_id IS NOT DISTINCT FROM b.store_id
                  AND a.stripe_account_id IS NOT DISTINCT FROM b.stripe_account_id
                  AND a.name = b.name
                  AND a.symbol IS NOT DISTINCT FROM b.symbol
                  AND a.id > b.id
            ');

            // Unique index so (store_id, stripe_account_id, name, symbol) is unique.
            // COALESCE makes NULLs compare equal so we only allow one global set (null, null, name, symbol).
            DB::statement("
                CREATE UNIQUE INDEX quantity_units_store_account_name_symbol_unique
                ON quantity_units (
                    COALESCE(store_id::text, ''),
                    COALESCE(stripe_account_id, ''),
                    name,
                    COALESCE(symbol, '')
                )
            ");
        } else {
            // MySQL / SQLite: delete duplicates then add simple unique index.
            $keepIds = DB::table('quantity_units')
                ->select(DB::raw('MIN(id) as id'))
                ->groupBy('store_id', 'stripe_account_id', 'name', 'symbol')
                ->pluck('id');

            DB::table('quantity_units')
                ->whereNotIn('id', $keepIds)
                ->delete();

            Schema::table('quantity_units', function (Blueprint $table) {
                $table->unique(['store_id', 'stripe_account_id', 'name', 'symbol'], 'quantity_units_store_account_name_symbol_unique');
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
            DB::statement('DROP INDEX IF EXISTS quantity_units_store_account_name_symbol_unique');
        } else {
            Schema::table('quantity_units', function (Blueprint $table) {
                $table->dropUnique('quantity_units_store_account_name_symbol_unique');
            });
        }
    }
};
