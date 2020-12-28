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

if ( defined( 'DISABLE_SUPERCACHE' ) ) {
	wp_cache_debug( 'DISABLE_SUPERCACHE set, super_cache disabled.' );
	$wp_super_cache_config['super_cache_enabled'] = 0;
}

if ( ! defined( 'WPCACHEHOME' ) ) {
	define( 'WPCACHEHOME', dirname( __FILE__ ) . '/' );
}

global $wpsc_http_host, $cache_enabled, $cache_path, $blogcacheid, $blog_cache_dir;

if ( ! empty( $_SERVER['HTTP_HOST'] ) ) {
	$wpsc_http_host = function_exists( 'mb_strtolower' ) ? mb_strtolower( $_SERVER['HTTP_HOST'] ) : strtolower( $_SERVER['HTTP_HOST'] ); // phpcs:ignore
	$wpsc_http_host = htmlentities( $wpsc_http_host );
} elseif ( PHP_SAPI === 'cli' && function_exists( 'get_option' ) ) {
	$wpsc_http_host = (string) wp_parse_url( get_option( 'home' ), PHP_URL_HOST );
} else {
	$cache_enabled  = false;
	$wpsc_http_host = '';
}

// We want to be able to identify each blog in a WordPress MU install.
$blogcacheid    = '';
$blog_cache_dir = $cache_path;

if ( is_multisite() ) {
	global $current_blog;

	if ( is_object( $current_blog ) && function_exists( 'is_subdomain_install' ) ) {
		$blogcacheid = is_subdomain_install() ? $current_blog->domain : trim( $current_blog->path, '/' );
	} elseif ( ( defined( 'SUBDOMAIN_INSTALL' ) && SUBDOMAIN_INSTALL ) || ( defined( 'VHOST' ) && VHOST === 'yes' ) ) {
		$blogcacheid = $wpsc_http_host;
	} else {
		$request_uri = str_replace( '..', '', preg_replace( '/[ <>\'\"\r\n\t\(\)]/', '', $_SERVER['REQUEST_URI'] ) ); // phpcs:ignore
		$request_uri = str_replace( '//', '/', $request_uri );

		$wpsc_path_segs  = array_filter( explode( '/', trim( $request_uri, '/' ) ) );
		$wpsc_base_count = defined( 'PATH_CURRENT_SITE' ) ? count( array_filter( explode( '/', trim( PATH_CURRENT_SITE, '/' ) ) ) ) : 0;
		if ( '/' !== substr( $request_uri, -1 ) ) {
			$wpsc_path_segs = array_slice( $wpsc_path_segs, 0, -1 );
		}

		if ( count( $wpsc_path_segs ) > $wpsc_base_count &&
			( ! defined( 'PATH_CURRENT_SITE' ) || 0 === strpos( $request_uri, PATH_CURRENT_SITE ) )
		) {
			$blogcacheid = $wpsc_path_segs[ $wpsc_base_count ];
		}
	}

	// If blogcacheid is empty then set it to main blog.
	if ( empty( $blogcacheid ) ) {
		$blogcacheid = 'blog';
	}
	$blog_cache_dir = str_replace( '//', '/', $cache_path . 'blogs/' . $blogcacheid . '/' );
}

if ( '' !== $blogcacheid ) {
	$blog_cache_dir = str_replace( '//', '/', $cache_path . 'blogs/' . $blogcacheid . '/' );
} else {
	$blog_cache_dir = $cache_path;
}


if ( ! isset( $wp_cache_plugins_dir ) ) {
	$wp_cache_plugins_dir = WPCACHEHOME . 'plugins';
}

if (
	// phpcs:ignore
	isset( $_GET['donotcachepage'] ) && isset( $wp_super_cache_config['cache_page_secret'] ) && $_GET['donotcachepage'] === $wp_super_cache_config['cache_page_secret']
) {
	$wp_super_cache_config['cache_enabled'] = false;
	define( 'DONOTCACHEPAGE', 1 );
}

$wp_super_cache_plugins = glob( $wp_super_cache_config['wp_cache_plugins_dir'] . '/*.php' );
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

// Cache this in case any plugin modifies it.
// Used to be: wp_cache_request_uri.
$wp_super_cache_request_uri = $_SERVER['REQUEST_URI']; // phpcs:ignore

$wp_super_cache_page = Wp_Super_Cache_Page::instance();

do_cacheaction( 'cache_init' );

if ( $wp_super_cache_page->is_cached() ) {
	$wp_super_cache_page->serve_page();
} elseif ( $wp_super_cache_page->ok_to_cache() ) {
	$wp_super_cache_page->cache_page();
}
