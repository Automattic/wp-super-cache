<?php
/**
 * File containing the class Wp_Super_Cache_User
 *
 * @package wp-super-cache
 *
 * @since   2.0.0
 */

/**
 * Handles user settings and information
 *
 * @since 2.0.0
 */
class Wp_Super_Cache_User {

	/**
	 * Configuration variables
	 *
	 * @since 1.0.1
	 * @var   array
	 */
	public $config = array();


	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	public function __construct() {
		$this->config = Wp_Super_Cache_Config::instance();
	}

	/**
	 * Get authentication cookies for visitor.
	 *
	 * @since  2.0
	 */
	public function get_auth_cookies() {
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
			wp_cache_debug( 'get_auth_cookies: no auth cookies detected', 5 );
		} else {
			if ( $duplicate_cookies ) {
				wp_cache_debug( 'get_auth_cookies: duplicate cookies detected( ' . implode( ', ', $duplicate_cookies ) . ' )', 5 );
			} else {
				wp_cache_debug( 'get_auth_cookies: cookies detected: ' . implode( ', ', $auth_cookies ), 5 );
			}
		}

		return $auth_cookies;
	}

	/**
	 * Check if caching is disabled for the current visitor based on their cookies
	 *
	 * @since  2.0
	 */
	public function is_caching_disabled() {
		if ( 2 === $this->config->config['wp_cache_not_logged_in'] && $this->get_auth_cookies() ) {
			wp_cache_debug( 'User - is_caching_disabled: true because logged in' );
			return true;
		} elseif ( 1 === $this->config->config['wp_cache_not_logged_in'] && ! empty( $_COOKIE ) ) {
			wp_cache_debug( 'User - is_caching_disabled: true because cookie found' );
			return true;
		} else {
			wp_cache_debug( 'User - is_caching_disabled: false' );
			return false;
		}
	}

	/**
	 * Return an instance of the current class, create one if it doesn't exist
	 *
	 * @since  2.0
	 * @return Wp_Super_Cache_User
	 */
	public static function instance() {

		static $instance;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}
}
