<?php
/**
 * Settings-page form handlers and validators: GC timing, rejected/accepted
 * lists, tracking parameters, debug settings, direct/locked pages, lockdown,
 * and the settings restore action.
 *
 * Relocated from wp-cache.php (issue #1061) as a pure move: identical function
 * signatures and hook registrations, no behaviour change.
 *
 * @package WP_Super_Cache
 */

function wpsc_restore_settings() {
	$admin_url = admin_url( 'options-general.php?page=wpsupercache' );
	wpsc_render_partial(
		'restore',
		compact( 'admin_url' )
	);
}

function wp_update_lock_down() {
	global $cache_path, $wp_cache_config_file, $valid_nonce;

	if ( isset( $_POST[ 'wp_lock_down' ] ) && $valid_nonce ) {
		$wp_lock_down = $_POST[ 'wp_lock_down' ] == '1' ? '1' : '0';
		wp_cache_replace_line( '^.*WPLOCKDOWN', "if ( ! defined( 'WPLOCKDOWN' ) ) define( 'WPLOCKDOWN', '$wp_lock_down' );", $wp_cache_config_file );
		if ( false == defined( 'WPLOCKDOWN' ) )
			define( 'WPLOCKDOWN', $wp_lock_down );
		if ( $wp_lock_down == '0' && function_exists( 'prune_super_cache' ) )
			prune_super_cache( $cache_path, true ); // clear the cache after lockdown
		return $wp_lock_down;
	}
	if ( defined( 'WPLOCKDOWN' ) )
		return constant( 'WPLOCKDOWN' );
	else
		return 0;
}

/*
 * Note on the two var_export() calls below. Each page has already been through a
 * metacharacter strip that removes $ ( ) ; [ ] ' " and #, but not the backslash.
 * Wrapping the value as "'$page', " therefore let a trailing backslash escape the
 * closing quote and break the $cached_direct_pages array literal in
 * wp-cache-config.php, which is included as PHP before WordPress boots. Building
 * the element with var_export() closes that, the way wp_cache_setting() does for
 * scalar settings and wp_cache_sanitize_value() does for the list settings.
 */
