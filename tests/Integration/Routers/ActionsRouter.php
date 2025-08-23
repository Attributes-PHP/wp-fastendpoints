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
use Attributes\Wp\FastEndpoints\Tests\Integration\Routers\Middlewares\OnRequestErrorActionMiddleware;
use Attributes\Wp\FastEndpoints\Tests\Integration\Routers\Middlewares\OnResponseErrorActionMiddleware;

$router = new Router('my-actions', 'v2');

// Triggers onRequest middleware
$router->get('/middleware/on-request/(?P<action>\w+)', function (): bool {
    return true;
})
    ->middleware(new OnRequestErrorActionMiddleware);

// Triggers onResponse middleware
$router->get('/middleware/on-response/(?P<action>\w+)', function (): bool {
    return true;
})
    ->middleware(new OnResponseErrorActionMiddleware);

$triggerPermissionCallback = function (string $action) {
    if ($action !== 'allowed') {
        return new WpError(403, 'Failed permission callback check');
    }

    return true;
};

// Triggers permission callable
$router->get('/permission/(?P<action>\w+)', function (): bool {
    return true;
})
    ->permission($triggerPermissionCallback);

return $router;
