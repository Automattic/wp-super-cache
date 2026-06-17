<?php
/**
 * Characterization tests for the preload status logic in wp-cache.php.
 *
 * These pin the CURRENT preload state contract (read / update / reset against
 * the status file, options, and marker files) before the preload subsystem is
 * relocated to inc/preload.php (issue #1061).
 *
 * Integration tier: the cluster lives in wp-cache.php and the functions use the
 * real options API, the option_preload_cache_counter filter (registered at
 * plugin load), and the $cache_path filesystem.
 *
 * @package automattic/wp-super-cache
 */
class PreloadStatusTest extends WP_UnitTestCase {

	/**
	 * Temp cache directory, removed in tear_down().
	 *
	 * @var string
	 */
	private $cache_dir;

	public function set_up() {
		parent::set_up();

		$this->cache_dir = trailingslashit( get_temp_dir() ) . 'wpsc-preload-' . uniqid();
		mkdir( $this->cache_dir, 0700, true );
		$GLOBALS['cache_path'] = trailingslashit( $this->cache_dir );

		delete_option( 'preload_cache_counter' );
	}

	public function tear_down() {
		$this->rrmdir( $this->cache_dir );
		parent::tear_down();
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
	 * Characterizes wpsc_get_preload_status_file_path(): the status file lives in
	 * the cache path.
	 */
	public function test_status_file_path() {
		$this->assertSame(
			$GLOBALS['cache_path'] . 'preload_permalink.txt',
			wpsc_get_preload_status_file_path()
		);
	}

	/**
	 * Characterizes wpsc_get_preload_status() with no status file: preload is
	 * idle with an empty history.
	 */
	public function test_get_preload_status_default_is_idle() {
		$expected = array(
			'running'  => false,
			'history'  => array(),
			'next'     => false,
			'previous' => null,
		);

		$this->assertSame( $expected, wpsc_get_preload_status() );
	}

	/**
	 * Characterizes wpsc_get_preload_status() reading an existing status file:
	 * the stored JSON is returned verbatim.
	 */
	public function test_get_preload_status_reads_status_file() {
		$stored = array(
			'running'  => true,
			'history'  => array(
				array(
					'group'    => 'posts',
					'progress' => 3,
					'url'      => '/hello/',
				),
			),
			'next'     => false,
			'previous' => 1700000000,
		);
		file_put_contents( wpsc_get_preload_status_file_path(), wp_json_encode( $stored ) );

		$this->assertSame( $stored, wpsc_get_preload_status() );
	}

	/**
	 * Characterizes wpsc_update_active_preload(): marks preload running and
	 * prepends history entries newest-first, capped at five.
	 */
	public function test_update_active_preload_tracks_running_history() {
		for ( $i = 1; $i <= 6; $i++ ) {
			wpsc_update_active_preload( 'posts', $i, "/page/$i/" );
		}

		$status = wpsc_get_preload_status();

		$this->assertTrue( $status['running'] );
		$this->assertCount( 5, $status['history'], 'history capped at 5' );
		$this->assertSame( 6, $status['history'][0]['progress'], 'newest entry first' );
		$this->assertSame( 2, $status['history'][4]['progress'], 'oldest retained entry' );
		$this->assertSame( '/page/6/', $status['history'][0]['url'] );
	}

	/**
	 * Characterizes wpsc_update_idle_preload(): clears running/history and records
	 * the finish time as the previous run.
	 */
	public function test_update_idle_preload_clears_and_records_finish() {
		wpsc_update_active_preload( 'posts', 1, '/a/' );

		wpsc_update_idle_preload( 1700000123 );

		$status = wpsc_get_preload_status();
		$this->assertFalse( $status['running'] );
		$this->assertSame( array(), $status['history'] );
		$this->assertSame( 1700000123, $status['previous'] );
	}

	/**
	 * Characterizes wpsc_is_preload_active() with no markers and a zero counter:
	 * preload is not active.
	 */
	public function test_is_preload_active_false_by_default() {
		$this->assertFalse( wpsc_is_preload_active() );
	}

	/**
	 * Characterizes wpsc_is_preload_active(): the stop-preload flag forces an
	 * inactive result even if the mutex is present.
	 */
	public function test_is_preload_active_false_when_stop_flag_present() {
		file_put_contents( $GLOBALS['cache_path'] . 'preload_mutex.tmp', '' );
		file_put_contents( $GLOBALS['cache_path'] . 'stop_preload.txt', '' );

		$this->assertFalse( wpsc_is_preload_active() );
	}

	/**
	 * Characterizes wpsc_is_preload_active(): the preload mutex marks it active.
	 */
	public function test_is_preload_active_true_with_mutex() {
		file_put_contents( $GLOBALS['cache_path'] . 'preload_mutex.tmp', '' );

		$this->assertTrue( wpsc_is_preload_active() );
	}

	/**
	 * Characterizes wpsc_is_preload_active(): a positive post counter marks it
	 * active.
	 */
	public function test_is_preload_active_true_with_positive_counter() {
		update_option(
			'preload_cache_counter',
			array(
				'c' => 3,
				't' => time(),
			)
		);

		$this->assertTrue( wpsc_is_preload_active() );
	}

	/**
	 * Characterizes wpsc_reset_preload_counter(): the counter is zeroed.
	 */
	public function test_reset_preload_counter_zeroes_count() {
		update_option(
			'preload_cache_counter',
			array(
				'c' => 9,
				't' => 123,
			)
		);

		wpsc_reset_preload_counter();

		$counter = get_option( 'preload_cache_counter' );
		$this->assertSame( 0, $counter['c'] );
	}

	/**
	 * Characterizes wpsc_reset_preload_settings(): clears the mutex, stop flag,
	 * and taxonomy markers, and zeroes the counter.
	 */
	public function test_reset_preload_settings_clears_markers() {
		file_put_contents( $GLOBALS['cache_path'] . 'preload_mutex.tmp', '' );
		file_put_contents( $GLOBALS['cache_path'] . 'stop_preload.txt', '' );
		file_put_contents( $GLOBALS['cache_path'] . 'taxonomy_post_tag.txt', '' );
		update_option(
			'preload_cache_counter',
			array(
				'c' => 4,
				't' => time(),
			)
		);

		wpsc_reset_preload_settings();

		$this->assertFileDoesNotExist( $GLOBALS['cache_path'] . 'preload_mutex.tmp' );
		$this->assertFileDoesNotExist( $GLOBALS['cache_path'] . 'stop_preload.txt' );
		$this->assertFileDoesNotExist( $GLOBALS['cache_path'] . 'taxonomy_post_tag.txt' );

		$counter = get_option( 'preload_cache_counter' );
		$this->assertSame( 0, $counter['c'] );
	}
}
