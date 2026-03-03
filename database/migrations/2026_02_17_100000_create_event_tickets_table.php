<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webflow_item_id')->nullable()->constrained('webflow_items')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->dateTime('event_date')->nullable();
            $table->string('event_time')->nullable();
            $table->string('venue')->nullable();
            $table->string('ticket_1_label')->default('Billett 1');
            $table->unsignedInteger('ticket_1_available')->nullable();
            $table->unsignedInteger('ticket_1_sold')->default(0);
            $table->string('ticket_1_payment_link_id')->nullable();
            $table->string('ticket_1_price_id')->nullable();
            $table->string('ticket_2_label')->nullable();
            $table->unsignedInteger('ticket_2_available')->nullable();
            $table->unsignedInteger('ticket_2_sold')->default(0);
            $table->string('ticket_2_payment_link_id')->nullable();
            $table->string('ticket_2_price_id')->nullable();
            $table->boolean('is_sold_out')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index(['store_id', 'event_date']);
            $table->index('ticket_1_payment_link_id');
            $table->index('ticket_2_payment_link_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_tickets');
    }
};
