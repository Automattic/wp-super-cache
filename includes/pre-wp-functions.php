<?php
/**
 * Functions used to create the cache file from the output buffer
 *
 * @link       https://automattic.com/
 * @since      2.0.0
 *
 * @package    Wp_Super_Cache
 * @subpackage Wp_Super_Cache/includes
 */

/**
 * Configuration and debug classes required for serving cache files.
 */
require_once 'class-wp-super-cache-config.php';
require_once 'class-wp-super-cache-debug.php';
require_once 'class-wp-super-cache-user.php';

$wp_super_cache_config = Wp_Super_cache_Config::instance()->get();

/**
 * Get cache directory
 *
 * @since  2.0
 * @return string
 */
function wp_super_cache_get_cache_dir() {
	global $wp_super_cache_config;
	if ( isset( $wp_super_cache_config['cache_path'] ) ) {
		return $wp_super_cache_config['cache_path'];
	} else {
		return ( defined( 'WPSC_CACHE_DIR' ) ) ? rtrim( WPSC_CACHE_DIR, '/' ) : rtrim( WP_CONTENT_DIR, '/' ) . '/cache';
	}
}

/**
 * Return true if in wp-admin or other admin non cacheable page.
 *
 * @since  2.0
 * @return bool
 */
function wpsc_is_backend() {
	static $is_backend;

	if ( isset( $is_backend ) ) {
		return $is_backend;
	}

	$is_backend = is_admin();
	if ( $is_backend ) {
		return $is_backend;
	}

	$script = isset( $_SERVER['PHP_SELF'] ) ? basename( $_SERVER['PHP_SELF'] ) : ''; // phpcs:ignore
	if ( 'index.php' !== $script ) {
		if ( in_array( $script, array( 'wp-login.php', 'xmlrpc.php', 'wp-cron.php' ), true ) ) {
			$is_backend = true;
		} elseif ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			$is_backend = true;
		} elseif ( 'cli' === PHP_SAPI || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			$is_backend = true;
		}
	}

	return $is_backend;
}

/**
 * Create cache file from buffer
 *
 * @param string $buffer the output buffer containing the current page.
 * @since  2.0
 */
function wp_super_cache_create_cache( $buffer ) {
	if ( mb_strlen( $buffer ) < 255 ) {
		return $buffer;
	}

	return $buffer;
}
