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
        Schema::create('connected_payment_links', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_payment_link_id')->unique();
            $table->string('stripe_account_id')->index();
            $table->string('stripe_price_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('url')->unique();
            $table->boolean('active')->default(true);
            $table->string('link_type')->default('direct'); // direct or destination
            $table->integer('application_fee_percent')->nullable(); // For destination links
            $table->integer('application_fee_amount')->nullable(); // For destination links
            $table->string('after_completion_redirect_url')->nullable();
            $table->json('line_items')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['stripe_account_id', 'active']);
            $table->index(['stripe_account_id', 'link_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connected_payment_links');
    }
};
