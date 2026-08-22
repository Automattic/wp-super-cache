<?php
/**
 * Owner of the on-disk wp-cache-config.php write path.
 *
 * The single place that knows the config file's format. Public write functions
 * (wp_cache_setting / wp_cache_replace_line) delegate here. Globals remain the
 * canonical store and supported read API; set() always updates $GLOBALS[$field].
 *
 * @package automattic/wp-super-cache
 */

namespace Automattic\WPSC;

/**
 * Config file write owner.
 */
class Config {

	/**
	 * Set a config field: update the runtime global, format the value, persist.
	 *
	 * Reproduces wp_cache_setting() exactly.
	 *
	 * @param string      $field Config field name (no leading $).
	 * @param mixed       $value Value to store.
	 * @param string|null $file  Config file path. Defaults to $GLOBALS['wp_cache_config_file'].
	 * @return bool True on success (or unchanged), false on write failure.
	 */
	public static function set( $field, $value, $file = null ) {
		$GLOBALS[ $field ] = $value;

		if ( null === $file ) {
			$file = $GLOBALS['wp_cache_config_file'];
		}

		return self::write_line(
			'^ *\$' . $field,
			"\$$field = " . self::format_value( $value ) . ';',
			$file
		);
	}

	/**
	 * Format a value as the PHP literal written into the config file.
	 *
	 * Reproduces wp_cache_setting()'s per-type formatting byte-for-byte.
	 *
	 * @param mixed $value Value to format.
	 * @return string PHP literal (without trailing semicolon).
	 */
	public static function format_value( $value ) {
		if ( is_numeric( $value ) ) {
			return (string) $value;
		} elseif ( is_bool( $value ) ) {
			return $value === true ? 'true' : 'false';
		} elseif ( is_object( $value ) || is_array( $value ) ) {
			$text = var_export( $value, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			return preg_replace( '/[\s]+/', ' ', $text );
		} else {
			return "'$value'";
		}
	}

	/**
	 * Rewrite a single line of the config file via per-line regex.
	 *
	 * Verbatim body of the legacy wp_cache_replace_line(); the atomic
	 * tempnam -> write -> rename -> chmod -> opcache_invalidate sequence is
	 * preserved exactly.
	 *
	 * @param string $old  Regex (without delimiters) matching the line to replace.
	 * @param string $new  Replacement line (no trailing newline).
	 * @param string $file Config file path.
	 * @return bool True on success/unchanged, false on failure.
	 */
	public static function write_line( $old, $new, $file ) {
		if ( ! is_string( $file ) || @is_file( $file ) === false ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( function_exists( 'set_transient' ) ) {
				set_transient( 'wpsc_config_error', 'config_file_missing', 10 );
			}
			return false;
		}
		if ( ! is_writeable_ACLSafe( $file ) ) {
			if ( function_exists( 'set_transient' ) ) {
				set_transient( 'wpsc_config_error', 'config_file_ro', 10 );
			}
			trigger_error( "Error: file $file is not writable." ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error,WordPress.Security.EscapeOutput.OutputNotEscaped
			return false;
		}

		$found  = false;
		$loaded = false;
		$c      = 0;
		$lines  = array();
		while ( ! $loaded ) {
			$lines = file( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_file
			if ( ! empty( $lines ) && is_array( $lines ) ) {
				$loaded = true;
			} else {
				++$c;
				if ( $c > 100 ) {
					if ( function_exists( 'set_transient' ) ) {
						set_transient( 'wpsc_config_error', 'config_file_not_loaded', 10 );
					}
					trigger_error( "wp_cache_replace_line: Error  - file $file could not be loaded." ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error,WordPress.Security.EscapeOutput.OutputNotEscaped
					return false;
				}
			}
		}
		foreach ( (array) $lines as $line ) {
			if (
				trim( $new ) != '' && // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
				trim( $new ) == trim( $line ) // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
			) {
				wp_cache_debug( "wp_cache_replace_line: setting not changed - $new" );
				return true;
			} elseif ( preg_match( "/$old/", $line ) ) {
				wp_cache_debug( 'wp_cache_replace_line: changing line ' . trim( $line ) . " to *$new*" );
				$found = true;
			}
		}

		$tmp_config_filename = tempnam( $GLOBALS['cache_path'], md5( (string) wp_rand( 0, 9999 ) ) );
		if ( file_exists( $tmp_config_filename . '.php' ) ) {
			unlink( $tmp_config_filename . '.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			if ( file_exists( $tmp_config_filename . '.php' ) ) {
				die( __( 'WARNING: attempt to intercept updating of config file.', 'wp-super-cache' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
		rename( $tmp_config_filename, $tmp_config_filename . '.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		$tmp_config_filename .= '.php';
		wp_cache_debug( 'wp_cache_replace_line: writing to ' . $tmp_config_filename );
		$fd = fopen( $tmp_config_filename, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $fd ) {
			if ( function_exists( 'set_transient' ) ) {
				set_transient( 'wpsc_config_error', 'config_file_ro', 10 );
			}
			trigger_error( "wp_cache_replace_line: Error  - could not write to $file" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error,WordPress.Security.EscapeOutput.OutputNotEscaped
			return false;
		}
		if ( $found ) {
			foreach ( (array) $lines as $line ) {
				if ( ! preg_match( "/$old/", $line ) ) {
					fwrite( $fd, $line ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
				} elseif ( $new != '' ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
					fwrite( $fd, "$new\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
				}
			}
		} else {
			$done = false;
			foreach ( (array) $lines as $line ) {
				if ( $done || ! preg_match( '/^(if\ \(\ \!\ )?define|\$|\?>/', $line ) ) {
					fwrite( $fd, $line ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
				} else {
					fwrite( $fd, "$new\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
					fwrite( $fd, $line ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
					$done = true;
				}
			}
		}
		fclose( $fd ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$my_file_permissions = fileperms( $file );

		rename( $tmp_config_filename, $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename

		if ( false !== $my_file_permissions ) {
			chmod( $file, $my_file_permissions ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
		}

		wp_cache_debug( 'wp_cache_replace_line: moved ' . $tmp_config_filename . ' to ' . $file );

		if ( function_exists( 'opcache_invalidate' ) ) {
			@opcache_invalidate( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		return true;
	}
}
