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
        Schema::create('pos_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('pos_device_id')->nullable()->constrained('pos_devices')->onDelete('set null');
            $table->foreignId('pos_session_id')->nullable()->constrained('pos_sessions')->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('related_charge_id')->nullable()->constrained('connected_charges')->onDelete('set null');
            $table->string('event_code', 10); // PredefinedBasicID-13 code (e.g., "13012")
            $table->enum('event_type', [
                'application',
                'user',
                'drawer',
                'report',
                'transaction',
                'payment',
                'session',
                'other'
            ]);
            $table->text('description')->nullable();
            $table->json('event_data')->nullable(); // Additional event-specific data
            $table->timestamp('occurred_at'); // When event actually occurred (may differ from created_at)
            $table->timestamps();

            // Indexes for performance
            $table->index(['store_id', 'occurred_at']);
            $table->index(['pos_session_id', 'event_code']);
            $table->index(['event_code', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
            $table->index('related_charge_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_events');
    }
};
