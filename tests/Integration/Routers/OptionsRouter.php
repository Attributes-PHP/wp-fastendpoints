<?php

/**
 * Holds an example of FastEndpoints router with injectors
 *
 * @since 1.0.0
 *
 * @license MIT
 */

declare(strict_types=1);

use Attributes\Options\Alias;
use Attributes\Options\AliasGenerator;
use Attributes\Wp\FastEndpoints\Options\Any;
use Attributes\Wp\FastEndpoints\Options\Body;
use Attributes\Wp\FastEndpoints\Options\Cookie;
use Attributes\Wp\FastEndpoints\Options\File;
use Attributes\Wp\FastEndpoints\Options\Header;
use Attributes\Wp\FastEndpoints\Options\Json;
use Attributes\Wp\FastEndpoints\Options\Query;
use Attributes\Wp\FastEndpoints\Options\Url;
use Attributes\Wp\FastEndpoints\Router;
use Attributes\Wp\FastEndpoints\Tests\Models\Post;

$router = new Router('options', 'v1');

$router->get('default/(?P<urlParam>[\d]+)', function (int $urlParam, int $queryParam, Post $post): array {
    return [
        'urlParam' => $urlParam,
        'queryParam' => $queryParam,
        'post' => $post->serialize(),
    ];
});

$router->get('query', #[AliasGenerator('snake')] function (#[Query] int $postId, #[Query] array $allPosts = []): array {
    return [
        'post_id' => $postId,
        'all_posts' => $allPosts,
    ];
});

$router->get('url/(?P<post_id>[\d]+)', function (#[Url] int $post_id): int {
    return $post_id;
});

$router->get('json', function (#[Json, Alias('post_id')] int $id): int {
    return $id;
});

$router->get('body', #[AliasGenerator('camel')] function (#[Body, Alias('post_id')] int $postId): int {
    return $postId;
});

$router->get('header', #[AliasGenerator('snake')] function (#[Header] int $postId, #[Header] array $allPosts = []): array {
    return [
        'post_id' => $postId,
        'all_posts' => $allPosts,
    ];
});

$router->get('cookie', #[AliasGenerator('snake')] function (#[Cookie] int $postId, #[Cookie] array $allPosts = []): array {
    return [
        'post_id' => $postId,
        'all_posts' => $allPosts,
    ];
});

$router->post('file', function (#[File] array $my_file) {
    return $my_file;
});

$router->post('any', #[AliasGenerator('snake')] function (#[Any] int $postId): int {
    return $postId;
});

$router->post('any/(?P<postId>[\d]+)', function (#[Any] int $postId): int {
    return $postId;
});

return $router;
