<?php
/**
 * Tests for wpsc_get_auth_cookie_values().
 *
 * @package automattic/wp-super-cache
 */

// wp-cache-phase2.php is loaded by the smoke bootstrap (tests/php/bootstrap-smoke.php).

use PHPUnit\Framework\TestCase;

/**
 * The smoke tier runs without WordPress, so COOKIEHASH and LOGGED_IN_COOKIE are
 * undefined and the function falls back to matching the `wordpress_logged_in_`,
 * `wp-postpass_` and `comment_author_` prefixes. These tests deliberately do not
 * define those constants: they are process-wide, and defining one here would
 * change the regex for every other test in the run.
 *
 * @covers ::wpsc_get_auth_cookie_values
 */
class WpscAuthCookieValuesTest extends TestCase {

	/**
	 * A logged-in visitor must never produce the anonymous (empty) value.
	 */
	public function test_logged_in_cookie_is_collected(): void {
		$cookies = array( 'wordpress_logged_in_abc123' => 'admin|1|token' );
		$this->assertSame( 'admin|1|token,', wpsc_get_auth_cookie_values( $cookies ) );
	}

	/**
	 * An anonymous visitor produces the empty string, which is what marks the
	 * request cacheable as a supercache file.
	 */
	public function test_no_auth_cookies_returns_empty_string(): void {
		$cookies = array(
			'wordpress_test_cookie' => 'WP Cookie check',
			'_ga'                   => 'GA1.1.1234',
		);
		$this->assertSame( '', wpsc_get_auth_cookie_values( $cookies ) );
	}

	/**
	 * Regression guard: PHP casts a cookie named "0" to integer key 0, which is
	 * falsy. The previous `while ( $key = key( $cookies ) )` loop ended there, so
	 * the auth cookie behind it was never seen and a logged-in visitor was handed
	 * the anonymous cache key — their page then became the supercache file served
	 * to everyone.
	 */
	public function test_cookie_named_zero_before_auth_cookie_does_not_hide_it(): void {
		$cookies = array(
			'0'                          => 'x',
			'wordpress_logged_in_abc123' => 'admin|1|token',
		);
		$this->assertSame( 'admin|1|token,', wpsc_get_auth_cookie_values( $cookies ) );
	}

	/**
	 * The same cookie sitting between two others truncated the scan mid-way.
	 */
	public function test_cookie_named_zero_between_cookies_does_not_truncate_scan(): void {
		$cookies = array(
			'wordpress_test_cookie'      => 'WP Cookie check',
			'0'                          => 'x',
			'wordpress_logged_in_abc123' => 'admin|1|token',
		);
		$this->assertSame( 'admin|1|token,', wpsc_get_auth_cookie_values( $cookies ) );
	}

	/**
	 * A post password holder must not share the anonymous key either.
	 */
	public function test_cookie_named_zero_before_postpass_cookie(): void {
		$cookies = array(
			'0'                  => 'x',
			'wp-postpass_abc123' => 'hashed-password',
		);
		$this->assertSame( 'hashed-password,', wpsc_get_auth_cookie_values( $cookies ) );
	}

	/**
	 * Every matching cookie is collected, in order, so two visitors holding
	 * different combinations do not collide.
	 */
	public function test_multiple_auth_cookies_are_all_collected(): void {
		$cookies = array(
			'comment_author_abc123'      => 'Some+One',
			'0'                          => 'x',
			'wordpress_logged_in_abc123' => 'admin|1|token',
		);
		$this->assertSame( 'Some+One,admin|1|token,', wpsc_get_auth_cookie_values( $cookies ) );
	}

	/**
	 * `Cookie: wordpress_logged_in_abc123[]=x` arrives as an array. It is not a
	 * valid auth cookie, and concatenating it produced the literal string "Array"
	 * — a value every sender of that cookie shared. Skip it instead.
	 */
	public function test_array_valued_auth_cookie_is_skipped(): void {
		$cookies = array( 'wordpress_logged_in_abc123' => array( 'x' ) );
		$this->assertSame( '', wpsc_get_auth_cookie_values( $cookies ) );
	}

	/**
	 * An array-valued cookie must not stop the scan for the ones behind it.
	 */
	public function test_array_valued_cookie_does_not_hide_later_cookies(): void {
		$cookies = array(
			'wordpress_logged_in_abc123' => array( 'x' ),
			'wp-postpass_abc123'         => 'hashed-password',
		);
		$this->assertSame( 'hashed-password,', wpsc_get_auth_cookie_values( $cookies ) );
	}

	/** An empty cookie array is anonymous. */
	public function test_empty_cookie_array_returns_empty_string(): void {
		$this->assertSame( '', wpsc_get_auth_cookie_values( array() ) );
	}
}
