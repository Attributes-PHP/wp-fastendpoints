<?php

/**
 * Holds an example of FastEndpoints router
 *
 * @since 1.0.0
 *
 * @license MIT
 */

declare(strict_types=1);

use Attributes\Wp\FastEndpoints\Helpers\WpError;
use Attributes\Wp\FastEndpoints\Router;
use Attributes\Wp\FastEndpoints\Tests\Models\Post;

$router = new Router('my-posts', 'v1');

// Fetches a single post
$router->get('(?P<ID>[\d]+)', function (int $ID) {
    return get_post($ID);
})
    ->returns(Post::class)
    ->hasCap('read');

// Updates a post
$router->put('(?P<ID>[\d]+)', function (int|float $ID, Post $post, WP_REST_Response $response) {
    $response->set_status(204);
    $post->id = $ID;
    $error = wp_update_post($post->serialize(), true);

    return is_wp_error($error) ? $error : get_post($post->id);
})
    ->returns(Post::class)
    ->hasCap('edit_post', '<ID>');

// Deletes a post
$router->delete('(?P<ID>[\d]+)', function (int $ID) {
    $result = wp_delete_post($ID);
    if ($result === false or $result === null) {
        return new WpError(500, 'Unable to delete post');
    }

    return esc_html__('Post deleted with success');
})
    ->hasCap('delete_post', '<ID>');

return $router;
