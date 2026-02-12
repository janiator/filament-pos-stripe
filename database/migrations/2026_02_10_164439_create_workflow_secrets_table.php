<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_secrets', function (Blueprint $table) {
            $table->id();

            // Add tenant column if multi-tenancy is enabled
            if (config('filament-workflows.tenancy.enabled', false)) {
                $tenantColumn = config('filament-workflows.tenancy.column', 'tenant_id');
                $table->foreignId($tenantColumn)->index()->constrained()->onDelete('cascade');
            }

            $table->string('name');
            $table->text('encrypted_value'); // Encrypted secret value
            $table->string('type')->default('api_key'); // api_key, bearer_token, url, custom
            $table->string('description')->nullable();
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            // Unique constraint: one secret name per tenant (or globally if no tenancy)
            if (config('filament-workflows.tenancy.enabled', false)) {
                $tenantColumn = config('filament-workflows.tenancy.column', 'tenant_id');
                $table->unique([$tenantColumn, 'name'], 'workflow_secrets_tenant_name_unique');
                $table->index([$tenantColumn, 'type'], 'workflow_secrets_tenant_type');
            } else {
                $table->unique('name');
                $table->index('type');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_secrets');
    }
};
