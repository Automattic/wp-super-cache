<?php
/**
 * Admin notices and plugin-row UI: index.html security notice, config-file
 * notices, the comment-form lockdown message, plugin-row notice / action links /
 * favorite action, the caching-broken admin notice, and the front-page site
 * check.
 *
 * Relocated from wp-cache.php (issue #1061) as a pure move: identical function
 * signatures and hook registrations, no behaviour change.
 *
 * @package WP_Super_Cache
 */

function comment_form_lockdown_message() {
	?><p><?php _e( "Comment moderation is enabled. Your comment may take some time to appear.", 'wp-super-cache' ); ?></p><?php
}
if( defined( 'WPLOCKDOWN' ) && constant( 'WPLOCKDOWN' ) )
	add_action( 'comment_form', 'comment_form_lockdown_message' );

function wp_cache_index_notice() {
	global $cache_path;

	if ( false == wpsupercache_site_admin() )
		return false;
	if ( false == get_site_option( 'wp_super_cache_index_detected' ) )
		return false;

	if ( strlen( $cache_path ) < strlen( ABSPATH )
		|| ABSPATH != substr( $cache_path, 0, strlen( ABSPATH ) ) )
		return false; // cache stored outside web root

	if ( get_site_option( 'wp_super_cache_index_detected' ) == 2 ) {
		update_site_option( 'wp_super_cache_index_detected', 3 );
		echo "<div class='error' style='padding: 10px 10px 50px 10px'>";
		echo "<h2>" . __( 'WP Super Cache Warning!', 'wp-super-cache' ) . '</h2>';
		echo '<p>' . __( 'All users of this site have been logged out to refresh their login cookies.', 'wp-super-cache' ) . '</p>';
		echo '</div>';
		return false;
	} elseif ( get_site_option( 'wp_super_cache_index_detected' ) != 3 ) {
		echo "<div id='wpsc-index-warning' class='error notice' style='padding: 10px 10px 50px 10px'>";
		echo "<h2>" . __( 'WP Super Cache Warning!', 'wp-super-cache' ) . '</h2>';
		echo '<p>' . __( 'Your server is configured to show files and directories, which may expose sensitive data such as login cookies to attackers in the cache directories. That has been fixed by adding a file named index.html to each directory. If you use simple caching, consider moving the location of the cache directory on the Advanced Settings page.', 'wp-super-cache' ) . '</p>';
		echo "<p><strong>";
		_e( 'If you just installed WP Super Cache for the first time, you can dismiss this message. Otherwise, you should probably refresh the login cookies of all logged in WordPress users here by clicking the logout link below.', 'wp-super-cache' );
		echo "</strong></p>";
		echo '<p>' . esc_html__( 'The logout link will log out all WordPress users on this site except you. Your authentication cookie will be updated, but you will not be logged out.', 'wp-super-cache' ) . '</p>';
		echo '<a id="wpsc-dismiss" href="#">' . esc_html__( 'Dismiss', 'wp-super-cache' ) . '</a>';
		echo '	| <a href="' . esc_url( wp_nonce_url( admin_url( '?action=wpsclogout' ), 'wpsc_logout' ) ) . '">' . esc_html__( 'Logout', 'wp-super-cache' ) . '</a>';
		echo '</div>';
?>
		<script  type='text/javascript'>
		<!--
			jQuery(document).ready(function(){
				jQuery('#wpsc-dismiss').on("click",function() {
						jQuery.ajax({
							type: "post",url: "admin-ajax.php",data: { action: 'wpsc-index-dismiss', _ajax_nonce: '<?php echo wp_create_nonce( 'wpsc-index-dismiss' ); ?>' },
							beforeSend: function() {jQuery("#wpsc-index-warning").fadeOut('slow');},
						});
				})
			})
		//-->
		</script>
<?php
	}
}
add_action( 'admin_notices', 'wp_cache_index_notice' );

