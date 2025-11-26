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
            if (!Schema::hasColumn('connected_products', 'package_dimensions')) {
                $table->json('package_dimensions')->nullable()->after('url');
            }
            if (!Schema::hasColumn('connected_products', 'shippable')) {
                $table->boolean('shippable')->nullable()->after('package_dimensions');
            }
            if (!Schema::hasColumn('connected_products', 'statement_descriptor')) {
                $table->string('statement_descriptor', 22)->nullable()->after('shippable');
            }
            if (!Schema::hasColumn('connected_products', 'tax_code')) {
                $table->string('tax_code')->nullable()->after('statement_descriptor');
            }
            if (!Schema::hasColumn('connected_products', 'unit_label')) {
                $table->string('unit_label')->nullable()->after('tax_code');
            }
            if (!Schema::hasColumn('connected_products', 'default_price')) {
                $table->string('default_price')->nullable()->after('unit_label');
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
                'package_dimensions',
                'shippable',
                'statement_descriptor',
                'tax_code',
                'unit_label',
                'default_price',
            ]);
        });
    }
};