function wpsc_update_direct_pages() {
	global $cached_direct_pages, $valid_nonce, $cache_path, $wp_cache_config_file;

	if ( false == isset( $cached_direct_pages ) )
		$cached_direct_pages = array();
	$out = '';
	if ( $valid_nonce && array_key_exists('direct_pages', $_POST) && is_array( $_POST[ 'direct_pages' ] ) && !empty( $_POST[ 'direct_pages' ] ) ) {
		$expiredfiles = array_diff( $cached_direct_pages, $_POST[ 'direct_pages' ] );
		$cached_direct_pages = array();
		foreach( $_POST[ 'direct_pages' ] as $page ) {
			$page = str_replace( '..', '', preg_replace( '/[ <>\'\"\r\n\t\(\)\$\[\];#]/', '', $page ) );
			if ( $page != '' ) {
				$cached_direct_pages[] = $page;
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Generating PHP source for the config file.
				$out .= var_export( (string) $page, true ) . ', ';
			}
		}
	}
	if ( $valid_nonce && array_key_exists('new_direct_page', $_POST) && $_POST[ 'new_direct_page' ] && '' != $_POST[ 'new_direct_page' ] ) {
		$page = str_replace( get_option( 'siteurl' ), '', $_POST[ 'new_direct_page' ] );
		$page = str_replace( '..', '', preg_replace( '/[ <>\'\"\r\n\t\(\)\$\[\];#]/', '', $page ) );
		if ( substr( $page, 0, 1 ) != '/' )
			$page = '/' . $page;
		if ( $page != '/' || false == is_array( $cached_direct_pages ) || in_array( $page, $cached_direct_pages ) == false ) {
			$cached_direct_pages[] = $page;
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Generating PHP source for the config file.
			$out .= var_export( (string) $page, true ) . ', ';

			@unlink( trailingslashit( ABSPATH . $page ) . "index.html" );
			// $page is typed by an admin, so its case is whatever they typed, while
			// the supercache directory for it is lowercased. The unlink above is a
			// real file under ABSPATH and must keep the spelling as given. See #1081.
			wpsc_delete_files( get_supercache_dir() . wpsc_normalize_uri_case( $page ) );
		}
	}

	if ( $out != '' ) {
		$out = substr( $out, 0, -2 );
	}
	if ( $out == "''" ) {
		$out = '';
	}
	$out = '$cached_direct_pages = array( ' . $out . ' );';
	wp_cache_replace_line('^ *\$cached_direct_pages', "$out", $wp_cache_config_file);

	if ( !empty( $expiredfiles ) ) {
		foreach( $expiredfiles as $file ) {
			if( $file != '' ) {
				$firstfolder = explode( '/', $file );
				$firstfolder = ABSPATH . $firstfolder[1];
				$file = ABSPATH . $file;
				$file = realpath( str_replace( '..', '', preg_replace('/[ <>\'\"\r\n\t\(\)]/', '', $file ) ) );
				if ( $file ) {
					@unlink( trailingslashit( $file ) . "index.html" );
					@unlink( trailingslashit( $file ) . "index.html.gz" );
					RecursiveFolderDelete( trailingslashit( $firstfolder ) );
				}
			}
		}
	}

	if ( $valid_nonce && array_key_exists('deletepage', $_POST) && $_POST[ 'deletepage' ] ) {
		$page = str_replace( '..', '', preg_replace('/[ <>\'\"\r\n\t\(\)]/', '', $_POST['deletepage'] ) ) . '/';
		$pagefile = realpath( ABSPATH . $page . 'index.html' );
		if ( substr( $pagefile, 0, strlen( ABSPATH ) ) != ABSPATH || false == wp_cache_confirm_delete( ABSPATH . $page ) ) {
			die( __( 'Cannot delete directory', 'wp-super-cache' ) );
		}
		$firstfolder = explode( '/', $page );
		$firstfolder = ABSPATH . $firstfolder[1];
		$page = ABSPATH . $page;
		if( is_file( $pagefile ) && is_writeable_ACLSafe( $pagefile ) && is_writeable_ACLSafe( $firstfolder ) ) {
			@unlink( $pagefile );
			@unlink( $pagefile . '.gz' );
			RecursiveFolderDelete( $firstfolder );
		}
	}

	return $cached_direct_pages;
}

function wpsc_lockdown() {
	global $cached_direct_pages, $cache_enabled, $super_cache_enabled;

	$admin_url = admin_url( 'options-general.php?page=wpsupercache' );
	$wp_lock_down = wp_update_lock_down();

	wpsc_render_partial(
		'lockdown',
		compact( 'cached_direct_pages', 'cache_enabled', 'super_cache_enabled', 'admin_url', 'wp_lock_down' )
	);
}

