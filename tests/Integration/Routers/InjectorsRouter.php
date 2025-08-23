<?php

/**
 * Holds an example of FastEndpoints router with injectors
 *
 * @since 1.0.0
 *
 * @license MIT
 */

declare(strict_types=1);

use Attributes\Validation\Validatable;
use Attributes\Wp\FastEndpoints\Options\Inject;
use Attributes\Wp\FastEndpoints\Router;
use Attributes\Wp\FastEndpoints\Tests\Models\Post;

$router = new Router('injectors', 'v1');

$router->inject('post', function (int|Post $post, #[Inject] Validatable $validator) {
    if ($post instanceof Post) {
        return $post;
    }

    $post = get_post($post, 'ARRAY_A');

    return $validator->validate($post, Post::class);
});

$router->get('retrieve', function (#[Inject] Post $post) {
    return $post;
})
    ->returns(Post::class);

if (! defined('INJECTORS')) {
    define('INJECTORS', true);

    function hello_world(): string
    {
        return 'Hello World!';
    }
}

$router->get('hello-world', function (#[Inject('hello_world')] string $helloWorld, #[Inject] string $hello_world) {
    return $helloWorld.$hello_world;
});

return $router;
