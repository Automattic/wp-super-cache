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
		unset(
			$GLOBALS['preload_schedule_type'],
			$GLOBALS['preload_scheduled_time'],
			$GLOBALS['preload_schedule_interval'],
			$GLOBALS['wp_cache_preload_on'],
			$GLOBALS['wp_cache_preload_interval'],
			$GLOBALS['wp_cache_config_file'],
			$GLOBALS['valid_nonce']
		);
		$_POST = array();
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

	/**
	 * Save the Preload tab form the way the settings page posts it.
	 *
	 * @param array $fields Posted fields overriding the defaults.
	 */
	private function save_preload_settings( array $fields ) {
		$config = $GLOBALS['cache_path'] . 'wp-cache-config.php';
		file_put_contents( $config, "<?php\n" );
		$GLOBALS['wp_cache_config_file'] = $config;
		$GLOBALS['valid_nonce']          = true;

		$_POST = array_merge(
			array(
				'action'                    => 'preload',
				'wp_cache_preload_interval' => '30',
				'wp_cache_preload_posts'    => 'all',
				'wp_cache_preload_on'       => '1',
				'preload_schedule_type'     => 'interval',
				'preload_scheduled_time'    => '00:00',
				'preload_schedule_interval' => 'daily',
			),
			$fields
		);
		wpsc_preload_settings();
	}

	public function test_first_save_without_clock_settings_keeps_pending_timer_preload() {
		$GLOBALS['wp_cache_preload_on']       = 1;
		$GLOBALS['wp_cache_preload_interval'] = 30;
		$pending                              = time() + 600;
		wp_schedule_single_event( $pending, 'wp_cache_full_preload_hook' );

		$this->save_preload_settings( array() );

		$this->assertSame( $pending, wp_next_scheduled( 'wp_cache_full_preload_hook' ) );
		$this->assertFileDoesNotExist( $GLOBALS['cache_path'] . 'stop_preload.txt' );
	}

	public function test_changing_clock_time_while_idle_does_not_skip_the_next_run() {
		$GLOBALS['wp_cache_preload_on']       = 1;
		$GLOBALS['wp_cache_preload_interval'] = 30;
		$GLOBALS['preload_schedule_type']     = 'time';
		$GLOBALS['preload_scheduled_time']    = '03:00';
		$GLOBALS['preload_schedule_interval'] = 'daily';
		wpsc_schedule_clock_preload();

		$this->save_preload_settings(
			array(
				'preload_schedule_type'  => 'time',
				'preload_scheduled_time' => '04:00',
			)
		);

		$this->assertSame( wpsc_next_scheduled_preload_timestamp( '04:00' ), wp_next_scheduled( 'wp_cache_full_preload_hook' ) );
		$this->assertFileDoesNotExist( $GLOBALS['cache_path'] . 'stop_preload.txt' );
	}

	public function test_clock_interval_below_minimum_falls_back_to_daily() {
		$every_minute = static function ( $schedules ) {
			$schedules['wpsc_every_minute'] = array(
				'interval' => MINUTE_IN_SECONDS,
				'display'  => 'Every minute',
			);
			return $schedules;
		};
		add_filter( 'cron_schedules', $every_minute ); // phpcs:ignore WordPress.WP.CronInterval -- Deliberately below the preload minimum.

		$this->save_preload_settings(
			array(
				'preload_schedule_type'     => 'time',
				'preload_schedule_interval' => 'wpsc_every_minute',
			)
		);

		remove_filter( 'cron_schedules', $every_minute );
		$this->assertSame( 'daily', wp_get_schedule( 'wp_cache_full_preload_hook' ) );
	}
}
