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
        Schema::create('pos_session_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->date('closing_date'); // Date of the closing report
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('closed_at');
            $table->integer('total_sessions')->default(0); // Number of sessions closed
            $table->integer('total_transactions')->default(0);
            $table->integer('total_amount')->default(0); // Total amount in cents
            $table->integer('total_cash')->default(0); // Total cash in cents
            $table->integer('total_card')->default(0); // Total card payments in cents
            $table->integer('total_refunds')->default(0); // Total refunds in cents
            $table->string('currency', 3)->default('nok');
            $table->json('summary_data')->nullable(); // Detailed summary (by payment method, etc.)
            $table->text('notes')->nullable();
            $table->boolean('verified')->default(false); // Whether closing has been verified
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'closing_date']); // One closing per store per day
            $table->index(['store_id', 'closing_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_session_closings');
    }
};
