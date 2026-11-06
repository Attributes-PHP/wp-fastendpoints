<?php

/**
 * Holds tests for registering a single FastEndpoints router
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
    $routes = $this->getRoutes('my-posts/v1');
    expect($routes)
        ->toBeArray()
        ->toContain('/my-posts/v1')
        ->toContain('/my-posts/v1/(?P<ID>[\\d]+)');
})->group('single');

test('Retrieving a post by id', function () {
    $userId = $this->createUser();
    $postId = $this->createPost(['post_author' => $userId, 'post_title' => 'My title']);
    $this->authenticateAs($userId);

    [$status, $data] = $this->request('GET', "/my-posts/v1/{$postId}");
    expect($status)->toBe(200)
        ->and($data)
        ->toBeArray()
        ->toBe([
            'ID' => $postId,
            'post_author' => $userId,
            'post_title' => 'My title',
            'post_status' => 'publish',
            'post_type' => 'post',
        ]);
})->group('single');

test('Trying to retrieve a post without permissions', function () {
    $postId = $this->createPost();

    [$status, $data] = $this->request('GET', "/my-posts/v1/{$postId}");
    expect($status)->toBe(403)
        ->and($data)
        ->toBe([
            'code' => 403,
            'message' => 'Not enough permissions',
            'data' => ['status' => 403],
        ]);
})->group('single');

test('Updating a post', function () {
    $userId = $this->createUser();
    get_user_by('id', $userId)->add_cap('edit_published_posts');
    $postId = $this->createPost(['post_author' => $userId]);
    $this->authenticateAs($userId);

    [$status] = $this->request('PUT', "/my-posts/v1/{$postId}", [
        'json' => [
            'post_author' => $userId,
            'post_title' => 'My title',
            'post_status' => 'publish',
        ],
    ]);
    // A 204 response never carries a response body, so the update
    // is verified against the database instead.
    expect($status)->toBe(204);
    $post = get_post($postId);
    expect($post->post_author)->toBe((string) $userId)
        ->and($post->post_title)->toBe('My title')
        ->and($post->post_status)->toBe('publish');
})->group('single');

test('Trying to update a post without permissions', function () {
    $allUserIds = [$this->createUser(), $this->createUser()];
    get_user_by('id', $allUserIds[0])->add_cap('edit_published_posts');
    $postId = $this->createPost(['post_author' => $allUserIds[1]]);
    $this->authenticateAs($allUserIds[1]);

    [$status, $data] = $this->request('PUT', "/my-posts/v1/{$postId}");
    expect($status)->toBe(403)
        ->and($data)
        ->toBe([
            'code' => 403,
            'message' => 'Not enough permissions',
            'data' => ['status' => 403],
        ]);
})->group('single');

test('Deleting a post', function () {
    $userId = $this->createUser();
    get_user_by('id', $userId)->add_cap('delete_published_posts');
    $postId = $this->createPost(['post_author' => $userId]);
    $this->authenticateAs($userId);

    [$status, $data] = $this->request('DELETE', "/my-posts/v1/{$postId}");
    expect($status)->toBe(200)
        ->and($data)
        ->toBeString()
        ->toBe('Post deleted with success');
})->group('single');

test('Trying to delete a post without permissions', function () {
    $allUserIds = [$this->createUser(), $this->createUser()];
    get_user_by('id', $allUserIds[0])->add_cap('delete_published_posts');
    $postId = $this->createPost(['post_author' => $allUserIds[1]]);
    $this->authenticateAs($allUserIds[0]);

    [$status, $data] = $this->request('DELETE', "/my-posts/v1/{$postId}");
    expect($status)->toBe(403)
        ->and($data)
        ->toBe([
            'code' => 403,
            'message' => 'Not enough permissions',
            'data' => ['status' => 403],
        ]);
})->group('single');
