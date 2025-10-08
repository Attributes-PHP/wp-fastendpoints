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

use Attributes\Wp\FastEndpoints\Contracts\Middlewares\Middleware;
use Attributes\Wp\FastEndpoints\DI\Invoker;
use Attributes\Wp\FastEndpoints\Endpoint;
use Attributes\Wp\FastEndpoints\Helpers\WpError;
use Attributes\Wp\FastEndpoints\Middlewares\ResponseMiddleware;
use Attributes\Wp\FastEndpoints\Tests\Helpers\Helpers;
use Attributes\Wp\FastEndpoints\Tests\Models\Post;
use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Exception;
use Invoker\ParameterResolver\DefaultValueResolver;
use Mockery;
use WP_REST_Request;
use WP_REST_Response;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Monkey\setUp();
});

afterEach(function () {
    Monkey\tearDown();
});

// Constructor

test('Creating Endpoint instance', function () {
    $endpoint = new Endpoint('GET', '/my-endpoint', '__return_false', ['my-args'], false);
    expect($endpoint)
        ->toBeInstanceOf(Endpoint::class)
        ->and(Helpers::getNonPublicClassProperty($endpoint, 'method'))->toBe('GET')
        ->and(Helpers::getNonPublicClassProperty($endpoint, 'route'))->toBe('/my-endpoint')
        ->and(Helpers::getNonPublicClassProperty($endpoint, 'handler'))->toBe('__return_false')
        ->and(Helpers::getNonPublicClassProperty($endpoint, 'args'))->toEqual(['my-args'])
        ->and(Helpers::getNonPublicClassProperty($endpoint, 'override'))->toBeFalse();
})->group('endpoint', 'constructor');

// Register

test('Registering an endpoint', function (bool $withResponseSchema, $permissionCallback) {
    $endpoint = new Endpoint('GET', '/my-endpoint', '__return_false', ['my-custom-arg' => true], false);
    expect($endpoint->getFullRoute())->toBe('/my-endpoint');
    $expectedArgs = [
        'methods' => 'GET',
        'callback' => [$endpoint, 'callback'],
        'permission_callback' => '__return_true',
        'my-custom-arg' => true,
    ];
    if ($withResponseSchema) {
        $mockedResponseSchema = Mockery::mock(ResponseMiddleware::class);
        $endpoint->returns($mockedResponseSchema);
    }
    if (! is_null($permissionCallback)) {
        $endpoint->permission($permissionCallback);
        $expectedArgs['permission_callback'] = [$endpoint, 'permissionCallback'];
    }
    Filters\expectApplied('fastendpoints_endpoint_args')
        ->once()
        ->with(Mockery::any(), 'my-namespace', 'v1/users', $endpoint)
        ->andReturnUsing(function ($givenArgs, $givenNamespace, $givenBase, $givenEndpoint) use ($expectedArgs) {
            expect($givenArgs)->toMatchArray($expectedArgs);

            return $givenArgs;
        });
    Functions\expect('register_rest_route')
        ->once()
        ->with('my-namespace', 'v1/users/my-endpoint', Mockery::any(), false)
        ->andReturnUsing(function ($givenNamespace, $givenBase, $givenArgs, $givenOverride) use ($expectedArgs) {
            expect($givenArgs)->toMatchArray($expectedArgs);

            return true;
        });
    expect($endpoint->register('my-namespace', 'v1/users'))
        ->toBeTrue()
        ->and($endpoint->getFullRoute())
        ->toBe('/my-namespace/v1/users/my-endpoint');
})->with([true, false])->with([null, '__return_false'])->group('endpoint', 'register');

test('Skipping registering endpoint if no args specified', function () {
    $endpoint = new Endpoint('GET', '/my-endpoint', '__return_false', ['my-custom-arg' => true], false);
    Filters\expectApplied('fastendpoints_endpoint_args')
        ->once()
        ->with(Mockery::any(), 'my-namespace', 'v1/users', $endpoint)
        ->andReturn(false);
    Functions\expect('register_rest_route')
        ->times(0);
    expect($endpoint->register('my-namespace', 'v1/users', ['my-schema-dir']))->toBeFalse();
})->group('endpoint', 'register');

