<?php
/**
 *
 * Check if the plugin should cache the current page and then create the
 * output buffer for the cache file.
 *
 * @link       https://automattic.com/
 * @since      2.0.0
 *
 * @package    Wp_Super_Cache
 * @subpackage Wp_Super_Cache/includes
 */

$wp_super_cache_page   = Wp_Super_Cache_Page::instance();
$wp_super_cache_config = Wp_Super_Cache_Config::instance();

global $cache_path;

if ( ! isset( $wp_cache_plugins_dir ) ) {
	$wp_cache_plugins_dir = WPCACHEHOME . 'plugins';
}

if (
	// phpcs:ignore
	isset( $_GET['donotcachepage'] ) && isset( $wp_super_cache_config->config['cache_page_secret'] ) && $_GET['donotcachepage'] === $wp_super_cache_config->config['cache_page_secret']
) {
	$wp_super_cache_config->config['cache_enabled'] = false;
	define( 'DONOTCACHEPAGE', 1 );
}

$wp_super_cache_plugins = glob( $wp_super_cache_config->config['wp_cache_plugins_dir'] . '/*.php' );
if ( is_array( $wp_super_cache_plugins ) ) {
	foreach ( $wp_super_cache_plugins as $wp_super_cache_plugin ) {
		if ( is_file( $wp_super_cache_plugin ) ) {
			require_once $wp_super_cache_plugin;
		}
	}
}

if ( isset( $wpsc_plugins ) && is_array( $wpsc_plugins ) ) {
	foreach ( $wpsc_plugins as $wp_super_cache_plugin_file ) {
		if ( file_exists( ABSPATH . $wp_super_cache_plugin_file ) ) {
			include_once ABSPATH . $wp_super_cache_plugin_file;
		}
	}
}

if (
	file_exists( WPCACHEHOME . '../wp-super-cache-plugins/' ) &&
	is_dir( WPCACHEHOME . '../wp-super-cache-plugins/' )
) {
	$wp_super_cache_plugins = glob( WPCACHEHOME . '../wp-super-cache-plugins/*.php' );
	if ( is_array( $wp_super_cache_plugins ) ) {
		foreach ( $wp_super_cache_plugins as $wp_super_cache_plugin ) {
			if ( is_file( $wp_super_cache_plugin ) ) {
				require_once $wp_super_cache_plugin;
			}
		}
	}
}

$wp_super_cache_start_time = microtime();

if ( $wp_super_cache_page->is_cached() ) {
	$wp_super_cache_page->serve_page();
} elseif ( $wp_super_cache_page->is_cacheable() ) {
	$wp_super_cache_page->cache_page();
}
