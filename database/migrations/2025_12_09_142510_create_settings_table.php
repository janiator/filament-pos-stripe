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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade')->unique();
            
            // Receipt settings
            $table->boolean('auto_print_receipts')->default(false);
            $table->foreignId('default_receipt_template_id')->nullable()->constrained('receipt_templates')->onDelete('set null');
            $table->string('receipt_printer_type')->default('epson');
            $table->string('receipt_number_format')->default('{store_id}-{type}-{number:06d}');
            $table->decimal('default_vat_rate', 5, 2)->default(25.00);
            
            // Cash drawer settings
            $table->boolean('cash_drawer_auto_open')->default(true);
            $table->integer('cash_drawer_open_duration_ms')->default(250);
            
            // General POS settings
            $table->string('currency', 3)->default('nok');
            $table->string('timezone')->default('Europe/Oslo');
            $table->string('locale', 10)->default('nb');
            $table->boolean('tax_included')->default(false);
            
            // Additional settings stored as JSON
            $table->json('additional_settings')->nullable();
            
            $table->timestamps();
            
            $table->index('store_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
