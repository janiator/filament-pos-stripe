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
        Schema::create('pos_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('pos_device_id')->nullable()->constrained('pos_devices')->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // Cashier/user who opened session
            $table->string('session_number')->unique(); // Sequential session number per store
            $table->enum('status', ['open', 'closed', 'abandoned'])->default('open');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->integer('opening_balance')->default(0); // Opening cash balance in cents
            $table->integer('expected_cash')->default(0); // Expected cash from transactions in cents
            $table->integer('actual_cash')->nullable(); // Actual cash counted at closing in cents
            $table->integer('cash_difference')->nullable(); // Difference between expected and actual
            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();
            $table->json('opening_data')->nullable(); // Additional opening data (device info, etc.)
            $table->json('closing_data')->nullable(); // Additional closing data
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'opened_at']);
            $table->index(['pos_device_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_sessions');
    }
};