function wp_cache_time_update() {
	global $cache_max_time, $wp_cache_config_file, $valid_nonce, $cache_schedule_type, $cache_scheduled_time, $cache_schedule_interval, $cache_time_interval, $cache_gc_email_me;
	if ( isset( $_POST[ 'action' ] ) && $_POST[ 'action' ] == 'expirytime' ) {

		if ( false == $valid_nonce )
			return false;

		if( !isset( $cache_schedule_type ) ) {
			$cache_schedule_type = 'interval';
			wp_cache_replace_line('^ *\$cache_schedule_type', "\$cache_schedule_type = '$cache_schedule_type';", $wp_cache_config_file);
		}

		if( !isset( $cache_scheduled_time ) ) {
			$cache_scheduled_time = '00:00';
			wp_cache_replace_line('^ *\$cache_scheduled_time', "\$cache_scheduled_time = '$cache_scheduled_time';", $wp_cache_config_file);
		}

		if( !isset( $cache_max_time ) ) {
			$cache_max_time = 3600;
			wp_cache_replace_line('^ *\$cache_max_time', "\$cache_max_time = $cache_max_time;", $wp_cache_config_file);
		}

		if ( !isset( $cache_time_interval ) ) {
			$cache_time_interval = $cache_max_time;
			wp_cache_replace_line('^ *\$cache_time_interval', "\$cache_time_interval = '$cache_time_interval';", $wp_cache_config_file);
		}

		if ( isset( $_POST['wp_max_time'] ) ) {
			$cache_max_time = (int)$_POST['wp_max_time'];
			wp_cache_replace_line('^ *\$cache_max_time', "\$cache_max_time = $cache_max_time;", $wp_cache_config_file);
			// schedule gc watcher
			if ( false == wp_next_scheduled( 'wp_cache_gc_watcher' ) )
				wp_schedule_event( time()+600, 'hourly', 'wp_cache_gc_watcher' );
		}

		if ( isset( $_POST[ 'cache_gc_email_me' ] ) ) {
			$cache_gc_email_me = 1;
			wp_cache_replace_line('^ *\$cache_gc_email_me', "\$cache_gc_email_me = $cache_gc_email_me;", $wp_cache_config_file);
		} else {
			$cache_gc_email_me = 0;
			wp_cache_replace_line('^ *\$cache_gc_email_me', "\$cache_gc_email_me = $cache_gc_email_me;", $wp_cache_config_file);
		}
		if ( isset( $_POST[ 'cache_schedule_type' ] ) && $_POST[ 'cache_schedule_type' ] == 'interval' && isset( $_POST['cache_time_interval'] ) ) {
			wp_clear_scheduled_hook( 'wp_cache_gc' );
			$cache_schedule_type = 'interval';
			if ( (int)$_POST[ 'cache_time_interval' ] == 0 )
				$_POST[ 'cache_time_interval' ] = 600;
			$cache_time_interval = (int)$_POST[ 'cache_time_interval' ];
			wp_schedule_single_event( time() + $cache_time_interval, 'wp_cache_gc' );
			wp_cache_replace_line('^ *\$cache_schedule_type', "\$cache_schedule_type = '$cache_schedule_type';", $wp_cache_config_file);
			wp_cache_replace_line('^ *\$cache_time_interval', "\$cache_time_interval = '$cache_time_interval';", $wp_cache_config_file);
		} else { // clock
			wp_clear_scheduled_hook( 'wp_cache_gc' );
			$cache_schedule_type = 'time';
			if ( !isset( $_POST[ 'cache_scheduled_time' ] ) ||
				$_POST[ 'cache_scheduled_time' ] == '' ||
				5 != strlen( $_POST[ 'cache_scheduled_time' ] ) ||
				":" != substr( $_POST[ 'cache_scheduled_time' ], 2, 1 )
			)
				$_POST[ 'cache_scheduled_time' ] = '00:00';

			$cache_scheduled_time = $_POST[ 'cache_scheduled_time' ];

			if ( ! preg_match( '/[0-9][0-9]:[0-9][0-9]/', $cache_scheduled_time ) ) {
				$cache_scheduled_time = '00:00';
			}
			$schedules = wp_get_schedules();
			if ( !isset( $cache_schedule_interval ) )
				$cache_schedule_interval = 'daily';
			if ( isset( $_POST[ 'cache_schedule_interval' ] ) && isset( $schedules[ $_POST[ 'cache_schedule_interval' ] ] ) )
				$cache_schedule_interval = $_POST[ 'cache_schedule_interval' ];
			wp_cache_replace_line('^ *\$cache_schedule_type', "\$cache_schedule_type = '$cache_schedule_type';", $wp_cache_config_file);
			wp_cache_replace_line('^ *\$cache_schedule_interval', "\$cache_schedule_interval = '{$cache_schedule_interval}';", $wp_cache_config_file);
			wp_cache_replace_line('^ *\$cache_scheduled_time', "\$cache_scheduled_time = '$cache_scheduled_time';", $wp_cache_config_file);
			wp_schedule_event( strtotime( $cache_scheduled_time ), $cache_schedule_interval, 'wp_cache_gc' );
		}
	}
}

