<?php
/**
 * Back-compat tests for the public config-write functions.
 *
 * Both wp_cache_setting() and wp_cache_replace_line() are part of the surface
 * that wp-cache-phase2.php and third-party cache plugins call directly. After issue
 * #1062 routed config writes through Automattic\WPSC\Config, these functions
 * remain as delegating shims with unchanged signatures. These tests lock that
 * public contract: the functions still exist, keep their parameter signatures,
 * and a positional call still updates $GLOBALS[$field] AND writes the config
 * file exactly as before.
 *
 * Integration tier: the rewriter calls wp_rand() and (optionally) set_transient().
 *
 * @package automattic/wp-super-cache
 */
class WpCacheSettingBackCompatTest extends WP_UnitTestCase {

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

	/**
	 * Both public write functions must remain defined for third-party callers.
	 */
	public function test_public_write_functions_exist() {
		$this->assertTrue( function_exists( 'wp_cache_setting' ) );
		$this->assertTrue( function_exists( 'wp_cache_replace_line' ) );
	}

	/**
	 * The wp_cache_setting() signature is part of the public contract: third-party
	 * code calls it positionally as wp_cache_setting( $field, $value ).
	 */
	public function test_wp_cache_setting_signature_unchanged() {
		$ref = new ReflectionFunction( 'wp_cache_setting' );

		$this->assertSame( 2, $ref->getNumberOfParameters() );
		$this->assertSame( 2, $ref->getNumberOfRequiredParameters() );

		$params = $ref->getParameters();
		$this->assertSame( 'field', $params[0]->getName() );
		$this->assertSame( 'value', $params[1]->getName() );
	}

	/**
	 * The wp_cache_replace_line() signature is part of the public contract: callers
	 * use wp_cache_replace_line( $old, $new, $my_file ).
	 */
	public function test_wp_cache_replace_line_signature_unchanged() {
		$ref = new ReflectionFunction( 'wp_cache_replace_line' );

		$this->assertSame( 3, $ref->getNumberOfParameters() );
		$this->assertSame( 3, $ref->getNumberOfRequiredParameters() );

		$params = $ref->getParameters();
		$this->assertSame( 'old', $params[0]->getName() );
		$this->assertSame( 'new', $params[1]->getName() );
		$this->assertSame( 'my_file', $params[2]->getName() );
	}

	/**
	 * A positional wp_cache_setting() call (as third-party code makes it) still
	 * updates the runtime global AND writes the config file, returning truthy.
	 */
	public function test_wp_cache_setting_public_api_updates_global_and_file() {
		$config = $this->make_config_file( array( '$wp_cache_mobile = 0;' ) );

		$result = wp_cache_setting( 'wp_cache_mobile', 1 );

		$this->assertTrue( $result );
		$this->assertSame( 1, $GLOBALS['wp_cache_mobile'] );
		$this->assertStringContainsString( '$wp_cache_mobile = 1;', file_get_contents( $config ) );
	}

	/**
	 * Array values keep their legacy on-disk format through the public function,
	 * so third-party callers persisting array settings are unaffected.
	 */
	public function test_wp_cache_setting_public_api_array_value() {
		$config = $this->make_config_file( array( '$cache_acceptable_files = array();' ) );

		wp_cache_setting( 'cache_acceptable_files', array( 'wp-comments-popup.php' ) );

		$this->assertSame( array( 'wp-comments-popup.php' ), $GLOBALS['cache_acceptable_files'] );
		$this->assertStringContainsString(
			"\$cache_acceptable_files = array ( 0 => 'wp-comments-popup.php', );",
			file_get_contents( $config )
		);
	}

	/**
	 * A direct wp_cache_replace_line() call (the raw rewriter third-party code may
	 * use) still replaces the matching line and returns truthy.
	 */
	public function test_wp_cache_replace_line_public_api_replaces_line() {
		$config = $this->make_config_file( array( '$wp_cache_front_page_checks = 0;' ) );

		$result = wp_cache_replace_line(
			'^ *\$wp_cache_front_page_checks',
			'$wp_cache_front_page_checks = 1;',
			$config
		);

		$this->assertTrue( $result );
		$this->assertStringContainsString( '$wp_cache_front_page_checks = 1;', file_get_contents( $config ) );
		$this->assertStringNotContainsString( '$wp_cache_front_page_checks = 0;', file_get_contents( $config ) );
	}
}
