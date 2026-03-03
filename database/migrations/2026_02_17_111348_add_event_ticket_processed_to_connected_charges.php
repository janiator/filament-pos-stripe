<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connected_charges', function (Blueprint $table) {
            $table->boolean('event_ticket_processed')->default(false)->after('application_fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('connected_charges', function (Blueprint $table) {
            $table->dropColumn('event_ticket_processed');
        });
    }
};
