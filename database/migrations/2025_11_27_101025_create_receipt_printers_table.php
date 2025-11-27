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
        Schema::create('receipt_printers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('pos_device_id')->nullable()->constrained('pos_devices')->onDelete('set null');
            
            $table->string('name'); // Display name for the printer
            $table->string('printer_type')->default('epson'); // epson, star, etc.
            $table->string('printer_model')->nullable(); // TM-m30III, TM-T88, etc.
            $table->string('paper_width')->default('80'); // 80mm, 58mm
            
            // Connection details
            $table->string('connection_type')->default('network'); // network, usb, bluetooth
            $table->string('ip_address')->nullable(); // For network printers
            $table->integer('port')->default(9100); // Default ePOS-Print port
            $table->string('device_id')->default('local_printer'); // ePOS device ID
            $table->boolean('use_https')->default(false);
            $table->integer('timeout')->default(60000); // milliseconds
            
            // Status and configuration
            $table->boolean('is_active')->default(true);
            $table->boolean('monitor_status')->default(false);
            $table->string('drawer_open_level')->default('low'); // low, high
            $table->boolean('use_job_id')->default(false);
            
            // Metadata for additional configuration
            $table->json('printer_metadata')->nullable();
            
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            
            $table->index(['store_id', 'is_active']);
            $table->index('printer_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_printers');
    }
};
