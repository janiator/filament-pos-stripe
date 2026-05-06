<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * POS cart lines and refunds support fractional quantities (e.g. 1.5 kg).
     * Stock movements and on-hand inventory must use the same precision.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('alter table product_variants alter column inventory_quantity type decimal(12, 4) using (case when inventory_quantity is null then null else inventory_quantity::numeric end)');
            DB::statement('alter table inventory_stock_movements alter column quantity_delta type decimal(12, 4) using quantity_delta::numeric');

            return;
        }

        if ($driver === 'mysql') {
            DB::statement('alter table product_variants modify inventory_quantity decimal(12, 4) null');
            DB::statement('alter table inventory_stock_movements modify quantity_delta decimal(12, 4) not null');

            return;
        }

        // SQLite and other drivers: leave schema unchanged; fractional inventory is not supported locally.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('alter table product_variants alter column inventory_quantity type integer using (case when inventory_quantity is null then null else round(inventory_quantity)::integer end)');
            DB::statement('alter table inventory_stock_movements alter column quantity_delta type integer using round(quantity_delta)::integer');

            return;
        }

        if ($driver === 'mysql') {
            DB::statement('alter table product_variants modify inventory_quantity int null');
            DB::statement('alter table inventory_stock_movements modify quantity_delta int not null');
        }
    }
};
