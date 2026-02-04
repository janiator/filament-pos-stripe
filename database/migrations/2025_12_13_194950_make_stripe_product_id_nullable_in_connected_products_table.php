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
        Schema::table('connected_products', function (Blueprint $table) {
            $table->dropUnique(['stripe_product_id']);
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE connected_products ALTER COLUMN stripe_product_id DROP NOT NULL');
        }
        Schema::table('connected_products', function (Blueprint $table) {
            $table->unique('stripe_product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("UPDATE connected_products SET stripe_product_id = 'temp_' || id::text WHERE stripe_product_id IS NULL");
        } elseif ($driver === 'sqlite') {
            DB::statement("UPDATE connected_products SET stripe_product_id = 'temp_' || id WHERE stripe_product_id IS NULL");
        } else {
            DB::table('connected_products')->whereNull('stripe_product_id')->update(['stripe_product_id' => DB::raw("CONCAT('temp_', id)")]);
        }

        Schema::table('connected_products', function (Blueprint $table) {
            $table->dropUnique(['stripe_product_id']);
        });

        if ($driver !== 'sqlite') {
            DB::statement('ALTER TABLE connected_products ALTER COLUMN stripe_product_id SET NOT NULL');
        }
        Schema::table('connected_products', function (Blueprint $table) {
            $table->unique('stripe_product_id');
        });
    }
};
