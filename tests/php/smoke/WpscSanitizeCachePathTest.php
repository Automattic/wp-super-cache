<?php
/**
 * Smoke tests for wpsc_sanitize_cache_path().
 *
 * This replaced a sanitize_text_field() call in the delete-cache button. That
 * function strips every percent escape it sees, so the button could never delete
 * a supercache directory for a non-ASCII slug: /category/%d2%b1lytau/ arrived as
 * /category/lytau/ and realpath() found nothing. See #1081.
 *
 * The value is used to build a filesystem path that a nonce has already been
 * checked against, so the function returns the path or it returns nothing. The
 * tests below cover both halves of that: keep what a URL path legitimately
 * contains, reject the whole value otherwise.
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

	/** The unreserved characters are all kept. */
	public function test_other_path_characters_are_kept(): void {
		$this->assertSame( '/a_b/c.d/~e-f/2026/', wpsc_sanitize_cache_path( '/a_b/c.d/~e-f/2026/' ) );
	}

	/**
	 * The sub-delims and '@' are kept. get_current_url_supercache_dir() does not
	 * strip these, so a directory on disk really can be named with them, and an
	 * ActivityPub profile page at /@donncha/ is the obvious case.
	 */
	public function test_sub_delims_and_at_are_kept(): void {
		$this->assertSame( '/@donncha/', wpsc_sanitize_cache_path( '/@donncha/' ) );
		$this->assertSame( '/a!b/c$d/e&f/g*h/i+j/k,l/m;n/o=p/', wpsc_sanitize_cache_path( '/a!b/c$d/e&f/g*h/i+j/k,l/m;n/o=p/' ) );
	}

	/**
	 * Raw bytes above \x7F are kept. A browser normally percent-encodes them, but
	 * not always, and get_current_url_supercache_dir() passes them through, so a
	 * directory can be spelled this way too.
	 */
	public function test_raw_utf8_is_kept(): void {
		$this->assertSame( '/Ұlytau-oblysy/', wpsc_sanitize_cache_path( '/Ұlytau-oblysy/' ) );
	}

	/**
	 * Anything off the allow-list rejects the whole value instead of being removed
	 * from it. Stripping would leave a shorter path that still resolves, so the
	 * delete would land on a real directory the nonce never covered.
	 */
	public function test_disallowed_characters_reject_the_whole_path(): void {
		$this->assertSame( '', wpsc_sanitize_cache_path( "/blog/<script>alert(1)</script>/\r\n" ) );
		$this->assertSame( '', wpsc_sanitize_cache_path( '/blog/ one /' ) );
		$this->assertSame( '', wpsc_sanitize_cache_path( '/blog/"quoted"/' ) );
	}

	/**
	 * A null byte cannot reach the filesystem calls. PHP rejects paths containing
	 * one, but rejecting here means it never gets that far.
	 */
	public function test_null_byte_is_rejected(): void {
		$this->assertSame( '', wpsc_sanitize_cache_path( "/blog/x\0/" ) );
	}

	/**
	 * A backslash is rejected, so a Windows-style separator cannot be smuggled
	 * past the '..' strip that runs on the result.
	 */
	public function test_backslash_is_rejected(): void {
		$this->assertSame( '', wpsc_sanitize_cache_path( '/blog/..\\etc/' ) );
	}

	/**
	 * ':' is rejected. wpsc_delete_cache_directory() truncates at ':' after
	 * calling this, so that line now has nothing left to find, but it is kept as
	 * a guard against this allow-list changing. Pinned here so the two stay in
	 * step.
	 */
	public function test_colon_is_rejected(): void {
		$this->assertSame( '', wpsc_sanitize_cache_path( '/blog/x/:8080/evil' ) );
	}

	/**
	 * The anchor is \z, not $, so a trailing newline cannot ride along on an
	 * otherwise valid path.
	 */
	public function test_trailing_newline_is_rejected(): void {
		$this->assertSame( '', wpsc_sanitize_cache_path( "/blog/hello-there/\n" ) );
	}

	/**
	 * $_POST['path'] is an array if the request sends path[]=. wp_unslash() hands
	 * that straight back, and a (string) cast on it would warn and produce the
	 * literal 'Array'.
	 */
	public function test_non_string_input_is_rejected(): void {
		$this->assertSame( '', wpsc_sanitize_cache_path( array( '/blog/' ) ) );
		$this->assertSame( '', wpsc_sanitize_cache_path( null ) );
	}

	/**
	 * A query string is kept whole rather than rejected or trimmed.
	 *
	 * The admin bar puts the delete button on any URL, so on a site using plain
	 * permalinks every path is '/?p=123' and on a search page it is '/?s=foo'.
	 * Rejecting those would empty $req_path and send wpsc_delete_cache_directory()
	 * down the branch that reports a nonce failure that did not happen. Trimming
	 * them would leave '/', the site root, which is a real directory and not the
	 * one the nonce covered. Keeping them means the path simply does not resolve,
	 * because supercache never names a directory after a query string, and the
	 * delete is the no-op it has always been.
	 */
	public function test_query_string_is_kept_whole(): void {
		$this->assertSame( '/?p=123', wpsc_sanitize_cache_path( '/?p=123' ) );
		$this->assertSame( '/?s=foo', wpsc_sanitize_cache_path( '/?s=foo' ) );
		$this->assertSame( '/blog/x/?utm_source=y', wpsc_sanitize_cache_path( '/blog/x/?utm_source=y' ) );
	}

	/**
	 * Encoded traversal passes the allow-list, deliberately.
	 *
	 * '%2e%2e%2f' is only percent escapes, which the whole point of this function
	 * is to preserve. It is safe because nothing downstream decodes it: realpath()
	 * looks for a directory literally named '%2E%2E%2F' and does not find one. The
	 * assertion is here so that if anything ever does decode it, this stops being
	 * a passing test.
	 */
	public function test_encoded_traversal_is_passed_through_undecoded(): void {
		$this->assertSame( '/%2e%2e%2f', wpsc_sanitize_cache_path( '/%2e%2e%2f' ) );
	}

	/** An empty string is not a special case. */
	public function test_empty_string(): void {
		$this->assertSame( '', wpsc_sanitize_cache_path( '' ) );
	}
}
