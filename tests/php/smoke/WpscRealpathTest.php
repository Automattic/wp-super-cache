<?php
/**
 * Smoke tests for the path-containment helpers wpsc_get_realpath() and
 * wpsc_is_in_cache_directory().
 *
 * Both operate on the real filesystem (realpath + the $cache_path global), so the
 * tests create real temporary directories rather than relying on a WordPress
 * runtime.
 *
 * Note: wpsc_is_in_cache_directory() caches the resolved cache path in a static
 * after its first call, so every test in this class deliberately uses the same
 * $cache_path established in setUpBeforeClass().
 *
 * @package automattic/wp-super-cache
 */

// wp-cache-phase2.php is loaded by the smoke bootstrap (tests/php/bootstrap-smoke.php).

use PHPUnit\Framework\TestCase;

/**
 * @covers ::wpsc_get_realpath
 * @covers ::wpsc_is_in_cache_directory
 */
class WpscRealpathTest extends TestCase {

	/** @var string Temporary cache directory used as $cache_path. */
	private static string $cache_path;

	/** @var string A directory that lives outside the cache directory. */
	private static string $outside_dir;

	public static function setUpBeforeClass(): void {
		$base = sys_get_temp_dir() . '/wpsc-realpath-' . getmypid();

		self::$cache_path  = $base . '/cache/';
		self::$outside_dir = $base . '/outside/';

		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- temp dirs for a no-WP smoke test.
		mkdir( self::$cache_path . 'supercache/example.com', 0777, true );
		mkdir( self::$outside_dir, 0777, true );
		// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
	}

	public static function tearDownAfterClass(): void {
		// Remove deepest paths first.
		foreach (
			array(
				self::$cache_path . 'supercache/example.com',
				self::$cache_path . 'supercache',
				self::$cache_path,
				self::$outside_dir,
				dirname( self::$cache_path ),
			) as $dir
		) {
			if ( is_dir( $dir ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- temp dir cleanup in a no-WP smoke test.
				rmdir( $dir );
			}
		}
	}

	protected function setUp(): void {
		$GLOBALS['cache_path'] = self::$cache_path;
	}

	// ── wpsc_get_realpath() ───────────────────────────────────────────────────

	/** Root is explicitly rejected. */
	public function test_realpath_of_root_is_false(): void {
		$this->assertFalse( wpsc_get_realpath( '/' ) );
	}

	/** A non-existent directory resolves to false. */
	public function test_realpath_of_missing_directory_is_false(): void {
		$this->assertFalse( wpsc_get_realpath( self::$cache_path . 'does/not/exist' ) );
	}

	/** An existing directory resolves and the trailing slash is removed. */
	public function test_realpath_strips_trailing_slash(): void {
		$resolved = wpsc_get_realpath( self::$cache_path );

		$this->assertNotFalse( $resolved );
		$this->assertSame( realpath( self::$cache_path ), $resolved );
		$this->assertDoesNotMatchRegularExpression( '#[/\\\\]$#', $resolved );
	}

	// ── wpsc_is_in_cache_directory() ──────────────────────────────────────────

	/** A subdirectory of the cache path is inside it. */
	public function test_subdirectory_is_in_cache_directory(): void {
		$this->assertTrue( wpsc_is_in_cache_directory( self::$cache_path . 'supercache/example.com' ) );
	}

	/** The cache path itself is considered inside the cache directory. */
	public function test_cache_path_itself_is_in_cache_directory(): void {
		$this->assertTrue( wpsc_is_in_cache_directory( self::$cache_path ) );
	}

	/** A directory outside the cache path is rejected. */
	public function test_outside_directory_is_not_in_cache_directory(): void {
		$this->assertFalse( wpsc_is_in_cache_directory( self::$outside_dir ) );
	}

	/** A blank directory argument is rejected. */
	public function test_blank_directory_is_not_in_cache_directory(): void {
		$this->assertFalse( wpsc_is_in_cache_directory( '' ) );
	}
}
