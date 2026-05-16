<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Container\Container;
use Illuminate\Database\ConfigurationUrlParser;
use Illuminate\Foundation\Application;
use Illuminate\Support\Env;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class DatabaseUsesDatabaseUrlFallbackTest extends TestCase
{
    /**
     * Load the real Laravel `config/database.php` with a functioning `database_path()`
     * helper (Composer autoload wires Foundation helpers to the container).
     */
    private function bootstrapConfigLoading(): void
    {
        require_once dirname(__DIR__, 2).'/vendor/autoload.php';

        if (! Container::getInstance() instanceof Application) {
            new Application(dirname(__DIR__, 2));
        }
    }

    private function resetEnvironmentRepository(): void
    {
        $reflection = new ReflectionClass(Env::class);
        $reflection->getProperty('repository')->setValue(null, null);
    }

    /**
     * @return array<string, array{hasEnv: bool, env: mixed, hasServer: bool, server: mixed, hasGetenv: bool, getenv: string|false}>
     */
    private function snapshotEnvironmentKeys(array $keys): array
    {
        $snapshot = [];

        foreach ($keys as $key) {
            $snapshot[$key] = [
                'hasEnv' => array_key_exists($key, $_ENV),
                'env' => $_ENV[$key] ?? null,
                'hasServer' => array_key_exists($key, $_SERVER),
                'server' => $_SERVER[$key] ?? null,
                'hasGetenv' => getenv($key) !== false,
                'getenv' => getenv($key),
            ];
        }

        return $snapshot;
    }

    /**
     * @param  array<string, array{hasEnv: bool, env: mixed, hasServer: bool, server: mixed, hasGetenv: bool, getenv: string|false}>  $snapshot
     */
    private function restoreEnvironmentSnapshot(array $snapshot): void
    {
        foreach ($snapshot as $key => $state) {
            if ($state['hasEnv']) {
                $_ENV[$key] = $state['env'];
            } else {
                unset($_ENV[$key]);
            }

            if ($state['hasServer']) {
                $_SERVER[$key] = $state['server'];
            } else {
                unset($_SERVER[$key]);
            }

            if ($state['hasGetenv']) {
                putenv($key.'='.$state['getenv']);
            } else {
                putenv($key);
            }
        }

        $this->resetEnvironmentRepository();
    }

    /**
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function test_postgres_connection_uses_database_url_when_db_url_missing(): void
    {
        $this->bootstrapConfigLoading();

        $keys = ['DB_URL', 'DATABASE_URL'];
        $snapshot = $this->snapshotEnvironmentKeys($keys);

        try {
            unset($_ENV['DB_URL'], $_SERVER['DB_URL']);
            putenv('DB_URL');

            $databaseUrl = 'postgres://user:secret@queue-db.invalid:5432/app';
            $_ENV['DATABASE_URL'] = $databaseUrl;
            $_SERVER['DATABASE_URL'] = $databaseUrl;
            putenv('DATABASE_URL='.$databaseUrl);

            $this->resetEnvironmentRepository();

            /** @var array $database */
            $database = require dirname(__DIR__, 2).'/config/database.php';
            $config = $database['connections']['pgsql'];
            $parsed = (new ConfigurationUrlParser)->parseConfiguration($config);

            $this->assertSame('queue-db.invalid', $parsed['host']);
            $this->assertSame('5432', (string) $parsed['port']);
        } finally {
            $this->restoreEnvironmentSnapshot($snapshot);
        }
    }

    /**
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function test_postgres_connection_prefers_db_url_over_database_url(): void
    {
        $this->bootstrapConfigLoading();

        $keys = ['DB_URL', 'DATABASE_URL'];
        $snapshot = $this->snapshotEnvironmentKeys($keys);

        try {
            $primary = 'postgres://a:a@primary.invalid:5432/db';
            $secondary = 'postgres://b:b@secondary.invalid:5432/db';

            $_ENV['DB_URL'] = $primary;
            $_SERVER['DB_URL'] = $primary;
            putenv('DB_URL='.$primary);

            $_ENV['DATABASE_URL'] = $secondary;
            $_SERVER['DATABASE_URL'] = $secondary;
            putenv('DATABASE_URL='.$secondary);

            $this->resetEnvironmentRepository();

            /** @var array $database */
            $database = require dirname(__DIR__, 2).'/config/database.php';
            $config = $database['connections']['pgsql'];
            $parsed = (new ConfigurationUrlParser)->parseConfiguration($config);

            $this->assertSame('primary.invalid', $parsed['host']);
        } finally {
            $this->restoreEnvironmentSnapshot($snapshot);
        }
    }
}