function wp_cache_sanitize_value($text, & $array) {
	$text = esc_html(strip_tags($text));
	$array = preg_split( '/[\s,]+/', rtrim( $text ) );
	$text = var_export($array, true);
	$text = preg_replace('/[\s]+/', ' ', $text);
	return $text;
}

function wp_cache_update_rejected_ua() {
	global $cache_rejected_user_agent, $wp_cache_config_file, $valid_nonce;

	if ( isset( $_POST[ 'wp_rejected_user_agent' ] ) && $valid_nonce ) {
		$_POST[ 'wp_rejected_user_agent' ] = str_replace( ' ', '___', $_POST[ 'wp_rejected_user_agent' ] );
		$text = str_replace( '___', ' ', wp_cache_sanitize_value( $_POST[ 'wp_rejected_user_agent' ], $cache_rejected_user_agent ) );
		wp_cache_replace_line( '^ *\$cache_rejected_user_agent', "\$cache_rejected_user_agent = $text;", $wp_cache_config_file );
		foreach( $cache_rejected_user_agent as $k => $ua ) {
			$cache_rejected_user_agent[ $k ] = str_replace( '___', ' ', $ua );
		}
		reset( $cache_rejected_user_agent );
	}
}

function wpsc_edit_rejected_ua() {
	global $cache_rejected_user_agent;

	$admin_url = admin_url( 'options-general.php?page=wpsupercache' );
	wp_cache_update_rejected_ua();
	wpsc_render_partial(
		'rejected_user_agents',
		compact( 'cache_rejected_user_agent', 'admin_url' )
	);
}

function wp_cache_update_rejected_pages() {
	global $wp_cache_config_file, $valid_nonce, $wp_cache_pages;

	if ( isset( $_POST[ 'wp_edit_rejected_pages' ] ) && $valid_nonce ) {
		$pages = array( 'single', 'pages', 'archives', 'tag', 'frontpage', 'home', 'category', 'feed', 'author', 'search' );
		foreach( $pages as $page ) {
			if ( isset( $_POST[ 'wp_cache_pages' ][ $page ] ) ) {
				$value = 1;
			} else {
				$value = 0;
			}
			wp_cache_replace_line('^ *\$wp_cache_pages\[ "' . $page . '" \]', "\$wp_cache_pages[ \"{$page}\" ] = $value;", $wp_cache_config_file);
			$wp_cache_pages[ $page ] = $value;
		}
	}
}

function wpsc_update_tracking_parameters() {
	global $wpsc_tracking_parameters, $valid_nonce, $wp_cache_config_file;

	if ( isset( $_POST['tracking_parameters'] ) && $valid_nonce ) {
		$text = wp_cache_sanitize_value( str_replace( '\\\\', '\\', $_POST['tracking_parameters'] ), $wpsc_tracking_parameters );
		wp_cache_replace_line( '^ *\$wpsc_tracking_parameters', "\$wpsc_tracking_parameters = $text;", $wp_cache_config_file );
		wp_cache_setting( 'wpsc_ignore_tracking_parameters', isset( $_POST['wpsc_ignore_tracking_parameters'] ) ? 1 : 0 );
	}
}

function wpsc_edit_tracking_parameters() {
	global $wpsc_tracking_parameters, $wpsc_ignore_tracking_parameters;

	$admin_url = admin_url( 'options-general.php?page=wpsupercache' );
	wpsc_update_tracking_parameters();

	if ( ! isset( $wpsc_tracking_parameters ) ) {
		$wpsc_tracking_parameters = array( 'fbclid', 'ref', 'gclid', 'fb_source', 'mc_cid', 'mc_eid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_expid', 'mtm_source', 'mtm_medium', 'mtm_campaign', 'mtm_keyword', 'mtm_content', 'mtm_cid', 'mtm_group', 'mtm_placement', 'ysclid', 'srsltid', 'yclid' );
	}

	if ( ! isset( $wpsc_ignore_tracking_parameters ) ) {
		$wpsc_ignore_tracking_parameters = 0;
	}
	wpsc_render_partial(
		'tracking_parameters',
		compact( 'wpsc_ignore_tracking_parameters', 'wpsc_tracking_parameters', 'admin_url' )
	);
}

