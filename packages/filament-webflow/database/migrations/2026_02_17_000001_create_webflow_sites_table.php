<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webflow_sites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->string('webflow_site_id')->unique();
            $table->text('api_token')->nullable();
            $table->string('name')->nullable();
            $table->string('domain')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webflow_sites');
    }
};
