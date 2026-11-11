<?php
/**
 * Smoke tests for supercache_filename().
 *
 * Regression guard for the filename-sanitization fix in #1050: the function must
 * always return a single, separator-free path segment, even when the
 * `supercache_filename_str` filter (or cacheaction) returns hostile input.
 *
 * @package automattic/wp-super-cache
 */

// wp-cache-phase2.php is loaded by the smoke bootstrap (tests/php/bootstrap-smoke.php).

use PHPUnit\Framework\TestCase;

/**
 * @covers ::supercache_filename
 */
class SupercacheFilenameTest extends TestCase {

	protected function setUp(): void {
		// Known-good defaults; individual tests override as needed.
		$GLOBALS['cached_direct_pages'] = array();
		$_SERVER['REQUEST_URI']         = '/some/page/';
		unset( $_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO'] );
		remove_all_filters( 'supercache_filename_str' );
	}

	protected function tearDown(): void {
		remove_all_filters( 'supercache_filename_str' );
		unset( $_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO'] );
	}

	/** Plain HTTP request yields the canonical index.html. */
	public function test_plain_request_returns_index_html(): void {
		$this->assertSame( 'index.html', supercache_filename() );
	}

	/** HTTPS requests get the -https suffix. */
	public function test_https_request_returns_index_https_html(): void {
		$_SERVER['HTTPS'] = 'on';
		$this->assertSame( 'index-https.html', supercache_filename() );
	}

	/** A reverse-proxy https hint (X-Forwarded-Proto) is also honoured. */
	public function test_forwarded_proto_https_returns_index_https_html(): void {
		$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
		$this->assertSame( 'index-https.html', supercache_filename() );
	}

	/**
	 * #1050: a path-traversal payload from the filter must be stripped to a
	 * single safe segment — no slashes, no backslashes, no dot-dot.
	 */
	public function test_path_traversal_filter_is_sanitised(): void {
		add_filter(
			'supercache_filename_str',
			static function () {
				return '../../../etc/passwd';
			}
		);

		$filename = supercache_filename();

		$this->assertSame( 'indexetcpasswd.html', $filename );
		$this->assertStringNotContainsString( '/', $filename );
		$this->assertStringNotContainsString( '\\', $filename );
		$this->assertStringNotContainsString( '..', $filename );
	}

	/**
	 * Any hostile filter output is constrained to [a-zA-Z0-9_-] between the
	 * `index` prefix and the `.html` suffix.
	 */
	public function test_arbitrary_filter_output_is_constrained(): void {
		add_filter(
			'supercache_filename_str',
			static function () {
				return "evil\0\n/\\.. <script>;%2e%2e";
			}
		);

		$filename = supercache_filename();

		$this->assertMatchesRegularExpression( '/^index[a-zA-Z0-9_-]*\.html$/', $filename );
	}

	/**
	 * When the request URI is in $cached_direct_pages the suffix is dropped, so
	 * even an https request resolves to the bare index.html.
	 */
	public function test_cached_direct_page_drops_suffix(): void {
		$_SERVER['HTTPS']               = 'on';
		$_SERVER['REQUEST_URI']         = '/direct/page/';
		$GLOBALS['cached_direct_pages'] = array( '/direct/page/' );

		$this->assertSame( 'index.html', supercache_filename() );
	}
}
