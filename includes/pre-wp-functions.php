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
require_once 'class-wp-super-cache-page.php';
require_once 'class-wp-super-cache-file-cache.php';

$wp_super_cache_config = Wp_Super_Cache_Config::instance()->get();

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
 * Actions for the pre-WordPress process.
 *
 * @param string $action The action to hook on to.
 * @param string $func The function to hook on to the action.
 * @since  2.0
 */
function add_cacheaction( $action, $func ) {
	global $wp_supercache_actions;
	$wp_supercache_actions[ $action ][] = $func;
}

/**
 * Perform the action and fire off functions.
 *
 * @param string $action The action to fire.
 * @param string $value The data to pass to functions hooked on toe $action.
 * @since  2.0
 */
function do_cacheaction( $action, $value = '' ) {
	global $wp_supercache_actions;

	if ( ! isset( $wp_supercache_actions ) || ! is_array( $wp_supercache_actions ) ) {
		return $value;
	}

	if ( array_key_exists( $action, $wp_supercache_actions ) && is_array( $wp_supercache_actions[ $action ] ) ) {
		$actions = $wp_supercache_actions[ $action ];
		foreach ( $actions as $func ) {
			$value = call_user_func_array( $func, array( $value ) );
		}
	}

	return $value;
}
