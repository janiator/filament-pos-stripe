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
     * Phone numbers must not use a 32-bit integer column: values such as 9545559600 overflow
     * PostgreSQL integer and raise QueryException. Store as varchar instead.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('stripe_connected_customer_mappings', 'phone')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE stripe_connected_customer_mappings ALTER COLUMN phone TYPE VARCHAR(255) USING (phone::text)');

            return;
        }

        Schema::table('stripe_connected_customer_mappings', function (Blueprint $table) {
            $table->string('phone', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally omitted: reverting phone to integer would truncate or reject data.
    }
};