// hasCap

test('User with valid permissions', function (string $capability, ...$args) {
    $endpoint = new Endpoint('GET', '/my-endpoint', '__return_false', ['my-custom-arg' => true], false);
    expect(Helpers::getNonPublicClassProperty($endpoint, 'permissionHandlers'))->toBeEmpty();
    $endpoint->hasCap($capability, ...$args);
    $permissionHandlers = Helpers::getNonPublicClassProperty($endpoint, 'permissionHandlers');
    expect($permissionHandlers)->toHaveCount(1);
    $mockedRequest = Mockery::mock(WP_REST_Request::class);
    $expectedParams = [];
    foreach ($args as $arg) {
        if (! is_string($arg) || ! str_starts_with($arg, '<')) {
            $expectedParams[] = $arg;

            continue;
        }

        $paramName = substr($arg, 1, -1);
        $isArgumentMissing = $arg === '<argument-missing>';
        $mockedRequest
            ->shouldReceive('has_param')
            ->once()
            ->with($paramName)
            ->andReturn(! $isArgumentMissing);
        if ($isArgumentMissing) {
            $expectedParams[] = $arg;

            continue;
        }
        $mockedRequest
            ->shouldReceive('get_param')
            ->once()
            ->with($paramName)
            ->andReturnUsing(function ($paramName) {
                return 'req_'.$paramName;
            });
        $expectedParams[] = 'req_'.$paramName;
    }
    Functions\expect('current_user_can')
        ->once()
        ->with($capability, ...$expectedParams)
        ->andReturn(true);
    expect($permissionHandlers[0]($mockedRequest))->toBeTrue();
})->with([
    'create_users', ['edit_plugins', 'delete_plugins', 98],
    ['create_users', '<post_id>', '<another_var>', false],
    ['edit_posts', '<argument-missing>'], '<custom-cap>',
])->group('endpoint', 'hasCap');

test('User not having enough permissions', function (string $capability, ...$args) {
    $endpoint = new Endpoint('GET', '/my-endpoint', '__return_false', ['my-custom-arg' => true], false);
    expect(Helpers::getNonPublicClassProperty($endpoint, 'permissionHandlers'))->toBeEmpty();
    $endpoint->hasCap($capability, ...$args);
    $permissionHandlers = Helpers::getNonPublicClassProperty($endpoint, 'permissionHandlers');
    expect($permissionHandlers)->toHaveCount(1);
    $mockedRequest = Mockery::mock(WP_REST_Request::class);
    $expectedParams = [];
    foreach ($args as $arg) {
        if (! str_starts_with($arg, '<')) {
            $expectedParams[] = $arg;

            continue;
        }

        $paramName = substr($arg, 1, -1);
        $isArgumentMissing = $arg === '<argument-missing>';
        $mockedRequest
            ->shouldReceive('has_param')
            ->once()
            ->with($paramName)
            ->andReturn(! $isArgumentMissing);
        if ($isArgumentMissing) {
            $expectedParams[] = $arg;

            continue;
        }

        $mockedRequest
            ->shouldReceive('get_param')
            ->once()
            ->with($paramName)
            ->andReturnUsing(function ($paramName) {
                return 'req_'.$paramName;
            });
        $expectedParams[] = 'req_'.$paramName;
    }
    Functions\expect('current_user_can')
        ->once()
        ->with($capability, ...$expectedParams)
        ->andReturn(false);
    expect($permissionHandlers[0]($mockedRequest))
        ->toBeInstanceOf(WpError::class)
        ->toHaveProperty('code', 403)
        ->toHaveProperty('message', 'Not enough permissions')
        ->toHaveProperty('data', ['status' => 403]);
})->with([
    'create_users', ['edit_plugins', 'delete_plugins'],
    '<custom_capability>', ['create_users', '<post_id>', '<another_var>'],
    ['create_users', '<post_id>', '<argument-missing>'],
])->group('endpoint', 'hasCap');

