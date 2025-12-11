<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Convert any existing abandoned sessions to closed
        DB::table('pos_sessions')
            ->where('status', 'abandoned')
            ->update([
                'status' => 'closed',
                'closed_at' => DB::raw('COALESCE(closed_at, updated_at)'),
            ]);

        $driver = DB::getDriverName();

        // PostgreSQL supports named constraint alterations.
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE pos_sessions DROP CONSTRAINT IF EXISTS pos_sessions_status_check");
            DB::statement("ALTER TABLE pos_sessions ADD CONSTRAINT pos_sessions_status_check CHECK (status IN ('open', 'closed'))");
            return;
        }

        // MySQL 8+ supports CHECK but historically ignored them.
        // If you used a named CHECK constraint already, you might manage it here.
        // Otherwise, no-op.
        if ($driver === 'mysql') {
            // Optional: attempt to drop/add if your schema actually has it and you rely on it.
            // Keeping this as a no-op avoids cross-version pain.
            return;
        }

        // SQLite cannot drop/add named CHECK constraints via ALTER TABLE.
        // If you *really* need enforcement in SQLite, you'd have to rebuild the table.
        // For dev/test, it's safe to skip.
        if ($driver === 'sqlite') {
            return;
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE pos_sessions DROP CONSTRAINT IF EXISTS pos_sessions_status_check");
            DB::statement("ALTER TABLE pos_sessions ADD CONSTRAINT pos_sessions_status_check CHECK (status IN ('open', 'closed', 'abandoned'))");
            return;
        }

        if ($driver === 'mysql') {
            return;
        }

        if ($driver === 'sqlite') {
            return;
        }
    }
};
