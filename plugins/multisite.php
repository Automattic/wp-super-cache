<?php

if ( is_multisite() ) {
	add_cacheaction( 'add_cacheaction', 'wp_super_cache_multisite_init' );
}

function wp_super_cache_multisite_init() {
	add_filter( 'wpmu_blogs_columns', 'wp_super_cache_blogs_col' );
	add_action( 'manage_sites_custom_column', 'wp_super_cache_blogs_field', 10, 2 );
	add_action( 'init', 'wp_super_cache_override_on_flag', 9 );
}

function wp_super_cache_blogs_col( $col ) {
	$col['wp_super_cache'] = __( 'Cached', 'wp-super-cache' );
	return $col;
}

function wp_super_cache_blogs_field( $name, $blog_id ) {
	if ( 'wp_super_cache' !== $name ) {
		return false;
	}

	$blog_id = (int) $blog_id;

	$get_id     = filter_input( INPUT_GET, 'id', FILTER_VALIDATE_INT );
	$get_action = filter_input( INPUT_GET, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
	$get_nonce  = filter_input( INPUT_GET, '_wpnonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

	if ( $get_id === $blog_id
		&& $get_nonce
		&& wp_verify_nonce( $get_nonce, 'wp-cache' . $blog_id )
	) {
		if ( 'disable_cache' === $get_action ) {
			add_blog_option( $blog_id, 'wp_super_cache_disabled', 1 );
		} elseif ( 'enable_cache' === $get_action ) {
			delete_blog_option( $blog_id, 'wp_super_cache_disabled' );
		}
	}

	$cache_disabled = 1 === (int) get_blog_option( $blog_id, 'wp_super_cache_disabled' );
	$action_text    = $cache_disabled ? __( 'Enable', 'wp-super-cache' ) : __( 'Disable', 'wp-super-cache' );
	$action_args    = array(
		'action'   => $cache_disabled ? 'enable_cache' : 'disable_cache',
		'id'       => $blog_id,
		'_wpnonce' => wp_create_nonce( 'wp-cache' . $blog_id ),
	);
	printf( '<a href="%s">%s</a>', esc_url( add_query_arg( $action_args ) ), esc_html( $action_text ) );
}

function wp_super_cache_multisite_notice() {
	if ( 'wpsupercache' === filter_input( INPUT_GET, 'page' ) ) {
		echo '<div class="error"><p><strong>' . __( 'Caching has been disabled on this blog on the Network Admin Sites page.', 'wp-super-cache' ) . '</strong></p></div>';
	}
}

function wp_super_cache_override_on_flag() {
	global $cache_enabled, $super_cache_enabled;
	if ( true !== $cache_enabled ) {
		return false;
	}

	if ( 1 === (int) get_option( 'wp_super_cache_disabled' ) ) {
		$cache_enabled       = false;
		$super_cache_enabled = false;
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', 1 );
		}
		if ( ! defined( 'SUBMITDISABLED' ) ) {
			define( 'SUBMITDISABLED', 'disabled style="color: #aaa" ' );
		}
		if ( is_admin() ) {
			add_action( 'admin_notices', 'wp_super_cache_multisite_notice' );
		}
	}
}
