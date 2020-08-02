<?php

function wpsc_load_config() {
	global $wpsc_config, $wp_cache_config_file_sample;

	if ( file_exists( WP_CONTENT_DIR . '/wp-cache-config.php' ) ) {
		include WP_CONTENT_DIR . '/wp-cache-config.php';
	} else {
		include $wp_cache_config_file_sample;
	}

	$config_variables = array( 'wpsc_version', 'dismiss_gc_warning', 'wpsc_fix_164', 'wp_cache_debug_username', 'cache_domain_mapping', 'wp_cache_mobile_groups', 'cache_page_secret', 'wpsc_cookies', 'wp_cache_home_path', 'wp_cache_slash_check', 'cache_time_interval', 'cache_compression', 'cache_enabled', 'super_cache_enabled', 'cache_max_time', 'use_flock', 'cache_path', 'file_prefix', 'ossdlcdn', 'cache_acceptable_files', 'cache_rejected_uri', 'cache_rejected_user_agent', 'cache_rebuild_files', 'wp_cache_mutex_disabled', 'sem_id', 'wp_cache_mobile', 'wp_cache_mobile_whitelist', 'wp_cache_mobile_browsers', 'wp_cache_plugins_dir', 'wp_cache_shutdown_gc', 'wp_super_cache_late_init', 'wp_super_cache_advanced_debug', 'wp_super_cache_front_page_text', 'wp_super_cache_front_page_clear', 'wp_super_cache_front_page_check', 'wp_super_cache_front_page_notification', 'wp_cache_object_cache', 'wp_cache_anon_only', 'wp_supercache_cache_list', 'wp_cache_debug_to_file', 'wp_super_cache_debug', 'wp_cache_debug_level', 'wp_cache_debug_ip', 'wp_cache_debug_log', 'wp_cache_debug_email', 'wp_cache_pages', 'wp_cache_hide_donation', 'wp_cache_not_logged_in', 'wp_cache_clear_on_post_edit', 'wp_cache_hello_world', 'wp_cache_mobile_enabled', 'wp_cache_cron_check', 'wp_cache_mfunc_enabled', 'wp_cache_make_known_anon', 'wp_cache_refresh_single_only', 'wp_cache_mod_rewrite', 'wp_supercache_304', 'wp_cache_front_page_checks', 'wp_cache_disable_utf8', 'wp_cache_no_cache_for_get', 'cache_scheduled_time', 'wp_cache_preload_interval', 'cache_schedule_type', 'wp_cache_preload_posts', 'wp_cache_preload_on', 'wp_cache_preload_taxonomies', 'wp_cache_preload_email_me', 'wp_cache_preload_email_volume', 'wp_cache_mobile_prefixes', 'cached_direct_pages', 'wpsc_served_header', 'cache_gc_email_me', 'wpsc_save_headers', 'cache_schedule_interval', 'wp_super_cache_comments' );
	foreach( $config_variables as $config ) {
		$wpsc_config[ $config ] = $$config;
	}
}
