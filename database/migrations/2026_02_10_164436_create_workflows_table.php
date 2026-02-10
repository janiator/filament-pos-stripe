<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();

            // Add tenant column if multi-tenancy is enabled
            if (config('filament-workflows.tenancy.enabled', false)) {
                $tenantColumn = config('filament-workflows.tenancy.column', 'tenant_id');
                $table->foreignId($tenantColumn)->index()->constrained()->onDelete('cascade');
            }

            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);

            // Trigger configuration
            $table->string('trigger_type'); // model_event, schedule, manual, date_condition, event
            $table->string('trigger_model_type')->nullable(); // For model_event triggers
            $table->string('trigger_event')->nullable(); // created, updated, deleted, status_changed
            $table->json('trigger_conditions')->nullable(); // Conditions for when trigger should fire
            $table->string('trigger_schedule')->nullable(); // Cron expression for schedule triggers

            // Workflow definition (visual designer JSON)
            $table->json('definition')->nullable();

            // Execution settings
            $table->unsignedInteger('max_retries')->default(3);
            $table->string('failure_strategy')->default('stop'); // stop, continue

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for efficient queries
            if (config('filament-workflows.tenancy.enabled', false)) {
                $tenantColumn = config('filament-workflows.tenancy.column', 'tenant_id');
                $table->index([$tenantColumn, 'is_active', 'trigger_type'], 'workflows_tenant_active_trigger');
            } else {
                $table->index(['is_active', 'trigger_type'], 'workflows_active_trigger');
            }
            $table->index(['trigger_model_type', 'trigger_event'], 'workflows_model_event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};
