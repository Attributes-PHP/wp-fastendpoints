<?php

/**
 * Base test case for integration tests, which provides access to all the
 * WordPress testing goodies, such as factories and proper cleanup.
 *
 * @license MIT
 */

declare(strict_types=1);

namespace Attributes\Wp\FastEndpoints\Tests\Integration;

use WP_UnitTestCase;

/*
 * The WordPress PHPUnit test suite is only available when running inside
 * the wp-env test environment, which boots it before loading the tests.
 */
if (! class_exists('WP_UnitTestCase')) {
    return;
}

/**
 * Base test case for integration tests, which makes both the WordPress test
 * factories and the database cleanup between tests available.
 */
abstract class TestCase extends WP_UnitTestCase {}
