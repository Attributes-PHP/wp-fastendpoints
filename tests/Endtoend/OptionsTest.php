<?php

/**
 * Holds tests for testing injecting dependencies in an endpoint
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Attributes\Wp\FastEndpoints\Tests\Endtoend;

/*
 * The routers under test are registered plugin side by the FastEndpoints end-to-end
 * tests plugin, meaning these tests interact with the real REST API served by wp-env.
 */
uses(TestCase::class);

// Defaults

test('Default option', function (bool $isJson) {
    $post = [
        'ID' => 10,
        'post_author' => 15,
        'post_title' => 'My title',
        'post_status' => 'publish',
        'post_type' => 'post',
    ];
    // urlencoded bodies are sent with a POST request, since WordPress
    // only parses body params for POST-like requests.
    [$status, $data] = $this->request($isJson ? 'GET' : 'POST', '/options/v1/default/10', [
        'query' => ['queryParam' => '11'],
        $isJson ? 'json' : 'form' => $post,
    ]);
    expect($status)->toBe(200)
        ->and($data)
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
    [$status, $data] = $this->request('GET', '/options/v1/query', [
        'query' => [
            'post_id' => $value,
            'all_posts' => "$value,$value,$value",
        ],
    ]);
    expect($status)->toBe(200)
        ->and($data)
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
    [$status, $data] = $this->request('GET', "/options/v1/url/$value");
    expect($status)->toBe(200)
        ->and($data)
        ->toBeInt()
        ->toBe($expectedValue);
})
    ->with('url')
    ->group('options', 'url');

// Json

test('Json option', function () {
    [$status, $data] = $this->request('GET', '/options/v1/json', [
        'json' => ['post_id' => 10],
    ]);
    expect($status)->toBe(200)
        ->and($data)
        ->toBeInt()
        ->toBe(10);
})->group('options', 'json');

// Body

test('Body option', function () {
    [$status, $data] = $this->request('POST', '/options/v1/body', [
        'form' => ['post_id' => 10],
    ]);
    expect($status)->toBe(200)
        ->and($data)
        ->toBeInt()
        ->toBe(10);
})
    ->group('options', 'body');

// Header

test('Header option', function (mixed $value, mixed $expectedValue) {
    [$status, $data] = $this->request('GET', '/options/v1/header', [
        'headers' => [
            // Apache drops headers with underscores in their name, which PHP
            // in turn converts back to the expected "post_id" and "all_posts".
            'post-id' => $value,
            'all-posts' => "$value,$value,$value",
        ],
    ]);
    expect($status)->toBe(200)
        ->and($data)
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
    [$status, $data] = $this->request('GET', '/options/v1/cookie', [
        'headers' => [
            'Cookie' => "post_id={$value}; all_posts={$value},{$value},{$value}",
        ],
    ]);
    expect($status)->toBe(200)
        ->and($data)
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
    $boundary = uniqid();
    [$status, $data] = $this->request('POST', '/options/v1/file', [
        'headers' => ['Content-Type' => "multipart/form-data; boundary={$boundary}"],
        'body' => "--{$boundary}\r\n"
            ."Content-Disposition: form-data; name=\"my_file\"; filename=\"hello-world.txt\"\r\n"
            ."Content-Type: text/plain\r\n"
            ."\r\n"
            ."Hello World!\r\n"
            ."--{$boundary}--\r\n",
    ]);
    expect($status)->toBe(200)
        ->and($data)
        ->toBeArray()
        ->toMatchArray([
            'name' => 'hello-world.txt',
            'type' => 'text/plain',
            'error' => 0,
            'size' => 12,
        ]);
})->group('options', 'file');

// Any

test('Any option - query', function () {
    [$status, $data] = $this->request('POST', '/options/v1/any', [
        'query' => ['post_id' => 10],
    ]);
    expect($status)->toBe(200)
        ->and($data)
        ->toBeInt()
        ->toBe(10);
})->group('options', 'any');

test('Any option - url', function () {
    [$status, $data] = $this->request('POST', '/options/v1/any/10');
    expect($status)->toBe(200)
        ->and($data)
        ->toBeInt()
        ->toBe(10);
})->group('options', 'any');

test('Any option - json', function () {
    [$status, $data] = $this->request('POST', '/options/v1/any', [
        'json' => ['post_id' => 10],
    ]);
    expect($status)->toBe(200)
        ->and($data)
        ->toBeInt()
        ->toBe(10);
})->group('options', 'any');

test('Any option - body', function () {
    [$status, $data] = $this->request('POST', '/options/v1/any', [
        'form' => ['post_id' => 10],
    ]);
    expect($status)->toBe(200)
        ->and($data)
        ->toBeInt()
        ->toBe(10);
})->group('options', 'any');
