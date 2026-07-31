<?php
/**
 * PHPUnit bootstrap — loads Composer autoloader + Brain Monkey
 * for testing WordPress plugin code in isolation.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Brain Monkey: WP function stubs for unit tests.
\Brain\Monkey\setUp();

// Autoload stubs for WordPress functions used by the codebase.
require_once __DIR__ . '/stubs/wordpress.php';
require_once __DIR__ . '/stubs/wpdb.php';
