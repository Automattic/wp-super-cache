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
 * Check if caching is disabled for the current visitor based on their cookies
 *
 * @since  2.0
 */
function wpsc_is_caching_user_disabled() {
	global $wp_super_cache_config;
	if ( 2 === $wp_super_cache_config['wp_cache_not_logged_in'] && wpsc_get_auth_cookies() ) {
		wp_cache_debug( 'wpsc_is_caching_user_disabled: true because logged in' );
		return true;
	} elseif ( 1 === $wp_super_cache_config['wp_cache_not_logged_in'] && ! empty( $_COOKIE ) ) {
		wp_cache_debug( 'wpsc_is_caching_user_disabled: true because cookie found' );
		return true;
	} else {
		wp_cache_debug( 'wpsc_is_caching_user_disabled: false' );
		return false;
	}
}

/**
 * Return auth cookies for the current user.
 *
 * @since  2.0
 */
function wpsc_get_auth_cookies() {
	static $cached_cookies;

	if ( isset( $cached_cookies ) && is_array( $cached_cookies ) ) {
		return $cached_cookies;
	}

	$cookies = array_keys( $_COOKIE );
	if ( empty( $cookies ) ) {
		return array();
	}

	$auth_cookies      = array();
	$duplicate_cookies = array();

	$wp_cookies = array(
		'AUTH_COOKIE'        => 'wordpress_',
		'SECURE_AUTH_COOKIE' => 'wordpress_sec_',
		'LOGGED_IN_COOKIE'   => 'wordpress_logged_in_',
	);

	foreach ( $wp_cookies as $cookie_const => $cookie_prefix ) {
		$cookie_key = strtolower( $cookie_const );

		if ( defined( $cookie_const ) ) {
			if ( in_array( constant( $cookie_const ), $cookies, true ) ) {
				$auth_cookies[ $cookie_key ] = constant( $cookie_const );
			}

			continue;
		}

		$found_cookies = preg_grep( '`^' . preg_quote( $cookie_prefix, '`' ) . '([0-9a-f]+)$`', $cookies );

		if ( count( $found_cookies ) === 1 ) {
			$auth_cookies[ $cookie_key ] = reset( $found_cookies );
		} elseif ( count( $found_cookies ) > 1 ) {
			$duplicate_cookies           = array_merge( $duplicate_cookies, $found_cookies );
			$auth_cookies[ $cookie_key ] = $found_cookies;
		}
	}

	$cookie_hash   = defined( 'COOKIEHASH' ) ? COOKIEHASH : '';
	$other_cookies = array(
		'comment_cookie'  => 'comment_author_',
		'postpass_cookie' => 'wp-postpass_',
	);

	foreach ( $other_cookies as $cookie_key => $cookie_prefix ) {

		if ( $cookie_hash ) {
			if ( in_array( $cookie_prefix . $cookie_hash, $cookies, true ) ) {
				$auth_cookies[ $cookie_key ] = $cookie_prefix . $cookie_hash;
			}

			continue;
		}

		$found_cookies = preg_grep( '`^' . preg_quote( $cookie_prefix, '`' ) . '([0-9a-f]+)$`', $cookies );

		if ( count( $found_cookies ) === 1 ) {
			$auth_cookies[ $cookie_key ] = reset( $found_cookies );
		} elseif ( count( $found_cookies ) > 1 ) {
			$duplicate_cookies           = array_merge( $duplicate_cookies, $found_cookies );
			$auth_cookies[ $cookie_key ] = $found_cookies;
		}
	}

	if ( ! $duplicate_cookies ) {
		$cached_cookies = $auth_cookies;
	}

	if ( empty( $auth_cookies ) ) {
		wp_cache_debug( 'wpsc_get_auth_cookies: no auth cookies detected', 5 );
	} else {
		if ( $duplicate_cookies ) {
			wp_cache_debug( 'wpsc_get_auth_cookies: duplicate cookies detected( ' . implode( ', ', $duplicate_cookies ) . ' )', 5 );
		} else {
			wp_cache_debug( 'wpsc_get_auth_cookies: cookies detected: ' . implode( ', ', $auth_cookies ), 5 );
		}
	}

	return $auth_cookies;
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
