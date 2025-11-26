<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('connected_charges', function (Blueprint $table) {
            $table->foreignId('pos_session_id')->nullable()->after('stripe_account_id')
                ->constrained('pos_sessions')->onDelete('set null');
            $table->index('pos_session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('connected_charges', function (Blueprint $table) {
            $table->dropForeign(['pos_session_id']);
            $table->dropColumn('pos_session_id');
        });
    }
};
