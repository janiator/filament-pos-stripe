<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class SafeMigrateFresh extends Command
{
    protected $signature = 'migrate:fresh-safe 
                            {--force : Force the operation to run when in production}
                            {--seed : Seed the database after migrating}';

    protected $description = 'Safely run migrate:fresh with environment checks';

    public function handle(): int
    {
        $env = config('app.env');
        $dbConnection = config('database.default');
        $dbDatabase = config("database.connections.{$dbConnection}.database");

        // Check if we're in a safe environment
        $isTesting = $env === 'testing';
        $isMemoryDb = $dbDatabase === ':memory:';
        $isSqlite = $dbConnection === 'sqlite' && $dbDatabase !== ':memory:' && str_contains($dbDatabase, 'test');

        // If not in a safe environment, require confirmation
        if (!$isTesting && !$isMemoryDb && !$isSqlite) {
            $this->error('⚠️  WARNING: This will DROP ALL TABLES in your database!');
            $this->warn("Environment: {$env}");
            $this->warn("Database: {$dbDatabase}");
            $this->warn("Connection: {$dbConnection}");

            if (!$this->option('force')) {
                if (!$this->confirm('Are you absolutely sure you want to continue?', false)) {
                    $this->info('Operation cancelled.');
                    return Command::FAILURE;
                }

                // Double confirmation for production-like databases
                if ($env === 'production' || $env === 'local') {
                    $this->error('⚠️  DOUBLE WARNING: You are about to delete your PRODUCTION/LOCAL database!');
                    if (!$this->confirm('Type "yes" to confirm deletion', false)) {
                        $this->info('Operation cancelled.');
                        return Command::FAILURE;
                    }
                }
            }
        }

        // If we're in testing with memory database, it's safe
        if ($isTesting || $isMemoryDb) {
            $this->info('✓ Safe environment detected (testing with in-memory database)');
        }

        // Run the actual migrate:fresh
        $this->call('migrate:fresh', [
            '--seed' => $this->option('seed'),
            '--force' => $this->option('force'),
        ]);

        return Command::SUCCESS;
    }
}

