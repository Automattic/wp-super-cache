<?php
/**
 * Characterization tests for the settings-form updaters in wp-cache.php.
 *
 * These pin the CURRENT parse/sanitise/persist behaviour of the settings-form
 * handlers before the settings-forms cluster is relocated to
 * inc/settings-forms.php (issue #1061). The binding contract is: given posted
 * input (with a valid nonce), what values are parsed into the runtime globals
 * and written to the config file.
 *
 * Integration tier: the cluster lives in wp-cache.php and the updaters call
 * WordPress functions and write to a real config file via wp_cache_replace_line
 * / wp_cache_setting.
 *
 * @package automattic/wp-super-cache
 */
class SettingsFormUpdatersTest extends WP_UnitTestCase {

	/**
	 * Temp directories created per test, removed in tear_down().
	 *
	 * @var string[]
	 */
	private $temp_dirs = array();

	public function set_up() {
		parent::set_up();

		// Updaters gate on a valid nonce; default to true and override where the
		// no-nonce path is the behaviour under test.
		$GLOBALS['valid_nonce'] = true;

		// Reset request superglobals so each test starts clean.
		$_POST    = array();
		$_REQUEST = array();
	}

	public function tear_down() {
		foreach ( $this->temp_dirs as $dir ) {
			$this->rrmdir( $dir );
		}
		$this->temp_dirs = array();
		$_POST           = array();
		$_REQUEST        = array();
		parent::tear_down();
	}

