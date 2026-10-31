<?php

use Attributes\Wp\FastEndpoints\Tests\Helpers\Helpers;

/**
 * Include core bootstrap for an integration test suite
 *
 * Integration tests run inside the wp-env "tests-cli" container, which provides
 * WordPress' PHPUnit test files through the WP_TESTS_DIR environment variable and
 * a pre-generated wp-tests-config.php. Running the tests from the command line
 * without wp-env (e.g. from an IDE) requires those files to be present locally.
 */
if (! Helpers::isIntegrationTest()) {
    return;
}

$wpTestsDir = getenv('WP_TESTS_DIR') ?: dirname(__FILE__, 2).'/wp/tests/phpunit';

// Point the WordPress test suite at the wp-env provided config when running in a container.
if (getenv('WP_TESTS_DIR') && ! defined('WP_TESTS_CONFIG_FILE_PATH')) {
    define('WP_TESTS_CONFIG_FILE_PATH', '/wordpress-phpunit/wp-tests-config.php');
}

// The WP core test bootstrap requires the PHPUnit Polyfills to be loaded first.
if (! defined('WP_TESTS_PHPUNIT_POLYFILLS_PATH')) {
    define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname(__DIR__).'/vendor/yoast/phpunit-polyfills');
}

/*
 * Bootstrap WordPress. This will also load the Composer autoload file and the
 * custom autoloader for the TestCase and the mock object classes.
 */
require_once $wpTestsDir.'/includes/bootstrap.php';
