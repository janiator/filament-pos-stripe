<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add store_id (tenant column) to workflow tables for Filament Store tenancy.
     * Original migrations ran with tenancy disabled; this adds the column so
     * workflows are scoped per store.
     */
    public function up(): void
    {
        $tables = [
            'workflows' => 'workflows_store_id_foreign',
            'workflow_runs' => 'workflow_runs_store_id_foreign',
            'workflow_secrets' => 'workflow_secrets_store_id_foreign',
            'workflow_metrics' => 'workflow_metrics_store_id_foreign',
        ];

        foreach ($tables as $tableName => $fkName) {
            if (! Schema::hasColumn($tableName, 'store_id')) {
                Schema::table($tableName, function (Blueprint $blueprint) {
                    $blueprint->foreignId('store_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('stores')
                        ->onDelete('cascade');
                    $blueprint->index('store_id');
                });
            }
        }

        // Composite indexes for tenant-scoped queries (matches plugin's tenant indexes).
        // Only create if they don't exist (e.g. create_workflows_table may have already created
        // workflows_tenant_active_trigger when tenancy was enabled with a different column name).
        if (Schema::hasTable('workflows') && Schema::hasColumn('workflows', 'store_id') && ! $this->indexExists('workflows', 'workflows_tenant_active_trigger')) {
            Schema::table('workflows', function (Blueprint $blueprint) {
                $blueprint->index(['store_id', 'is_active', 'trigger_type'], 'workflows_tenant_active_trigger');
            });
        }
        if (Schema::hasTable('workflow_runs') && Schema::hasColumn('workflow_runs', 'store_id') && ! $this->indexExists('workflow_runs', 'workflow_runs_tenant_status')) {
            Schema::table('workflow_runs', function (Blueprint $blueprint) {
                $blueprint->index(['store_id', 'status'], 'workflow_runs_tenant_status');
            });
        }
    }

    /**
     * Check if an index exists on the given table (driver-agnostic).
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $result = DB::selectOne(
                'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                [$table, $indexName]
            );

            return $result !== null;
        }

        if ($driver === 'mysql') {
            $result = DB::selectOne(
                'SHOW INDEX FROM '.$table.' WHERE Key_name = ?',
                [$indexName]
            );

            return $result !== null;
        }

        return false;
    }

    public function down(): void
    {
        if (Schema::hasTable('workflows') && $this->indexExists('workflows', 'workflows_tenant_active_trigger')) {
            Schema::table('workflows', function (Blueprint $blueprint) {
                $blueprint->dropIndex('workflows_tenant_active_trigger');
            });
        }
        if (Schema::hasTable('workflow_runs') && $this->indexExists('workflow_runs', 'workflow_runs_tenant_status')) {
            Schema::table('workflow_runs', function (Blueprint $blueprint) {
                $blueprint->dropIndex('workflow_runs_tenant_status');
            });
        }

        $tables = ['workflows', 'workflow_runs', 'workflow_secrets', 'workflow_metrics'];
        foreach ($tables as $tableName) {
            if (Schema::hasColumn($tableName, 'store_id')) {
                Schema::table($tableName, function (Blueprint $blueprint) {
                    $blueprint->dropForeign(['store_id']);
                    $blueprint->dropIndex(['store_id']);
                });
            }
        }
    }
};
