<?php

use App\Models\QuantityUnit;
use Database\Seeders\QuantityUnitSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Keeps only the global default quantity units (store_id null). Ensures global
     * units exist, reassigns any connected_products that used a per-store unit to
     * the global unit with the same name/symbol, then deletes all per-store quantity units.
     */
    public function up(): void
    {
        (new QuantityUnitSeeder)->run();

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // Reassign products from per-store units to global (same name/symbol)
            DB::statement('
                UPDATE connected_products cp
                SET quantity_unit_id = g.id
                FROM quantity_units store_unit
                JOIN quantity_units g ON g.name = store_unit.name
                    AND g.store_id IS NULL
                    AND g.stripe_account_id IS NULL
                    AND (g.symbol IS NOT DISTINCT FROM store_unit.symbol)
                WHERE cp.quantity_unit_id = store_unit.id
                  AND store_unit.store_id IS NOT NULL
            ');
        } else {
            $perStoreIds = QuantityUnit::whereNotNull('store_id')->pluck('id');
            $globals = QuantityUnit::whereNull('store_id')->whereNull('stripe_account_id')->get();

            foreach (QuantityUnit::whereNotNull('store_id')->get() as $storeUnit) {
                $global = $globals->first(fn ($g) => $g->name === $storeUnit->name && $g->symbol === $storeUnit->symbol);
                if ($global) {
                    DB::table('connected_products')
                        ->where('quantity_unit_id', $storeUnit->id)
                        ->update(['quantity_unit_id' => $global->id]);
                }
            }
        }

        QuantityUnit::whereNotNull('store_id')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot restore deleted per-store units; no-op.
    }
};
