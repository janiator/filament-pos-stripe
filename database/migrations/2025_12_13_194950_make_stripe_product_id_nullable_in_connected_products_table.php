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
            // Drop the unique constraint first if it exists
            $table->dropUnique(['stripe_product_id']);
        });

        // Use raw SQL to alter the column to nullable
        // Laravel's change() method doesn't handle nullable changes well for existing columns
        DB::statement('ALTER TABLE connected_products ALTER COLUMN stripe_product_id DROP NOT NULL');

        // Re-add the unique constraint (nullable columns can still be unique)
        Schema::table('connected_products', function (Blueprint $table) {
            $table->unique('stripe_product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First, set any NULL values to a placeholder (since we're making it NOT NULL again)
        // In practice, you might want to handle this differently
        DB::statement("UPDATE connected_products SET stripe_product_id = 'temp_' || id::text WHERE stripe_product_id IS NULL");

        Schema::table('connected_products', function (Blueprint $table) {
            $table->dropUnique(['stripe_product_id']);
        });

        DB::statement('ALTER TABLE connected_products ALTER COLUMN stripe_product_id SET NOT NULL');

        Schema::table('connected_products', function (Blueprint $table) {
            $table->unique('stripe_product_id');
        });
    }
};
