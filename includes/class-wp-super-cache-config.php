<?php
/**
 * File containing the class Wp_Super_Cache_Config
 *
 * @package wp-super-cache
 *
 * @since   2.0.0
 */

/**
 * Handles front admin page for Crowdsignal.
 *
 * @since 2.0.0
 */
class Wp_Super_Cache_Config {

	/**
	 * Config file location.
	 *
	 * @since 1.0.1
	 * @var   string
	 */
	public $config_filename = WP_CONTENT_DIR . '/wp-cache-config.php';

	/**
	 * Configuration variables
	 *
	 * @since 1.0.1
	 * @var   array
	 */
	public $config = array();


	/**
	 * Set config defaults
	 *
	 * @since 1.0
	 */
	public function __construct() {
		$this->get();
	}

	/**
	 *  Get configuration.
	 *
	 * @since 1.0
	 */
	public function get() {

		if ( ! empty( $this->config ) ) {
			return $this->config;
		}

		if ( ! file_exists( WP_CONTENT_DIR . '/wp-cache-config.php' ) || ! include WP_CONTENT_DIR . '/wp-cache-config.php' ) {
			return false;
		}
		$this->config = get_defined_vars();

		return $this->config;
	}

	/**
	 *  Update a setting.
	 *
	 * @param string $field the name of the setting.
	 * @param string $value the value of the setting.
	 * @since 2.0
	 */
	public function update_setting( $field, $value ) {
		$this->config[ $field ] = $value;
		if ( is_numeric( $value ) ) {
			return $this->replace_line_in_file( '^ *\$' . $field, "\$$field = $value;", $this->config_filename );
		} elseif ( is_bool( $value ) ) {
			$output_value = true === $value ? 'true' : 'false';
			return $this->replace_line_in_file( '^ *\$' . $field, "\$$field = $output_value;", $this->config_filename );
		} elseif ( is_object( $value ) || is_array( $value ) ) {
			$text = var_export( $value, true ); // phpcs:ignore
			$text = preg_replace( '/[\s]+/', ' ', $text );
			return $this->replace_line_in_file( '^ *\$' . $field, "\$$field = $text;", $this->config_filename );
		} else {
			return $this->replace_line_in_file( '^ *\$' . $field, "\$$field = '$value';", $this->config_filename );
		}
	}

	/**
	 *  Replace a line in the config file.
	 *
	 * @param string $old the old line in the file.
	 * @param string $new the new line to replace it.
	 * @param string $filename the filename of the file to write to.
	 * @since 2.0
	 */
	public function replace_line_in_file( $old, $new, $filename ) {
		if ( is_file( $filename ) === false ) {
			if ( function_exists( 'set_transient' ) ) {
				set_transient( 'wpsc_config_error', 'config_file_missing', 10 );
			}
			return false;
		}
		if ( ! $this->is_writeable( $filename ) ) {
			if ( function_exists( 'set_transient' ) ) {
				set_transient( 'wpsc_config_error', 'config_file_ro', 10 );
			}
			return false;
		}

		$found  = false;
		$loaded = false;
		$c      = 0;
		$lines  = array();
		while ( ! $loaded ) {
			$lines = file( $filename );
			if ( ! empty( $lines ) && is_array( $lines ) ) {
				$loaded = true;
			} else {
				$c++;
				if ( $c > 100 ) {
					if ( function_exists( 'set_transient' ) ) {
						set_transient( 'wpsc_config_error', 'config_file_not_loaded', 10 );
					}
					return false;
				}
			}
		}
		foreach ( (array) $lines as $line ) {
			if (
				trim( $new ) !== '' &&
				trim( $new ) === trim( $line )
			) {
				wp_cache_debug( "replace_line_in_file: setting not changed - $new" );
				return true;
			} elseif ( preg_match( "/$old/", $line ) ) {
				wp_cache_debug( 'replace_line_in_file: changing line ' . trim( $line ) . " to *$new*" );
				$found = true;
			}
		}

		// $tmp_config_filename = tempnam( $GLOBALS['cache_path'], 'wpsc' );
		$tmp_config_filename = tempnam( '/tmp/', 'wpsc' );
		rename( $tmp_config_filename, $tmp_config_filename . '.php' );
		$tmp_config_filename .= '.php';
		wp_cache_debug( 'replace_line_in_file: writing to ' . $tmp_config_filename );
		$fd = fopen( $tmp_config_filename, 'w' );
		if ( ! $fd ) {
			if ( function_exists( 'set_transient' ) ) {
				set_transient( 'wpsc_config_error', 'config_file_ro', 10 );
			}
			return false;
		}
		if ( $found ) {
			foreach ( (array) $lines as $line ) {
				if ( ! preg_match( "/$old/", $line ) ) {
					fputs( $fd, $line );
				} elseif ( '' !== $new ) {
					fputs( $fd, "$new\n" );
				}
			}
		} else {
			$done = false;
			foreach ( (array) $lines as $line ) {
				if ( $done || ! preg_match( '/^(if\ \(\ \!\ )?define|\$|\?>/', $line ) ) {
					fputs( $fd, $line );
				} else {
					fputs( $fd, "$new\n" );
					fputs( $fd, $line );
					$done = true;
				}
			}
		}
		fclose( $fd );
		rename( $tmp_config_filename, $filename );
		wp_cache_debug( 'replace_line_in_file: moved ' . $tmp_config_filename . ' to ' . $filename );

		if ( function_exists( 'opcache_invalidate' ) ) {
			@opcache_invalidate( $filename );
		}

		return true;
	}

	/**
	 * Check if $path is writeable.
	 * From legolas558 d0t users dot sf dot net at http://www.php.net/is_writable
	 *
	 * @param string $path the path to be checked.
	 * @since 2.0
	 */
	private function is_writeable( $path ) {

		// PHP's is_writable does not work with Win32 NTFS.

		if ( '/' === $path[ strlen( $path ) - 1 ] ) { // recursively return a temporary file path.
			return $this->is_writeable( $path . uniqid( wp_rand() ) . '.tmp' );
		} elseif ( is_dir( $path ) ) {
			return $this->is_writeable( $path . '/' . uniqid( wp_rand() ) . '.tmp' );
		}

		// check tmp file for read/write capabilities.
		$rm = file_exists( $path );
		$f = @fopen( $path, 'a' );
		if ( false === $f ) {
			return false;
		}
		fclose( $f );
		if ( ! $rm ) {
			unlink( $path );
		}

		return true;
	}

	/**
	 * Return an instance of the current class, create one if it doesn't exist
	 *
	 * @since  2.0
	 * @return Wp_Super_Cache_Config
	 */
	public static function instance() {

		static $instance;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}
}

/**
 * Return the config for the plugin.
 *
 * @since  2.0
 * @return array of configuration data.
 **/
function wp_super_cache_get_config() {
	global $wpsc_config;

	if ( false === isset( $wpsc_config ) ) {
		$wpsc_config = Wp_Super_cache_Config::instance();
	}

	return $wpsc_config;
}
