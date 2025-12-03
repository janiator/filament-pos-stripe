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
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->integer('transaction_count')->default(0)->after('expected_cash');
            $table->integer('total_amount')->default(0)->after('transaction_count'); // Total amount in øre
        });

        // Populate existing sessions with calculated values
        DB::statement('
            UPDATE pos_sessions 
            SET 
                transaction_count = (
                    SELECT COUNT(*) 
                    FROM connected_charges 
                    WHERE connected_charges.pos_session_id = pos_sessions.id 
                    AND connected_charges.status = \'succeeded\'
                ),
                total_amount = (
                    SELECT COALESCE(SUM(amount), 0) 
                    FROM connected_charges 
                    WHERE connected_charges.pos_session_id = pos_sessions.id 
                    AND connected_charges.status = \'succeeded\'
                )
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->dropColumn(['transaction_count', 'total_amount']);
        });
    }
};
