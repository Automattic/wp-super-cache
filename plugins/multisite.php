<?php

if ( is_multisite() ) {
	if ( function_exists( 'add_action' ) ) {
		add_action( 'ms_loaded', 'wp_super_cache_multisite_loaded' );
	} else {
		add_cacheaction( 'add_cacheaction', 'wp_super_cache_multisite_init' );
	}
}

function wp_super_cache_multisite_loaded() {
	wp_super_cache_override_on_flag();
	wp_super_cache_multisite_admin_init();
}

function wp_super_cache_multisite_init() {
	add_action( 'init', 'wp_super_cache_override_on_flag', 9 );
	wp_super_cache_multisite_admin_init();
}

function wp_super_cache_multisite_admin_init() {
	if ( is_admin() ) {
		add_filter( 'wpmu_blogs_columns', 'wp_super_cache_blogs_col' );
		add_action( 'manage_sites_custom_column', 'wp_super_cache_blogs_field', 10, 2 );
	}
}

function wp_super_cache_blogs_col( $col ) {
	$col['wp_super_cache'] = __( 'Cached', 'wp-super-cache' );
	return $col;
}

function wp_super_cache_blogs_field( $name, $blog_id ) {
	if ( 'wp_super_cache' !== $name ) {
		return;
	}

	$blog_id = (int) $blog_id;

	if ( isset( $_GET['id'], $_GET['action'], $_GET['_wpnonce'] )
		&& filter_input( INPUT_GET, 'id', FILTER_VALIDATE_INT ) === $blog_id
		&& wp_verify_nonce( $_GET['_wpnonce'], 'wp-cache' . $blog_id )
	) {
		if ( filter_input( INPUT_GET, 'action' ) === 'disable_cache' ) {
			add_blog_option( $blog_id, 'wp_super_cache_disabled', 1 );
		} elseif ( filter_input( INPUT_GET, 'action' ) === 'enable_cache' ) {
			delete_blog_option( $blog_id, 'wp_super_cache_disabled' );
		}
	}

	$cache_option = (int) get_blog_option( $blog_id, 'wp_super_cache_disabled' ) === 1;

	printf( '<a href="%s">%s</a>',
		esc_url_raw( wp_nonce_url(
			add_query_arg( array(
				'action' => $cache_option ? 'enable_cache' : 'disable_cache',
				'id'     => $blog_id,
			) ),
			'wp-cache' . $blog_id
		) ),
		esc_html( $cache_option ? __( 'Enable', 'wp-super-cache' ) : __( 'Disable', 'wp-super-cache' ) )
	);
}

function wp_super_cache_multisite_notice() {
	if ( filter_input( INPUT_GET, 'page' ) === 'wpsupercache' ) {
		echo '<div class="error"><p><strong>' . esc_html__( 'Caching has been disabled on this blog on the Network Admin Sites page.', 'wp-super-cache' ) . '</strong></p></div>';
	}
}

function wp_super_cache_override_on_flag() {
	global $cache_enabled, $super_cache_enabled, $cache_path, $blogcacheid, $blog_cache_dir, $current_blog;

	if ( is_object( $current_blog ) ) {
		$blogcacheid    = trim( is_subdomain_install() ? $current_blog->domain : $current_blog->path, '/' );
		$blog_cache_dir = $cache_path . 'blogs/' . $blogcacheid . '/';
	}

	if ( true !== $cache_enabled || (int) get_option( 'wp_super_cache_disabled' ) !== 1 ) {
		return false;
	}

	$cache_enabled       = false;
	$super_cache_enabled = false;
	defined( 'DONOTCACHEPAGE' ) || define( 'DONOTCACHEPAGE', 1 );

	if ( is_admin() ) {
		define( 'SUBMITDISABLED', 'disabled style="color: #aaa" ' );
		add_action( 'admin_notices', 'wp_super_cache_multisite_notice' );
	}
}
