<?php

declare(strict_types=1);

namespace Tests\Unit\Queue;

use App\Queue\FailedJobs\DeduplicatingDatabaseUuidFailedJobProvider;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DeduplicatingDatabaseUuidFailedJobProviderTest extends TestCase
{
    private Capsule $capsule;

    private DeduplicatingDatabaseUuidFailedJobProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(new Container);

        $this->capsule = new Capsule;
        $this->capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ], 'default');
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();

        $schema = $this->capsule->getConnection()->getSchemaBuilder();

        $schema->create('failed_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        $this->provider = new DeduplicatingDatabaseUuidFailedJobProvider(
            $this->capsule->getDatabaseManager(),
            'default',
            'failed_jobs',
        );
    }

    protected function tearDown(): void
    {
        $this->capsule->getConnection()->disconnect();

        parent::tearDown();
    }

    public function test_it_logs_the_same_payload_uuid_twice_without_unique_violation(): void
    {
        $uuid = '018f3c4c-aaaa-7012-8566-aaaaaaaaaaaa';
        $payload = '{"uuid":"'.$uuid.'"}';

        $this->provider->log('redis', 'default', $payload, new RuntimeException('first'));
        self::assertSame(
            1,
            $this->capsule->getConnection()->table('failed_jobs')->where('uuid', $uuid)->count(),
        );

        $this->provider->log('redis', 'default', $payload, new RuntimeException('second'));

        self::assertSame(
            1,
            $this->capsule->getConnection()->table('failed_jobs')->where('uuid', $uuid)->count(),
        );

        $row = $this->capsule->getConnection()->table('failed_jobs')->where('uuid', $uuid)->first();

        self::assertNotNull($row);
        self::assertIsString($row->exception ?? null);
        self::assertStringContainsString('second', $row->exception);
    }
}
