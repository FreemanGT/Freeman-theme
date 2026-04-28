<?php
/**
 * PHPUnit bootstrap for Freeman Digital tests.
 *
 * Expects the standard WordPress PHPUnit test harness to be available at the
 * path pointed to by WP_TESTS_DIR env var (set up by `bin/install-wp-tests.sh`
 * which ships with `wp scaffold plugin-tests` — generate that once locally).
 */

$wp_tests_dir = getenv('WP_TESTS_DIR');
if (!$wp_tests_dir) {
    $wp_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (!file_exists($wp_tests_dir . '/includes/functions.php')) {
    echo "WordPress test suite not found at: {$wp_tests_dir}\n";
    echo "Run: bash bin/install-wp-tests.sh wordpress_test root '' localhost latest\n";
    exit(1);
}

require_once $wp_tests_dir . '/includes/functions.php';

tests_add_filter('muplugins_loaded', function () {
    require dirname(__DIR__) . '/freeman-digital.php';
});

require $wp_tests_dir . '/includes/bootstrap.php';
