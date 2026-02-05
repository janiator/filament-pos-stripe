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
        // Convert any existing abandoned sessions to closed
        DB::table('pos_sessions')
            ->where('status', 'abandoned')
            ->update([
                'status' => 'closed',
                'closed_at' => DB::raw('COALESCE(closed_at, updated_at)'),
            ]);

        // Change the enum type - PostgreSQL requires dropping and recreating; SQLite has no named check constraint
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE pos_sessions DROP CONSTRAINT IF EXISTS pos_sessions_status_check');
            DB::statement("ALTER TABLE pos_sessions ADD CONSTRAINT pos_sessions_status_check CHECK (status IN ('open', 'closed'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert enum back to include abandoned (PostgreSQL only; SQLite has no named check constraint)
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE pos_sessions DROP CONSTRAINT IF EXISTS pos_sessions_status_check');
            DB::statement("ALTER TABLE pos_sessions ADD CONSTRAINT pos_sessions_status_check CHECK (status IN ('open', 'closed', 'abandoned'))");
        }
    }
};
