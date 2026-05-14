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
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropUnique(['store_id', 'session_number']);
            $table->dropIndex(['store_id', 'status']);
            $table->dropIndex(['store_id', 'opened_at']);
            $table->dropColumn('store_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->unique(['store_id', 'session_number']);
            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'opened_at']);
        });
    }
};
