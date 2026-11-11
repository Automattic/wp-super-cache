<?php
/**
 * Smoke tests for wpsc_remove_tracking_params_from_uri().
 *
 * @package automattic/wp-super-cache
 */

// wp-cache-phase2.php is loaded by the smoke bootstrap (tests/php/bootstrap-smoke.php).

use PHPUnit\Framework\TestCase;

/**
 * @covers ::wpsc_remove_tracking_params_from_uri
 */
class WpscRemoveTrackingParamsTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wpsc_ignore_tracking_parameters'] = 1;
		$GLOBALS['wpsc_tracking_parameters']        = array( 'utm_source' );
		$GLOBALS['WPSC_HTTP_HOST']                  = 'example.com';

		unset( $_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO'] );
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['wpsc_ignore_tracking_parameters'],
			$GLOBALS['wpsc_tracking_parameters'],
			$GLOBALS['WPSC_HTTP_HOST']
		);
		$_GET = array();
		unset( $_SERVER['REQUEST_URI'] );
	}

	/** Tracking parameters are removed and the rest of the query is kept. */
	public function test_removes_tracking_parameters(): void {
		$_GET                   = array(
			'utm_source' => 'x',
			's'          => 'text',
		);
		$_SERVER['REQUEST_URI'] = '/page/?utm_source=x&s=text';

		$this->assertSame( '/page/?s=text', wpsc_remove_tracking_params_from_uri( '/page/?utm_source=x&s=text' ) );
	}

	/**
	 * An unparseable Host leaves the URI and the request untouched.
	 */
	public function test_unparseable_host_returns_uri_unchanged(): void {
		$GLOBALS['WPSC_HTTP_HOST'] = 'example.com:00000080';
		$_GET                      = array( 'utm_source' => 'x' );
		$_SERVER['REQUEST_URI']    = '/page/?utm_source=x';

		$this->assertSame( '/page/?utm_source=x', wpsc_remove_tracking_params_from_uri( '/page/?utm_source=x' ) );
		$this->assertSame( array( 'utm_source' => 'x' ), $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- asserting the raw value is untouched.
		$this->assertSame( '/page/?utm_source=x', $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- asserting the raw value is untouched.
	}
}
