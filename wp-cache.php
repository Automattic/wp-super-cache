<?php
/*
 * Plugin Name: WP Super Cache
 * Plugin URI: https://wordpress.org/plugins/wp-super-cache/
 * Description: Very fast caching plugin for WordPress.
 * Version: 3.1.3
 * Author: Automattic
 * Author URI: https://automattic.com/
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wp-super-cache
 */

/*
Copyright Automattic and many other contributors.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, see <https://www.gnu.org/licenses/>.
*/

define( 'WPSC_VERSION_ID', '1.12.1' );

require_once( __DIR__. '/inc/delete-cache-button.php');
require_once( __DIR__. '/inc/preload-notification.php');
require_once __DIR__ . '/inc/boost.php';
require_once __DIR__ . '/inc/plugins-cookies.php';
require_once __DIR__ . '/inc/cache-files.php';
require_once __DIR__ . '/inc/htaccess.php';
require_once __DIR__ . '/inc/settings-forms.php';
require_once __DIR__ . '/inc/preload.php';
require_once __DIR__ . '/inc/lifecycle.php';
require_once __DIR__ . '/inc/admin-notices.php';
require_once __DIR__ . '/inc/admin-ui.php';

// Plugin activation/deactivation/uninstall hooks must be registered from the
// main plugin file so __FILE__ resolves to wp-cache.php; the handlers live in
// inc/lifecycle.php (relocated in #1061).
register_activation_hook( __FILE__, 'wpsupercache_activate' );
register_deactivation_hook( __FILE__, 'wpsupercache_deactivate' );
if ( is_admin() ) {
	register_uninstall_hook( __FILE__, 'wpsupercache_uninstall' );
}

if ( ! function_exists( 'wp_cache_phase2' ) ) {
	require_once( __DIR__. '/wp-cache-phase2.php');
}

if ( ! defined( 'PHP_VERSION_ID' ) ) {
	// For versions of PHP below 5.2.7, this constant doesn't exist.
	$wpsc_php_version = explode( '.', PHP_VERSION );
	define( 'PHP_VERSION_ID', intval( $wpsc_php_version[0] * 10000 + $wpsc_php_version[1] * 100 + $wpsc_php_version[2] ) );
	unset( $wpsc_php_version );
}

/**
 * Defines how many posts to preload per loop.
 */
if ( ! defined( 'WPSC_PRELOAD_POST_COUNT' ) ) {
	define( 'WPSC_PRELOAD_POST_COUNT', 10 );
}

/**
 * Defines the interval in seconds between preloading pages.
 */
if ( ! defined( 'WPSC_PRELOAD_POST_INTERVAL' ) ) {
	define( 'WPSC_PRELOAD_POST_INTERVAL', 1 );
}

/**
 * Defines the interval in seconds between preloading loops.
 */
if ( ! defined( 'WPSC_PRELOAD_LOOP_INTERVAL' ) ) {
	define( 'WPSC_PRELOAD_LOOP_INTERVAL', 0 );
}

function wpsc_init() {
	global $wp_cache_config_file, $wp_cache_config_file_sample, $wpsc_advanced_cache_dist_filename, $wp_cache_check_wp_config, $wpsc_advanced_cache_filename, $wpsc_promo_links;

	if ( ! defined( 'WPCACHECONFIGPATH' ) ) {
		define( 'WPCACHECONFIGPATH', WP_CONTENT_DIR );
	}

	$wp_cache_config_file = WPCACHECONFIGPATH . '/wp-cache-config.php';

	// Centralise the promotional links to other products
	$wpsc_promo_links = array(
		'boost'       => 'https://jetpack.com/boost/?utm_source=wporg&utm_medium=plugin&utm_campaign=wp-super-cache&utm_id=wp-super-cache',
		'photon'      => 'https://jetpack.com/features/design/content-delivery-network/?utm_source=wporg&utm_medium=plugin&utm_campaign=wp-super-cache&utm_id=wp-super-cache',
		'videopress'  => 'https://jetpack.com/videopress/?utm_source=wporg&utm_medium=plugin&utm_campaign=wp-super-cache&utm_id=wp-super-cache',
		'crowdsignal' => 'https://crowdsignal.com/?utm_source=wporg&utm_medium=plugin&utm_campaign=wp-super-cache&utm_id=wp-super-cache',
		'jetpack'     => 'https://jetpack.com/?utm_source=wporg&utm_medium=plugin&utm_campaign=wp-super-cache&utm_id=wp-super-cache',
	);

	if ( !defined( 'WPCACHEHOME' ) ) {
		define( 'WPCACHEHOME', __DIR__ . '/' );
		$wp_cache_config_file_sample = WPCACHEHOME . 'wp-cache-config-sample.php';
		$wpsc_advanced_cache_dist_filename = WPCACHEHOME . 'advanced-cache.php';
	} elseif ( realpath( WPCACHEHOME ) != realpath( __DIR__ ) ) {
		$wp_cache_config_file_sample = __DIR__. '/wp-cache-config-sample.php';
		$wpsc_advanced_cache_dist_filename = __DIR__. '/advanced-cache.php';
		if ( ! defined( 'ADVANCEDCACHEPROBLEM' ) ) {
			define( 'ADVANCEDCACHEPROBLEM', 1 ); // force an update of WPCACHEHOME
		}
	} else {
		$wp_cache_config_file_sample = WPCACHEHOME . 'wp-cache-config-sample.php';
		$wpsc_advanced_cache_dist_filename = WPCACHEHOME . 'advanced-cache.php';
	}
	$wpsc_advanced_cache_filename = WP_CONTENT_DIR . '/advanced-cache.php';

	if ( !defined( 'WP_CACHE' ) || ( defined( 'WP_CACHE' ) && constant( 'WP_CACHE' ) == false ) ) {
		$wp_cache_check_wp_config = true;
	}
}

