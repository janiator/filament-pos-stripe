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
        Schema::create('collection_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained('collections')->onDelete('cascade');
            $table->foreignId('connected_product_id')->constrained('connected_products')->onDelete('cascade');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            // Ensure a product can only be in a collection once
            $table->unique(['collection_id', 'connected_product_id']);
            
            // Indexes for performance
            $table->index('collection_id');
            $table->index('connected_product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_product');
    }
};
