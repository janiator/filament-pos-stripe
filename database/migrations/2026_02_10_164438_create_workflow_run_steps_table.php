<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_run_steps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workflow_run_id')->constrained('workflow_runs')->cascadeOnDelete();

            // Step identification from visual designer
            $table->string('step_id'); // Unique ID from the visual designer definition
            $table->string('step_type'); // task, switch (condition), container
            $table->string('action_type')->nullable(); // e.g., create_task, send_email, http_request

            // Execution tracking
            $table->string('status'); // pending, running, completed, failed, skipped
            $table->unsignedInteger('attempt_number')->default(1);

            // Input/output data
            $table->json('input_data')->nullable(); // Resolved configuration passed to action
            $table->json('output_data')->nullable(); // Data returned by action

            // Error tracking
            $table->text('error_message')->nullable();

            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->timestamps();

            // Indexes for efficient queries
            $table->index(['workflow_run_id', 'step_id'], 'workflow_run_steps_run_step');
            $table->index(['workflow_run_id', 'status'], 'workflow_run_steps_run_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_run_steps');
    }
};
