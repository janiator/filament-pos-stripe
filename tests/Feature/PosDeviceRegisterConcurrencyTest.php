<?php

use App\Models\PosDevice;
use App\Models\Store;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('register trims device_name to match an existing device row', function (): void {
    $user = User::factory()->create();
    $store = Store::factory()->create();
    $user->stores()->attach($store);
    $user->setCurrentStore($store);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/pos-devices/register', [
        'device_identifier' => 'vendor-id-trim-test',
        'device_name' => 'iPad',
        'platform' => 'ios',
    ])->assertStatus(201);

    $response = $this->postJson('/api/pos-devices/register', [
        'device_identifier' => 'vendor-id-trim-test-updated',
        'device_name' => " \t iPad ",
        'platform' => 'ios',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('device.device_name', 'iPad');
    $response->assertJsonPath('device.device_identifier', 'vendor-id-trim-test-updated');
    expect(PosDevice::where('store_id', $store->id)->where('device_name', 'iPad')->count())->toBe(1);
});

it('concurrent register requests for the same device_name do not fail with a unique constraint violation', function (): void {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('pcntl_fork is not available in this environment.');
    }

    $user = User::factory()->create();
    $store = Store::factory()->create();
    $user->stores()->attach($store);
    $user->setCurrentStore($store);
    $token = $user->createToken('concurrency-test')->plainTextToken;

    $payload = [
        'device_identifier' => 'fork-concurrent-'.uniqid(),
        'device_name' => 'iPad',
        'platform' => 'ios',
    ];

    $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR);

    $basePath = dirname(__DIR__, 2);
    $statusFile = tempnam(sys_get_temp_dir(), 'posregfork');
    if ($statusFile === false) {
        $this->fail('Could not create temp file for fork status.');
    }

    $runRegisterRequest = static function () use ($basePath, $token, $payloadJson): int {
        /** @var Application $app */
        $app = require $basePath.'/bootstrap/app.php';
        $app->make(ConsoleKernel::class)->bootstrap();
        DB::purge();
        DB::reconnect();

        $kernel = $app->make(HttpKernel::class);
        $request = Request::create(
            '/api/pos-devices/register',
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            $payloadJson
        );

        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response->getStatusCode();
    };

    $pid = pcntl_fork();
    if ($pid === -1) {
        @unlink($statusFile);
        $this->fail('pcntl_fork failed.');
    }

    if ($pid === 0) {
        $code = $runRegisterRequest();
        file_put_contents($statusFile, (string) $code);

        exit(0);
    }

    $parentCode = $runRegisterRequest();
    pcntl_waitpid($pid, $status);
    $childContents = @file_get_contents($statusFile);
    @unlink($statusFile);

    expect($childContents)->not->toBeFalse('child status file was not written');
    $childCode = (int) $childContents;

    expect($parentCode)->toBeIn([200, 201]);
    expect($childCode)->toBeIn([200, 201]);
    expect(PosDevice::where('store_id', $store->id)->where('device_name', 'iPad')->count())->toBe(1);
})->group('pcntl');
