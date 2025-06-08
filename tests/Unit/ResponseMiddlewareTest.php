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

use Attributes\Serialization\Exceptions\SerializeException;
use Attributes\Serialization\Serializable;
use Attributes\Serialization\Serializer;
use Attributes\Wp\FastEndpoints\Endpoint;
use Attributes\Wp\FastEndpoints\Helpers\WpError;
use Attributes\Wp\FastEndpoints\Middlewares\ResponseMiddleware;
use Attributes\Wp\FastEndpoints\Tests\Helpers\Helpers;
use Attributes\Wp\FastEndpoints\Tests\Models\Post;
use Attributes\Wp\FastEndpoints\Tests\Models\PostsArr;
use Attributes\Wp\FastEndpoints\Tests\Models\Status;
use Attributes\Wp\FastEndpoints\Tests\Models\Type;
use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use DateTime;
use Mockery;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Monkey\setUp();
});

afterEach(function () {
    Monkey\tearDown();
});

// returns

test('Retrieves a single post', function () {
    $endpoint = new Endpoint('POST', '/returns/single', function () {
        return [
            'ID' => 10,
            'password' => 'ignored',
            'post_author' => 15,
            'post_title' => 'Hello World',
            'post_status' => 'publish',
            'post_type' => 'post',
        ];
    });
    $endpoint->returns(Post::class);

    $req = Mockery::mock(WP_REST_Request::class);
    expect($endpoint->callback($req))
        ->toBeInstanceOf(WP_REST_Response::class)
        ->toHaveProperty('status', 200)
        ->toHaveProperty('data', [
            'ID' => 10,
            'post_author' => 15,
            'post_title' => 'Hello World',
            'post_status' => 'publish',
            'post_type' => 'post',
        ]);
})->group('response', 'middleware');

test('Retrieves multiple posts', function () {
    $endpoint = new Endpoint('GET', '/returns/multiple', function () {
        $allPosts = [];
        for ($i = 0; $i < 3; $i++) {
            $allPosts[] = [
                'ID' => 10 + $i,
                'password' => 'ignored',
                'post_author' => 15 + $i,
                'post_title' => 'Hello World',
                'post_status' => 'publish',
                'post_type' => 'post',
            ];
        }

        return ['posts' => $allPosts];
    });
    $endpoint->returns(new class
    {
        public PostsArr $posts;
    });

    $req = Mockery::mock(WP_REST_Request::class);
    expect($endpoint->callback($req))
        ->toBeInstanceOf(WP_REST_Response::class)
        ->toHaveProperty('status', 200)
        ->toHaveProperty('data', ['posts' => [
            [
                'ID' => 10,
                'post_author' => 15,
                'post_title' => 'Hello World',
                'post_status' => 'publish',
                'post_type' => 'post',
            ],
            [
                'ID' => 11,
                'post_author' => 16,
                'post_title' => 'Hello World',
                'post_status' => 'publish',
                'post_type' => 'post',
            ],
            [
                'ID' => 12,
                'post_author' => 17,
                'post_title' => 'Hello World',
                'post_status' => 'publish',
                'post_type' => 'post',
            ],
        ]]);
})->group('response', 'middleware');

test('Retrieves data from to_array function', function () {
    $endpoint = new Endpoint('GET', '/returns/to_array', function () {
        $newPost = new class extends Post
        {
            public function to_array(): array
            {
                return [
                    'ID' => 10,
                    'post_author' => 15,
                    'post_title' => 'This is from to_array',
                    'post_status' => 'draft',
                    'post_type' => 'page',
                ];
            }
        };
        $newPost->id = 0;
        $newPost->postAuthor = 0;
        $newPost->postType = Type::POST;
        $newPost->postTitle = 'Title';
        $newPost->postStatus = Status::PRIVATE;

        return $newPost;
    });
    $endpoint->returns(Post::class);

    $req = Mockery::mock(WP_REST_Request::class);
    expect($endpoint->callback($req))
        ->toBeInstanceOf(WP_REST_Response::class)
        ->toHaveProperty('status', 200)
        ->toHaveProperty('data', [
            'ID' => 10,
            'post_author' => 15,
            'post_title' => 'This is from to_array',
            'post_status' => 'draft',
            'post_type' => 'page',
        ]);
})->group('response', 'middleware');