function wpsc_config_file_notices() {
	global $wp_cache_config_file;
	if ( ! isset( $_GET['page'] ) || $_GET['page'] != 'wpsupercache' ) {
		return false;
	}
	$notice = get_transient( 'wpsc_config_error' );
	if ( ! $notice ) {
		return false;
	}
	switch( $notice ) {
		case 'error_move_tmp_config_file':
			$msg = sprintf( __( 'Error: Could not rename temporary file to configuration file. Please make sure %s is writeable by the webserver.' ), $wp_cache_config_file );
			break;
		case 'config_file_ro':
			$msg = sprintf( __( 'Error: Configuration file is read only. Please make sure %s is writeable by the webserver.' ), $wp_cache_config_file );
			break;
		case 'tmp_file_ro':
			$msg = sprintf( __( 'Error: The directory containing the configuration file %s is read only. Please make sure it is writeable by the webserver.' ), $wp_cache_config_file );
			break;
		case 'config_file_not_loaded':
			$msg = sprintf( __( 'Error: Configuration file %s could not be loaded. Please reload the page.' ), $wp_cache_config_file );
			break;
		case 'config_file_missing':
			$msg = sprintf( __( 'Error: Configuration file %s is missing. Please reload the page.' ), $wp_cache_config_file );
			break;

	}
	echo '<div class="error"><p><strong>' . $msg . '</strong></p></div>';
}
add_action( 'admin_notices', 'wpsc_config_file_notices' );
function wpsc_dismiss_indexhtml_warning() {
	check_ajax_referer( 'wpsc-index-dismiss' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}

	update_site_option( 'wp_super_cache_index_detected', 3 );
	die( 0 );
}
add_action( 'wp_ajax_wpsc-index-dismiss', 'wpsc_dismiss_indexhtml_warning' );

if( get_option( 'gzipcompression' ) )
	update_option( 'gzipcompression', 0 );

function wp_cache_favorite_action( $actions ) {
	if ( function_exists( '_deprecated_function' ) ) {
		_deprecated_function( __FUNCTION__, 'WP Super Cache 1.6.4' );
	}

	if ( false == wpsupercache_site_admin() )
		return $actions;

	if ( function_exists('current_user_can') && !current_user_can('manage_options') )
		return $actions;

	$actions[ wp_nonce_url( 'options-general.php?page=wpsupercache&wp_delete_cache=1&tab=contents', 'wp-cache' ) ] = array( __( 'Delete Cache', 'wp-super-cache' ), 'manage_options' );

	return $actions;
}
//add_filter( 'favorite_actions', 'wp_cache_favorite_action' );

function wp_cache_plugin_notice( $plugin ) {
	global $cache_enabled;
	if( $plugin == 'wp-super-cache/wp-cache.php' && !$cache_enabled && function_exists( 'admin_url' ) )
		echo '<td colspan="5" class="plugin-update">' . sprintf( __( 'WP Super Cache must be configured. Go to <a href="%s">the admin page</a> to enable and configure the plugin.', 'wp-super-cache' ), admin_url( 'options-general.php?page=wpsupercache' ) ) . '</td>';
}
add_action( 'after_plugin_row', 'wp_cache_plugin_notice' );

function wp_cache_plugin_actions( $links, $file ) {
	if ( $file === 'wp-super-cache/wp-cache.php' && function_exists( 'admin_url' ) && is_array( $links ) ) {
		$settings_link = '<a href="' . admin_url( 'options-general.php?page=wpsupercache' ) . '">' . __( 'Settings', 'wp-super-cache' ) . '</a>';
		array_unshift( $links, $settings_link ); // before other links
	}
	return $links;
}
add_filter( 'plugin_action_links', 'wp_cache_plugin_actions', 10, 2 );

function wp_cache_admin_notice() {
	global $cache_enabled, $wp_cache_phase1_loaded;
	if( substr( $_SERVER['PHP_SELF'], -11 ) == 'plugins.php' && !$cache_enabled && function_exists( 'admin_url' ) )
		echo '<div class="notice notice-info"><p><strong>' . sprintf( __('WP Super Cache is disabled. Please go to the <a href="%s">plugin admin page</a> to enable caching.', 'wp-super-cache' ), admin_url( 'options-general.php?page=wpsupercache' ) ) . '</strong></p></div>';

	if ( defined( 'WP_CACHE' ) && WP_CACHE == true && ( defined( 'ADVANCEDCACHEPROBLEM' ) || ( $cache_enabled && false == isset( $wp_cache_phase1_loaded ) ) ) ) {
		if ( wp_cache_create_advanced_cache() ) {
			echo '<div class="notice notice-error"><p>' . sprintf( __( 'Warning! WP Super Cache caching <strong>was</strong> broken but has been <strong>fixed</strong>! The script advanced-cache.php could not load wp-cache-phase1.php.<br /><br />The file %1$s/advanced-cache.php has been recreated and WPCACHEHOME fixed in your wp-config.php. Reload to hide this message.', 'wp-super-cache' ), WP_CONTENT_DIR ) . '</p></div>';
		}
	}
}
add_action( 'admin_notices', 'wp_cache_admin_notice' );

