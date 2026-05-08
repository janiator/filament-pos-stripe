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
        Schema::table('stores', function (Blueprint $table) {
            $table->string('default_terminal_provider')
                ->default('stripe')
                ->after('default_terminal_location_id');
            $table->string('verifone_api_base_url')
                ->nullable()
                ->after('default_terminal_provider');
            $table->string('verifone_user_uid')
                ->nullable()
                ->after('verifone_api_base_url');
            $table->text('verifone_api_key')
                ->nullable()
                ->after('verifone_user_uid');
            $table->string('verifone_site_entity_id')
                ->nullable()
                ->after('verifone_api_key');
            $table->string('verifone_sale_id')
                ->nullable()
                ->after('verifone_site_entity_id');
            $table->string('verifone_operator_id')
                ->nullable()
                ->after('verifone_sale_id');
            $table->boolean('verifone_terminal_simulator')
                ->default(false)
                ->after('verifone_operator_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'default_terminal_provider',
                'verifone_api_base_url',
                'verifone_user_uid',
                'verifone_api_key',
                'verifone_site_entity_id',
                'verifone_sale_id',
                'verifone_operator_id',
                'verifone_terminal_simulator',
            ]);
        });
    }
};