function wp_cache_update_rejected_cookies() {
	global $wpsc_rejected_cookies, $wp_cache_config_file, $valid_nonce;

	if ( isset( $_POST['wp_rejected_cookies'] ) && $valid_nonce ) {
		$text = wp_cache_sanitize_value( str_replace( '\\\\', '\\', $_POST['wp_rejected_cookies'] ), $wpsc_rejected_cookies );
		wp_cache_replace_line( '^ *\$wpsc_rejected_cookies', "\$wpsc_rejected_cookies = $text;", $wp_cache_config_file );
	}
}

function wp_cache_update_rejected_strings() {
	global $cache_rejected_uri, $wp_cache_config_file, $valid_nonce;

	if ( isset($_REQUEST['wp_rejected_uri']) && $valid_nonce ) {
		$text = wp_cache_sanitize_value( str_replace( '\\\\', '\\', $_REQUEST['wp_rejected_uri'] ), $cache_rejected_uri );
		wp_cache_replace_line('^ *\$cache_rejected_uri', "\$cache_rejected_uri = $text;", $wp_cache_config_file);
	}
}

function wp_cache_update_accepted_strings() {
	global $cache_acceptable_files, $wp_cache_config_file, $valid_nonce;

	if ( isset( $_REQUEST[ 'wp_accepted_files' ] ) && $valid_nonce ) {
		$text = wp_cache_sanitize_value( $_REQUEST[ 'wp_accepted_files' ], $cache_acceptable_files );
		wp_cache_replace_line( '^ *\$cache_acceptable_files', "\$cache_acceptable_files = $text;", $wp_cache_config_file );
	}
}

