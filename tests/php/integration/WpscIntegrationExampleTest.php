<?php
/**
 * Example integration test — the copyable pattern for the integration tier.
 *
 * Runs under a real WordPress runtime (WP_UnitTestCase: real hook system,
 * options, transients, database). Use this as the template for any test that
 * genuinely needs WordPress rather than the WordPress-free smoke tier.
 *
 * The procedural caching files are loaded by tests/php/bootstrap-integration.php.
 *
 * @package automattic/wp-super-cache
 */
class WpscIntegrationExampleTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		$_SERVER['REQUEST_URI']         = '/integration/example/';
		$GLOBALS['cached_direct_pages'] = array();
		unset( $_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO'] );
	}

	/**
	 * The *real* WordPress hook system drives supercache_filename() here (not the
	 * smoke-tier filter double), and the #1050 sanitisation still reduces hostile
	 * filter output to a single safe path segment.
	 */
	public function test_real_filter_is_applied_and_sanitised() {
		add_filter(
			'supercache_filename_str',
			static function () {
				return '../evil/../path';
			}
		);

		$this->assertSame( 'indexevilpath.html', supercache_filename() );
	}

	/**
	 * Confirms the database-backed WordPress runtime is available — an options
	 * round-trip is the kind of behaviour the smoke tier deliberately cannot
	 * cover.
	 */
	public function test_options_roundtrip_through_database() {
		update_option( 'wpsc_integration_probe', 'cached-value' );
		$this->assertSame( 'cached-value', get_option( 'wpsc_integration_probe' ) );
	}
}
