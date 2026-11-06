<?php

/**
 * Plugin Name: FastEndpoints end-to-end tests
 * Description: Registers the REST API routers used by the end-to-end test suite. Not meant for production use.
 * Version: 1.0.0
 * License: MIT
 *
 * @license MIT
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

if (! defined('FASTENDPOINTS_ENDTOEND_TESTS')) {
    return;
}

// The WordPress PHPUnit test suite registers its own routers in-process,
// so this plugin stays inert when loaded alongside it.
if (function_exists('tests_add_filter')) {
    return;
}

use Attributes\Wp\FastEndpoints\Router;

require_once __DIR__.'/vendor/autoload.php';

// The end-to-end tests authenticate their requests with application passwords.
add_filter('wp_is_application_passwords_supported', '__return_true');

$routersDir = __DIR__.'/tests/Endtoend/Routers/';

// Serves the /my-posts/v1 endpoints.
(require $routersDir.'PostsRouter.php')->register();

// Serves the /my-api/v1 endpoints, composed of multiple routers.
$postsRouter = require $routersDir.'PostsRouter.php';
$actionsRouter = require $routersDir.'ActionsRouter.php';
$apiRouter = new Router('my-api', 'v1');
$apiRouter->includeRouter($postsRouter);
$apiRouter->includeRouter($actionsRouter);
$apiRouter->register();

// Serves the /options/v1 endpoints.
(require $routersDir.'OptionsRouter.php')->register();

// Serves the /injectors/v1 endpoints.
(require $routersDir.'InjectorsRouter.php')->register();
