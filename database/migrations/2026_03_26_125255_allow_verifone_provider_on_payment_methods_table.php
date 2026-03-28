<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE payment_methods DROP CONSTRAINT IF EXISTS payment_methods_provider_check');
        DB::statement("ALTER TABLE payment_methods ADD CONSTRAINT payment_methods_provider_check CHECK (((provider)::text = ANY ((ARRAY['stripe'::character varying, 'cash'::character varying, 'other'::character varying, 'verifone'::character varying])::text[])))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE payment_methods DROP CONSTRAINT IF EXISTS payment_methods_provider_check');
        DB::statement("ALTER TABLE payment_methods ADD CONSTRAINT payment_methods_provider_check CHECK (((provider)::text = ANY ((ARRAY['stripe'::character varying, 'cash'::character varying, 'other'::character varying])::text[])))");
    }
};
