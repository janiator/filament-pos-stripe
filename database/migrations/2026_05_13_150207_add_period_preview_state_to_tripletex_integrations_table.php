<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tripletex_integrations', function (Blueprint $table): void {
            $table->json('period_preview_state')->nullable()->after('settings');
        });
    }

    public function down(): void
    {
        Schema::table('tripletex_integrations', function (Blueprint $table): void {
            $table->dropColumn('period_preview_state');
        });
    }
};
