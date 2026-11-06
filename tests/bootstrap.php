<?php

/**
 * Include core bootstrap for an integration test suite
 *
 * WordPress itself is provided by wp-env, which sets the WP_TESTS_DIR
 * environment variable inside the container. To run the integration tests:
 *
 * npm install
 * npm run test:integration
 */
$argv = $GLOBALS['argv'] ?? [];

/*
 * The end-to-end tests talk HTTP with the live WordPress site served by
 * wp-env, so load WordPress itself instead of the testing framework.
 */
$wpLoad = dirname(__DIR__, 4).'/wp-load.php';
if (in_array('--group=endtoend', $argv, true)) {
    if (! file_exists($wpLoad)) {
        echo PHP_EOL,
        'ERROR: End-to-end tests require the wp-env test environment to be running.'.PHP_EOL,
        'Start it with: npm install && npm run test:endtoend'.PHP_EOL,
        PHP_EOL;
        exit(1);
    }

    require_once $wpLoad;

    return;
}

// Bootstrap WordPress when the wp-env test environment is available.
if (getenv('WP_TESTS_DIR') !== false && file_exists(getenv('WP_TESTS_DIR').'/includes/bootstrap.php')) {
    require_once getenv('WP_TESTS_DIR').'/includes/bootstrap.php';

    return;
}

// Pointed at integration tests while wp-env isn't running, meaning WordPress is nowhere to be found.
if (in_array('--group=integration', $argv, true)) {
    echo PHP_EOL,
    'ERROR: Integration tests require the wp-env test environment to be running.'.PHP_EOL,
    'Start it with: npm install && npm run test:integration'.PHP_EOL,
    PHP_EOL;
    exit(1);
}
