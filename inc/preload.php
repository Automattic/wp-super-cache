<?php
/**
 * The preload subsystem: status read/update/reset, the cron preload worker and
 * scheduling, the preload-counter option filter, the init kickstart, enable /
 * cancel, post-type and post-count helpers, the preload settings UI, and the
 * status AJAX endpoint.
 *
 * Relocated from wp-cache.php (issue #1061) as a pure move: identical function
 * signatures and hook registrations, no behaviour change.
 *
 * @package WP_Super_Cache
 */

/**
 * Serves an AJAX endpoint to return the current state of the preload process.
 */
function wpsc_ajax_get_preload_status() {
	check_ajax_referer( 'wpsc-get-preload-status' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}

	$preload_status = wpsc_get_preload_status( true );
	wp_send_json_success( $preload_status, null, JSON_UNESCAPED_SLASHES );
}
add_action( 'wp_ajax_wpsc_get_preload_status', 'wpsc_ajax_get_preload_status' );

/**
 * Returns the location of the preload status file.
 */
function wpsc_get_preload_status_file_path() {
	global $cache_path;
	return $cache_path . 'preload_permalink.txt';
}

/**
 * Get the timestamp of the next preload.
 */
function wpsc_get_next_preload_time() {
	$next = wp_next_scheduled( 'wp_cache_preload_hook' );
	if ( ! $next ) {
		$next = wp_next_scheduled( 'wp_cache_full_preload_hook' );
	}

	return $next;
}

/**
 * Read the preload status. Caches the result in a static variable.
 */
function wpsc_get_preload_status( $include_next = false ) {
	$status = array(
		'running'  => false,
		'history'  => array(),
		'next'     => false,
		'previous' => null,
	);

	$filename = wpsc_get_preload_status_file_path();
	if ( file_exists( $filename ) ) {
		$data = wp_json_file_decode( $filename, array( 'associative' => true ) );
		if ( is_array( $data ) ) {
			$status = $data;
		}
	}

	if ( $include_next ) {
		$status['next'] = wpsc_get_next_preload_time();
	}

	return $status;
}

/**
 * Update the preload status file during a preload.
 */
function wpsc_update_active_preload( $group = null, $progress = null, $url = null ) {
	$preload_status = wpsc_get_preload_status();

	$preload_status['running'] = true;

	// Add the new entry to the history.
	array_unshift(
		$preload_status['history'],
		array(
			'group'    => $group,
			'progress' => $progress,
			'url'      => $url,
		)
	);

	// Limit to 5 in the history.
	$preload_status['history'] = array_slice( $preload_status['history'], 0, 5 );

	$filename = wpsc_get_preload_status_file_path();
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	if ( false === file_put_contents( $filename, wp_json_encode( $preload_status, JSON_UNESCAPED_SLASHES ) ) ) {
		wp_cache_debug( "wpsc_update_active_preload: failed to write to $filename" );
	}
}

/**
 * Update the preload status to indicate it is idle. If a finish time is specified, store it.
 */
function wpsc_update_idle_preload( $finish_time = null ) {
	$preload_status = wpsc_get_preload_status();

	$preload_status['running'] = false;
	$preload_status['history'] = array();

	if ( ! empty( $finish_time ) ) {
		$preload_status['previous'] = $finish_time;
	}

	$filename = wpsc_get_preload_status_file_path();
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	if ( false === file_put_contents( $filename, wp_json_encode( $preload_status, JSON_UNESCAPED_SLASHES ) ) ) {
		wp_cache_debug( "wpsc_update_idle_preload: failed to write to $filename" );
	}
}

