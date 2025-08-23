<?php

/**
 * Holds tests for testing injecting dependencies in an endpoint
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
    $router = Helpers::getRouter('InjectorsRouter.php');
    $router->register();
    do_action('rest_api_init', $this->server);
});

afterEach(function () {
    global $wp_rest_server;
    $wp_rest_server = null;

    parent::tearDown();
});

test('Inject Post via ID', function (?array $jsonBody) {
    $userId = $this::factory()->user->create();
    $postId = $this::factory()->post->create(['post_author' => $userId, 'post_title' => 'My title']);

    $request = new WP_REST_Request('GET', '/injectors/v1/retrieve');
    $request->set_header('content-type', 'application/json');
    $request->set_query_params(['post' => $postId]);
    if ($jsonBody) {
        $request->set_body(json_encode($jsonBody));
    }

    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(200);
    $data = $response->get_data();
    expect($data)
        ->toBeArray()
        ->toBe([
            'post_author' => $userId,
            'post_title' => 'My title',
            'post_status' => 'publish',
        ]);
})
    ->with([
        null,
        [[
            'ID' => 10,
            'post_author' => 15,
            'post_title' => 'This should be ignored',
            'post_status' => 'ignored',
            'post_type' => 'ignored',
        ]],
    ])
    ->group('injectors');

test('Inject Post via JSON data', function (bool $isJson) {
    $request = new WP_REST_Request('GET', '/injectors/v1/retrieve');
    $bodyParams = [
        'ID' => 10,
        'post_author' => 15,
        'post_title' => 'My title',
        'post_status' => 'publish',
        'post_type' => 'post',
    ];
    if ($isJson) {
        $request->set_header('content-type', 'application/json');
        $request->set_body(json_encode($bodyParams));
    } else {
        $request->set_body_params($bodyParams);
    }

    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(200);
    $data = $response->get_data();
    expect($data)
        ->toBeArray()
        ->toBe([
            'post_author' => 15,
            'post_title' => 'My title',
            'post_status' => 'publish',
        ]);
})
    ->with([true, false])
    ->group('injectors');

test('Injected function result', function () {
    $request = new WP_REST_Request('GET', '/injectors/v1/hello-world');
    $request->set_header('content-type', 'application/json');

    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(200);
    $data = $response->get_data();
    expect($data)
        ->toBeString()
        ->toBe('Hello World!Hello World!');
})->group('injectors');
