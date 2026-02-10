<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();

            // Add tenant column if multi-tenancy is enabled
            if (config('filament-workflows.tenancy.enabled', false)) {
                $tenantColumn = config('filament-workflows.tenancy.column', 'tenant_id');
                $table->foreignId($tenantColumn)->index()->constrained()->onDelete('cascade');
            }

            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();

            // Polymorphic relation to the model that triggered the run
            $table->nullableMorphs('triggerable');

            // Execution tracking
            $table->string('trigger_source'); // model_event, schedule, manual, date_condition, event
            $table->string('status')->default('pending'); // pending, running, paused, completed, failed, cancelled
            $table->unsignedInteger('current_step_index')->default(0);

            // Context data accumulated from step outputs
            $table->json('context_data')->nullable();

            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('scheduled_resume_at')->nullable(); // For delayed/paused workflows

            // Retry tracking
            $table->unsignedInteger('retry_count')->default(0);
            $table->text('error_message')->nullable();

            // Who triggered the run (for manual triggers)
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Indexes for efficient queries
            if (config('filament-workflows.tenancy.enabled', false)) {
                $tenantColumn = config('filament-workflows.tenancy.column', 'tenant_id');
                $table->index([$tenantColumn, 'status'], 'workflow_runs_tenant_status');
            }
            $table->index(['workflow_id', 'status'], 'workflow_runs_workflow_status');
            $table->index(['status', 'scheduled_resume_at'], 'workflow_runs_status_resume');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_runs');
    }
};
