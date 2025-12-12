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
        Schema::create('product_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('product_name')->default('POS Stripe Backend - Kassasystem');
            $table->string('vendor_name')->nullable();
            $table->string('version')->default('1.0.0');
            $table->string('version_identification')->default('POS-STRIPE-BACKEND-1.0.0');
            $table->date('declaration_date')->nullable();
            $table->text('content')->nullable(); // Full product declaration content in markdown/HTML
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('store_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_declarations');
    }
};
