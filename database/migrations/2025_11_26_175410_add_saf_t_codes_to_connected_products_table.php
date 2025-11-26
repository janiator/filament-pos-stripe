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
            $table->string('article_group_code', 10)->nullable()->after('tax_code'); // PredefinedBasicID-04
            $table->string('product_code', 50)->nullable()->after('article_group_code'); // PLU code (BasicType-02)
            $table->index('article_group_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('connected_products', function (Blueprint $table) {
            $table->dropIndex(['article_group_code']);
            $table->dropColumn(['article_group_code', 'product_code']);
        });
    }
};
