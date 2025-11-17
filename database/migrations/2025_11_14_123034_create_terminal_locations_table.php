<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminal_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            // Stripe Terminal location ID (loc_...)
            $table->string('stripe_location_id')->nullable()->unique();

            $table->string('display_name');

            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city');
            $table->string('state')->nullable();
            $table->string('postal_code');
            $table->string('country', 2); // ISO 2-letter code

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_locations');
    }
};