test('Ignores response validation via early return', function (WP_REST_Response|WP_Error $earlyResponse) {
    $endpoint = new Endpoint('POST', '/returns', function () use ($earlyResponse) {
        return $earlyResponse;
    });
    $endpoint->returns(Post::class);

    $req = Mockery::mock(WP_REST_Request::class);
    expect($endpoint->callback($req))->toBe($earlyResponse);
})
    ->with([
        new WP_REST_Response(status: 204),
        new WP_Error(code: 404, message: 'Not Found'),
    ])
    ->group('response', 'middleware');

test('Ignores response validation via hook', function (WP_REST_Response|WP_Error $earlyResponse) {
    Filters\expectApplied('fastendpoints_response_data')->andReturn($earlyResponse);
    $req = Mockery::mock(WP_REST_Request::class);
    $res = Mockery::mock(WP_REST_Response::class);
    $res->expects()->get_data()->andReturn([1, 2, 3, 4]);
    $serializer = new Serializer;

    $responseMiddleware = new ResponseMiddleware(Post::class);
    $result = $responseMiddleware->onResponse($req, $res, $serializer);
    expect($result)->toBe($earlyResponse);
})
    ->with([
        new WP_REST_Response(status: 204),
        new WP_Error(code: 404, message: 'Not Found'),
    ])
    ->group('response', 'middleware');

test('Invalid response data', function (mixed $invalidData) {
    $endpoint = new Endpoint('GET', '/returns/invalid', function () use ($invalidData) {
        return $invalidData;
    });
    $endpoint->returns(Post::class);

    $req = Mockery::mock(WP_REST_Request::class);
    $errorMessage = is_object($invalidData) || is_array($invalidData) ? 'Invalid response' : 'Invalid response data. Expected \'array\' or \'object\' but '.gettype($invalidData).' given.';
    expect($endpoint->callback($req))
        ->toBeInstanceOf(WpError::class)
        ->toHaveProperty('code', 500)
        ->toHaveProperty('message', $errorMessage);
})
    ->with([true, false, null, 'yup', [['ID' => 10]], new DateTime])
    ->group('response', 'middleware');

test('Unable to serialise', function () {
    Filters\expectApplied('fastendpoints_response_data')->andReturnFirstArg();
    $req = Mockery::mock(WP_REST_Request::class);
    $res = Mockery::mock(WP_REST_Response::class);
    $res->expects()->get_data()->andReturn(new Post);
    $serializer = Mockery::mock(Serializable::class);
    $serializer->shouldReceive('serialize')->andThrow(new SerializeException('reason'));

    $responseMiddleware = new ResponseMiddleware(Post::class);
    $result = $responseMiddleware->onResponse($req, $res, $serializer);
    expect($result)
        ->toBeInstanceOf(WpError::class)
        ->toHaveProperty('code', 500)
        ->toHaveProperty('message', 'Unable to serialize response due to reason');
})->group('response', 'middleware');

test('Caches validator', function () {
    $responseMiddleware = new ResponseMiddleware(Post::class);
    expect(Helpers::getNonPublicClassProperty($responseMiddleware, 'validator'))->toBeNull();

    $validator = Helpers::invokeNonPublicClassMethod($responseMiddleware, 'getValidator');
    expect(Helpers::getNonPublicClassProperty($responseMiddleware, 'validator'))->toBe($validator);

    $secondValidator = Helpers::invokeNonPublicClassMethod($responseMiddleware, 'getValidator');
    expect($secondValidator)->toBe($validator);
})->group('response', 'middleware');
