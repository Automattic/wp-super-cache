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

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

// wp-phpunit's autoload (__loaded.php) sets WP_PHPUNIT__DIR; honour an explicit
// WP_TESTS_DIR override, then fall back to the bundled library path.
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = getenv( 'WP_PHPUNIT__DIR' );
}
if ( ! $_tests_dir ) {
	$_tests_dir = dirname( __DIR__, 2 ) . '/vendor/wp-phpunit/wp-phpunit';
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
 * Load the procedural caching engine and the main plugin file once WordPress
 * (with the real hook system) is available, so their functions are defined under
 * a genuine WP runtime.
 */
function _wpsc_manually_load_procedural_files() {
	$plugin_dir = dirname( __DIR__, 2 );

	// Guard against a redeclaration fatal if the plugin is ever active in the
	// tests env and loads wp-cache-phase2.php via a different (WPCACHEHOME) path,
	// which would defeat require_once's path-based idempotency.
	if ( ! function_exists( 'supercache_filename' ) ) {
		require_once $plugin_dir . '/wp-cache-phase2.php';
	}

	// Load the main plugin file so the admin / lifecycle procedural functions
	// (cache-file stats, htaccess generation, settings-form updaters, preload,
	// ...) are defined under a real WP runtime. wp-cache.php pulls in the inc/
	// files and runs wpsc_init() at top level. Guarded for the same reason as
	// above (plugin active in the tests env loading via a different path).
	if ( ! function_exists( 'wpsc_init' ) ) {
		require_once $plugin_dir . '/wp-cache.php';
	}
}
tests_add_filter( 'muplugins_loaded', '_wpsc_manually_load_procedural_files' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
