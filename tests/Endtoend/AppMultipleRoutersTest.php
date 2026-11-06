<?php

/**
 * Holds tests for registering multiple FastEndpoints router's
 *
 * @since 1.0.0
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

test('REST API endpoints registered', function () {
    $routes = $this->getRoutes('my-api/v1');
    expect($routes)
        ->toBeArray()
        ->toContain('/my-api/v1')
        ->toContain('/my-api/v1/my-posts/v1/(?P<ID>[\\d]+)')
        ->toContain('/my-api/v1/my-actions/v2/middleware/on-request/(?P<action>\\w+)')
        ->toContain('/my-api/v1/my-actions/v2/middleware/on-response/(?P<action>\\w+)')
        ->toContain('/my-api/v1/my-actions/v2/permission/(?P<action>\\w+)')
        ->toContain('/my-api/v1/my-actions/v2/bool/permission/(?P<action>\\w+)');
})->group('multiple');

test('Retrieving a post by id', function () {
    $userId = $this->createUser();
    $postId = $this->createPost(['post_author' => $userId]);
    $this->authenticateAs($userId);

    [$status] = $this->request('GET', "/my-api/v1/my-posts/v1/{$postId}");
    expect($status)->toBe(200);
})->group('multiple');

test('Updating a post', function () {
    $userId = $this->createUser();
    get_user_by('id', $userId)->add_cap('edit_published_posts');
    $postId = $this->createPost(['post_author' => $userId]);
    $this->authenticateAs($userId);

    [$status] = $this->request('PUT', "/my-api/v1/my-posts/v1/{$postId}", [
        'json' => [
            'post_author' => $userId,
            'post_title' => 'My title',
            'post_status' => 'publish',
        ],
    ]);
    expect($status)->toBe(204);
})->group('multiple');

test('Deleting a post', function () {
    $userId = $this->createUser();
    get_user_by('id', $userId)->add_cap('delete_published_posts');
    $postId = $this->createPost(['post_author' => $userId]);
    $this->authenticateAs($userId);

    [$status] = $this->request('DELETE', "/my-api/v1/my-posts/v1/{$postId}");
    expect($status)->toBe(200);
})->group('multiple');

test('Trigger error in a middleware', function (string $middlewareType, string $errorMessage) {
    [$status, $data] = $this->request(
        'GET',
        "/my-api/v1/my-actions/v2/middleware/{$middlewareType}/error"
    );
    // Note: WP core's status_header() silently drops status codes it has no
    // description for, so over real HTTP the custom 469 code is served as a 200.
    // The error details still reach the consumer through the response body.
    expect($status)->toBe(200)
        ->and($data)
        ->toBe([
            'code' => 469,
            'message' => $errorMessage,
            'data' => ['status' => 469],
        ]);
})->with([
    ['on-request', 'Triggered error action before handling request'],
    ['on-response', 'Triggered error action before sending response']])->group('multiple');

test('Trigger success in a middleware', function (string $middlewareType) {
    [$status] = $this->request(
        'GET',
        "/my-api/v1/my-actions/v2/middleware/{$middlewareType}/success"
    );
    expect($status)->toBe(200);
})->with(['on-request', 'on-response'])->group('multiple');

test('Trigger no permissions in permission callback - WP Error', function () {
    [$status, $data] = $this->request('GET', '/my-api/v1/my-actions/v2/permission/notAllowed');
    expect($status)->toBe(403)
        ->and($data)
        ->toBe([
            'code' => 403,
            'message' => 'Failed permission callback check',
            'data' => ['status' => 403],
        ]);
})->group('multiple');

test('Trigger no permissions in permission callback - boolean', function () {
    [$status, $data] = $this->request('GET', '/my-api/v1/my-actions/v2/bool/permission/notAllowed');
    expect($status)->toBe(401)
        ->and($data)
        ->toBe([
            'code' => 'rest_forbidden',
            'message' => 'Sorry, you are not allowed to do that.',
            'data' => ['status' => 401],
        ]);
})->group('multiple');

test('Trigger has permissions in permission callback', function (string $endpoint) {
    [$status] = $this->request('GET', $endpoint);
    expect($status)->toBe(200);
})
    ->with(['/my-api/v1/my-actions/v2/permission/allowed', '/my-api/v1/my-actions/v2/bool/permission/allowed'])
    ->group('multiple');
