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
        Schema::create('payment_method_pos_device', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pos_device_id')->constrained()->cascadeOnDelete();
            $table->primary(['payment_method_id', 'pos_device_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_method_pos_device');
    }
};
