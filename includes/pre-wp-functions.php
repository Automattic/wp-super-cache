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

$wp_super_cache_config = wp_super_cache_load_config();

/**
 * Load the configuration.
 *
 * @since    2.0.0
 */
function wp_super_cache_load_config() {
	static $config = array();

	if ( ! empty( $config ) ) {
		return $config;
	}

	if ( ! file_exists( WP_CONTENT_DIR . '/wp-cache-config.php' ) || ! include WP_CONTENT_DIR . '/wp-cache-config.php' ) {
		return array();
	}

	$config = get_defined_vars();
	return $config;
}

/**
 * Get cache directory
 *
 * @since  2.0
 * @return string
 */
function wp_super_cache_get_cache_dir() {
	$config = wp_super_cache_load_config();
	if ( isset( $config['cache_path'] ) ) {
		return $config['cache_path'];
	} else {
		return ( defined( 'WPSC_CACHE_DIR' ) ) ? rtrim( WPSC_CACHE_DIR, '/' ) : rtrim( WP_CONTENT_DIR, '/' ) . '/cache';
	}
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
