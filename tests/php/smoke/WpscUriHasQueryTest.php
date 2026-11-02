<?php
/**
 * Smoke tests for wpsc_uri_has_query() and wpsc_is_get_query().
 *
 * @package automattic/wp-super-cache
 */

// wp-cache-phase2.php is loaded by the smoke bootstrap (tests/php/bootstrap-smoke.php).

use PHPUnit\Framework\TestCase;

/**
 * @covers ::wpsc_uri_has_query
 * @covers ::wpsc_is_get_query
 */
class WpscUriHasQueryTest extends TestCase {

	/**
	 * Request URIs that carry a query string.
	 *
	 * @return array<string, array{string}>
	 */
	public function uris_with_query(): array {
		return array(
			'home search'         => array( '/?s=text' ),
			'path with query'     => array( '/some-post/?p=1' ),
			'double slash'        => array( '//?s=text' ),
			'triple slash'        => array( '///?s=text' ),
			'host-like path'      => array( '//evil.example/?s=text' ),
			'at sign in path'     => array( '/@x?s=text' ),
			'colon in path'       => array( '/a:99999?s=text' ),
			'colon-led target'    => array( ':abc?s=text' ),
			'no leading slash'    => array( '?s=text' ),
			'query then fragment' => array( '/?s=text#top' ),
			'flag without value'  => array( '/?preview' ),
			'empty value'         => array( '/?s=' ),
			'encoded NUL in path' => array( '/%00?s=text' ),
		);
	}

	/**
	 * Request URIs with no query string, matching what parse_url() and $_GET
	 * report for them.
	 *
	 * @return array<string, array{string}>
	 */
	public function uris_without_query(): array {
		return array(
			'home'                 => array( '/' ),
			'path'                 => array( '/some-post/' ),
			'bare question mark'   => array( '/?' ),
			'question in fragment' => array( '/#?s=text' ),
			'fragment only'        => array( '/#top' ),
			'empty'                => array( '' ),
		);
	}

	/**
	 * @dataProvider uris_with_query
	 *
	 * @param string $uri Request URI.
	 */
	public function test_detects_query( string $uri ): void {
		$this->assertTrue( wpsc_uri_has_query( $uri ) );
	}

	/**
	 * @dataProvider uris_without_query
	 *
	 * @param string $uri Request URI.
	 */
	public function test_detects_no_query( string $uri ): void {
		$this->assertFalse( wpsc_uri_has_query( $uri ) );
	}

	/**
	 * Malformed Host header values.
	 *
	 * @return array<string, array{string}>
	 */
	public function unparseable_hosts(): array {
		return array(
			'zero-padded port' => array( 'example.com:00000080' ),
			'port above 65535' => array( 'example.com:65536' ),
			'non-numeric port' => array( 'example.com:x' ),
			'hash after port'  => array( 'example.com:80#x' ),
			'hash, empty port' => array( 'example.com:#x' ),
		);
	}

	/**
	 * The Host header doesn't affect query detection.
	 *
	 * Runs in its own process because wpsc_is_get_query() keeps its answer in a
	 * static for the rest of the request.
	 *
	 * @dataProvider unparseable_hosts
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @param string $host Host header value as stored in $WPSC_HTTP_HOST.
	 */
	public function test_is_get_query_ignores_host( string $host ): void {
		$GLOBALS['WPSC_HTTP_HOST'] = $host;
		$_SERVER['REQUEST_URI']    = '/?s=text';
		unset( $_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO'] );

		$this->assertTrue( wpsc_is_get_query() );
	}

	/**
	 * With no request URI (WP-CLI, cron) there is no query, and no warning.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_is_get_query_without_request_uri(): void {
		unset( $_SERVER['REQUEST_URI'] );

		$this->assertFalse( wpsc_is_get_query() );
	}
}
