<?php

/**
 * Holds tests for all options classes.
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Attributes\Wp\FastEndpoints\Tests\Unit\Schemas;

use Attributes\Wp\FastEndpoints\Options\Any;
use Attributes\Wp\FastEndpoints\Options\Body;
use Attributes\Wp\FastEndpoints\Options\Cookie;
use Attributes\Wp\FastEndpoints\Options\File;
use Attributes\Wp\FastEndpoints\Options\Header;
use Attributes\Wp\FastEndpoints\Options\Inject;
use Attributes\Wp\FastEndpoints\Options\Json;
use Attributes\Wp\FastEndpoints\Options\Query;
use Attributes\Wp\FastEndpoints\Options\Url;
use Mockery;
use WP_REST_Request;

// Query

test('Fetch query params', function () {
    $from = new Query;
    $request = Mockery::mock(WP_REST_Request::class);
    $request->shouldReceive('get_query_params')
        ->once()
        ->andReturn(['post_id' => 10]);
    $params = $from->getParams($request);
    expect($params)
        ->toBeArray()
        ->toMatchArray(['post_id' => 10]);
})->group('options');

// Url

test('Fetch url params', function () {
    $from = new Url;
    $request = Mockery::mock(WP_REST_Request::class);
    $request->shouldReceive('get_url_params')
        ->once()
        ->andReturn(['post_id' => 10]);
    $params = $from->getParams($request);
    expect($params)
        ->toBeArray()
        ->toMatchArray(['post_id' => 10]);
})->group('options');

// Json

test('Fetch json params', function () {
    $from = new Json;
    $request = Mockery::mock(WP_REST_Request::class);
    $request->shouldReceive('get_json_params')
        ->once()
        ->andReturn(['post_id' => 10]);
    $params = $from->getParams($request);
    expect($params)
        ->toBeArray()
        ->toMatchArray(['post_id' => 10]);
})->group('options');

// Body

test('Fetch body params', function () {
    $from = new Body;
    $request = Mockery::mock(WP_REST_Request::class);
    $request->shouldReceive('get_body_params')
        ->once()
        ->andReturn(['post_id' => 10]);
    $params = $from->getParams($request);
    expect($params)
        ->toBeArray()
        ->toMatchArray(['post_id' => 10]);
})->group('options');

// Header

test('Fetch header params', function () {
    $from = new Header;
    $request = Mockery::mock(WP_REST_Request::class);
    $request->shouldReceive('get_headers')
        ->once()
        ->andReturn(['post_id' => true]);
    $request->shouldReceive('get_header')
        ->once()
        ->with('post_id')
        ->andReturn(10);
    $params = $from->getParams($request);
    expect($params)
        ->toBeArray()
        ->toMatchArray(['post_id' => 10]);
})->group('options');

// Cookie

test('Fetch cookie params', function () {
    $from = new Cookie;

    $request = Mockery::mock(WP_REST_Request::class);
    $params = $from->getParams($request);
    expect($params)
        ->toBeArray()
        ->toMatchArray($_COOKIE);
})->group('options');

// File

test('Fetch file params', function () {
    $from = new File;
    $request = Mockery::mock(WP_REST_Request::class);
    $request->shouldReceive('get_file_params')
        ->once()
        ->andReturn(['my-file' => ['full_path' => ['hello-world.txt']]]);
    $params = $from->getParams($request);
    expect($params)
        ->toBeArray()
        ->toMatchArray(['my-file' => ['full_path' => ['hello-world.txt']]]);
})->group('options');

// Any

test('Fetch any params', function () {
    $from = new Any;
    $request = Mockery::mock(WP_REST_Request::class);
    $request->shouldReceive('get_params')
        ->once()
        ->andReturn(['post_id' => 10]);
    $params = $from->getParams($request);
    expect($params)
        ->toBeArray()
        ->toMatchArray(['post_id' => 10]);
})->group('options');

// Inject

test('Inject option', function (?string $name) {
    $inject = new Inject($name);
    expect($inject->getName())->toBe($name);
})
    ->with([null, 'my-name'])
    ->group('options');
