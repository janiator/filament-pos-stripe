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
        // Drop the unique constraint first
        Schema::table('connected_charges', function (Blueprint $table) {
            // Remove the unique constraint on stripe_charge_id
            $table->dropUnique(['stripe_charge_id']);
        });

        // Make stripe_charge_id nullable
        Schema::table('connected_charges', function (Blueprint $table) {
            $table->string('stripe_charge_id')->nullable()->change();
        });

        // Add a partial unique index that only applies when stripe_charge_id is not null
        // This ensures Stripe charges remain unique while allowing nulls for cash payments
        DB::statement('CREATE UNIQUE INDEX connected_charges_stripe_charge_id_unique ON connected_charges (stripe_charge_id) WHERE stripe_charge_id IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the partial unique index
        DB::statement('DROP INDEX IF EXISTS connected_charges_stripe_charge_id_unique');

        // Make stripe_charge_id NOT NULL again
        Schema::table('connected_charges', function (Blueprint $table) {
            $table->string('stripe_charge_id')->nullable(false)->change();
        });

        // Re-add the unique constraint
        Schema::table('connected_charges', function (Blueprint $table) {
            $table->unique('stripe_charge_id');
        });
    }
};
