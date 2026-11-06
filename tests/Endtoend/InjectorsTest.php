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

test('Inject Post via ID', function (?array $jsonBody) {
    $userId = $this->createUser();
    $postId = $this->createPost(['post_author' => $userId, 'post_title' => 'My title']);

    $args = ['query' => ['post' => $postId]];
    if ($jsonBody) {
        $args['json'] = $jsonBody;
    }
    [$status, $data] = $this->request('GET', '/injectors/v1/retrieve', $args);
    expect($status)->toBe(200)
        ->and($data)
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
    $bodyParams = [
        'ID' => 10,
        'post_author' => 15,
        'post_title' => 'My title',
        'post_status' => 'publish',
        'post_type' => 'post',
    ];
    // urlencoded bodies are sent with a POST request, since WordPress
    // only parses body params for POST-like requests.
    [$status, $data] = $this->request($isJson ? 'GET' : 'POST', '/injectors/v1/retrieve', [
        $isJson ? 'json' : 'form' => $bodyParams,
    ]);
    expect($status)->toBe(200)
        ->and($data)
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
    [$status, $data] = $this->request('GET', '/injectors/v1/hello-world');
    expect($status)->toBe(200)
        ->and($data)
        ->toBeString()
        ->toBe('Hello World!Hello World!');
})->group('injectors');
