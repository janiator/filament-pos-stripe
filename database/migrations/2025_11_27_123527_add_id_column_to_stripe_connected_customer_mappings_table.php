<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if id column already exists
        if (Schema::hasColumn('stripe_connected_customer_mappings', 'id')) {
            return;
        }

        // Check if table exists
        if (!Schema::hasTable('stripe_connected_customer_mappings')) {
            // Table doesn't exist yet, so the original migration will create it
            // We'll modify the original migration instead, but for now just return
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite doesn't support adding primary key columns to existing tables
            // We need to recreate the table. Get all existing columns
            $columns = Schema::getColumnListing('stripe_connected_customer_mappings');
            $hasData = DB::table('stripe_connected_customer_mappings')->exists();
            
            // Rename old table
            DB::statement('ALTER TABLE stripe_connected_customer_mappings RENAME TO stripe_connected_customer_mappings_old');

            // Create new table with id column and all expected columns
            // (name, email, timestamps were added in later migrations)
            // Note: We use raw SQL to avoid index conflicts
            DB::statement('
                CREATE TABLE stripe_connected_customer_mappings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    model TEXT,
                    model_id INTEGER,
                    model_uuid TEXT,
                    stripe_customer_id TEXT NOT NULL,
                    stripe_account_id TEXT NOT NULL,
                    name TEXT,
                    email TEXT,
                    created_at DATETIME,
                    updated_at DATETIME
                )
            ');
            
            // Create indexes (IF NOT EXISTS for SQLite compatibility)
            try {
                DB::statement('CREATE INDEX IF NOT EXISTS stripe_connected_customer_mappings_model_id_index ON stripe_connected_customer_mappings (model_id)');
                DB::statement('CREATE INDEX IF NOT EXISTS stripe_connected_customer_mappings_model_uuid_index ON stripe_connected_customer_mappings (model_uuid)');
                DB::statement('CREATE INDEX IF NOT EXISTS stripe_connected_customer_mappings_stripe_customer_id_index ON stripe_connected_customer_mappings (stripe_customer_id)');
                DB::statement('CREATE INDEX IF NOT EXISTS stripe_connected_customer_mappings_stripe_account_id_index ON stripe_connected_customer_mappings (stripe_account_id)');
            } catch (\Exception $e) {
                // Indexes might already exist, ignore
            }

            // Copy data from old table if it has data
            if ($hasData) {
                $selectCols = implode(', ', array_filter($columns, fn($col) => $col !== 'id'));
                $insertCols = $selectCols; // Same columns for insert
                
                if (!empty($selectCols)) {
                    DB::statement("
                        INSERT INTO stripe_connected_customer_mappings ({$insertCols})
                        SELECT {$selectCols}
                        FROM stripe_connected_customer_mappings_old
                    ");
                }
            }

            // Drop old table
            Schema::dropIfExists('stripe_connected_customer_mappings_old');
        } else {
            // For PostgreSQL and MySQL, we can add the column directly
        Schema::table('stripe_connected_customer_mappings', function (Blueprint $table) {
                $table->id()->first();
        });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('stripe_connected_customer_mappings', 'id')) {
            return;
        }

        // For all databases, dropping the id column is straightforward
        Schema::table('stripe_connected_customer_mappings', function (Blueprint $table) {
            $table->dropColumn('id');
        });
    }
};
