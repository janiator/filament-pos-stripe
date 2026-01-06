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
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained('stores')->onDelete('cascade');
            $table->string('stripe_account_id')->nullable()->index();
            $table->string('event_type')->index();
            $table->string('event_id')->index();
            $table->string('account_id')->nullable()->index();
            $table->boolean('processed')->default(false);
            $table->text('message')->nullable();
            $table->json('warnings')->nullable();
            $table->json('errors')->nullable();
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->integer('http_status_code')->default(200);
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->index(['store_id', 'created_at']);
            $table->index(['stripe_account_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
