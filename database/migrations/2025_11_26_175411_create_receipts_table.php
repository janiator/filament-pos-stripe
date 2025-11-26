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
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('pos_session_id')->nullable()->constrained('pos_sessions')->onDelete('set null');
            $table->foreignId('charge_id')->nullable()->constrained('connected_charges')->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // Cashier
            $table->string('receipt_number')->unique(); // Sequential per store
            $table->enum('receipt_type', [
                'sales',
                'return',
                'copy',
                'steb',
                'provisional',
                'training',
                'delivery'
            ]);
            $table->foreignId('original_receipt_id')->nullable()->constrained('receipts')->onDelete('set null'); // For returns/copies
            $table->json('receipt_data'); // All receipt content
            $table->boolean('printed')->default(false);
            $table->timestamp('printed_at')->nullable();
            $table->integer('reprint_count')->default(0);
            $table->timestamps();

            $table->index(['store_id', 'receipt_number']);
            $table->index(['store_id', 'receipt_type']);
            $table->index(['pos_session_id', 'receipt_type']);
            $table->index('charge_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
