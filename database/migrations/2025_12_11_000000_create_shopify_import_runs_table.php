<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_import_runs', function (Blueprint $table) {
            $table->id();

            // Optional: if you have Store model, use foreignId
            if (Schema::hasTable('stores')) {
                $table->foreignId('store_id')
                    ->nullable()
                    ->constrained()
                    ->cascadeOnDelete();
            } else {
                $table->unsignedBigInteger('store_id')->nullable()->index();
            }

            $table->string('stripe_account_id')->index();

            // pending, running, completed, completed-with-errors, failed
            $table->string('status')->default('pending');

            $table->unsignedInteger('total_products')->nullable();
            $table->unsignedInteger('imported')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->unsignedInteger('error_count')->default(0);

            $table->unsignedInteger('current_index')->default(0);
            $table->string('current_title')->nullable();
            $table->string('current_handle')->nullable();
            $table->string('current_category')->nullable();

            // Extra metadata: parse stats + final result, etc.
            $table->json('meta')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_import_runs');
    }
};