function wp_cache_check_site() {
	global $wp_super_cache_front_page_check, $wp_super_cache_front_page_clear, $wp_super_cache_front_page_text, $wp_super_cache_front_page_notification, $wpdb;

	if ( empty( $wp_super_cache_front_page_check ) ) {
		return false;
	}

	if ( function_exists( "wp_remote_get" ) == false ) {
		return false;
	}
	$front_page = wp_remote_get( site_url(), array('timeout' => 60, 'blocking' => true ) );
	if( is_array( $front_page ) ) {
		// Check for gzipped front page
		if ( $front_page[ 'headers' ][ 'content-type' ] == 'application/x-gzip' ) {
			if ( ! isset( $wp_super_cache_front_page_clear ) || $wp_super_cache_front_page_clear === 0 ) {
				wp_mail( get_option( 'admin_email' ), sprintf( __( '[%s] Front page is gzipped! Please clear cache!', 'wp-super-cache' ), home_url() ), sprintf( __( "Please visit %s to clear the cache as the front page of your site is now downloading!", 'wp-super-cache' ), admin_url( 'options-general.php?page=wpsupercache' ) ) );
			} else {
				wp_cache_clear_cache( $wpdb->blogid );
				wp_mail( get_option( 'admin_email' ), sprintf( __( '[%s] Front page is gzipped! Cache Cleared!', 'wp-super-cache' ), home_url() ), sprintf( __( "The cache on your blog has been cleared because the front page of your site is now downloading. Please visit %s to verify the cache has been cleared.", 'wp-super-cache' ), admin_url( 'options-general.php?page=wpsupercache' ) ) );
			}
		}

		// Check for broken front page
		if (
			! empty( $wp_super_cache_front_page_text )
			&& ! str_contains( $front_page['body'], $wp_super_cache_front_page_text )
		) {
			if ( ! isset( $wp_super_cache_front_page_clear ) || $wp_super_cache_front_page_clear === 0 ) {
				wp_mail( get_option( 'admin_email' ), sprintf( __( '[%s] Front page is not correct! Please clear cache!', 'wp-super-cache' ), home_url() ), sprintf( __( 'Please visit %1$s to clear the cache as the front page of your site is not correct and missing the text, "%2$s"!', 'wp-super-cache' ), admin_url( 'options-general.php?page=wpsupercache' ), $wp_super_cache_front_page_text ) );
			} else {
				wp_cache_clear_cache( $wpdb->blogid );
				wp_mail( get_option( 'admin_email' ), sprintf( __( '[%s] Front page is not correct! Cache Cleared!', 'wp-super-cache' ), home_url() ), sprintf( __( 'The cache on your blog has been cleared because the front page of your site is missing the text "%2$s". Please visit %1$s to verify the cache has been cleared.', 'wp-super-cache' ), admin_url( 'options-general.php?page=wpsupercache' ), $wp_super_cache_front_page_text ) );
			}
		}
	}
	if ( isset( $wp_super_cache_front_page_notification ) && $wp_super_cache_front_page_notification == 1 ) {
		wp_mail( get_option( 'admin_email' ), sprintf( __( '[%s] Front page check!', 'wp-super-cache' ), home_url() ), sprintf( __( "WP Super Cache has checked the front page of your blog. Please visit %s if you would like to disable this.", 'wp-super-cache' ) . "\n\n", admin_url( 'options-general.php?page=wpsupercache' ) ) );
	}

	if ( !wp_next_scheduled( 'wp_cache_check_site_hook' ) ) {
		wp_schedule_single_event( time() + 360 , 'wp_cache_check_site_hook' );
		wp_cache_debug( 'scheduled wp_cache_check_site_hook for 360 seconds time.', 2 );
	}
}
add_action( 'wp_cache_check_site_hook', 'wp_cache_check_site' );
