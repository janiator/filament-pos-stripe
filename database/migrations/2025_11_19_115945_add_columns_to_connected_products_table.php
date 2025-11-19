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
        Schema::table('connected_products', function (Blueprint $table) {
            $table->string('stripe_product_id')->unique()->after('id');
            $table->string('stripe_account_id')->index()->after('stripe_product_id');
            $table->string('name')->after('stripe_account_id');
            $table->text('description')->nullable()->after('name');
            $table->boolean('active')->default(true)->after('description');
            $table->json('images')->nullable()->after('active');
            $table->json('metadata')->nullable()->after('images');
            $table->string('type')->default('service')->after('metadata');
            $table->string('url')->nullable()->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('connected_products', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_product_id',
                'stripe_account_id',
                'name',
                'description',
                'active',
                'images',
                'metadata',
                'type',
                'url',
            ]);
        });
    }
};
