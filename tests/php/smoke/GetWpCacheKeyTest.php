<?php
/**
 * Smoke tests for get_wp_cache_key().
 *
 * Exercises cache-key composition from the request globals/superglobals without a
 * WordPress runtime. The accept-header classification embedded in the key is
 * covered in depth by WpscParseAcceptHeaderTest; here we only assert the key
 * incorporates it.
 *
 * @package automattic/wp-super-cache
 */

// wp-cache-phase2.php is loaded by the smoke bootstrap (tests/php/bootstrap-smoke.php).

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;

#[CoversFunction( 'get_wp_cache_key' )]
class GetWpCacheKeyTest extends TestCase {

	protected function setUp(): void {
		// Globals get_wp_cache_key() (and its helpers) read; all must be defined
		// to satisfy the suite's failOnWarning policy.
		$GLOBALS['WPSC_HTTP_HOST']          = 'example.com';
		$GLOBALS['wp_cache_request_uri']    = '/from-global/';
		$GLOBALS['wp_cache_gzip_encoding']  = '';
		$GLOBALS['wp_cache_mobile_enabled'] = false;

		unset( $_SERVER['SERVER_PORT'], $_SERVER['HTTP_ACCEPT'] );
		$_COOKIE = array();
	}

	/** With no URL argument the request-uri global is used. */
	public function test_uses_request_uri_global_when_no_url(): void {
		$key = get_wp_cache_key();

		$this->assertStringContainsString( 'example.com', $key );
		$this->assertStringContainsString( '/from-global/', $key );
	}

	/** An explicit URL argument overrides the request-uri global. */
	public function test_explicit_url_overrides_global(): void {
		$key = get_wp_cache_key( '/explicit/page/' );

		$this->assertStringContainsString( '/explicit/page/', $key );
		$this->assertStringNotContainsString( '/from-global/', $key );
	}

	/** '/index.php' is normalised to '/' so it does not leak into the key. */
	public function test_index_php_is_normalised(): void {
		$key = get_wp_cache_key( '/index.php' );

		$this->assertStringNotContainsString( 'index.php', $key );
	}

	/** A URL fragment is stripped before the key is built. */
	public function test_fragment_is_stripped(): void {
		$key = get_wp_cache_key( '/page/#section' );

		$this->assertStringNotContainsString( '#section', $key );
		$this->assertStringContainsString( '/page/', $key );
	}

	/** The key always embeds the accept-header classification. */
	public function test_key_includes_accept_classification(): void {
		$this->assertStringContainsString( '-' . wpsc_get_accept_header(), get_wp_cache_key( '/x/' ) );
	}

	/** The key is deterministic for identical request state. */
	public function test_key_is_deterministic(): void {
		$this->assertSame( get_wp_cache_key( '/stable/' ), get_wp_cache_key( '/stable/' ) );
	}
}
