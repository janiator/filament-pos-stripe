<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            // Required by Stripe (must be named "email")
            $table->string('email')->unique();

            // Platform fee configuration
            $table->enum('commission_type', ['percentage', 'fixed'])
                ->default('percentage');

            // If percentage: whole percentage (e.g., 5 = 5%).
            // If fixed: amount in minor units (e.g., 500 = 5.00).
            $table->integer('commission_rate')->default(0);

            // Stripe Connect account ID (acct_xxx)
            $table->string('stripe_account_id')
                ->nullable()
                ->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
