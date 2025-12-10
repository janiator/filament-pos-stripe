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
            $table->boolean('no_price_in_pos')->default(false)->after('price');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->boolean('no_price_in_pos')->default(false)->after('price_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('connected_products', function (Blueprint $table) {
            $table->dropColumn('no_price_in_pos');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('no_price_in_pos');
        });
    }
};
