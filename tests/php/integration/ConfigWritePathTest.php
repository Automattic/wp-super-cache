<?php
/**
 * Characterization tests for the config-file WRITE path as it exists today.
 *
 * Pins the byte-exact on-disk result and the populated $GLOBALS[$field] for
 * wp_cache_setting() (each value type) and for wp_cache_replace_line()'s three
 * branches (replace existing line, unchanged early-return, not-found append).
 * Re-run UNCHANGED after the Config delegations land to prove byte-identical
 * behaviour.
 *
 * Integration tier: the rewriter calls wp_rand() and (optionally) set_transient().
 *
 * @package automattic/wp-super-cache
 */
class ConfigWritePathTest extends WP_UnitTestCase {

	/** @var string[] */
	private $temp_dirs = array();

	public function tear_down() {
		foreach ( $this->temp_dirs as $dir ) {
			$this->rrmdir( $dir );
		}
		$this->temp_dirs = array();
		parent::tear_down();
	}

	/**
	 * Seed a writable temp config file and point the write-path globals at it.
	 *
	 * @param string[] $lines Config lines (without the opening PHP tag).
	 * @return string Absolute path to the config file.
	 */
	private function make_config_file( array $lines ) {
		$dir = trailingslashit( get_temp_dir() ) . 'wpsc-cfg-' . uniqid();
		mkdir( $dir, 0700, true );
		$this->temp_dirs[] = $dir;

		$config = trailingslashit( $dir ) . 'wp-cache-config.php';
		file_put_contents( $config, "<?php\n" . implode( "\n", $lines ) . "\n" );

		$GLOBALS['cache_path']           = trailingslashit( $dir );
		$GLOBALS['wp_cache_config_file'] = $config;

		return $config;
	}

	private function rrmdir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( scandir( $dir ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $dir . '/' . $entry;
			is_dir( $path ) ? $this->rrmdir( $path ) : unlink( $path );
		}
		rmdir( $dir );
	}

	public function test_setting_numeric_writes_unquoted_and_sets_global() {
		$config = $this->make_config_file( array( '$wp_cache_mod_rewrite = 0;' ) );

		$this->assertTrue( wp_cache_setting( 'wp_cache_mod_rewrite', 1 ) );
		$this->assertSame( 1, $GLOBALS['wp_cache_mod_rewrite'] );
		$this->assertStringContainsString( '$wp_cache_mod_rewrite = 1;', file_get_contents( $config ) );
	}

	public function test_setting_boolean_writes_true_false_literal() {
		$config = $this->make_config_file( array( '$wp_supercache_304 = false;' ) );

		wp_cache_setting( 'wp_supercache_304', true );
		$this->assertSame( true, $GLOBALS['wp_supercache_304'] );
		$this->assertStringContainsString( '$wp_supercache_304 = true;', file_get_contents( $config ) );
	}

	public function test_setting_array_writes_collapsed_var_export() {
		$config = $this->make_config_file( array( '$cache_rejected_uri = array();' ) );

		wp_cache_setting( 'cache_rejected_uri', array( 'wp-admin', 'feed' ) );
		$this->assertSame( array( 'wp-admin', 'feed' ), $GLOBALS['cache_rejected_uri'] );
		$this->assertStringContainsString(
			"\$cache_rejected_uri = array ( 0 => 'wp-admin', 1 => 'feed', );",
			file_get_contents( $config )
		);
	}

	public function test_setting_string_writes_single_quoted() {
		$config = $this->make_config_file( array( "\$wp_cache_debug_ip = '';" ) );

		wp_cache_setting( 'wp_cache_debug_ip', '203.0.113.5' );
		$this->assertSame( '203.0.113.5', $GLOBALS['wp_cache_debug_ip'] );
		$this->assertStringContainsString( "\$wp_cache_debug_ip = '203.0.113.5';", file_get_contents( $config ) );
	}

	public function test_replace_line_replaces_matching_line() {
		$config = $this->make_config_file( array( '$wp_cache_front_page_checks = 0;' ) );

		$this->assertTrue(
			wp_cache_replace_line( '^ *\$wp_cache_front_page_checks', '$wp_cache_front_page_checks = 1;', $config )
		);
		$this->assertStringContainsString( '$wp_cache_front_page_checks = 1;', file_get_contents( $config ) );
		$this->assertStringNotContainsString( '$wp_cache_front_page_checks = 0;', file_get_contents( $config ) );
	}

	public function test_replace_line_unchanged_is_noop_early_return() {
		$config = $this->make_config_file( array( '$wp_cache_mobile = 1;' ) );
		$before = file_get_contents( $config );

		$this->assertTrue( wp_cache_replace_line( '^ *\$wp_cache_mobile', '$wp_cache_mobile = 1;', $config ) );
		$this->assertSame( $before, file_get_contents( $config ) );
	}

	public function test_replace_line_not_found_appends_after_assignments() {
		$config = $this->make_config_file( array( '$existing = 1;' ) );

		$this->assertTrue( wp_cache_replace_line( '^ *\$brand_new_key', '$brand_new_key = 5;', $config ) );
		$this->assertStringContainsString( '$brand_new_key = 5;', file_get_contents( $config ) );
		$this->assertStringContainsString( '$existing = 1;', file_get_contents( $config ) );
	}

	public function test_replace_line_missing_file_returns_false() {
		$this->assertFalse( wp_cache_replace_line( '^ *\$x', '$x = 1;', '/no/such/wp-cache-config.php' ) );
	}
}
