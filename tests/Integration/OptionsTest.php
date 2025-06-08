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
    $router = Helpers::getRouter('OptionsRouter.php');
    $router->register();
    do_action('rest_api_init', $this->server);
});

afterEach(function () {
    global $wp_rest_server;
    $wp_rest_server = null;

    parent::tearDown();
});

// Defaults

test('Default option', function (bool $isJson) {
    $request = new WP_REST_Request('GET', '/options/v1/default/10');
    $request->set_query_params(['queryParam' => '11']);
    $post = [
        'ID' => 10,
        'post_author' => 15,
        'post_title' => 'My title',
        'post_status' => 'publish',
        'post_type' => 'post',
    ];
    if ($isJson) {
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode($post));
    } else {
        $request->set_body_params($post);
    }

    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(200);
    $data = $response->get_data();
    expect($data)
        ->toBeArray()
        ->toMatchArray([
            'urlParam' => 10,
            'queryParam' => 11,
            'post' => [
                'post_author' => 15,
                'post_title' => 'My title',
                'post_status' => 'publish',
            ],
        ]);
})
    ->with([true, false])
    ->group('options', 'default');

// Query

test('Query option', function (mixed $value, mixed $expectedValue) {
    $request = new WP_REST_Request('GET', '/options/v1/query');
    $request->set_query_params([
        'post_id' => $value,
        'all_posts' => "$value,$value,$value",
    ]);

    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(200);
    $data = $response->get_data();
    expect($data)
        ->toBeArray()
        ->toMatchArray([
            'post_id' => $expectedValue,
            'all_posts' => [$value, $value, $value],
        ]);
})
    ->with('query')
    ->group('options', 'query');

// Url

test('Url option', function (mixed $value, mixed $expectedValue) {
    $request = new WP_REST_Request('GET', "/options/v1/url/$value");
    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(200);
    $data = $response->get_data();
    expect($data)
        ->toBeInt()
        ->toBe($expectedValue);
})
    ->with('url')
    ->group('options', 'url');

// Json

test('Json option', function () {
    $request = new WP_REST_Request('GET', '/options/v1/json');
    $request->set_header('content-type', 'application/json');
    $request->set_body(json_encode(['post_id' => 10]));

    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(200);
    $data = $response->get_data();
    expect($data)
        ->toBeInt()
        ->toBe(10);
})->group('options', 'json');

// Body

test('Body option', function () {
    $request = new WP_REST_Request('GET', '/options/v1/body');
    $request->set_body_params(['post_id' => 10]);

    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(200);
    $data = $response->get_data();
    expect($data)
        ->toBeInt()
        ->toBe(10);
})
    ->group('options', 'body');

// Header

test('Header option', function (mixed $value, mixed $expectedValue) {
    $request = new WP_REST_Request('GET', '/options/v1/header');
    $request->set_header('post_id', $value);
    $request->set_header('all_posts', "$value,$value,$value");

    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(200);
    $data = $response->get_data();
    expect($data)
        ->toBeArray()
        ->toMatchArray([
            'post_id' => $expectedValue,
            'all_posts' => [$value, $value, $value],
        ]);
})
    ->with('header')
    ->group('options', 'header');

// Cookie

test('Cookie option', function (mixed $value, mixed $expectedValue) {
    $request = new WP_REST_Request('GET', '/options/v1/cookie');
    $_COOKIE['post_id'] = $value;
    $_COOKIE['all_posts'] = "$value,$value,$value";

    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(200);
    $data = $response->get_data();
    expect($data)
        ->toBeArray()
        ->toMatchArray([
            'post_id' => $expectedValue,
            'all_posts' => [$value, $value, $value],
        ]);
})
    ->with('cookie')
    ->group('options', 'cookie');

// File

test('File option', function () {
    $request = new WP_REST_Request('POST', '/options/v1/file');
    $request->set_file_params(['my_file' => ['full_path' => 'hello-world.txt']]);

    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(200);
    $data = $response->get_data();
    expect($data)
        ->toBeArray()
        ->toMatchArray([
            'full_path' => 'hello-world.txt',
        ]);
})->group('options', 'file');

// Any

test('Any option - query', function () {
    $request = new WP_REST_Request('POST', '/options/v1/any');
    $request->set_query_params(['post_id' => 10]);

    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(200);
    $data = $response->get_data();
    expect($data)
        ->toBeInt()
        ->toBe(10);
})->group('options', 'any');

test('Any option - url', function () {
    $request = new WP_REST_Request('POST', '/options/v1/any/10');

    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(200);
    $data = $response->get_data();
    expect($data)
        ->toBeInt()
        ->toBe(10);
})->group('options', 'any');

test('Any option - json', function () {
    $request = new WP_REST_Request('POST', '/options/v1/any');
    $request->set_header('content-type', 'application/json');
    $request->set_body(json_encode(['post_id' => 10]));

    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(200);
    $data = $response->get_data();
    expect($data)
        ->toBeInt()
        ->toBe(10);
})->group('options', 'any');

test('Any option - body', function () {
    $request = new WP_REST_Request('POST', '/options/v1/any');
    $request->set_body_params(['post_id' => 10]);

    $response = $this->server->dispatch($request);
    expect($response->get_status())->toBe(200);
    $data = $response->get_data();
    expect($data)
        ->toBeInt()
        ->toBe(10);
})->group('options', 'any');
