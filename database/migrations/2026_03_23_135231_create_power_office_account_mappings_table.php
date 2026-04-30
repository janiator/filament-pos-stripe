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
        Schema::create('power_office_account_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('power_office_integration_id')->constrained()->cascadeOnDelete();
            $table->string('basis_type', 32)->index();
            $table->string('basis_key', 191);
            $table->string('basis_label')->nullable();
            $table->string('sales_account_no', 64);
            $table->string('vat_account_no', 64)->nullable();
            $table->string('fees_account_no', 64)->nullable();
            $table->string('tips_account_no', 64)->nullable();
            $table->string('cash_account_no', 64)->nullable();
            $table->string('card_clearing_account_no', 64)->nullable();
            $table->string('rounding_account_no', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['power_office_integration_id', 'basis_type', 'basis_key'], 'po_mapping_integration_basis_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('power_office_account_mappings');
    }
};
