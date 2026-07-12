<?php

use App\Http\Middleware\CleanApiQueryParameters;
use Illuminate\Http\Request;

test('middleware strips string null from json body on api routes', function () {
    $middleware = new CleanApiQueryParameters;

    $base = Request::createFromBase(
        Symfony\Component\HttpFoundation\Request::create(
            '/api/example',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            json_encode([
                'pos_session_id' => 'null',
                'name' => 'ok',
            ], JSON_THROW_ON_ERROR)
        )
    );

    $middleware->handle($base, fn (Request $request) => response('ok'));

    expect($base->input('pos_session_id'))->toBeNull()
        ->and($base->input('name'))->toBe('ok');
});

test('middleware strips string null from nested json body keys', function () {
    $middleware = new CleanApiQueryParameters;

    $base = Request::createFromBase(
        Symfony\Component\HttpFoundation\Request::create(
            '/api/example',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'cart' => [
                    'customer_id' => 'null',
                    'total' => 100,
                ],
            ], JSON_THROW_ON_ERROR)
        )
    );

    $middleware->handle($base, fn (Request $request) => response('ok'));

    expect($base->input('cart.customer_id'))->toBeNull()
        ->and($base->input('cart.total'))->toBe(100);
});

test('middleware reindexes json lists after removing string null entries', function () {
    $middleware = new CleanApiQueryParameters;

    $base = Request::createFromBase(
        Symfony\Component\HttpFoundation\Request::create(
            '/api/example',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'tags' => ['null', 'alpha', 'null', 'beta'],
            ], JSON_THROW_ON_ERROR)
        )
    );

    $middleware->handle($base, fn (Request $request) => response('ok'));

    expect($base->input('tags'))->toBe(['alpha', 'beta']);
});
