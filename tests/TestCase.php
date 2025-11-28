<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // CRITICAL SAFETY: Force in-memory SQLite database for ALL tests
        // This prevents tests from accidentally using production database
        // even if .env file has production settings
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Force testing environment
        Config::set('app.env', 'testing');

        // Verify we're using in-memory database
        $dbConnection = config('database.default');
        $dbDatabase = config("database.connections.{$dbConnection}.database");
        $env = config('app.env');

        // CRITICAL: Fail immediately if not using in-memory database
        if ($dbDatabase !== ':memory:') {
            $this->markTestSkipped(
                "🚨 SAFETY CHECK FAILED: Tests must use ':memory:' database, but got '{$dbDatabase}'. " .
                "This could delete your production data! Aborting test."
            );
        }

        // Additional safety: Ensure we're in testing environment
        if ($env !== 'testing') {
            $this->markTestSkipped(
                "🚨 SAFETY CHECK FAILED: Running tests in '{$env}' environment. " .
                "Tests should only run in 'testing' environment."
            );
        }
    }

    /**
     * Hook called by RefreshDatabase trait before refreshing database
     * This ensures we never accidentally refresh a production database
     */
    protected function beforeRefreshingDatabase()
    {
        // Double-check we're using in-memory database before RefreshDatabase runs
        $dbConnection = config('database.default');
        $dbDatabase = config("database.connections.{$dbConnection}.database");
        
        if ($dbDatabase !== ':memory:') {
            throw new \RuntimeException(
                "🚨 CRITICAL ERROR: Attempted to refresh database '{$dbDatabase}' which is NOT in-memory! " .
                "This would delete your production data. Aborting test."
            );
        }
    }
}
