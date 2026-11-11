<?php
/**
 * Tests for Clock-mode ("time") preload scheduling in inc/preload.php.
 *
 * @package automattic/wp-super-cache
 */
class PreloadScheduleTest extends WP_UnitTestCase {

	/**
	 * Temp cache directory, removed in tear_down().
	 *
	 * @var string
	 */
	private $cache_dir;

	public function set_up() {
		parent::set_up();

		$this->cache_dir = trailingslashit( get_temp_dir() ) . 'wpsc-preload-schedule-' . uniqid();
		mkdir( $this->cache_dir, 0700, true );
		$GLOBALS['cache_path'] = trailingslashit( $this->cache_dir );

		_set_cron_array( array() );
		update_option( 'timezone_string', 'America/Chicago' );
	}

	public function tear_down() {
		unset( $GLOBALS['preload_scheduled_time'], $GLOBALS['preload_schedule_interval'] );
		foreach ( glob( $this->cache_dir . '/*' ) as $file ) {
			unlink( $file );
		}
		rmdir( $this->cache_dir );
		_set_cron_array( array() );
		parent::tear_down();
	}

	public function test_cancel_clears_recurring_preload_alongside_preload_now() {
		$GLOBALS['preload_scheduled_time']    = '03:00';
		$GLOBALS['preload_schedule_interval'] = 'daily';
		wpsc_schedule_clock_preload();
		wpsc_enable_preload();

		wpsc_cancel_preload();

		$this->assertFalse( wp_next_scheduled( 'wp_cache_full_preload_hook' ) );
	}

	public function test_unregistered_interval_falls_back_to_daily() {
		$GLOBALS['preload_scheduled_time']    = '03:00';
		$GLOBALS['preload_schedule_interval'] = 'wpsc_removed_schedule';

		$this->assertTrue( wpsc_schedule_clock_preload() );
		$this->assertSame( 'daily', wp_get_schedule( 'wp_cache_full_preload_hook' ) );
	}

	/**
	 * @dataProvider data_minute_offsets
	 *
	 * @param int $offset Minutes from now, in the site timezone.
	 */
	public function test_next_timestamp_is_next_future_occurrence( $offset ) {
		$now  = time();
		$hhmm = ( new DateTime( '@' . ( $now + $offset * 60 ) ) )->setTimezone( wp_timezone() )->format( 'H:i' );

		$next = wpsc_next_scheduled_preload_timestamp( $hhmm );

		$this->assertGreaterThan( $now, $next );
		$this->assertLessThanOrEqual( $now + DAY_IN_SECONDS, $next );
		$this->assertSame( $hhmm, wp_date( 'H:i', $next ) );
	}

	public function data_minute_offsets() {
		return array(
			'earlier today' => array( -5 ),
			'later today'   => array( 90 ),
		);
	}
}