test('Missing capability', function () {
    Functions\when('esc_html__')->returnArg();
    Functions\when('wp_die')->alias(function ($msg) {
        throw new \Exception($msg);
    });
    $endpoint = new Endpoint('GET', '/my-endpoint', '__return_false', ['my-custom-arg' => true], false);
    expect(function () use ($endpoint) {
        $endpoint->hasCap('');
    })->toThrow(Exception::class, 'Invalid capability. Empty capability given')
        ->and(Helpers::getNonPublicClassProperty($endpoint, 'permissionHandlers'))->toBeEmpty();
})->group('endpoint', 'hasCap');

// returns

test('Adding response validation schema', function ($schema) {
    $endpoint = new Endpoint('GET', '/my-endpoint', '__return_false', ['my-custom-arg' => true], false);
    expect(Helpers::getNonPublicClassProperty($endpoint, 'onResponseHandlers'))
        ->toBeEmpty()
        ->and($endpoint->returns($schema))
        ->toBe($endpoint);
    $onResponseHandlers = Helpers::getNonPublicClassProperty($endpoint, 'onResponseHandlers');
    expect($onResponseHandlers)
        ->toHaveCount(1)
        ->and($onResponseHandlers[0])
        ->toBeCallable();
})->with([Post::class, new Post])->group('endpoint', 'returns');

// middleware

test('Adding middleware before handling a request', function () {
    class MyRequestMiddleware extends Middleware
    {
        public function onRequest()
        {
            return 'onRequest';
        }
    }
    $middleware = new MyRequestMiddleware;
    $endpoint = new Endpoint('GET', '/my-endpoint', '__return_false', ['my-custom-arg' => true], false);
    expect(Helpers::getNonPublicClassProperty($endpoint, 'onRequestHandlers'))->toBeEmpty();
    $endpoint->middleware($middleware);
    $onRequestHandlers = Helpers::getNonPublicClassProperty($endpoint, 'onRequestHandlers');
    expect($onRequestHandlers)->toHaveCount(1)
        ->and($onRequestHandlers[0])
        ->toBeCallable()
        ->and($onRequestHandlers[0]->call($middleware))
        ->toBe('onRequest');
})->group('endpoint', 'middleware');

test('Adding middleware before sending response', function () {
    class MyResponseMiddleware extends Middleware
    {
        public function onResponse()
        {
            return 'onResponse';
        }
    }
    $middleware = new MyResponseMiddleware;
    $endpoint = new Endpoint('GET', '/my-endpoint', '__return_false', ['my-custom-arg' => true], false);
    expect(Helpers::getNonPublicClassProperty($endpoint, 'onResponseHandlers'))->toBeEmpty();
    $endpoint->middleware($middleware);
    $onResponseHandlers = Helpers::getNonPublicClassProperty($endpoint, 'onResponseHandlers');
    expect($onResponseHandlers)->toHaveCount(1)
        ->and($onResponseHandlers[0])
        ->toBeCallable()
        ->and($onResponseHandlers[0]->call($middleware))
        ->toBe('onResponse');
})->group('endpoint', 'middleware');

test('Adding middleware to trigger before handling a request and before sending a response', function () {
    class MyMiddleware extends Middleware
    {
        public function onRequest()
        {
            return 'onRequest';
        }

        public function onResponse()
        {
            return 'onResponse';
        }
    }
    $middleware = new MyMiddleware;
    $endpoint = new Endpoint('GET', '/my-endpoint', '__return_false', ['my-custom-arg' => true], false);
    expect(Helpers::getNonPublicClassProperty($endpoint, 'onResponseHandlers'))->toBeEmpty()
        ->and(Helpers::getNonPublicClassProperty($endpoint, 'onRequestHandlers'))->toBeEmpty();
    $endpoint->middleware($middleware);
    $onRequestHandlers = Helpers::getNonPublicClassProperty($endpoint, 'onRequestHandlers');
    expect($onRequestHandlers)->toHaveCount(1)
        ->and($onRequestHandlers[0])
        ->toBeCallable()
        ->and($onRequestHandlers[0]->call($middleware))
        ->toBe('onRequest');
    $onResponseHandlers = Helpers::getNonPublicClassProperty($endpoint, 'onResponseHandlers');
    expect($onResponseHandlers)->toHaveCount(1)
        ->and($onResponseHandlers[0])
        ->toBeCallable()
        ->and($onResponseHandlers[0]->call($middleware))
        ->toBe('onResponse');
})->group('endpoint', 'middleware');

