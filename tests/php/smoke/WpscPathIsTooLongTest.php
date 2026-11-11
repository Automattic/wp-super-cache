<?php
/**
 * Smoke tests for wpsc_path_is_too_long().
 *
 * PHP emits "File name is longer than the maximum allowed path length on this
 * platform" from every filesystem call made with an overlong path, which fills an
 * error log with warnings. A malformed srcset gets a browser to request the whole
 * attribute as a single URL, and a long URL on a site with a deep cache_path
 * arrives at the same place. See #1085.
 *
 * The helper is pure, so it belongs in the smoke tier, which is the one CI runs.
 *
 * @package automattic/wp-super-cache
 */

// wp-cache-phase2.php is loaded by the smoke bootstrap (tests/php/bootstrap-smoke.php).

use PHPUnit\Framework\TestCase;

/**
 * @covers ::wpsc_path_is_too_long
 */
class WpscPathIsTooLongTest extends TestCase {

	/** Bytes of headroom the helper reserves for the filename appended to a directory. */
	const HEADROOM = 64;

	/**
	 * The platform limit the helper measures against.
	 *
	 * @return int
	 */
	private function max_path() {
		return defined( 'PHP_MAXPATHLEN' ) ? PHP_MAXPATHLEN : 1024;
	}

	/** An ordinary cache path is fine. */
	public function test_a_normal_path_is_accepted(): void {
		$this->assertFalse( wpsc_path_is_too_long( '/var/www/wp-content/cache/supercache/example.com/hello-there/' ) );
	}

	/** An empty string is not too long. */
	public function test_empty_string_is_accepted(): void {
		$this->assertFalse( wpsc_path_is_too_long( '' ) );
	}

	/**
	 * The reported case: a srcset requested as a URL. Roughly the shape from
	 * #1085, where the whole attribute becomes one path.
	 */
	public function test_a_srcset_sized_path_is_rejected(): void {
		$prefix = '/data/www/25451/feedit_cz/www/wp-content/cache/supercache/feedit.cz/wp-content/uploads/2026/03/';
		$chunk  = 'zastupci-ceskych-firem-pred-autonomnim-katamaranem-1024x768.jpeg%201024w,%20https:/feedit.cz/';

		// Sized from the platform limit rather than a fixed count. PHP_MAXPATHLEN is
		// 1024 on macOS, which is what the report shows, but 4096 on Linux, which is
		// what CI runs. A hardcoded length passes on one and fails on the other.
		$path = $prefix . str_repeat( $chunk, (int) ceil( $this->max_path() / strlen( $chunk ) ) + 1 );

		$this->assertGreaterThan( $this->max_path(), strlen( $path ), 'Fixture is not actually overlong.' );
		$this->assertTrue( wpsc_path_is_too_long( $path ) );
	}

	/**
	 * Right at the limit. A path that fits exactly is still rejected, because a
	 * filename gets appended to it before anything touches the filesystem.
	 */
	public function test_the_headroom_is_reserved(): void {
		$exactly_at_limit = str_repeat( 'a', $this->max_path() );
		$this->assertTrue( wpsc_path_is_too_long( $exactly_at_limit ) );

		$fits_with_headroom = str_repeat( 'a', $this->max_path() - self::HEADROOM );
		$this->assertFalse( wpsc_path_is_too_long( $fits_with_headroom ) );

		$one_over = str_repeat( 'a', $this->max_path() - self::HEADROOM + 1 );
		$this->assertTrue( wpsc_path_is_too_long( $one_over ) );
	}

	/**
	 * Measured in bytes, not characters. A path of raw UTF-8 is longer on disk
	 * than a naive character count suggests, and the filesystem counts bytes.
	 */
	public function test_length_is_measured_in_bytes(): void {
		// 'Ұ' is two bytes, so this is under the limit by character count and over
		// it by byte count. The filesystem cares about the latter.
		$chars = (int) floor( ( $this->max_path() - self::HEADROOM ) * 0.75 );
		$path  = str_repeat( 'Ұ', $chars );

		$this->assertLessThan( $this->max_path(), mb_strlen( $path ) );
		$this->assertTrue( wpsc_path_is_too_long( $path ) );
	}
}
