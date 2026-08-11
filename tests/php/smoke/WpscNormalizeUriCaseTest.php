<?php
/**
 * Smoke tests for wpsc_normalize_uri_case().
 *
 * Supercache directory names are lowercase with any percent escapes uppercased.
 * The same path reaches us spelled two ways: sanitize_title_with_dashes() stores
 * lowercase escapes in post_name (utf8_uri_encode() builds them with dechex()),
 * while a URL encoder emits them uppercase. We normalise both to one spelling so
 * they land on one directory. Uppercase is the target because RFC 3986 section
 * 2.1 recommends it and because it is the shape already on disk.
 *
 * The helper is pure, so it belongs in the smoke tier. That matters: CI runs
 * `composer test-php`, which is smoke only, so this is the tier where the rule is
 * actually covered on every push. See #1081.
 *
 * @package automattic/wp-super-cache
 */

// wp-cache-phase2.php is loaded by the smoke bootstrap (tests/php/bootstrap-smoke.php).

use PHPUnit\Framework\TestCase;

/**
 * @covers ::wpsc_normalize_uri_case
 */
class WpscNormalizeUriCaseTest extends TestCase {

	/**
	 * Both spellings of the Kazakh slug from #1080, plus the shape we expect on
	 * disk. The lowercase form is what WordPress stores in post_name; the
	 * uppercase form is what a URL encoder produces for the same characters.
	 */
	const SLUG_LOWER = '/%d2%b1lytau-oblysy/';
	const SLUG_UPPER = '/%D2%B1LYTAU-OBLYSY/';
	const SLUG_DISK  = '/%D2%B1lytau-oblysy/';

	/** WordPress's own spelling normalises to the directory on disk. */
	public function test_lowercase_escapes_are_uppercased(): void {
		$this->assertSame( self::SLUG_DISK, wpsc_normalize_uri_case( self::SLUG_LOWER ) );
	}

	/** A URL encoder's spelling of the same path normalises to the same place. */
	public function test_uppercase_input_normalises_to_the_same_result(): void {
		$this->assertSame( self::SLUG_DISK, wpsc_normalize_uri_case( self::SLUG_UPPER ) );
	}

	/**
	 * The regression from #1080 and #1081, stated directly: the two spellings a
	 * visitor and WordPress produce for one URL have to collapse to one string.
	 */
	public function test_both_spellings_agree(): void {
		$this->assertSame(
			wpsc_normalize_uri_case( self::SLUG_LOWER ),
			wpsc_normalize_uri_case( self::SLUG_UPPER )
		);
	}

	/** Mixed case in a single escape is normalised too. */
	public function test_mixed_case_escapes_are_uppercased(): void {
		$this->assertSame( '/%D2%B1/', wpsc_normalize_uri_case( '/%d2%B1/' ) );
	}

	/**
	 * Order matters. Lowercasing has to happen first, otherwise the ASCII around
	 * an escape would drag the escape back down with it.
	 */
	public function test_ascii_is_lowercased_around_the_escapes(): void {
		$this->assertSame( '/blog/%D2%B1lytau/', wpsc_normalize_uri_case( '/Blog/%D2%B1LYTAU/' ) );
	}

	/** A plain ASCII path is just lowercased. */
	public function test_path_without_escapes_is_lowercased(): void {
		$this->assertSame( '/blog/hello-there/', wpsc_normalize_uri_case( '/Blog/Hello-There/' ) );
	}

	/** A path that is already normalised comes back untouched. */
	public function test_normalising_twice_changes_nothing(): void {
		$this->assertSame( self::SLUG_DISK, wpsc_normalize_uri_case( self::SLUG_DISK ) );
	}

	/**
	 * A percent that is not the start of an escape is left alone. `%zz` is not
	 * hex, and a bare `%` has nothing following it.
	 */
	public function test_percent_that_is_not_an_escape_is_left_alone(): void {
		$this->assertSame( '/50%-off/', wpsc_normalize_uri_case( '/50%-Off/' ) );
		$this->assertSame( '/%zz/', wpsc_normalize_uri_case( '/%ZZ/' ) );
	}

	/**
	 * Raw UTF-8 bytes survive. strtolower() is byte-wise ASCII, so the high bytes
	 * of an unencoded unicode path pass through unchanged rather than being
	 * mangled.
	 */
	public function test_raw_utf8_is_not_mangled(): void {
		$this->assertSame( '/Ұlytau/', wpsc_normalize_uri_case( '/Ұlytau/' ) );
	}

	/** An empty string is not a special case. */
	public function test_empty_string(): void {
		$this->assertSame( '', wpsc_normalize_uri_case( '' ) );
	}
}
