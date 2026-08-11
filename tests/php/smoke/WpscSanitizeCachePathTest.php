<?php
/**
 * Smoke tests for wpsc_sanitize_cache_path().
 *
 * This replaced a sanitize_text_field() call in the delete-cache button. That
 * function strips every percent escape it sees, so the button could never delete
 * a supercache directory for a non-ASCII slug: /category/%d2%b1lytau/ arrived as
 * /category/lytau/ and realpath() found nothing. See #1081.
 *
 * The value is used to build a filesystem path, so the tests below cover both
 * halves of the job: keep what a URL path legitimately contains, drop everything
 * else.
 *
 * @package automattic/wp-super-cache
 */

// wp-cache-phase2.php is loaded by the smoke bootstrap (tests/php/bootstrap-smoke.php).

use PHPUnit\Framework\TestCase;

/**
 * @covers ::wpsc_sanitize_cache_path
 */
class WpscSanitizeCachePathTest extends TestCase {

	/** The regression: percent escapes survive, which sanitize_text_field() did not allow. */
	public function test_percent_escapes_are_kept(): void {
		$this->assertSame(
			'/category/%d2%b1lytau-oblysy/',
			wpsc_sanitize_cache_path( '/category/%d2%b1lytau-oblysy/' )
		);
	}

	/** An ordinary path is returned unchanged. */
	public function test_plain_path_is_unchanged(): void {
		$this->assertSame( '/blog/hello-there/', wpsc_sanitize_cache_path( '/blog/hello-there/' ) );
	}

	/** The remaining characters a URL path can carry are all kept. */
	public function test_other_path_characters_are_kept(): void {
		$this->assertSame( '/a_b/c.d/~e-f/2026/', wpsc_sanitize_cache_path( '/a_b/c.d/~e-f/2026/' ) );
	}

	/**
	 * Angle brackets, quotes and whitespace are dropped. The old
	 * preg_replace() in wpsc_admin_bar_render() removed exactly these before the
	 * value ever reached a nonce, and the allow-list keeps that guarantee.
	 */
	public function test_markup_and_whitespace_are_dropped(): void {
		$this->assertSame( '/blog/scriptalert1/script/', wpsc_sanitize_cache_path( "/blog/<script>alert(1)</script>/\r\n" ) );
		$this->assertSame( '/blog/one/', wpsc_sanitize_cache_path( '/blog/ one /' ) );
	}

	/**
	 * A null byte cannot reach the filesystem calls. PHP rejects paths containing
	 * one, but stripping it here means it never gets that far.
	 */
	public function test_null_byte_is_dropped(): void {
		$this->assertSame( '/blog/x/', wpsc_sanitize_cache_path( "/blog/x\0/" ) );
	}

	/**
	 * Backslashes go, so a Windows-style separator cannot be smuggled past the
	 * '..' strip that runs on the result.
	 */
	public function test_backslashes_are_dropped(): void {
		$this->assertSame( '/blog/..etc/', wpsc_sanitize_cache_path( '/blog/..\\etc/' ) );
	}

	/**
	 * Colons go. wpsc_delete_cache_directory() truncates at ':' after calling
	 * this, so that line now has nothing left to find, but it is kept as a guard
	 * against this allow-list changing. Pinned here so the two stay in step.
	 */
	public function test_colons_are_dropped(): void {
		$this->assertSame( '/blog/x/8080/evil', wpsc_sanitize_cache_path( '/blog/x/:8080/evil' ) );
	}

	/** A query string loses the characters that make it one. */
	public function test_query_string_punctuation_is_dropped(): void {
		$this->assertSame( '/blog/x/idsomething', wpsc_sanitize_cache_path( '/blog/x/?id=something' ) );
	}

	/** An empty string is not a special case. */
	public function test_empty_string(): void {
		$this->assertSame( '', wpsc_sanitize_cache_path( '' ) );
	}
}
