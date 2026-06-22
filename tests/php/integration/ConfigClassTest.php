<?php
/**
 * Direct unit tests for the Config module. Mirror ConfigWritePathTest but call
 * the typed Config API that Phase C callers will use.
 *
 * @package automattic/wp-super-cache
 */
class ConfigClassTest extends WP_UnitTestCase {

	/** @var string[] */
	private $temp_dirs = array();

	public function tear_down() {
		foreach ( $this->temp_dirs as $dir ) {
			$this->rrmdir( $dir );
		}
		$this->temp_dirs = array();
		parent::tear_down();
	}

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

	public function test_format_value_numeric() {
		$this->assertSame( '5', \Automattic\WPSC\Config::format_value( 5 ) );
		$this->assertSame( '0', \Automattic\WPSC\Config::format_value( 0 ) );
	}

	public function test_format_value_boolean() {
		$this->assertSame( 'true', \Automattic\WPSC\Config::format_value( true ) );
		$this->assertSame( 'false', \Automattic\WPSC\Config::format_value( false ) );
	}

	public function test_format_value_array_is_whitespace_collapsed() {
		$this->assertSame(
			"array ( 0 => 'a', 1 => 'b', )",
			\Automattic\WPSC\Config::format_value( array( 'a', 'b' ) )
		);
	}

	public function test_format_value_string_single_quoted() {
		$this->assertSame( "'hello'", \Automattic\WPSC\Config::format_value( 'hello' ) );
	}

	public function test_set_updates_global_and_file() {
		$config = $this->make_config_file( array( '$wp_cache_mobile = 0;' ) );
		$this->assertTrue( \Automattic\WPSC\Config::set( 'wp_cache_mobile', 1 ) );
		$this->assertSame( 1, $GLOBALS['wp_cache_mobile'] );
		$this->assertStringContainsString( '$wp_cache_mobile = 1;', file_get_contents( $config ) );
	}

	public function test_set_array_matches_legacy_format() {
		$config = $this->make_config_file( array( '$cache_acceptable_files = array();' ) );
		\Automattic\WPSC\Config::set( 'cache_acceptable_files', array( 'wp-comments-popup.php' ) );
		$this->assertStringContainsString(
			"\$cache_acceptable_files = array ( 0 => 'wp-comments-popup.php', );",
			file_get_contents( $config )
		);
	}

	public function test_write_line_replaces() {
		$config = $this->make_config_file( array( '$x = 0;' ) );
		$this->assertTrue( \Automattic\WPSC\Config::write_line( '^ *\$x', '$x = 9;', $config ) );
		$this->assertStringContainsString( '$x = 9;', file_get_contents( $config ) );
	}

	public function test_write_line_missing_file_returns_false() {
		$this->assertFalse( \Automattic\WPSC\Config::write_line( '^ *\$x', '$x = 1;', '/no/such/file.php' ) );
	}
}
