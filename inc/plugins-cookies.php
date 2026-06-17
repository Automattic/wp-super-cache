<?php
/**
 * WPSC plugin-list and extra-cookie management, plus feed cache invalidation.
 *
 * Relocated from wp-cache.php (issue #1061) as a pure move: identical function
 * signatures and hook registrations, no behaviour change.
 *
 * @package WP_Super_Cache
 */

// Delete feeds when the site is updated so that feed files are always fresh
function wpsc_feed_update( $type, $permalink ) {
	$wpsc_feed_list = get_option( 'wpsc_feed_list' );

	update_option( 'wpsc_feed_list', array() );
	if ( is_array( $wpsc_feed_list ) && ! empty( $wpsc_feed_list ) ) {
		foreach( $wpsc_feed_list as $file ) {
			wp_cache_debug( "wpsc_feed_update: deleting feed: $file" );
			prune_super_cache( $file, true );
			prune_super_cache( dirname( $file ) . '/meta-' . basename( $file ), true );
		}
	}
}
add_action( 'gc_cache', 'wpsc_feed_update', 10, 2 );

function wpsc_get_plugin_list() {
	$list = do_cacheaction( 'wpsc_filter_list' );
	foreach( $list as $t => $details ) {
		$key = "cache_" . $details[ 'key' ];
		if ( isset( $GLOBALS[ $key ] ) && $GLOBALS[ $key ] == 1 ) {
			$list[ $t ][ 'enabled' ] = true;
		} else {
			$list[ $t ][ 'enabled' ] = false;
		}

		$list[ $t ]['desc']  = strip_tags( $list[ $t ]['desc'] ?? '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
		$list[ $t ]['title'] = strip_tags( $list[ $t ]['title'] ?? '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
	}
	return $list;
}

function wpsc_update_plugin_list( $update ) {
	$list = do_cacheaction( 'wpsc_filter_list' );
	foreach( $update as $key => $enabled ) {
		$plugin_toggle = "cache_{$key}";
		if ( isset( $GLOBALS[ $plugin_toggle ] ) || isset( $list[ $key ] ) ) {
			wp_cache_setting( $plugin_toggle, (int)$enabled );
		}
	}
}

function wpsc_add_plugin( $file ) {
	global $wpsc_plugins;
	if ( substr( $file, 0, strlen( ABSPATH ) ) == ABSPATH ) {
		$file = substr( $file, strlen( ABSPATH ) ); // remove ABSPATH
	}
	if (
		! isset( $wpsc_plugins ) ||
		! is_array( $wpsc_plugins ) ||
		! in_array( $file, $wpsc_plugins )
	) {
		$wpsc_plugins[] = $file;
		wp_cache_setting( 'wpsc_plugins', $wpsc_plugins );
	}
	return $file;
}
add_action( 'wpsc_add_plugin', 'wpsc_add_plugin' );

function wpsc_delete_plugin( $file ) {
	global $wpsc_plugins;
	if ( substr( $file, 0, strlen( ABSPATH ) ) == ABSPATH ) {
		$file = substr( $file, strlen( ABSPATH ) ); // remove ABSPATH
	}
	if (
		isset( $wpsc_plugins ) &&
		is_array( $wpsc_plugins ) &&
		in_array( $file, $wpsc_plugins )
	) {
		unset( $wpsc_plugins[ array_search( $file, $wpsc_plugins ) ] );
		wp_cache_setting( 'wpsc_plugins', $wpsc_plugins );
	}
	return $file;
}
add_action( 'wpsc_delete_plugin', 'wpsc_delete_plugin' );

function wpsc_get_plugins() {
	global $wpsc_plugins;
	return $wpsc_plugins;
}

function wpsc_add_cookie( $name ) {
	global $wpsc_cookies;
	if (
		! isset( $wpsc_cookies ) ||
		! is_array( $wpsc_cookies ) ||
		! in_array( $name, $wpsc_cookies )
	) {
		$wpsc_cookies[] = $name;
		wp_cache_setting( 'wpsc_cookies', $wpsc_cookies );
	}
	return $name;
}
add_action( 'wpsc_add_cookie', 'wpsc_add_cookie' );

function wpsc_delete_cookie( $name ) {
	global $wpsc_cookies;
	if (
		isset( $wpsc_cookies ) &&
		is_array( $wpsc_cookies ) &&
		in_array( $name, $wpsc_cookies )
	) {
		unset( $wpsc_cookies[ array_search( $name, $wpsc_cookies ) ] );
		wp_cache_setting( 'wpsc_cookies', $wpsc_cookies );
	}
	return $name;
}
add_action( 'wpsc_delete_cookie', 'wpsc_delete_cookie' );

function wpsc_get_cookies() {
	global $wpsc_cookies;
	return $wpsc_cookies;
}

function wpsc_get_extra_cookies() {
	global $wpsc_cookies;
	if (
		is_array( $wpsc_cookies ) &&
		! empty( $wpsc_cookies )
	) {
		return '|' . implode( '|', $wpsc_cookies );
	} else {
		return '';
	}
}
