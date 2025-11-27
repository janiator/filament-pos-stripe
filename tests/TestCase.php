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

        // Safety check: Ensure we're using a test database
        $dbConnection = config('database.default');
        $dbDatabase = config("database.connections.{$dbConnection}.database");
        $env = config('app.env');

        // If we're in testing but not using a safe database, warn and fail
        if ($env === 'testing' && $dbDatabase !== ':memory:' && !str_contains($dbDatabase, 'test')) {
            $this->markTestSkipped(
                "SAFETY CHECK FAILED: Tests are configured to use database '{$dbDatabase}' " .
                "instead of ':memory:' or a test database. This could delete your production data!"
            );
        }

        // Additional safety: If not in testing environment, warn
        if ($env !== 'testing' && $dbDatabase !== ':memory:') {
            $this->markTestSkipped(
                "SAFETY CHECK FAILED: Running tests in '{$env}' environment with database '{$dbDatabase}'. " .
                "Tests should only run in 'testing' environment with ':memory:' database."
            );
        }
    }
}
