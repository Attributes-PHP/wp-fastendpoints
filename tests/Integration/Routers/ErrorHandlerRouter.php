<?php

/**
 * Holds a router with custom error handlers
 *
 * @license MIT
 */

declare(strict_types=1);

use Attributes\Wp\FastEndpoints\Helpers\WpError;
use Attributes\Wp\FastEndpoints\Router;

if (! defined('ERROR_HANDLER')) {
    define('ERROR_HANDLER', 'true');

    class CustomException extends Exception {}
    class NoHandlerException extends Exception {}
}

$router = new Router('error-handler', 'v1');
$router->onException(CustomException::class, fn (CustomException $e) => new WpError(550, 'CustomException handler'));
$router->onException(Exception::class, fn () => new WpError(570, 'Exception handler'));

// When an exception occurs during the handler
$router->post('/handler', function (): bool {
    throw new Exception('Something went wrong');
});

$router->post('/handler/custom-exception', function (): bool {
    throw new NoHandlerException('Something went wrong');
});

$router->post('/handler/ignore-all-errors', function (): bool {
    throw new Exception('Something went wrong');
})->onException(Exception::class, fn () => 'Exception ignored');

// Permission handler error
$router->post('/permission', fn () => true)
    ->permission(fn () => throw new Exception('Something went wrong'));

$router->post('/permission/custom-exception', fn () => true)
    ->permission(fn () => throw new Exception('Something went wrong'));

$router->post('/permission/ignore-all-errors', fn () => null)
    ->permission(fn () => throw new Exception('Something went wrong'))
    ->onException(Exception::class, fn () => 'Exception ignored');

// Exception handler error

$router->post('/exception-handler', fn () => throw new Exception('Handler exception'))
    ->onException(Exception::class, fn () => throw new Exception('Something went wrong'));

$router->post('/exception-handler/missing-field', fn () => throw new Exception('Handler exception'))
    ->onException(Exception::class, fn (string $missingField) => true);

return $router;
