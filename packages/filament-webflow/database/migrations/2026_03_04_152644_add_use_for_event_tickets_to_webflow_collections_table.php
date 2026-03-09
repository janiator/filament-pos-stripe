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
        Schema::table('webflow_collections', function (Blueprint $table) {
            $table->boolean('use_for_event_tickets')->default(false)->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webflow_collections', function (Blueprint $table) {
            $table->dropColumn('use_for_event_tickets');
        });
    }
};
