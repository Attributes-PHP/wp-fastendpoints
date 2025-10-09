<?php

/**
 * Holds tests for testing custom exception handling
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Attributes\Wp\FastEndpoints\Tests\Integration;

use Attributes\Wp\FastEndpoints\Tests\Helpers\Helpers;
use WP_REST_Request;
use WP_REST_Server;
use Yoast\WPTestUtils\WPIntegration\TestCase;

if (! Helpers::isIntegrationTest()) {
    return;
}

/*
 * We need to provide the base test class to every integration test.
 * This will enable us to use all the WordPress test goodies, such as
 * factories and proper test cleanup.
 */
uses(TestCase::class);

beforeEach(function () {
    parent::setUp();

    // Set up a REST server instance.
    global $wp_rest_server;

    $this->server = $wp_rest_server = new WP_REST_Server;
    $router = Helpers::getRouter('ErrorHandlerRouter.php');
    $router->register();
    do_action('rest_api_init', $this->server);
});

afterEach(function () {
    global $wp_rest_server;
    $wp_rest_server = null;

    parent::tearDown();
});

// Common

test('Ignores all errors', function (string $endpoint) {
    $request = new WP_REST_Request('POST', $endpoint);
    $request->set_header('content-type', 'application/json');

    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(200);
    $data = $response->get_data();
    expect($data)->toBeNull();
})
    ->with(['/error-handler/v1/handler/ignore-all-errors', '/error-handler/v1/permission/ignore-all-errors'])
    ->group('error-handler');

// Handler

test('Handle handler exceptions', function (string $endpoint) {
    $request = new WP_REST_Request('POST', $endpoint);
    $request->set_header('content-type', 'application/json');

    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(570);
    $data = $response->get_data();
    expect($data)
        ->toBeArray()
        ->toBe([
            'code' => 570,
            'message' => 'Exception handler',
            'data' => ['status' => 570],
        ]);
})
    ->with(['/error-handler/v1/handler', '/error-handler/v1/handler/custom-exception'])
    ->group('error-handler', 'handler');

// Permission callback

test('Handle permission handler exceptions', function (string $endpoint) {
    $request = new WP_REST_Request('POST', $endpoint);
    $request->set_header('content-type', 'application/json');

    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(570);
    $data = $response->get_data();
    expect($data)
        ->toBeArray()
        ->toBe([
            'code' => 570,
            'message' => 'Exception handler',
            'data' => ['status' => 570],
        ]);
})
    ->with(['/error-handler/v1/permission', '/error-handler/v1/permission/custom-exception'])
    ->group('error-handler', 'permission');

// Exception handler

test('Handle exceptions in exception handler', function () {
    $request = new WP_REST_Request('POST', '/error-handler/v1/exception-handler');
    $request->set_header('content-type', 'application/json');

    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(500);
    $data = $response->get_data();
    expect($data)
        ->toBeArray()
        ->toBe([
            'code' => 500,
            'message' => 'Bad exception handler for: Exception',
            'data' => ['status' => 500],
        ]);
})
    ->group('error-handler', 'exception-handler');

test('Handle different exceptions in exception handler', function () {
    $request = new WP_REST_Request('POST', '/error-handler/v1/exception-handler/missing-field');
    $request->set_header('content-type', 'application/json');

    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(422);
    $data = $response->get_data();
    expect($data)
        ->toBeArray()
        ->toBe([
            'code' => 422,
            'message' => 'Invalid data',
            'data' => [
                'status' => 422,
                'errors' => [[
                    'field' => 'missingField',
                    'reason' => 'Missing required argument \'missingField\'',
                ]],
            ],
        ]);
})
    ->group('error-handler', 'exception-handler');