function wpsc_update_debug_settings() {
	global $wp_super_cache_debug, $wp_cache_debug_log, $wp_cache_debug_ip, $cache_path, $valid_nonce, $wp_cache_config_file, $wp_super_cache_comments;
	global $wp_super_cache_front_page_check, $wp_super_cache_front_page_clear, $wp_super_cache_front_page_text, $wp_super_cache_front_page_notification, $wp_super_cache_advanced_debug;
	global $wp_cache_debug_username;

	if ( ! isset( $wp_super_cache_comments ) ) {
		$wp_super_cache_comments = 1; // defaults to "enabled".
		wp_cache_setting( 'wp_super_cache_comments', $wp_super_cache_comments );
	}

	if ( false == $valid_nonce ) {
		return array (
			'wp_super_cache_debug' => $wp_super_cache_debug,
			'wp_cache_debug_log' => $wp_cache_debug_log,
			'wp_cache_debug_ip' => $wp_cache_debug_ip,
			'wp_super_cache_comments' => $wp_super_cache_comments,
			'wp_super_cache_front_page_check' => $wp_super_cache_front_page_check,
			'wp_super_cache_front_page_clear' => $wp_super_cache_front_page_clear,
			'wp_super_cache_front_page_text' => $wp_super_cache_front_page_text,
			'wp_super_cache_front_page_notification' => $wp_super_cache_front_page_notification,
			'wp_super_cache_advanced_debug' => $wp_super_cache_advanced_debug,
			'wp_cache_debug_username' => $wp_cache_debug_username,
		);
	}

	if ( isset( $_POST[ 'wpsc_delete_log' ] ) && $_POST[ 'wpsc_delete_log' ] == 1 && $wp_cache_debug_log != '' ) {
		@unlink( $cache_path . $wp_cache_debug_log );
		extract( wpsc_create_debug_log( $wp_cache_debug_log, $wp_cache_debug_username ) ); // $wp_cache_debug_log, $wp_cache_debug_username
	}

	if ( ! isset( $wp_cache_debug_log ) || $wp_cache_debug_log == '' ) {
		extract( wpsc_create_debug_log() ); // $wp_cache_debug_log, $wp_cache_debug_username
	} elseif ( ! file_exists( $cache_path . $wp_cache_debug_log ) ) { // make sure debug log exists before toggling debugging
		extract( wpsc_create_debug_log( $wp_cache_debug_log, $wp_cache_debug_username ) ); // $wp_cache_debug_log, $wp_cache_debug_username
	}
	$wp_super_cache_debug = ( isset( $_POST[ 'wp_super_cache_debug' ] ) && $_POST[ 'wp_super_cache_debug' ] == 1 ) ? 1 : 0;
	wp_cache_setting( 'wp_super_cache_debug', $wp_super_cache_debug );

	if ( isset( $_POST[ 'wp_cache_debug' ] ) ) {
		wp_cache_setting( 'wp_cache_debug_username', $wp_cache_debug_username );
		wp_cache_setting( 'wp_cache_debug_log', $wp_cache_debug_log );
		$wp_super_cache_comments = isset( $_POST[ 'wp_super_cache_comments' ] ) ? 1 : 0;
		wp_cache_setting( 'wp_super_cache_comments', $wp_super_cache_comments );
		if ( isset( $_POST[ 'wp_cache_debug_ip' ] ) && filter_var( $_POST[ 'wp_cache_debug_ip' ], FILTER_VALIDATE_IP ) ) {
			$wp_cache_debug_ip = esc_html( preg_replace( '/[ <>\'\"\r\n\t\(\)\$\[\];#]/', '', $_POST[ 'wp_cache_debug_ip' ] ) );
		} else {
			$wp_cache_debug_ip = '';
		}
		wp_cache_setting( 'wp_cache_debug_ip', $wp_cache_debug_ip );
		$wp_super_cache_front_page_check = isset( $_POST[ 'wp_super_cache_front_page_check' ] ) ? 1 : 0;
		wp_cache_setting( 'wp_super_cache_front_page_check', $wp_super_cache_front_page_check );
		$wp_super_cache_front_page_clear = isset( $_POST[ 'wp_super_cache_front_page_clear' ] ) ? 1 : 0;
		wp_cache_setting( 'wp_super_cache_front_page_clear', $wp_super_cache_front_page_clear );
		if ( isset( $_POST[ 'wp_super_cache_front_page_text' ] ) ) {
			$wp_super_cache_front_page_text = esc_html( preg_replace( '/[ <>\'\"\r\n\t\(\)\$\[\];#]/', '', $_POST[ 'wp_super_cache_front_page_text' ] ) );
		} else {
			$wp_super_cache_front_page_text = '';
		}
		wp_cache_setting( 'wp_super_cache_front_page_text', $wp_super_cache_front_page_text );
		$wp_super_cache_front_page_notification = isset( $_POST[ 'wp_super_cache_front_page_notification' ] ) ? 1 : 0;
		wp_cache_setting( 'wp_super_cache_front_page_notification', $wp_super_cache_front_page_notification );
		if ( $wp_super_cache_front_page_check == 1 && !wp_next_scheduled( 'wp_cache_check_site_hook' ) ) {
			wp_schedule_single_event( time() + 360 , 'wp_cache_check_site_hook' );
			wp_cache_debug( 'scheduled wp_cache_check_site_hook for 360 seconds time.' );
		}
	}

	return array (
		'wp_super_cache_debug' => $wp_super_cache_debug,
		'wp_cache_debug_log' => $wp_cache_debug_log,
		'wp_cache_debug_ip' => $wp_cache_debug_ip,
		'wp_super_cache_comments' => $wp_super_cache_comments,
		'wp_super_cache_front_page_check' => $wp_super_cache_front_page_check,
		'wp_super_cache_front_page_clear' => $wp_super_cache_front_page_clear,
		'wp_super_cache_front_page_text' => $wp_super_cache_front_page_text,
		'wp_super_cache_front_page_notification' => $wp_super_cache_front_page_notification,
		'wp_super_cache_advanced_debug' => $wp_super_cache_advanced_debug,
		'wp_cache_debug_username' => $wp_cache_debug_username,
	);
}