test('Adding middleware with missing methods', function () {
    Functions\when('esc_html__')->returnArg();
    class InvalidMiddleware extends Middleware
    {
        public function hey(): void {}
    }
    expect(fn () => new InvalidMiddleware)
        ->toThrow(Exception::class, 'At least one method onRequest() or onResponse() must be declared on the class.');
})->group('endpoint', 'middleware');

// permission

test('Adding permission callable', function () {
    $permissionCallable = function () {
        return true;
    };
    $endpoint = new Endpoint('GET', '/my-endpoint', '__return_false', ['my-custom-arg' => true], false);
    expect(Helpers::getNonPublicClassProperty($endpoint, 'permissionHandlers'))->toBeEmpty();
    $endpoint->permission($permissionCallable);
    $permissionHandlers = Helpers::getNonPublicClassProperty($endpoint, 'permissionHandlers');
    expect($permissionHandlers)->toHaveCount(1)
        ->and($permissionHandlers[0])->toBe($permissionCallable);
})->group('endpoint', 'permission');

// permissionCallback

test('Running permission handlers in permission callback', function ($returnValue) {
    if (is_string($returnValue)) {
        $returnValue = new $returnValue(123, 'testing-error');
    }
    $req = Mockery::mock(WP_REST_Request::class);
    $mockedEndpoint = Mockery::mock(Endpoint::class)
        ->shouldAllowMockingProtectedMethods()
        ->makePartial();
    Helpers::setNonPublicClassProperty($mockedEndpoint, 'invoker', new Invoker);
    Helpers::setNonPublicClassProperty($mockedEndpoint, 'permissionHandlers', ['test-permission-handler']);
    $mockedEndpoint->shouldReceive('runHandlers')
        ->once()
        ->with(['test-permission-handler'], Mockery::type('array'), true, true)
        ->andReturn($returnValue);
    expect($mockedEndpoint->permissionCallback($req))
        ->toBe($returnValue ?? true);
})
    ->with([null, WpError::class, false, true])
    ->group('endpoint', 'permission', 'permissionCallback');

// callback

test('Endpoint request handler', function (bool $hasRequestHandlers, bool $hasResponseHandlers) {
    $endpoint = new Endpoint('GET', '/my-endpoint', function () {
        return 'my-response';
    }, ['my-custom-arg' => true], true);
    $req = Mockery::mock(WP_REST_Request::class);
    if ($hasRequestHandlers) {
        $onRequestHandlers = [function () {
            return false;
        }];
        Helpers::setNonPublicClassProperty($endpoint, 'onRequestHandlers', $onRequestHandlers);
    }
    if ($hasResponseHandlers) {
        $onResponseHandlers = [function () {
            return null;
        }];
        Helpers::setNonPublicClassProperty($endpoint, 'onResponseHandlers', $onResponseHandlers);
    }
    expect($endpoint->callback($req))
        ->toBeInstanceOf(WP_REST_Response::class)
        ->toHaveProperty('data', 'my-response');
})->with([true, false])->with([true, false])->group('endpoint', 'callback');

test('Handling request and a WpError is returned', function ($onRequestReturnVal, $handlerReturnVal, $onResponseReturnVal) {
    $endpoint = new Endpoint('GET', '/my-endpoint', function () use ($handlerReturnVal) {
        return is_string($handlerReturnVal) ? new $handlerReturnVal(123, 'my-error-msg') : $handlerReturnVal;
    }, ['my-custom-arg' => true], true);
    $req = Mockery::mock(WP_REST_Request::class);
    $onRequestHandlers = [function () use ($onRequestReturnVal) {
        return is_string($onRequestReturnVal) ? new $onRequestReturnVal(123, 'my-error-msg') : $onRequestReturnVal;
    }];
    Helpers::setNonPublicClassProperty($endpoint, 'onRequestHandlers', $onRequestHandlers);
    $onResponseHandlers = [function () use ($onResponseReturnVal) {
        return is_string($onResponseReturnVal) ? new $onResponseReturnVal(123, 'my-error-msg') : $onResponseReturnVal;
    }];
    Helpers::setNonPublicClassProperty($endpoint, 'onResponseHandlers', $onResponseHandlers);
    expect($endpoint->callback($req))
        ->toBeInstanceOf(WpError::class)
        ->toHaveProperty('code', 123)
        ->toHaveProperty('message', 'my-error-msg')
        ->toHaveProperty('data', ['status' => 123]);
})->with([
    [WpError::class, true, true],
    [true, WpError::class, true],
    [true, true, WpError::class],
])->group('endpoint', 'callback');

