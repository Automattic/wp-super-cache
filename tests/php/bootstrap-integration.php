<?php
/**
 * Integration-tier bootstrap (local only, run via `make test-integration`).
 *
 * Loads the WordPress PHPUnit test library (shipped by wp-phpunit/wp-phpunit) and
 * the WP Super Cache procedural files under a real WordPress runtime backed by a
 * database. Unlike the smoke tier this does NOT stub apply_filters(): WordPress
 * core is loaded first, so the real hook system is in play. Tests here may use
 * options, transients, hooks, and the filesystem against a real install.
 *
 * @package automattic/wp-super-cache
 */

// The integration tier runs against an isolated PHPUnit 9 toolchain (see
// tests/php/tools/) because WordPress's WP_UnitTestCase is not PHPUnit 10+
// compatible. `make test-integration` points WPSC_INTEGRATION_AUTOLOAD at that
// toolchain's autoloader; fall back to the root vendor for other setups.
$_autoload = getenv( 'WPSC_INTEGRATION_AUTOLOAD' );
if ( ! $_autoload || ! file_exists( $_autoload ) ) {
	$_autoload = dirname( __DIR__, 2 ) . '/vendor/autoload.php';
}
require_once $_autoload;

// wp-phpunit's autoload (__loaded.php) sets WP_PHPUNIT__DIR; honour an explicit
// WP_TESTS_DIR override, then fall back to the toolchain's bundled library path.
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = getenv( 'WP_PHPUNIT__DIR' );
}
if ( ! $_tests_dir ) {
	$_tests_dir = __DIR__ . '/tools/vendor/wp-phpunit/wp-phpunit';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- test bootstrap, no WP runtime yet.
	fwrite(
		STDERR,
		"Could not find the WordPress test library in {$_tests_dir}.\n"
		. "Run the integration suite via `make test-integration` (it sets up wp-env).\n"
	);
	exit( 1 );
}

// Give access to tests_add_filter() before WordPress is loaded.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Load the procedural caching engine once WordPress (with the real hook system)
 * is available, so its functions are defined under a genuine WP runtime.
 */
function _wpsc_manually_load_procedural_files() {
	require_once dirname( __DIR__, 2 ) . '/wp-cache-phase2.php';
}
tests_add_filter( 'muplugins_loaded', '_wpsc_manually_load_procedural_files' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