wpsc_init();

/**
 * WP-CLI requires explicit declaration of global variables.
 * It's minimal list of global variables.
 */
global $super_cache_enabled, $cache_enabled, $wp_cache_mod_rewrite, $wp_cache_home_path, $cache_path, $file_prefix;
global $wp_cache_mutex_disabled, $mutex_filename, $sem_id, $wp_super_cache_late_init;
global $cache_compression, $cache_max_time, $wp_cache_shutdown_gc, $cache_rebuild_files;
global $wp_super_cache_debug, $wp_super_cache_advanced_debug, $wp_cache_debug_level, $wp_cache_debug_to_file;
global $wp_cache_debug_log, $wp_cache_debug_ip, $wp_cache_debug_username, $wp_cache_debug_email;
global $cache_time_interval, $cache_scheduled_time, $cache_schedule_interval, $cache_schedule_type, $cache_gc_email_me;
global $wp_cache_preload_on, $wp_cache_preload_interval, $wp_cache_preload_posts, $wp_cache_preload_taxonomies;

// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- these are used by various functions but the linter complains.
global $wp_cache_preload_email_me, $wp_cache_preload_email_volume;
global $wp_cache_mobile, $wp_cache_mobile_enabled, $wp_cache_mobile_browsers, $wp_cache_mobile_prefixes;
global $wp_cache_config_file, $wp_cache_config_file_sample;

// phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
global $wpsc_advanced_cache_filename, $wpsc_advanced_cache_dist_filename;
global $wp_cache_phase1_loaded, $blog_cache_dir, $wp_supercache_304, $wp_cache_mfunc_enabled;
global $wp_cache_front_page_checks, $wpsc_save_headers, $is_nginx, $wpsc_promo_links;
global $wp_cache_disable_utf8, $wp_cache_not_logged_in, $wp_cache_make_known_anon;
global $wpsc_tracking_parameters, $wpsc_rejected_cookies, $cache_rejected_uri;
global $cache_acceptable_files, $wp_super_cache_comments;
global $wp_super_cache_front_page_check, $wp_super_cache_front_page_clear;
global $wp_super_cache_front_page_text, $wp_super_cache_front_page_notification;
global $wpsc_plugins, $wpsc_cookies, $wpsc_version, $wp_cache_clear_on_post_edit;
// phpcs:enable VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
// Check is cache config already loaded.
if ( ! isset( $cache_enabled, $super_cache_enabled, $wp_cache_mod_rewrite, $cache_path ) &&
	empty( $wp_cache_phase1_loaded ) &&
	// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
	! @include( $wp_cache_config_file )
) {
	@include $wp_cache_config_file_sample; // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
}

include(WPCACHEHOME . 'wp-cache-base.php');
if ( class_exists( 'WP_REST_Controller' ) ) {
	include( __DIR__. '/rest/load.php' );
}

function wp_super_cache_init_action() {

	load_plugin_textdomain( 'wp-super-cache', false, basename( __DIR__ ) . '/languages' );

	wpsc_register_post_hooks();
}
add_action( 'init', 'wp_super_cache_init_action' );

/**
 * Disable caching for pages rendered via wp_die().
 *
 * The function wp_die() is used to render error and interstitial pages (e.g.
 * "Error establishing a database connection"); caching them causes the error to
 * persist for subsequent visitors even after the underlying issue is resolved.
 *
 * @param callable $handler The registered wp_die handler, returned unchanged.
 * @return callable
 */
function wpsc_wp_die_disable_cache( $handler ) {
	/**
	 * Filters whether to disable caching when wp_die() is invoked.
	 *
	 * @param bool $disable Whether to set DONOTCACHEPAGE. Default true.
	 */
	if ( apply_filters( 'wpsc_disable_cache_on_wp_die', true ) && ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	return $handler;
}
add_filter( 'wp_die_handler', 'wpsc_wp_die_disable_cache' );

function wp_cache_set_home() {
	global $wp_cache_is_home;
	$wp_cache_is_home = ( is_front_page() || is_home() );
	if ( $wp_cache_is_home && is_paged() )
		$wp_cache_is_home = false;
}
add_action( 'template_redirect', 'wp_cache_set_home' );

include_once( WPCACHEHOME . 'ossdl-cdn.php' );

