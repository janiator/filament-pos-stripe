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
        Schema::table('connected_charges', function (Blueprint $table) {
            $table->string('transaction_code', 10)->nullable()->after('charge_type'); // PredefinedBasicID-11
            $table->string('payment_code', 10)->nullable()->after('transaction_code'); // PredefinedBasicID-12
            $table->integer('tip_amount')->default(0)->after('payment_code'); // Tips in cents
            $table->string('article_group_code', 10)->nullable()->after('tip_amount'); // From product or overridden
            $table->index('transaction_code');
            $table->index('payment_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('connected_charges', function (Blueprint $table) {
            $table->dropIndex(['transaction_code']);
            $table->dropIndex(['payment_code']);
            $table->dropColumn(['transaction_code', 'payment_code', 'tip_amount', 'article_group_code']);
        });
    }
};
