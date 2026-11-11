<?php
/**
 * Smoke tests for wpsc_is_valid_http_host() and the HTTP_HOST check in
 * wp-cache-base.php.
 *
 * @package automattic/wp-super-cache
 */

// wp-cache-phase2.php is loaded by the smoke bootstrap (tests/php/bootstrap-smoke.php).

use PHPUnit\Framework\TestCase;

/**
 * @covers ::wpsc_is_valid_http_host
 */
class WpscIsValidHttpHostTest extends TestCase {

	/**
	 * Hosts a browser or a correctly configured proxy sends.
	 *
	 * @return array<string, array{string}>
	 */
	public function valid_hosts(): array {
		return array(
			'hostname'            => array( 'example.com' ),
			'subdomain'           => array( 'www.example.com' ),
			'single label'        => array( 'localhost' ),
			'hyphen'              => array( 'my-site.example' ),
			'underscore'          => array( 'my_site.local' ),
			'punycode'            => array( 'xn--bcher-kva.example' ),
			'trailing dot'        => array( 'example.com.' ),
			'default http port'   => array( 'example.com:80' ),
			'default https port'  => array( 'example.com:443' ),
			'non-default port'    => array( 'localhost:8080' ),
			'highest port'        => array( 'example.com:65535' ),
			'lowest port'         => array( 'example.com:1' ),
			'IPv4'                => array( '127.0.0.1' ),
			'IPv4 with port'      => array( '127.0.0.1:8080' ),
			'IPv6'                => array( '[::1]' ),
			'IPv6 with port'      => array( '[::1]:8080' ),
			'IPv6 with IPv4 tail' => array( '[::ffff:127.0.0.1]' ),
			'uppercase hostname'  => array( 'Example.COM' ),
		);
	}

	/**
	 * Hosts that must not be trusted to name a cache directory.
	 *
	 * @return array<string, array{string}>
	 */
	public function invalid_hosts(): array {
		return array(
			'six-digit zero-padded port'    => array( 'example.com:000080' ),
			'eight-digit zero-padded port'  => array( 'example.com:00000080' ),
			'very long zero-padded port'    => array( 'example.com:00000000000000000000000000000080' ),
			'port above 65535'              => array( 'example.com:65536' ),
			'five-digit port above 65535'   => array( 'example.com:99999' ),
			'non-numeric port'              => array( 'example.com:x' ),
			'hash after port'               => array( 'example.com:80#x' ),
			'hash after empty port'         => array( 'example.com:#x' ),
			'short zero-padded port'        => array( 'example.com:0080' ),
			'five-digit zero-padded port'   => array( 'example.com:00080' ),
			'port zero'                     => array( 'example.com:0' ),
			'empty port'                    => array( 'example.com:' ),
			'hash'                          => array( 'example.com#x' ),
			'path'                          => array( 'example.com/evil' ),
			'query'                         => array( 'example.com?x' ),
			'userinfo'                      => array( 'user@example.com' ),
			'space'                         => array( 'example .com' ),
			'invalid UTF-8 byte'            => array( "example.com\xFF" ),
			'mbstring substitute character' => array( 'example.com?' ),
			'non-ASCII'                     => array( 'bücher.example' ),
			'trailing newline'              => array( "example.com\n" ),
			'NUL byte'                      => array( "example.com\0" ),
			'HTML'                          => array( 'example.com"><b>' ),
			'two ports'                     => array( 'example.com:80:80' ),
			'unbracketed IPv6'              => array( '::1' ),
			'unclosed IPv6 bracket'         => array( '[::1' ),
			'junk inside IPv6 bracket'      => array( '[::1#x]' ),
			'empty'                         => array( '' ),
		);
	}

	/**
	 * @dataProvider valid_hosts
	 *
	 * @param string $host Host header value.
	 */
	public function test_accepts_valid_hosts( string $host ): void {
		$this->assertTrue( wpsc_is_valid_http_host( $host ) );
	}

	/**
	 * @dataProvider invalid_hosts
	 *
	 * @param string $host Host header value.
	 */
	public function test_rejects_invalid_hosts( string $host ): void {
		$this->assertFalse( wpsc_is_valid_http_host( $host ) );
	}

	/** Anything other than a string is rejected rather than coerced. */
	public function test_rejects_non_strings(): void {
		$this->assertFalse( wpsc_is_valid_http_host( null ) );
		$this->assertFalse( wpsc_is_valid_http_host( array( 'example.com' ) ) );
	}

	/**
	 * An invalid Host header turns caching off in wp-cache-base.php.
	 *
	 * Runs in its own process because wp-cache-base.php sets globals at file
	 * scope and calls is_multisite(), which the smoke tier does not otherwise
	 * provide.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_base_disables_caching_for_invalid_host(): void {
		$this->load_base_with_host( 'example.com:00000080' );

		$this->assertFalse( $GLOBALS['cache_enabled'] );
	}

	/**
	 * A valid Host header leaves caching on in wp-cache-base.php.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_base_keeps_caching_for_valid_host(): void {
		$this->load_base_with_host( 'Example.com:8080' );

		$this->assertTrue( $GLOBALS['cache_enabled'] );
		$this->assertSame( 'example.com:8080', $GLOBALS['WPSC_HTTP_HOST'] );
	}

	/**
	 * Include wp-cache-base.php with the given Host header and caching on.
	 *
	 * @param string $host Host header value.
	 */
	private function load_base_with_host( string $host ): void {
		if ( ! function_exists( 'is_multisite' ) ) {
			/**
			 * Single site (test double for WordPress is_multisite()).
			 *
			 * @return bool
			 */
			function is_multisite() { // phpcs:ignore MediaWiki.Usage.NestedFunctions.NestedFunction -- defined only in this separate test process.
				return false;
			}
		}

		$_SERVER['HTTP_HOST']     = $host;
		$GLOBALS['cache_enabled'] = true;
		$GLOBALS['cache_path']    = sys_get_temp_dir() . '/wpsc-smoke/';

		require dirname( __DIR__, 3 ) . '/wp-cache-base.php';
	}
}
