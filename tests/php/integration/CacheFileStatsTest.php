<?php
/**
 * Characterization tests for the cache-file stats helpers in wp-cache.php.
 *
 * These pin the CURRENT behaviour of the cache-file size/format/stats cluster
 * before it is relocated to inc/cache-files.php (issue #1061). They assert
 * observable outputs only, so they survive the pure-relocation move unchanged.
 *
 * Integration tier: although wp_cache_format_fsize() is pure, the whole cluster
 * lives in wp-cache.php (which the smoke tier does not load) and the other
 * helpers call WordPress functions (apply_filters, trailingslashit,
 * update_option, get_supercache_dir), so all of these run under the real WP
 * runtime.
 *
 * @package automattic/wp-super-cache
 */
class CacheFileStatsTest extends WP_UnitTestCase {

	/**
	 * Temp cache directories created per test, removed in tear_down().
	 *
	 * @var string[]
	 */
	private $temp_dirs = array();

	public function set_up() {
		parent::set_up();
		// Cluster globals read by the stats helpers. Defaults chosen so the
		// classification logic is deterministic per test.
		$GLOBALS['cache_max_time']      = 3600;
		$GLOBALS['wp_cache_preload_on'] = false;
		$GLOBALS['valid_nonce']         = false;
		$GLOBALS['file_prefix']         = 'wp-cache-';
		$GLOBALS['cache_path']          = '/tmp/wpsc-stats-test/';
	}

	public function tear_down() {
		foreach ( $this->temp_dirs as $dir ) {
			$this->rrmdir( $dir );
		}
		$this->temp_dirs = array();
		parent::tear_down();
	}

	/**
	 * Build a cache directory populated with the given files.
	 *
	 * @param array $files Map of relative filename => mtime offset in seconds
	 *                     (0 = now/fresh, negative = older/expired).
	 * @return string Absolute path to the created directory (trailing slash).
	 */
	private function make_cache_dir( array $files ) {
		$dir = trailingslashit( get_temp_dir() ) . 'wpsc-dirsize-' . uniqid();
		mkdir( $dir, 0700, true );
		$this->temp_dirs[] = $dir;

		$now = time();
		foreach ( $files as $name => $offset ) {
			$path = trailingslashit( $dir ) . $name;
			file_put_contents( $path, str_repeat( 'x', 100 ) );
			touch( $path, $now + $offset );
		}
		return trailingslashit( $dir );
	}

	private function rrmdir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( scandir( $dir ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $dir . '/' . $entry;
			is_dir( $path ) ? $this->rrmdir( $path ) : unlink( $path );
		}
		rmdir( $dir );
	}

	/**
	 * Characterizes wp_cache_format_fsize(), the pure size formatter.
	 *
	 * @dataProvider provide_fsize
	 *
	 * @param int|float $input    Size value (in KB) passed to the formatter.
	 * @param string    $expected Formatted output string.
	 */
	public function test_wp_cache_format_fsize( $input, $expected ) {
		$this->assertSame( $expected, wp_cache_format_fsize( $input ) );
	}

	public function provide_fsize() {
		return array(
			'zero'                 => array( 0, '0KB' ),
			'float zero'           => array( 0.0, '0KB' ),
			'small KB'             => array( 500, '500.00KB' ),
			'boundary 1024 is KB'  => array( 1024, '1,024.00KB' ),
			'just over 1024 is MB' => array( 1025, '1.00MB' ),
			'2048 is MB'           => array( 2048, '2.00MB' ),
			'large MB'             => array( 1048576, '1,024.00MB' ),
		);
	}

	/**
	 * Characterizes wpsc_generate_sizes_array(): the zeroed stats skeleton for
	 * each cache type.
	 */
	public function test_wpsc_generate_sizes_array_default_shape() {
		$expected = array(
			'supercache' => array(
				'expired'      => 0,
				'cached'       => 0,
				'fsize'        => 0,
				'cached_list'  => array(),
				'expired_list' => array(),
			),
			'wpcache'    => array(
				'expired'      => 0,
				'cached'       => 0,
				'fsize'        => 0,
				'cached_list'  => array(),
				'expired_list' => array(),
			),
		);

		$this->assertSame( $expected, wpsc_generate_sizes_array() );
	}

	/**
	 * Characterizes wpsc_dirsize(): it classifies files as wpcache vs supercache
	 * and expired vs cached, accumulates counts, and skips meta files. A path
	 * containing "/{$file_prefix}" is wpcache; anything else is supercache. A
	 * file is expired when its mtime + cache_max_time has passed.
	 */
	public function test_wpsc_dirsize_classifies_and_counts() {
		$dir = $this->make_cache_dir(
			array(
				'index.html'            => 0,     // supercache, fresh.
				'old.html'              => -7200, // supercache, expired (>1h old).
				'wp-cache-abc.php'      => 0,     // wpcache, fresh.
				'meta-wp-cache-abc.php' => 0,     // meta: must be skipped entirely.
			)
		);

		$sizes = wpsc_dirsize( $dir, wpsc_generate_sizes_array() );

		$this->assertSame( 1, $sizes['supercache']['cached'], 'fresh supercache file counted' );
		$this->assertSame( 1, $sizes['supercache']['expired'], 'old supercache file expired' );
		$this->assertSame( 1, $sizes['wpcache']['cached'], 'fresh wpcache file counted' );
		$this->assertSame( 0, $sizes['wpcache']['expired'], 'no expired wpcache files' );
	}

	/**
	 * Characterizes wpsc_dirsize() with preload on: supercache files are kept
	 * fresh (never counted as expired) regardless of age.
	 */
	public function test_wpsc_dirsize_preload_keeps_supercache_fresh() {
		$GLOBALS['wp_cache_preload_on'] = true;

		$dir = $this->make_cache_dir(
			array(
				'old.html' => -7200, // would be expired, but preload keeps it fresh.
			)
		);

		$sizes = wpsc_dirsize( $dir, wpsc_generate_sizes_array() );

		$this->assertSame( 1, $sizes['supercache']['cached'] );
		$this->assertSame( 0, $sizes['supercache']['expired'] );
	}

	/**
	 * Characterizes wp_cache_regenerate_cache_file_stats(): it walks
	 * $supercachedir, returns a stats array stamped with a generated time, and
	 * persists it to the supercache_stats option.
	 */
	public function test_wp_cache_regenerate_cache_file_stats() {
		$GLOBALS['cache_compression'] = false;
		$GLOBALS['supercachedir']     = $this->make_cache_dir(
			array(
				'index.html' => 0,     // supercache, fresh.
				'old.html'   => -7200, // supercache, expired.
			)
		);

		$before = time();
		$stats  = wp_cache_regenerate_cache_file_stats();

		$this->assertIsArray( $stats );
		$this->assertArrayHasKey( 'generated', $stats );
		$this->assertGreaterThanOrEqual( $before, $stats['generated'] );
		$this->assertSame( 1, $stats['supercache']['cached'] );
		$this->assertSame( 1, $stats['supercache']['expired'] );
		$this->assertSame( 0, $stats['wpcache']['cached'] );

		// The stats are persisted to the option store.
		$this->assertSame( $stats, get_option( 'supercache_stats' ) );
	}
}
