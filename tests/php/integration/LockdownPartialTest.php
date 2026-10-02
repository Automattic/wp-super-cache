<?php
/**
 * Tests for the Directly Cached Files table in the lockdown partial.
 *
 * @package automattic/wp-super-cache
 */
class LockdownPartialTest extends WP_UnitTestCase {

	/** @var string */
	private $temp_dir;

	/** @var string */
	private $direct_page;

	/** @var array<string, array{exists: bool, value: mixed}> */
	private $saved_globals = array();

	public function set_up() {
		parent::set_up();

		if ( ! defined( 'SUBMITDISABLED' ) ) {
			define( 'SUBMITDISABLED', ' ' );
		}

		$this->temp_dir    = trailingslashit( get_temp_dir() ) . 'wpsc-lockdown-' . uniqid();
		$this->direct_page = '/wpsc-direct-' . uniqid() . '/';
		mkdir( $this->temp_dir, 0700, true );

		foreach ( array( 'cache_path', 'wp_cache_config_file', 'cached_direct_pages', 'valid_nonce' ) as $key ) {
			$this->saved_globals[ $key ] = array(
				'exists' => array_key_exists( $key, $GLOBALS ),
				'value'  => $GLOBALS[ $key ] ?? null,
			);
		}

		$GLOBALS['cache_path']           = trailingslashit( $this->temp_dir );
		$GLOBALS['wp_cache_config_file'] = $this->temp_dir . '/wp-cache-config.php';
		$GLOBALS['cached_direct_pages']  = array( $this->direct_page );
		$GLOBALS['valid_nonce']          = false;
		$_POST                           = array();

		file_put_contents(
			$GLOBALS['wp_cache_config_file'],
			"<?php\n\$cached_direct_pages = array();\n"
		);
	}

	public function tear_down() {
		$direct_dir = ABSPATH . trim( $this->direct_page, '/' );
		if ( is_file( $direct_dir . '/index.html' ) ) {
			unlink( $direct_dir . '/index.html' );
		}
		if ( is_dir( $direct_dir ) ) {
			rmdir( $direct_dir );
		}

		foreach ( glob( $this->temp_dir . '/*' ) as $file ) {
			unlink( $file );
		}
		rmdir( $this->temp_dir );

		foreach ( $this->saved_globals as $key => $saved ) {
			if ( $saved['exists'] ) {
				$GLOBALS[ $key ] = $saved['value'];
			} else {
				unset( $GLOBALS[ $key ] );
			}
		}
		$_POST = array();

		parent::tear_down();
	}

	/** Render the partial with Directly Cached Files enabled. */
	private function render_partial() {
		ob_start();
		wpsc_render_partial(
			'lockdown',
			array(
				'wp_lock_down'        => '0',
				'admin_url'           => admin_url( 'options-general.php?page=wpsupercache' ),
				'cache_enabled'       => true,
				'super_cache_enabled' => true,
			)
		);
		return ob_get_clean();
	}

	/** A configured page without a generated file explains why deletion is unavailable. */
	public function test_ungenerated_direct_page_explains_empty_delete_cell() {
		$html = $this->render_partial();

		$this->assertStringContainsString( '<td>Not generated yet</td>', $html );
		$this->assertStringNotContainsString( 'name="deletepage"', $html );
	}

	/** A generated direct page keeps its existing delete button. */
	public function test_generated_direct_page_shows_delete_button() {
		$direct_dir = ABSPATH . trim( $this->direct_page, '/' );
		mkdir( $direct_dir, 0700, true );
		file_put_contents( $direct_dir . '/index.html', 'cached' );

		$html = $this->render_partial();

		$this->assertStringContainsString( 'name="deletepage" value="' . $this->direct_page . '"', $html );
		$this->assertStringNotContainsString( 'Not generated yet', $html );
	}
}
