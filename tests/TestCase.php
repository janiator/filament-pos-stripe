<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;

abstract class TestCase extends BaseTestCase
{
    /**
     * Database names allowed for testing. Prevents running tests against production.
     */
    private static function isAllowedTestDatabase(string $connection, string $database): bool
    {
        if ($connection === 'sqlite') {
            return $database === ':memory:';
        }

        return str_contains($database, '_test') || str_ends_with($database, '_testing');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.env', 'testing');

        $dbConnection = config('database.default');
        $dbDatabase = config("database.connections.{$dbConnection}.database");
        $env = config('app.env');

        if ($env !== 'testing') {
            $this->markTestSkipped(
                "🚨 SAFETY CHECK FAILED: Running tests in '{$env}' environment. " .
                'Tests should only run in \'testing\' environment.'
            );
        }

        if (! self::isAllowedTestDatabase($dbConnection, (string) $dbDatabase)) {
            $this->markTestSkipped(
                "🚨 SAFETY CHECK FAILED: Test database must be ':memory:' (SQLite) or a name containing '_test'. " .
                "Got connection '{$dbConnection}' with database '{$dbDatabase}'. " .
                'Use DB_CONNECTION=pgsql and DB_DATABASE=pos_stripe_test (see phpunit.xml).'
            );
        }
    }

    /**
     * Hook called by RefreshDatabase trait before refreshing database.
     */
    protected function beforeRefreshingDatabase()
    {
        $dbConnection = config('database.default');
        $dbDatabase = config("database.connections.{$dbConnection}.database");

        if (! self::isAllowedTestDatabase($dbConnection, (string) $dbDatabase)) {
            throw new \RuntimeException(
                "🚨 CRITICAL ERROR: Attempted to refresh database '{$dbDatabase}' which is not an allowed test database. " .
                'Aborting to protect production data.'
            );
        }
    }
}
