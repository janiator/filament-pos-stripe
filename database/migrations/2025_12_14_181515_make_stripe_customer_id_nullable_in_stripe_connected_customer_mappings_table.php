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
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // PostgreSQL: Use raw SQL to alter the column to nullable
            // Laravel's change() method doesn't handle nullable changes well for existing columns
            DB::statement('ALTER TABLE stripe_connected_customer_mappings ALTER COLUMN stripe_customer_id DROP NOT NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite doesn't support ALTER COLUMN, so we need to recreate the table
            // However, this is complex and risky. For now, we'll just use change() which might work
            // If it doesn't work, a manual migration might be needed
            Schema::table('stripe_connected_customer_mappings', function (Blueprint $table) {
                $table->string('stripe_customer_id')->nullable()->change();
            });
        } else {
            // MySQL and others
            Schema::table('stripe_connected_customer_mappings', function (Blueprint $table) {
                $table->string('stripe_customer_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // First, set any NULL values to a placeholder (since we're making it NOT NULL again)
            // In practice, you might want to handle this differently or prevent rollback if NULLs exist
            DB::statement("UPDATE stripe_connected_customer_mappings SET stripe_customer_id = 'temp_' || id::text WHERE stripe_customer_id IS NULL");

            DB::statement('ALTER TABLE stripe_connected_customer_mappings ALTER COLUMN stripe_customer_id SET NOT NULL');
        } elseif ($driver === 'sqlite') {
            Schema::table('stripe_connected_customer_mappings', function (Blueprint $table) {
                $table->string('stripe_customer_id')->nullable(false)->change();
            });
        } else {
            Schema::table('stripe_connected_customer_mappings', function (Blueprint $table) {
                $table->string('stripe_customer_id')->nullable(false)->change();
            });
        }
    }
};
