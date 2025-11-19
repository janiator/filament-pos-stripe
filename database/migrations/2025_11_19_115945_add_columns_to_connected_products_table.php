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
            if (!Schema::hasColumn('connected_products', 'stripe_product_id')) {
                $table->string('stripe_product_id')->unique()->after('id');
            }
            if (!Schema::hasColumn('connected_products', 'stripe_account_id')) {
                $table->string('stripe_account_id')->index()->after('stripe_product_id');
            }
            if (!Schema::hasColumn('connected_products', 'name')) {
                $table->string('name')->after('stripe_account_id');
            }
            if (!Schema::hasColumn('connected_products', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            if (!Schema::hasColumn('connected_products', 'active')) {
                $table->boolean('active')->default(true)->after('description');
            }
            if (!Schema::hasColumn('connected_products', 'images')) {
                $table->json('images')->nullable()->after('active');
            }
            if (!Schema::hasColumn('connected_products', 'metadata')) {
                $table->json('metadata')->nullable()->after('images');
            }
            if (!Schema::hasColumn('connected_products', 'type')) {
                $table->string('type')->default('service')->after('metadata');
            }
            if (!Schema::hasColumn('connected_products', 'url')) {
                $table->string('url')->nullable()->after('type');
            }
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