	/**
	 * Create a writable temp config file seeded with the given PHP lines and
	 * point $wp_cache_config_file / cache_path (used for the atomic temp write)
	 * at it.
	 *
	 * @param string[] $lines Config lines (without the opening PHP tag).
	 * @return string Absolute path to the config file.
	 */
	private function make_config_file( array $lines ) {
		$dir = trailingslashit( get_temp_dir() ) . 'wpsc-cfg-' . uniqid();
		mkdir( $dir, 0700, true );
		$this->temp_dirs[] = $dir;

		$config = trailingslashit( $dir ) . 'wp-cache-config.php';
		file_put_contents( $config, "<?php\n" . implode( "\n", $lines ) . "\n" );

		$GLOBALS['cache_path']           = trailingslashit( $dir );
		$GLOBALS['wp_cache_config_file'] = $config;

		return $config;
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
	 * Characterizes wp_cache_sanitize_value(): HTML-strips/escapes the input,
	 * splits on whitespace and commas into the by-reference array, and returns a
	 * whitespace-collapsed var_export of that array.
	 */
	public function test_wp_cache_sanitize_value_splits_and_escapes() {
		$array = array();
		$text  = wp_cache_sanitize_value( "foo, bar  baz\nqux", $array );

		$this->assertSame( array( 'foo', 'bar', 'baz', 'qux' ), $array );
		$this->assertSame( "array ( 0 => 'foo', 1 => 'bar', 2 => 'baz', 3 => 'qux', )", $text );
	}

	/**
	 * Characterizes wp_cache_sanitize_value() HTML handling: tags are stripped
	 * and entities escaped before splitting.
	 */
	public function test_wp_cache_sanitize_value_strips_tags_and_escapes_entities() {
		$array = array();
		wp_cache_sanitize_value( '<b>keep</b> a&b', $array );

		$this->assertSame( array( 'keep', 'a&amp;b' ), $array );
	}

	/**
	 * Characterizes wp_cache_update_rejected_strings(): posted URIs are parsed
	 * into $cache_rejected_uri and the parsed array is written to the config
	 * file.
	 */
	public function test_update_rejected_strings_parses_and_persists() {
		$config = $this->make_config_file( array( '$cache_rejected_uri = array();' ) );

		$_REQUEST['wp_rejected_uri'] = 'wp-admin, feed';

		wp_cache_update_rejected_strings();

		$this->assertSame( array( 'wp-admin', 'feed' ), $GLOBALS['cache_rejected_uri'] );
		$this->assertStringContainsString(
			"\$cache_rejected_uri = array ( 0 => 'wp-admin', 1 => 'feed', );",
			file_get_contents( $config )
		);
	}

	/**
	 * Characterizes wp_cache_update_rejected_pages(): each known page type is
	 * set to 1 when its checkbox is posted, otherwise 0.
	 */
	public function test_update_rejected_pages_sets_flags_from_checkboxes() {
		$this->make_config_file( array( '// pages' ) );

		$_POST['wp_edit_rejected_pages'] = '1';
		$_POST['wp_cache_pages']         = array(
			'single' => '1',
			'feed'   => '1',
		);

		wp_cache_update_rejected_pages();

		$this->assertSame( 1, $GLOBALS['wp_cache_pages']['single'] );
		$this->assertSame( 1, $GLOBALS['wp_cache_pages']['feed'] );
		$this->assertSame( 0, $GLOBALS['wp_cache_pages']['archives'] );
		$this->assertSame( 0, $GLOBALS['wp_cache_pages']['search'] );
	}

	/**
	 * Characterizes wpsc_update_tracking_parameters(): posted parameters are
	 * parsed into $wpsc_tracking_parameters and the ignore flag is stored.
	 */
	public function test_update_tracking_parameters_parses_and_sets_ignore_flag() {
		$this->make_config_file( array( '$wpsc_tracking_parameters = array();' ) );

		$_POST['tracking_parameters']             = 'utm_source, fbclid';
		$_POST['wpsc_ignore_tracking_parameters'] = 'on';

		wpsc_update_tracking_parameters();

		$this->assertSame( array( 'utm_source', 'fbclid' ), $GLOBALS['wpsc_tracking_parameters'] );
		$this->assertSame( 1, $GLOBALS['wpsc_ignore_tracking_parameters'] );
	}

	/**
	 * Characterizes wp_cache_time_update() interval-schedule path.
	 *
	 * Critical contract: cache_max_time is written as an UNQUOTED integer
	 * (e.g. `$cache_max_time = 3600;`) while cache_time_interval is written
	 * QUOTED even though it is also numeric (e.g. `$cache_time_interval = '600';`).
	 *
	 * This distinction is preserved verbatim across the #1062 migration: L145/L155
	 * (max_time) are migrated to Config::set() whose format_value() emits numeric
	 * values unquoted, while L150/L176 (time_interval) are left on raw
	 * wp_cache_replace_line() to keep the quoted form.
	 */
	public function test_time_update_interval_path_writes_max_time_unquoted_and_interval_quoted() {
		$config = $this->make_config_file(
			array(
				"\$cache_schedule_type = 'interval';",
				"\$cache_scheduled_time = '00:00';",
				'$cache_max_time = 1800;',
				"\$cache_time_interval = '1800';",
				'$cache_gc_email_me = 0;',
				"\$cache_schedule_interval = 'daily';",
			)
		);

		// Pre-populate all globals so none of the "unset → initialize" branches fire.
		$GLOBALS['cache_schedule_type']     = 'interval';
		$GLOBALS['cache_scheduled_time']    = '00:00';
		$GLOBALS['cache_max_time']          = 1800;
		$GLOBALS['cache_time_interval']     = 1800;
		$GLOBALS['cache_gc_email_me']       = 0;
		$GLOBALS['cache_schedule_interval'] = 'daily';

		$_POST['action']              = 'expirytime';
		$_POST['wp_max_time']         = '3600';      // updates cache_max_time
		$_POST['cache_schedule_type'] = 'interval';
		$_POST['cache_time_interval'] = '600';       // updates cache_time_interval

		wp_cache_time_update();

		$content = file_get_contents( $config );

		// cache_max_time → numeric → UNQUOTED.
		$this->assertStringContainsString( '$cache_max_time = 3600;', $content, 'cache_max_time must be unquoted integer' );
		$this->assertStringNotContainsString( "\$cache_max_time = '3600';", $content, 'cache_max_time must NOT be quoted' );

		// cache_time_interval → numeric but written QUOTED by original code.
		$this->assertStringContainsString( "\$cache_time_interval = '600';", $content, 'cache_time_interval must be quoted' );
		$this->assertStringNotContainsString( '$cache_time_interval = 600;', $content, 'cache_time_interval must NOT be unquoted' );

		// Globals updated.
		$this->assertSame( 3600, $GLOBALS['cache_max_time'] );
		$this->assertSame( 600, $GLOBALS['cache_time_interval'] );
		$this->assertSame( 'interval', $GLOBALS['cache_schedule_type'] );
	}

	/**
	 * Characterizes wp_cache_time_update() initialization branch for cache_max_time.
	 *
	 * When cache_max_time is not set in globals the function sets it to 3600 and
	 * writes it UNQUOTED. This is the L145 site migrated to Config::set().
	 */
	public function test_time_update_initializes_max_time_as_unquoted_integer() {
		$config = $this->make_config_file(
			array(
				"\$cache_schedule_type = 'interval';",
				"\$cache_scheduled_time = '00:00';",
				'$cache_max_time = 1800;',
				"\$cache_time_interval = '1800';",
				'$cache_gc_email_me = 0;',
			)
		);

		// Unset cache_max_time to trigger the initialization branch (L143–146).
		unset( $GLOBALS['cache_max_time'] );
		$GLOBALS['cache_schedule_type']  = 'interval';
		$GLOBALS['cache_scheduled_time'] = '00:00';
		$GLOBALS['cache_time_interval']  = 1800;
		$GLOBALS['cache_gc_email_me']    = 0;

		$_POST['action']              = 'expirytime';
		$_POST['cache_schedule_type'] = 'interval';
		$_POST['cache_time_interval'] = '1800';

		wp_cache_time_update();

		$content = file_get_contents( $config );

		// Initialization default 3600 written UNQUOTED.
		$this->assertStringContainsString( '$cache_max_time = 3600;', $content, 'init cache_max_time must be unquoted' );
		$this->assertStringNotContainsString( "\$cache_max_time = '3600';", $content, 'init cache_max_time must NOT be quoted' );
	}

	/**
	 * Characterizes wp_cache_time_update() clock-schedule path (L197–199).
	 *
	 * All three clock-path fields (cache_schedule_type, cache_schedule_interval,
	 * cache_scheduled_time) are written as QUOTED strings — verified for the
	 * L197/L198/L199 sites migrated to Config::set().
	 */
	public function test_time_update_clock_path_writes_schedule_fields_quoted() {
		$config = $this->make_config_file(
			array(
				"\$cache_schedule_type = 'interval';",
				"\$cache_scheduled_time = '00:00';",
				'$cache_max_time = 1800;',
				"\$cache_time_interval = '1800';",
				'$cache_gc_email_me = 0;',
				"\$cache_schedule_interval = 'daily';",
			)
		);

		$GLOBALS['cache_schedule_type']     = 'interval';
		$GLOBALS['cache_scheduled_time']    = '00:00';
		$GLOBALS['cache_max_time']          = 1800;
		$GLOBALS['cache_time_interval']     = 1800;
		$GLOBALS['cache_gc_email_me']       = 0;
		$GLOBALS['cache_schedule_interval'] = 'daily';

		$_POST['action']                  = 'expirytime';
		$_POST['cache_schedule_type']     = 'time'; // not 'interval' → clock branch
		$_POST['cache_scheduled_time']    = '14:00';
		$_POST['cache_schedule_interval'] = 'daily';

		wp_cache_time_update();

		$content = file_get_contents( $config );

		$this->assertStringContainsString( "\$cache_schedule_type = 'time';", $content );
		$this->assertStringContainsString( "\$cache_schedule_interval = 'daily';", $content );
		$this->assertStringContainsString( "\$cache_scheduled_time = '14:00';", $content );
	}

	/**
	 * Characterizes wpsc_update_debug_settings() without a valid nonce: it makes
	 * no changes and returns a snapshot of the current debug-related settings.
	 */
	public function test_update_debug_settings_without_nonce_returns_snapshot() {
		$GLOBALS['valid_nonce']                            = false;
		$GLOBALS['wp_super_cache_debug']                   = 1;
		$GLOBALS['wp_cache_debug_log']                     = 'log.php';
		$GLOBALS['wp_cache_debug_ip']                      = '203.0.113.5';
		$GLOBALS['wp_super_cache_comments']                = 1;
		$GLOBALS['wp_super_cache_front_page_check']        = 0;
		$GLOBALS['wp_super_cache_front_page_clear']        = 0;
		$GLOBALS['wp_super_cache_front_page_text']         = '';
		$GLOBALS['wp_super_cache_front_page_notification'] = 0;
		$GLOBALS['wp_super_cache_advanced_debug']          = 0;
		$GLOBALS['wp_cache_debug_username']                = 'someuser';

		$snapshot = wpsc_update_debug_settings();

		$this->assertSame( 1, $snapshot['wp_super_cache_debug'] );
		$this->assertSame( 'log.php', $snapshot['wp_cache_debug_log'] );
		$this->assertSame( '203.0.113.5', $snapshot['wp_cache_debug_ip'] );
		$this->assertSame( 'someuser', $snapshot['wp_cache_debug_username'] );
	}
}
