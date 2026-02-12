<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_metrics', function (Blueprint $table) {
            $table->id();

            // Add tenant column if multi-tenancy is enabled
            if (config('filament-workflows.tenancy.enabled', false)) {
                $tenantColumn = config('filament-workflows.tenancy.column', 'tenant_id');
                $table->foreignId($tenantColumn)->index()->constrained()->onDelete('cascade');
            }

            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();

            // Period definition
            $table->string('period_type'); // rolling_24h, rolling_7d, rolling_30d
            $table->timestamp('period_start')->nullable(); // null for rolling periods
            $table->timestamp('period_end')->nullable();

            // Execution counts
            $table->unsignedInteger('total_runs')->default(0);
            $table->unsignedInteger('completed_runs')->default(0);
            $table->unsignedInteger('failed_runs')->default(0);
            $table->unsignedInteger('cancelled_runs')->default(0);

            // Timing metrics (in milliseconds)
            $table->unsignedBigInteger('total_duration_ms')->default(0);
            $table->unsignedBigInteger('min_duration_ms')->nullable();
            $table->unsignedBigInteger('max_duration_ms')->nullable();

            // For P95 calculation - store sorted durations as JSON
            // Limited to last 100 samples for memory efficiency
            $table->json('duration_samples')->nullable();

            // Computed metrics (cached for quick access)
            $table->decimal('success_rate', 5, 2)->default(0); // 0.00-100.00
            $table->unsignedBigInteger('avg_duration_ms')->nullable();
            $table->unsignedBigInteger('p95_duration_ms')->nullable();

            // Metadata
            $table->timestamp('last_computed_at')->nullable();
            $table->timestamps();

            // Indexes for efficient queries
            $table->unique(['workflow_id', 'period_type', 'period_start'], 'wf_metrics_unique');
            $table->index(['workflow_id', 'period_type'], 'wf_metrics_workflow_period');
            $table->index('period_type', 'wf_metrics_period_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_metrics');
    }
};
