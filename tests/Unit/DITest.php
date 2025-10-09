<?php

/**
 * Holds tests for the Endpoint class.
 *
 * @since 1.0.0
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Attributes\Wp\FastEndpoints\Tests\Unit\Schemas;

use Attributes\Serialization\Serializable;
use Attributes\Validation\Validatable;
use Attributes\Wp\FastEndpoints\DI\LazyLoadParameters;
use Attributes\Wp\FastEndpoints\Endpoint;
use Attributes\Wp\FastEndpoints\Helpers\WpError;
use Attributes\Wp\FastEndpoints\Options\Header;
use Attributes\Wp\FastEndpoints\Options\Inject;
use Attributes\Wp\FastEndpoints\Options\Json;
use Attributes\Wp\FastEndpoints\Options\Query;
use Attributes\Wp\FastEndpoints\Tests\Helpers\Helpers;
use Attributes\Wp\FastEndpoints\Tests\Models\Post;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use ReflectionFunction;
use WP_REST_Request;
use WP_REST_Response;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Monkey\setUp();
});

afterEach(function () {
    Monkey\tearDown();
});

// Inject parameter resolver

test('Inject dependencies', function () {
    $endpoint = new Endpoint('POST', '/inject', function (#[Inject] string $user, #[Inject] Validatable $validator, #[Inject] Serializable $serializer) {
        return $user;
    });
    Helpers::invokeNonPublicClassMethod($endpoint, 'registerDefaultExceptionHandlers');

    $endpoint->getInvoker()->setInjectables([
        'getFirstName' => fn () => 'Andre',
        'getLastName' => fn () => 'Gil',
        'user' => fn (#[Inject] string $getFirstName, #[Inject('getLastName')] string $lastName) => "$getFirstName $lastName",
    ]);
    $req = Mockery::mock(WP_REST_Request::class);
    expect($endpoint->callback($req))
        ->toBeInstanceOf(WP_REST_Response::class)
        ->toHaveProperty('data', 'Andre Gil')
        ->toHaveProperty('status', 200);
})->group('di', 'inject');

test('Only resolves injected dependencies once', function () {
    $endpoint = new Endpoint('POST', '/inject', function (#[Inject] string $user, #[Inject('getFirstName')] string $firstName) {
        return "$firstName $user";
    });
    Helpers::invokeNonPublicClassMethod($endpoint, 'registerDefaultExceptionHandlers');

    $calledTimes = ['firstName' => 0, 'lastName' => 0, 'user' => 0];
    $endpoint->getInvoker()->setInjectables([
        'getFirstName' => function () use (&$calledTimes) {
            $calledTimes['firstName']++;

            return 'Andre';
        },
        'getLastName' => function () use (&$calledTimes) {
            $calledTimes['lastName']++;

            return 'Gil';
        },
        'user' => function (#[Inject] string $getFirstName, #[Inject('getLastName')] string $lastName) use (&$calledTimes) {
            $calledTimes['user']++;

            return "$getFirstName $lastName";
        },
    ]);

    $req = Mockery::mock(WP_REST_Request::class);
    expect($endpoint->callback($req))
        ->toBeInstanceOf(WP_REST_Response::class)
        ->toHaveProperty('data', 'Andre Andre Gil')
        ->toHaveProperty('status', 200)
        ->and($calledTimes)
        ->toMatchArray([
            'firstName' => 1,
            'lastName' => 1,
            'user' => 1,
        ]);
})->group('di', 'inject');

test('Unable to find injectable dependency', function () {
    $endpoint = new Endpoint('POST', '/inject', function (#[Inject] string $notFound) {
        return $notFound;
    });
    Helpers::invokeNonPublicClassMethod($endpoint, 'registerDefaultExceptionHandlers');

    $req = Mockery::mock(WP_REST_Request::class);
    expect($endpoint->callback($req))
        ->toBeInstanceOf(WpError::class)
        ->toHaveProperty('code', 500)
        ->toHaveProperty('message', 'Injectable not found for parameter notFound in route /inject')
        ->toHaveProperty('data', ['status' => 500]);
})->group('di', 'inject');

test('Infinite injectable loop', function () {
    $endpoint = new Endpoint('POST', '/inject', function (#[Inject] string $user) {
        return $user;
    });
    Helpers::invokeNonPublicClassMethod($endpoint, 'registerDefaultExceptionHandlers');

    $endpoint->getInvoker()->setInjectables([
        'user' => fn (#[Inject] string $user) => $user,
    ]);
    $req = Mockery::mock(WP_REST_Request::class);
    expect($endpoint->callback($req))
        ->toBeInstanceOf(WpError::class)
        ->toHaveProperty('code', 500)
        ->toHaveProperty('message', 'Infinite injectables loop in route /inject')
        ->toHaveProperty('data', ['status' => 500]);
})->group('di', 'inject');

// Static parameter resolver

test('Static dependencies', function () {
    $req = Mockery::mock(WP_REST_Request::class);
    $endpoint = new Endpoint('GET', '/static', fn () => true);
    $handler = function (WP_REST_Request $request, WP_REST_Response $response, Endpoint $currentEndpoint) use ($req, $endpoint) {
        expect($request)->toBe($req)
            ->and($currentEndpoint)->toBe($endpoint);

        $response->set_status(204);
    };
    Helpers::setNonPublicClassProperty($endpoint, 'handler', $handler);

    expect($endpoint->callback($req))
        ->toBeInstanceOf(WP_REST_Response::class)
        ->toHaveProperty('data', null)
        ->toHaveProperty('status', 204);
})->group('di', 'static');

// Validation parameter resolver

test('Validates dependencies', function () {
    $endpoint = new Endpoint('POST', '/validation/(?P<id>[\\d]+)', function (int $id, Post $post, $anyValue) {
        return [
            'id' => $id,
            'post' => $post->serialize(),
            'anyValue' => $anyValue,
        ];
    });
    Helpers::invokeNonPublicClassMethod($endpoint, 'registerDefaultExceptionHandlers');

    $req = Mockery::mock(WP_REST_Request::class);
    $req->expects()->get_url_params()->times(2)->andReturn(['id' => 15]);
    $req->expects()->get_query_params()->andReturn(['anyValue' => 'My value']);
    $req->expects()->is_json_content_type()->andReturn(true);
    $req->expects()->get_json_params()->andReturn([
        'ID' => 10,
        'post_author' => 1,
        'post_title' => 'My title',
        'post_status' => 'publish',
        'post_type' => 'post',
    ]);

    expect($endpoint->callback($req))
        ->toBeInstanceOf(WP_REST_Response::class)
        ->toHaveProperty('status', 200)
        ->toHaveProperty('data', [
            'id' => 15,
            'post' => [
                'post_author' => 1,
                'post_title' => 'My title',
                'post_status' => 'publish',
            ],
            'anyValue' => 'My value',
        ]);
})->group('di', 'validation');

// Lazy load parameters

test('Load parameters from respective options', function (int|array $post) {
    $endpoint = new Endpoint('POST', '/lazyload', function (#[Header] array $userIds, #[Json] int|Post $post) {
        return [
            'userIds' => $userIds,
            'post' => is_int($post) ? $post : $post->serialize(useValidation: true),
        ];
    });
    Helpers::invokeNonPublicClassMethod($endpoint, 'registerDefaultExceptionHandlers');

    $req = Mockery::mock(WP_REST_Request::class);
    $req->expects()->get_headers()->andReturn(['userIds' => '10,20,30']);
    $req->expects()->get_header('userIds')->andReturn('10,20,30');
    $req->expects()->get_json_params()->andReturn(is_int($post) ? ['post' => $post] : $post);

    expect($endpoint->callback($req))
        ->toBeInstanceOf(WP_REST_Response::class)
        ->toHaveProperty('status', 200)
        ->toHaveProperty('data', [
            'userIds' => ['10', '20', '30'],
            'post' => $post,
        ]);
})
    ->with([1, ['post_author' => 11, 'post_title' => 'My first post', 'post_status' => 'publish']])
    ->group('di', 'lazyload', 'options');

test('Unable to find parameter', function () {
    $endpoint = new Endpoint('POST', '/lazyload', function (#[Query] string|int $missing) {
        return $missing;
    });
    Helpers::invokeNonPublicClassMethod($endpoint, 'registerDefaultExceptionHandlers');
    $req = Mockery::mock(WP_REST_Request::class);
    $req->expects()->get_query_params()->andReturn(['hello' => 10]);

    expect($endpoint->callback($req))
        ->toBeInstanceOf(WpError::class)
        ->toHaveProperty('code', 422)
        ->toHaveProperty('data', [
            'status' => 422,
            'errors' => [
                ['field' => 'missing', 'reason' => 'Missing required argument \'missing\''],
            ],
        ]);
})->group('di', 'lazyload');

test('Preserves last accessed index - offsetExists', function () {
    $reflection = new ReflectionFunction(fn () => true);
    $req = Mockery::mock(WP_REST_Request::class);
    $lazyLoadParameters = new LazyLoadParameters($reflection, $req, []);
    $currentIndex = Helpers::getNonPublicClassProperty($lazyLoadParameters, 'currentIndex');
    expect($currentIndex)->toBeNull();

    $lazyLoadParameters[2] ?? null;

    $currentIndex = Helpers::getNonPublicClassProperty($lazyLoadParameters, 'currentIndex');
    expect($currentIndex)->toBe(2);
})->group('di', 'lazyload');

test('Preserves last accessed index - offsetGet', function () {
    $reflection = new ReflectionFunction(fn () => true);
    $req = Mockery::mock(WP_REST_Request::class);
    $lazyLoadParameters = new LazyLoadParameters($reflection, $req, [3 => 'Hello']);
    $currentIndex = Helpers::getNonPublicClassProperty($lazyLoadParameters, 'currentIndex');
    expect($currentIndex)->toBeNull();

    $lazyLoadParameters[3];

    $currentIndex = Helpers::getNonPublicClassProperty($lazyLoadParameters, 'currentIndex');
    expect($currentIndex)->toBe(3);
})->group('di', 'lazyload');
