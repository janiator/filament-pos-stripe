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
        Schema::create('pos_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            
            // Device identifier - unique ID for the POS device (from device_info_plus)
            // iOS: identifierForVendor, Android: androidId (or combination)
            $table->string('device_identifier')->unique();
            
            // Device name - human-readable name (iOS: name, Android: device)
            $table->string('device_name');
            
            // Platform information
            $table->string('platform'); // 'ios' or 'android'
            $table->string('device_model')->nullable(); // iOS: model, Android: model
            $table->string('device_brand')->nullable(); // Android: brand, iOS: null
            $table->string('device_manufacturer')->nullable(); // Android: manufacturer
            $table->string('device_product')->nullable(); // Android: product
            $table->string('device_hardware')->nullable(); // Android: hardware
            $table->string('machine_identifier')->nullable(); // iOS: utsname.machine (e.g., "iPad13,1")
            
            // System information
            $table->string('system_name')->nullable(); // iOS: systemName, Android: null
            $table->string('system_version')->nullable(); // iOS: systemVersion, Android: version.release
            
            // Identifiers
            $table->string('vendor_identifier')->nullable(); // iOS: identifierForVendor
            $table->string('android_id')->nullable(); // Android: androidId
            $table->string('serial_number')->nullable(); // Serial number if available
            
            // Device status and metadata
            $table->string('device_status')->default('active'); // active, inactive, maintenance, offline
            $table->timestamp('last_seen_at')->nullable();
            $table->json('device_metadata')->nullable(); // Additional device info (battery, storage, etc.)
            
            $table->timestamps();
            
            $table->index(['store_id', 'device_status']);
            $table->index('last_seen_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_devices');
    }
};