function wp_cron_preload_cache() {
	global $wpdb, $wp_cache_preload_interval, $wp_cache_preload_posts, $wp_cache_preload_email_me, $wp_cache_preload_email_volume, $cache_path, $wp_cache_preload_taxonomies;

	// check if stop_preload.txt exists and preload should be stopped.
	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if ( @file_exists( $cache_path . 'stop_preload.txt' ) ) {
		wp_cache_debug( 'wp_cron_preload_cache: preload cancelled. Aborting preload.' );
		wpsc_reset_preload_settings();
		return true;
	}

	/*
	 * The mutex file is used to prevent multiple preload processes from running at the same time.
	 * If the mutex file is found, the preload process will wait 3-8 seconds and then check again.
	 * If the mutex file is still found, the preload process will abort.
	 * If the mutex file is not found, the preload process will create the mutex file and continue.
	 * The mutex file is deleted at the end of the preload process.
	 * The mutex file is deleted if it is more than 10 minutes old.
	 * The mutex file should only be deleted by the preload process that created it.
	 * If the mutex file is deleted by another process, another preload process may start.
	 */
	$mutex = $cache_path . "preload_mutex.tmp";
	if ( @file_exists( $mutex ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		sleep( 3 + wp_rand( 1, 5 ) );
		// check again just in case another preload process is still running.
		if ( @file_exists( $mutex ) && @filemtime( $mutex ) > ( time() - 600 ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			wp_cache_debug( 'wp_cron_preload_cache: preload mutex found and less than 600 seconds old. Aborting preload.', 1 );
			return true;
		} else {
			wp_cache_debug( 'wp_cron_preload_cache: old preload mutex found and deleted. Preload continues.', 1 );
			@unlink( $mutex );
		}
	}
	$fp = @fopen( $mutex, 'w' );
	@fclose( $fp );

	$counter = get_option( 'preload_cache_counter' );
	$c = $counter[ 'c' ];

	if ( $wp_cache_preload_email_volume == 'none' && $wp_cache_preload_email_me == 1 ) {
		$wp_cache_preload_email_me = 0;
		wp_cache_setting( 'wp_cache_preload_email_me', 0 );
	}

	$just_started_preloading = false;

	/*
	 * Preload taxonomies first.
	 *
	 */
	if ( isset( $wp_cache_preload_taxonomies ) && $wp_cache_preload_taxonomies ) {
		wp_cache_debug( 'wp_cron_preload_cache: doing taxonomy preload.', 5 );
		$taxonomies = apply_filters(
			'wp_cache_preload_taxonomies',
			array(
				'post_tag' => 'tag',
				'category' => 'category',
			)
		);

		$preload_more_taxonomies = false;

		foreach ( $taxonomies as $taxonomy => $path ) {
			$taxonomy_filename = $cache_path . 'taxonomy_' . $taxonomy . '.txt';

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( false === @file_exists( $taxonomy_filename ) ) {

				if ( ! $just_started_preloading && $wp_cache_preload_email_me ) {
					// translators: 1: site url
					wp_mail( get_option( 'admin_email' ), sprintf( __( '[%1$s] Cache Preload Started', 'wp-super-cache' ), home_url(), '' ), ' ' );
				}

				$just_started_preloading = true;
				$out                     = '';
				$records                 = get_terms( $taxonomy );
				foreach ( $records as $term ) {
					$out .= get_term_link( $term ) . "\n";
				}
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
				$fp = fopen( $taxonomy_filename, 'w' );
				if ( $fp ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
					fwrite( $fp, $out );
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
					fclose( $fp );
				}
				$details = explode( "\n", $out );
			} else {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$details = explode( "\n", file_get_contents( $taxonomy_filename ) );
			}
			if ( count( $details ) > 0 && $details[0] !== '' ) {
				$rows = array_splice( $details, 0, WPSC_PRELOAD_POST_COUNT );
				if ( $wp_cache_preload_email_me && $wp_cache_preload_email_volume === 'many' ) {
					// translators: 1: Site URL, 2: Taxonomy name, 3: Number of posts done, 4: Number of posts to preload
					wp_mail( get_option( 'admin_email' ), sprintf( __( '[%1$s] Refreshing %2$s taxonomy from %3$d to %4$d', 'wp-super-cache' ), home_url(), $taxonomy, $c, ( $c + WPSC_PRELOAD_POST_COUNT ) ), 'Refreshing: ' . print_r( $rows, 1 ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				}

				foreach ( $rows as $url ) {
					set_time_limit( 60 );
					if ( $url === '' ) {
						continue;
					}

					// get_term_link() spells percent escapes in lowercase; the
					// directory on disk has them uppercased. Without this the
					// delete misses and the wp_remote_get() below is served the
					// stale file it was meant to replace. See #1081.
					$dir = wpsc_supercache_dir_for_url( $url );
					wp_cache_debug( "wp_cron_preload_cache: delete $dir" );
					wpsc_delete_files( $dir );
					prune_super_cache( trailingslashit( $dir ) . 'feed/', true );
					prune_super_cache( trailingslashit( $dir ) . 'page/', true );

					wpsc_update_active_preload( 'taxonomies', $taxonomy, $url );

					wp_remote_get(
						$url,
						array(
							'timeout'  => 60,
							'blocking' => true,
						)
					);
					wp_cache_debug( "wp_cron_preload_cache: fetched $url" );
					sleep( WPSC_PRELOAD_POST_INTERVAL );

					if ( ! wpsc_is_preload_active() ) {
						wp_cache_debug( 'wp_cron_preload_cache: cancelling preload process.' );
						wpsc_reset_preload_settings();

						if ( $wp_cache_preload_email_me ) {
							// translators: Home URL of website
							wp_mail( get_option( 'admin_email' ), sprintf( __( '[%1$s] Cache Preload Stopped', 'wp-super-cache' ), home_url(), '' ), ' ' );
						}
						wpsc_update_idle_preload( time() );
						return true;
					}
				}
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
				$fp = fopen( $taxonomy_filename, 'w' );
				if ( $fp ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
					fwrite( $fp, implode( "\n", $details ) );
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
					fclose( $fp );
				}
			}

			if (
				$preload_more_taxonomies === false &&
				count( $details ) > 0 &&
				$details[0] !== ''
			) {
				$preload_more_taxonomies = true;
			}
		}

		if ( $preload_more_taxonomies === true ) {
			wpsc_schedule_next_preload();
			sleep( WPSC_PRELOAD_LOOP_INTERVAL );
			return true;
		}
	} elseif ( $c === 0 && $wp_cache_preload_email_me ) {
		// translators: Home URL of website
		wp_mail( get_option( 'admin_email' ), sprintf( __( '[%1$s] Cache Preload Started', 'wp-super-cache' ), home_url(), '' ), ' ' );
	}

	/*
	 *
	 * Preload posts now.
	 *
	 * The preload_cache_counter has two values:
	 * c = the number of posts we've preloaded after this loop.
	 * t = the time we started preloading in the current loop.
	 *
	 * $c is set to the value of preload_cache_counter['c'] at the start of the function
	 * before it is incremented by WPSC_PRELOAD_POST_COUNT here.
	 * The time is used to check if preloading has stalled in check_up_on_preloading().
	 */

	update_option(
		'preload_cache_counter',
		array(
			'c' => ( $c + WPSC_PRELOAD_POST_COUNT ),
			't' => time(),
		)
	);

	if ( $wp_cache_preload_posts == 'all' || $c < $wp_cache_preload_posts ) {
		$types = wpsc_get_post_types();
		$posts = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ( post_type IN ( $types ) ) AND post_status = 'publish' ORDER BY ID DESC LIMIT %d," . WPSC_PRELOAD_POST_COUNT, $c ) ); // phpcs:ignore
		wp_cache_debug( 'wp_cron_preload_cache: got ' . WPSC_PRELOAD_POST_COUNT . ' posts from position ' . $c );
	} else {
		wp_cache_debug( "wp_cron_preload_cache: no more posts to get. Limit ($wp_cache_preload_posts) reached.", 5 );
		$posts = false;
	}
	if ( !isset( $wp_cache_preload_email_volume ) )
		$wp_cache_preload_email_volume = 'medium';

	if ( $posts ) {
		if ( get_option( 'show_on_front' ) == 'page' ) {
			$page_on_front = get_option( 'page_on_front' );
			$page_for_posts = get_option( 'page_for_posts' );
		} else {
			$page_on_front = $page_for_posts = 0;
		}
		if ( $wp_cache_preload_email_me && $wp_cache_preload_email_volume === 'many' ) {
			/* translators: 1: home url, 2: start post id, 3: end post id */
			wp_mail( get_option( 'admin_email' ), sprintf( __( '[%1$s] Refreshing posts from %2$d to %3$d', 'wp-super-cache' ), home_url(), $c, ( $c + WPSC_PRELOAD_POST_COUNT ) ), ' ' );
		}
		$msg = '';
		$count = $c + 1;

		foreach( $posts as $post_id ) {
			set_time_limit( 60 );
			if ( $page_on_front != 0 && ( $post_id == $page_on_front || $post_id == $page_for_posts ) )
				continue;
			$url = get_permalink( $post_id );

			if ( ! is_string( $url ) ) {
					wp_cache_debug( "wp_cron_preload_cache: skipped $post_id. Expected a URL, received: " . gettype( $url ) );
					continue;
			}

			if ( wp_cache_is_rejected( $url ) ) {
				wp_cache_debug( "wp_cron_preload_cache: skipped $url per rejected strings setting" );
				continue;
			}
			clear_post_supercache( $post_id );

			wpsc_update_active_preload( 'posts', $count, $url );

			if ( ! wpsc_is_preload_active() ) {
				wp_cache_debug( 'wp_cron_preload_cache: cancelling preload process.' );
				wpsc_reset_preload_settings();

				if ( $wp_cache_preload_email_me ) {
					// translators: Home URL of website
					wp_mail( get_option( 'admin_email' ), sprintf( __( '[%1$s] Cache Preload Stopped', 'wp-super-cache' ), home_url(), '' ), ' ' );
				}

				wpsc_update_idle_preload( time() );
				return true;
			}

			$msg .= "$url\n";
			wp_remote_get( $url, array('timeout' => 60, 'blocking' => true ) );
			wp_cache_debug( "wp_cron_preload_cache: fetched $url", 5 );
			++$count;
			sleep( WPSC_PRELOAD_POST_INTERVAL );
		}

		if ( $wp_cache_preload_email_me && ( $wp_cache_preload_email_volume === 'medium' || $wp_cache_preload_email_volume === 'many' ) ) {
			// translators: 1: home url, 2: number of posts refreshed
			wp_mail( get_option( 'admin_email' ), sprintf( __( '[%1$s] %2$d posts refreshed', 'wp-super-cache' ), home_url(), ( $c + WPSC_PRELOAD_POST_COUNT ) ), __( 'Refreshed the following posts:', 'wp-super-cache' ) . "\n$msg" );
		}

		wpsc_schedule_next_preload();
		wpsc_delete_files( get_supercache_dir() );
		sleep( WPSC_PRELOAD_LOOP_INTERVAL );
	} else {
		$msg = '';
		wpsc_reset_preload_counter();
		if ( (int)$wp_cache_preload_interval && defined( 'DOING_CRON' ) ) {
			if ( $wp_cache_preload_email_me )
				$msg = sprintf( __( 'Scheduling next preload refresh in %d minutes.', 'wp-super-cache' ), (int)$wp_cache_preload_interval );
			wp_cache_debug( "wp_cron_preload_cache: no more posts. scheduling next preload in $wp_cache_preload_interval minutes.", 5 );
			wp_schedule_single_event( time() + ( (int)$wp_cache_preload_interval * 60 ), 'wp_cache_full_preload_hook' );
		}
		global $file_prefix, $cache_max_time;
		if ( $wp_cache_preload_interval > 0 ) {
			$cache_max_time = (int)$wp_cache_preload_interval * 60; // fool the GC into expiring really old files
		} else {
			$cache_max_time = 86400; // fool the GC into expiring really old files
		}
		if ( $wp_cache_preload_email_me )
			wp_mail( get_option( 'admin_email' ), sprintf( __( '[%s] Cache Preload Completed', 'wp-super-cache' ), home_url() ), __( "Cleaning up old supercache files.", 'wp-super-cache' ) . "\n" . $msg );
		if ( $cache_max_time > 0 ) { // GC is NOT disabled
			wp_cache_debug( "wp_cron_preload_cache: clean expired cache files older than $cache_max_time seconds.", 5 );
			wp_cache_phase2_clean_expired( $file_prefix, true ); // force cleanup of old files.
		}

		wpsc_reset_preload_settings();
		wpsc_update_idle_preload( time() );
	}
	@unlink( $mutex );
}
add_action( 'wp_cache_preload_hook', 'wp_cron_preload_cache' );
add_action( 'wp_cache_full_preload_hook', 'wp_cron_preload_cache' );

/*
 * Schedule the next preload event without resetting the preload counter.
 * This happens when the next loop of an active preload is scheduled.
 */
function wpsc_schedule_next_preload() {
	global $cache_path;

	/*
	 * Edge case: If preload is not active, don't schedule the next preload.
	 * This can happen if the preload is cancelled by the user right after a loop finishes.
	 */
	if ( ! wpsc_is_preload_active() ) {
		wpsc_reset_preload_settings();
		wp_cache_debug( 'wpsc_schedule_next_preload: preload is not active. not scheduling next preload.' );
		return;
	}

	if ( defined( 'DOING_CRON' ) ) {
		wp_cache_debug( 'wp_cron_preload_cache: scheduling the next preload in 3 seconds.' );
		wp_schedule_single_event( time() + 3, 'wp_cache_preload_hook' );
	}

	// we always want to delete the mutex file, even if we're not using cron
	$mutex = $cache_path . 'preload_mutex.tmp';
	wp_delete_file( $mutex );
}

function option_preload_cache_counter( $value ) {
	if ( false == is_array( $value ) ) {
		return array(
			'c' => 0,
			't' => time(),
		);
	} else {
		return $value;
	}
}
add_filter( 'option_preload_cache_counter', 'option_preload_cache_counter' );

function check_up_on_preloading() {
	$value = get_option( 'preload_cache_counter' );
	if ( is_array( $value ) && $value['c'] > 0 && ( time() - $value['t'] ) > 3600 && false === wp_next_scheduled( 'wp_cache_preload_hook' ) ) {
		wp_schedule_single_event( time() + 5, 'wp_cache_preload_hook' );
	}
}
add_action( 'init', 'check_up_on_preloading' ); // sometimes preloading stops working. Kickstart it.

/*
 * returns true if preload is active
 */
function wpsc_is_preload_active() {
	global $cache_path;

	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if ( @file_exists( $cache_path . 'stop_preload.txt' ) ) {
		return false;
	}

	if ( file_exists( $cache_path . 'preload_mutex.tmp' ) ) {
		return true;
	}

	// check taxonomy preload loop
	$taxonomies = apply_filters(
		'wp_cache_preload_taxonomies',
		array(
			'post_tag' => 'tag',
			'category' => 'category',
		)
	);

	foreach ( $taxonomies as $taxonomy => $path ) {
		$taxonomy_filename = $cache_path . 'taxonomy_' . $taxonomy . '.txt';
		if ( file_exists( $taxonomy_filename ) ) {
			return true;
		}
	}

	// check post preload loop
	$preload_cache_counter = get_option( 'preload_cache_counter' );
	if (
		is_array( $preload_cache_counter )
		&& isset( $preload_cache_counter['c'] )
		&& $preload_cache_counter['c'] > 0
	) {
		return true;
	}

	return false;
}

/*
 * This function will reset the preload cache counter
 */
function wpsc_reset_preload_counter() {
	update_option(
		'preload_cache_counter',
		array(
			'c' => 0,
			't' => time(),
		)
	);
}

/*
 * This function will reset all preload settings
 */
function wpsc_reset_preload_settings() {
	global $cache_path;

	$mutex = $cache_path . 'preload_mutex.tmp';
	wp_delete_file( $mutex );
	wp_delete_file( $cache_path . 'stop_preload.txt' );
	wpsc_reset_preload_counter();

	$taxonomies = apply_filters(
		'wp_cache_preload_taxonomies',
		array(
			'post_tag' => 'tag',
			'category' => 'category',
		)
	);

	foreach ( $taxonomies as $taxonomy => $path ) {
		$taxonomy_filename = $cache_path . 'taxonomy_' . $taxonomy . '.txt';
		wp_delete_file( $taxonomy_filename );
	}
}

function wpsc_cancel_preload() {
	$next_preload      = wp_next_scheduled( 'wp_cache_preload_hook' );
	$next_full_preload = wp_next_scheduled( 'wp_cache_full_preload_hook' );

	if ( $next_preload || $next_full_preload ) {
		wp_cache_debug( 'wpsc_cancel_preload: reset preload settings' );
		wpsc_reset_preload_settings();
	}

	if ( $next_preload ) {
		wp_cache_debug( 'wpsc_cancel_preload: unscheduling wp_cache_preload_hook' );
		wp_unschedule_event( $next_preload, 'wp_cache_preload_hook' );
	}
	if ( $next_full_preload ) {
		wp_cache_debug( 'wpsc_cancel_preload: unscheduling wp_cache_full_preload_hook' );
		wp_unschedule_event( $next_full_preload, 'wp_cache_full_preload_hook' );
	}
	wp_cache_debug( 'wpsc_cancel_preload: creating stop_preload.txt' );

	/*
	* Reset the preload settings, but also create the stop_preload.txt file to
	* prevent the preload from starting again.
	* By creating the stop_preload.txt file, we can be sure the preload will cancel.
	*/
	wpsc_reset_preload_settings();
	wpsc_create_stop_preload_flag();
	wpsc_update_idle_preload( time() );
}

/*
 * The preload process checks for a file called stop_preload.txt and will stop if found.
 * This function creates that file.
 */
function wpsc_create_stop_preload_flag() {
	global $cache_path;
	// phpcs:ignore -- WordPress.WP.AlternativeFunctions.file_system_read_fopen WordPress.PHP.NoSilencedErrors.Discouraged
	$fp = @fopen( $cache_path . 'stop_preload.txt', 'w' );
	// phpcs:ignore -- WordPress.WP.AlternativeFunctions.file_system_operations_fclose WordPress.PHP.NoSilencedErrors.Discouraged
	@fclose( $fp );
}

function wpsc_enable_preload() {

	wpsc_reset_preload_settings();
	wp_schedule_single_event( time() + 10, 'wp_cache_full_preload_hook' );
}

function wpsc_get_post_types() {

	$preload_type_args = apply_filters( 'wpsc_preload_post_types_args', array(
		'public'             => true,
		'publicly_queryable' => true
	) );

	$post_types = (array) apply_filters( 'wpsc_preload_post_types', get_post_types( $preload_type_args, 'names', 'or' ));

	return "'" . implode( "', '", array_map( 'esc_sql', $post_types ) ) . "'";
}
function wpsc_post_count() {
	global $wpdb;
	static $count;

	if ( isset( $count ) ) {
		return $count;
	}

	$post_type_list = wpsc_get_post_types();
	$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ( $post_type_list ) AND post_status = 'publish'" );

	return $count;
}

/**
 * Get the minimum interval in minutes between preload refreshes.
 * Filter the default value of 10 minutes using the `wpsc_minimum_preload_interval` filter.
 *
 * @return int
 */
function wpsc_get_minimum_preload_interval() {
	return apply_filters( 'wpsc_minimum_preload_interval', 10 );
}

function wpsc_preload_settings() {
	global $wp_cache_preload_interval, $wp_cache_preload_on, $wp_cache_preload_taxonomies, $wp_cache_preload_email_volume, $wp_cache_preload_posts, $valid_nonce;

	if ( isset( $_POST[ 'action' ] ) == false || $_POST[ 'action' ] != 'preload' )
		return;

	if ( ! $valid_nonce ) {
		return;
	}

	if ( isset( $_POST[ 'preload_off' ] ) ) {
		wpsc_cancel_preload();
		return;
	} elseif ( isset( $_POST[ 'preload_now' ] ) ) {
		wpsc_enable_preload();
		wpsc_update_idle_preload();
		?>
		<div class="notice notice-warning">
			<h4><?php esc_html_e( 'Preload has been activated', 'wp-super-cache' ); ?></h4>
		</div>
		<?php
		return;
	}

	$min_refresh_interval = wpsc_get_minimum_preload_interval();

	// Set to true if the preload interval is changed, and a reschedule is required.
	$force_preload_reschedule = false;

	if ( isset( $_POST[ 'wp_cache_preload_interval' ] ) && ( $_POST[ 'wp_cache_preload_interval' ] == 0 || $_POST[ 'wp_cache_preload_interval' ] >= $min_refresh_interval ) ) {
		$_POST[ 'wp_cache_preload_interval' ] = (int)$_POST[ 'wp_cache_preload_interval' ];
		if ( $wp_cache_preload_interval != $_POST[ 'wp_cache_preload_interval' ] ) {
			$force_preload_reschedule = true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$wp_cache_preload_interval = (int) $_POST['wp_cache_preload_interval'];
		wp_cache_setting( 'wp_cache_preload_interval', $wp_cache_preload_interval );
	}

	if ( $_POST[ 'wp_cache_preload_posts' ] == 'all' ) {
		$wp_cache_preload_posts = 'all';
	} else {
		$wp_cache_preload_posts = (int)$_POST[ 'wp_cache_preload_posts' ];
	}
	wp_cache_setting( 'wp_cache_preload_posts', $wp_cache_preload_posts );

	if ( isset( $_POST[ 'wp_cache_preload_email_volume' ] ) && in_array( $_POST[ 'wp_cache_preload_email_volume' ], array( 'none', 'less', 'medium', 'many' ) ) ) {
		$wp_cache_preload_email_volume = $_POST[ 'wp_cache_preload_email_volume' ];
	} else {
		$wp_cache_preload_email_volume = 'none';
	}
	wp_cache_setting( 'wp_cache_preload_email_volume', $wp_cache_preload_email_volume );

	if ( $wp_cache_preload_email_volume == 'none' )
		wp_cache_setting( 'wp_cache_preload_email_me', 0 );
	else
		wp_cache_setting( 'wp_cache_preload_email_me', 1 );

	if ( isset( $_POST[ 'wp_cache_preload_taxonomies' ] ) ) {
		$wp_cache_preload_taxonomies = 1;
	} else {
		$wp_cache_preload_taxonomies = 0;
	}
	wp_cache_setting( 'wp_cache_preload_taxonomies', $wp_cache_preload_taxonomies );

	if ( isset( $_POST[ 'wp_cache_preload_on' ] ) ) {
		$wp_cache_preload_on = 1;
	} else {
		$wp_cache_preload_on = 0;
	}
	wp_cache_setting( 'wp_cache_preload_on', $wp_cache_preload_on );

	// Ensure that preload settings are applied to scheduled cron.
	$next_preload    = wp_next_scheduled( 'wp_cache_full_preload_hook' );
	$should_schedule = ( $wp_cache_preload_on === 1 && $wp_cache_preload_interval > 0 );

	// If forcing a reschedule, or preload is disabled, clear the next scheduled event.
	if ( $next_preload && ( ! $should_schedule || $force_preload_reschedule ) ) {
		wp_cache_debug( 'Clearing old preload event' );
		wpsc_reset_preload_counter();
		wpsc_create_stop_preload_flag();
		wp_unschedule_event( $next_preload, 'wp_cache_full_preload_hook' );

		$next_preload = 0;
	}

	// Ensure a preload is scheduled if it should be.
	if ( ! $next_preload && $should_schedule ) {
		wp_cache_debug( 'Scheduling new preload event' );
		wp_schedule_single_event( time() + ( $wp_cache_preload_interval * 60 ), 'wp_cache_full_preload_hook' );
	}
}

function wpsc_is_preloading() {
	if ( wp_next_scheduled( 'wp_cache_preload_hook' ) || wp_next_scheduled( 'wp_cache_full_preload_hook' ) ) {
		return true;
	} else {
		return false;
	}
}