test('Handling request with an invalid request payload', function () {
    Filters\expectApplied('fastendpoints_request_error')->once();

    $endpoint = new Endpoint('GET', '/my-endpoint', function (int $missingField, int $postId) {
        return $missingField + $postId;
    });
    $req = Mockery::mock(WP_REST_Request::class);
    $req->expects()
        ->get_url_params()
        ->times(2)
        ->andReturn(['postId' => 10]);
    $req->expects()
        ->get_query_params()
        ->once()
        ->andReturn(['another' => 2]);

    expect($endpoint->callback($req))
        ->toBeInstanceOf(WpError::class)
        ->toHaveProperty('code', 422)
        ->toHaveProperty('message', 'Invalid data')
        ->toHaveProperty('data', [
            'status' => 422,
            'errors' => [
                [
                    'field' => 'missingField',
                    'reason' => 'Missing required argument \'missingField\'',
                ],
            ]]);
})->group('endpoint', 'callback');

test('Handling request and invoker is unable to resolve dependencies', function () {
    Filters\expectApplied('fastendpoints_request_error')->once();

    $endpoint = new Endpoint('GET', '/my-endpoint', function (int $missingField) {
        return $missingField;
    });
    $req = Mockery::mock(WP_REST_Request::class);
    $invoker = new Invoker(parameterResolver: new DefaultValueResolver);
    Helpers::setNonPublicClassProperty($endpoint, 'invoker', $invoker);

    expect($endpoint->callback($req))
        ->toBeInstanceOf(WpError::class)
        ->toHaveProperty('code', 500)
        ->toHaveProperty('message', 'Unable to invoke the callable because no value was given for parameter 1 ($missingField)')
        ->toHaveProperty('data', ['status' => 500]);
})->group('endpoint', 'callback');

// getRoute

test('Getting endpoint route', function (string $route, string $expectedRoute) {
    $endpoint = new Endpoint('GET', $route, '__return_false');
    Filters\expectApplied('fastendpoints_endpoint_route')
        ->once()
        ->with($expectedRoute, $endpoint);
    expect(Helpers::invokeNonPublicClassMethod($endpoint, 'getRoute', '/my-base'))
        ->toBe($expectedRoute);
})->with([
    ['', '/my-base/'],
    ['/', '/my-base/'],
    ['/hello', '/my-base/hello'],
    ['hello', '/my-base/hello'],
    ['hello/another', '/my-base/hello/another'],
])->group('endpoint', 'getRoute');

// depends

test('Specifying endpoint dependencies', function (array|string $dependencies) {
    $endpoint = new Endpoint('GET', '/dependencies', '__return_false');
    $endpoint->depends($dependencies);
    $dependencies = is_string($dependencies) ? [$dependencies] : $dependencies;
    $plugins = Helpers::getNonPublicClassProperty($endpoint, 'plugins');
    expect($plugins)
        ->toBeArray()
        ->toBe($dependencies);
})->with([
    'plugin1',
    [['plugin1', 'plugin2']],
])->group('endpoint', 'depends');

test('Specifying endpoint dependencies multiple times', function (string $firstDependency, string $secondDependency) {
    $endpoint = new Endpoint('GET', '/dependencies', '__return_false');
    $endpoint->depends($firstDependency);
    $plugins = Helpers::getNonPublicClassProperty($endpoint, 'plugins');
    expect($plugins)
        ->toBeArray()
        ->toBe([$firstDependency]);
    $endpoint->depends($secondDependency);
    $plugins = Helpers::getNonPublicClassProperty($endpoint, 'plugins');
    expect($plugins)
        ->toBeArray()
        ->toBe([$firstDependency, $secondDependency]);
})->with([['plugin1', 'plugin2']])->group('endpoint', 'depends');
