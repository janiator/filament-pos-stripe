<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminal_readers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('terminal_location_id')
                ->nullable()
                ->constrained('terminal_locations')
                ->nullOnDelete();

            // Stripe reader ID (tmr_...)
            $table->string('stripe_reader_id')->nullable()->unique();

            $table->string('label');
            $table->boolean('tap_to_pay')->default(false);
            $table->string('device_type')->nullable();
            $table->string('status')->nullable(); // optional status from Stripe

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminal_readers');
    }
};
