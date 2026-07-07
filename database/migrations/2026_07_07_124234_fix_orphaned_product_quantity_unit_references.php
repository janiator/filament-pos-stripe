<?php

use App\Models\QuantityUnit;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Ensure global quantity units exist and remap products that still reference
     * deleted per-store units or missing rows.
     */
    public function up(): void
    {
        QuantityUnit::remapLegacyProductReferences();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data repair migration; no rollback.
    }
};
