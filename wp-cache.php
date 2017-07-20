<?php
/*
Plugin Name: WP Super Cache
Plugin URI: https://wordpress.org/plugins/wp-super-cache/
Description: Very fast caching plugin for WordPress.
Version: 1.5.1
Author: Automattic
Author URI: https://automattic.com/
License: GPL2+
Text Domain: wp-super-cache
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
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// Pre-2.6 compatibility
if( !defined('WP_CONTENT_URL') )
	define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
if( !defined('WP_CONTENT_DIR') )
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );

$wp_cache_config_file = WP_CONTENT_DIR . '/wp-cache-config.php';

if ( !defined( 'WPCACHEHOME' ) ) {
	define( 'WPCACHEHOME', dirname( __FILE__ ) . '/' );
	$wp_cache_config_file_sample = WPCACHEHOME . 'wp-cache-config-sample.php';
	$wp_cache_file = WPCACHEHOME . 'advanced-cache.php';
} elseif ( WPCACHEHOME != dirname( __FILE__ ) . '/' ) {
	$wp_cache_config_file_sample = dirname( __FILE__ ) . '/wp-cache-config-sample.php';
	$wp_cache_file = dirname( __FILE__ ) . '/advanced-cache.php';
} else {
	$wp_cache_config_file_sample = WPCACHEHOME . 'wp-cache-config-sample.php';
	$wp_cache_file = WPCACHEHOME . 'advanced-cache.php';
}

if( !@include($wp_cache_config_file) ) {
	get_wpcachehome();
	$wp_cache_config_file_sample = WPCACHEHOME . 'wp-cache-config-sample.php';
	@include($wp_cache_config_file_sample);
} else {
	get_wpcachehome();
}

$wp_cache_link = WP_CONTENT_DIR . '/advanced-cache.php';

if( !defined( 'WP_CACHE' ) || ( defined( 'WP_CACHE' ) && constant( 'WP_CACHE' ) == false ) ) {
	$wp_cache_check_wp_config = true;
}

include(WPCACHEHOME . 'wp-cache-base.php');
if ( class_exists( 'WP_REST_Controller' ) ) {
	include( WPCACHEHOME . 'rest/load.php' );
}

function wp_super_cache_text_domain() {
	load_plugin_textdomain( 'wp-super-cache', false, basename( dirname( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'wp_super_cache_text_domain' );

function wp_cache_set_home() {
	global $wp_cache_is_home;
	$wp_cache_is_home = ( is_front_page() || is_home() );
	if ( $wp_cache_is_home && is_paged() )
		$wp_cache_is_home = false;
}
add_action( 'template_redirect', 'wp_cache_set_home' );


// OSSDL CDN plugin (http://wordpress.org/plugins/ossdl-cdn-off-linker/)
include_once( WPCACHEHOME . 'ossdl-cdn.php' );

// from legolas558 d0t users dot sf dot net at http://www.php.net/is_writable
function is_writeable_ACLSafe($path) {

	// PHP's is_writable does not work with Win32 NTFS

	if ($path{strlen($path)-1}=='/') // recursively return a temporary file path
		return is_writeable_ACLSafe($path.uniqid(mt_rand()).'.tmp');
	else if (is_dir($path))
		return is_writeable_ACLSafe($path.'/'.uniqid(mt_rand()).'.tmp');
	// check tmp file for read/write capabilities
	$rm = file_exists($path);
	$f = @fopen($path, 'a');
	if ($f===false)
		return false;
	fclose($f);
	if (!$rm)
		unlink($path);
	return true;
}

function get_wpcachehome() {
	if( defined( 'WPCACHEHOME' ) == false ) {
		if( is_file( dirname(__FILE__) . '/wp-cache-config-sample.php' ) ) {
			define( 'WPCACHEHOME', trailingslashit( dirname(__FILE__) ) );
		} elseif( is_file( dirname(__FILE__) . '/wp-super-cache/wp-cache-config-sample.php' ) ) {
			define( 'WPCACHEHOME', dirname(__FILE__) . '/wp-super-cache/' );
		} else {
			die( sprintf( __( 'Please create %s /wp-cache-config.php from wp-super-cache/wp-cache-config-sample.php', 'wp-super-cache' ), WP_CONTENT_DIR ) );
		}
	}
}

function wpsupercache_uninstall() {
	global $wp_cache_config_file, $wp_cache_link, $cache_path;
	$files = array( $wp_cache_config_file, $wp_cache_link );
	foreach( $files as $file ) {
		if ( file_exists( $file ) )
			unlink( $file );
	}
	if ( !function_exists( 'wp_cache_debug' ) )
		include_once( 'wp-cache-phase1.php' );
	if ( !function_exists( 'prune_super_cache' ) )
		include_once( 'wp-cache-phase2.php' );
	prune_super_cache( $cache_path, true );
	wp_cache_remove_index();
	@unlink( $cache_path . '.htaccess' );
	@unlink( $cache_path . 'meta' );
	@unlink( $cache_path . 'supercache' );
	wp_clear_scheduled_hook( 'wp_cache_check_site_hook' );
	wp_clear_scheduled_hook( 'wp_cache_gc' );
	wp_clear_scheduled_hook( 'wp_cache_gc_watcher' );
	wp_cache_disable_plugin();
	delete_site_option( 'wp_super_cache_index_detected' );
}
register_uninstall_hook( __FILE__, 'wpsupercache_uninstall' );

function wpsupercache_deactivate() {
	global $wp_cache_config_file, $wp_cache_link, $cache_path;
	if ( file_exists( $wp_cache_link ) )
		unlink( $wp_cache_link );
	if ( !function_exists( 'wp_cache_debug' ) )
		include_once( 'wp-cache-phase1.php' );
	if ( !function_exists( 'prune_super_cache' ) )
		include_once( 'wp-cache-phase2.php' );
	prune_super_cache( $cache_path, true );
	wp_cache_remove_index();
	@unlink( $cache_path . '.htaccess' );
	@unlink( $cache_path . 'meta' );
	@unlink( $cache_path . 'supercache' );
	wp_clear_scheduled_hook( 'wp_cache_check_site_hook' );
	wp_clear_scheduled_hook( 'wp_cache_gc' );
	wp_clear_scheduled_hook( 'wp_cache_gc_watcher' );
	wp_cache_replace_line('^ *\$cache_enabled', '$cache_enabled = false;', $wp_cache_config_file);
	wp_cache_disable_plugin( false ); // don't delete configuration file
}
register_deactivation_hook( __FILE__, 'wpsupercache_deactivate' );

function wpsupercache_activate() {
	wp_schedule_single_event( time() + 10, 'wp_cache_add_site_cache_index' );
}
register_activation_hook( __FILE__, 'wpsupercache_activate' );

function wpsupercache_site_admin() {
	global $wp_version;

	if ( version_compare( "4.8", $wp_version, "<=" ) ) {
		return current_user_can( 'setup_network' );
	}

	if ( function_exists( 'is_super_admin' ) ) {
		return is_super_admin();
	} elseif ( function_exists( 'is_site_admin' ) ) {
		return is_site_admin();
	} else {
		return true;
	}
}

function wp_cache_add_pages() {
	global $wpmu_version;
	if ( wpsupercache_site_admin() ) { // in single or MS mode add this menu item too, but only for superadmins in MS mode.
		add_options_page( 'WP Super Cache', 'WP Super Cache', 'manage_options', 'wpsupercache', 'wp_cache_manager');
	}
}
add_action('admin_menu', 'wp_cache_add_pages');

function wp_cache_network_pages() {
	add_submenu_page('settings.php', 'WP Super Cache', 'WP Super Cache', 'manage_options', 'wpsupercache', 'wp_cache_manager');
}
add_action( 'network_admin_menu', 'wp_cache_network_pages' );

function wp_cache_manager_error_checks() {
	global $wp_cache_debug, $wp_cache_cron_check, $cache_enabled, $super_cache_enabled, $wp_cache_config_file, $wp_cache_mobile_browsers, $wp_cache_mobile_prefixes, $wp_cache_mobile_browsers, $wp_cache_mobile_enabled, $wp_cache_mod_rewrite;
	global $dismiss_htaccess_warning, $dismiss_readable_warning, $dismiss_gc_warning, $wp_cache_shutdown_gc;

	if ( !wpsupercache_site_admin() )
		return false;

	if ( version_compare( PHP_VERSION, '5.3.0', '<' ) && ( 1 == ini_get( 'safe_mode' ) || "on" == strtolower( ini_get( 'safe_mode' ) ) ) ) {
		echo '<div class="notice notice-error"><h3>' . __( 'Warning! PHP Safe Mode Enabled!', 'wp-super-cache' ) . '</h3><p>' .
			__( 'You may experience problems running this plugin because SAFE MODE is enabled.', 'wp-super-cache' ) . '<br />';


		if( !ini_get( 'safe_mode_gid' ) ) {
			echo __( 'Your server is set up to check the owner of PHP scripts before allowing them to read and write files.', 'wp-super-cache' ) . '<br />';
			printf( __( 'You or an administrator may be able to make it work by changing the group owner of the plugin scripts to match that of the web server user. The group owner of the %s/cache/ directory must also be changed. See the <a href="http://php.net/features.safe-mode">safe mode manual page</a> for further details.', 'wp-super-cache' ), WP_CONTENT_DIR );
		} else {
			_e( 'You or an administrator must disable this. See the <a href="http://php.net/features.safe-mode">safe mode manual page</a> for further details. This cannot be disabled in a .htaccess file unfortunately. It must be done in the php.ini config file.', 'wp-super-cache' );
		}
		echo '</p></div>';
	}

	if ( '' == get_option( 'permalink_structure' ) ) {
		echo '<div class="notice notice-error"><h3>' . __( 'Permlink Structure Error', 'wp-super-cache' ) . '</h3>';
		echo "<p>" . __( 'A custom url or permalink structure is required for this plugin to work correctly. Please go to the <a href="options-permalink.php">Permalinks Options Page</a> to configure your permalinks.', 'wp-super-cache' ) . "</p>";
		echo '</div>';
		return false;
	}

	if( $wp_cache_debug || !$wp_cache_cron_check ) {
		if( function_exists( "wp_remote_get" ) == false ) {
			$hostname = str_replace( 'http://', '', str_replace( 'https://', '', get_option( 'siteurl' ) ) );
			if( strpos( $hostname, '/' ) )
				$hostname = substr( $hostname, 0, strpos( $hostname, '/' ) );
			$ip = gethostbyname( $hostname );
			if( substr( $ip, 0, 3 ) == '127' || substr( $ip, 0, 7 ) == '192.168' ) {
				?><div class="notice notice-warning"><h3><?php printf( __( 'Warning! Your hostname "%s" resolves to %s', 'wp-super-cache' ), $hostname, $ip ); ?></h3>
					<p><?php printf( __( 'Your server thinks your hostname resolves to %s. Some services such as garbage collection by this plugin, and WordPress scheduled posts may not operate correctly.', 'wp-super-cache' ), $ip ); ?></p>
					<p><?php printf( __( 'Please see entry 16 in the <a href="%s">Troubleshooting section</a> of the readme.txt', 'wp-super-cache' ), 'http://wordpress.org/plugins/wp-super-cache/faq/' ); ?></p>
					</div>
					<?php
					return false;
			} else {
				wp_cache_replace_line('^ *\$wp_cache_cron_check', "\$wp_cache_cron_check = 1;", $wp_cache_config_file);
			}
		} else {
			$cron_url = get_option( 'siteurl' ) . '/wp-cron.php?check=' . wp_hash('187425');
			$cron = wp_remote_get($cron_url, array('timeout' => 0.01, 'blocking' => true));
			if( is_array( $cron ) ) {
				if( $cron[ 'response' ][ 'code' ] == '404' ) {
					?><div class="notice notice-error"><h3>Warning! wp-cron.php not found!</h3>
					<p><?php _e( 'Unfortunately, WordPress cannot find the file wp-cron.php. This script is required for the correct operation of garbage collection by this plugin, WordPress scheduled posts as well as other critical activities.', 'wp-super-cache' ); ?></p>
					<p><?php printf( __( 'Please see entry 16 in the <a href="%s">Troubleshooting section</a> of the readme.txt', 'wp-super-cache' ), 'http://wordpress.org/plugins/wp-super-cache/faq/' ); ?></p>
					</div>
					<?php
				} else {
					wp_cache_replace_line('^ *\$wp_cache_cron_check', "\$wp_cache_cron_check = 1;", $wp_cache_config_file);
				}
			}
		}
	}

	if ( !wp_cache_check_link() ||
		!wp_cache_verify_config_file() ||
		!wp_cache_verify_cache_dir() ) {
		echo '<p>' . __( "Cannot continue... fix previous problems and retry.", 'wp-super-cache' ) . '</p>';
		return false;
	}

	if (!wp_cache_check_global_config()) {
		return false;
	}

	if ( 1 == ini_get( 'zlib.output_compression' ) || "on" == strtolower( ini_get( 'zlib.output_compression' ) ) ) {
		?><div class="notice notice-warning"><h3><?php _e( 'Zlib Output Compression Enabled!', 'wp-super-cache' ); ?></h3>
		<p><?php _e( 'PHP is compressing the data sent to the visitors of your site. Disabling this is recommended as the plugin caches the compressed output once instead of compressing the same page over and over again. Also see #21 in the Troubleshooting section. See <a href="http://php.net/manual/en/zlib.configuration.php">this page</a> for instructions on modifying your php.ini.', 'wp-super-cache' ); ?></p></div><?php
	}

	if( $cache_enabled == true && $super_cache_enabled == true && $wp_cache_mod_rewrite && !got_mod_rewrite() ) {
		?><div class="notice notice-warning"><h3><?php _e( 'Mod rewrite may not be installed!', 'wp-super-cache' ); ?></h3>
		<p><?php _e( 'It appears that mod_rewrite is not installed. Sometimes this check isn&#8217;t 100% reliable, especially if you are not using Apache. Please verify that the mod_rewrite module is loaded. It is required for serving Super Cache static files in expert mode. You will still be able to simple mode.', 'wp-super-cache' ); ?></p></div><?php
	}

	if( !is_writeable_ACLSafe( $wp_cache_config_file ) ) {
		if ( !defined( 'SUBMITDISABLED' ) )
			define( "SUBMITDISABLED", 'disabled style="color: #aaa" ' );
		?><div class="notice notice-error"><h3><?php _e( 'Read Only Mode. Configuration cannot be changed.', 'wp-super-cache' ); ?></h3>
		<p><?php printf( __( 'The WP Super Cache configuration file is <code>%s/wp-cache-config.php</code> and cannot be modified. That file must be writeable by the web server to make any changes.', 'wp-super-cache' ), WP_CONTENT_DIR ); ?>
		<?php _e( 'A simple way of doing that is by changing the permissions temporarily using the CHMOD command or through your ftp client. Make sure it&#8217;s globally writeable and it should be fine.', 'wp-super-cache' ); ?></p>
		<p><?php _e( '<a href="http://codex.wordpress.org/Changing_File_Permissions">This page</a> explains how to change file permissions.', 'wp-super-cache' ); ?></p>
		<?php _e( 'Writeable:', 'wp-super-cache' ); ?> <code>chmod 666 <?php echo WP_CONTENT_DIR; ?>/wp-cache-config.php</code><br />
		<?php _e( 'Read-only:', 'wp-super-cache' ); ?> <code>chmod 644 <?php echo WP_CONTENT_DIR; ?>/wp-cache-config.php</code></p>
		</div><?php
	} elseif ( !defined( 'SUBMITDISABLED' ) ) {
		define( "SUBMITDISABLED", ' ' );
	}

	$valid_nonce = isset($_REQUEST['_wpnonce']) ? wp_verify_nonce($_REQUEST['_wpnonce'], 'wp-cache') : false;
	// Check that garbage collection is running
	if ( $valid_nonce && isset( $_POST[ 'action' ] ) && $_POST[ 'action' ] == 'dismiss_gc_warning' ) {
		wp_cache_replace_line('^ *\$dismiss_gc_warning', "\$dismiss_gc_warning = 1;", $wp_cache_config_file);
		$dismiss_gc_warning = 1;
	} elseif ( !isset( $dismiss_gc_warning ) ) {
		$dismiss_gc_warning = 0;
	}
	if ( $cache_enabled && ( !isset( $wp_cache_shutdown_gc ) || $wp_cache_shutdown_gc == 0 ) && function_exists( 'get_gc_flag' ) ) {
		$gc_flag = get_gc_flag();
		if ( $dismiss_gc_warning == 0 ) {
			if ( false == maybe_stop_gc( $gc_flag ) && false == wp_next_scheduled( 'wp_cache_gc' ) ) {
				?><div class="notice notice-warning"><h3><?php _e( 'Warning! Garbage collection is not scheduled!', 'wp-super-cache' ); ?></h3>
				<p><?php _e( 'Garbage collection by this plugin clears out expired and old cached pages on a regular basis. Use <a href="#expirytime">this form</a> to enable it.', 'wp-super-cache' ); ?> </p>
				<form action="" method="POST">
				<input type="hidden" name="action" value="dismiss_gc_warning" />
				<input type="hidden" name="page" value="wpsupercache" />
				<?php wp_nonce_field( 'wp-cache' ); ?>
				<input class='button-secondary' type='submit' value='<?php _e( 'Dismiss', 'wp-super-cache' ); ?>' />
				</form>
				<br />
				</div>
				<?php
			}
		}
	}

	// Server could be running as the owner of the wp-content directory.  Therefore, if it's
	// writable, issue a warning only if the permissions aren't 755.
	if ( $valid_nonce && isset( $_POST[ 'action' ] ) && $_POST[ 'action' ] == 'dismiss_readable_warning' ) {
		wp_cache_replace_line('^ *\$dismiss_readable_warning', "\$dismiss_readable_warning = 1;", $wp_cache_config_file);
		$dismiss_readable_warning = 1;
	} elseif ( !isset( $dismiss_readable_warning ) ) {
		$dismiss_readable_warning = 0;
	}
	if( $dismiss_readable_warning == 0 && is_writeable_ACLSafe( WP_CONTENT_DIR . '/' ) ) {
		$wp_content_stat = stat(WP_CONTENT_DIR . '/');
		$wp_content_mode = decoct( $wp_content_stat[ 'mode' ] & 0777 );
		if( substr( $wp_content_mode, -2 ) == '77' ) {
			?><div class="notice notice-warning"><h3><?php printf( __( 'Warning! %s is writeable!', 'wp-super-cache' ), WP_CONTENT_DIR ); ?></h3>
			<p><?php printf( __( 'You should change the permissions on %s and make it more restrictive. Use your ftp client, or the following command to fix things:', 'wp-super-cache' ), WP_CONTENT_DIR ); ?> <code>chmod 755 <?php echo WP_CONTENT_DIR; ?>/</code></p>
			<p><?php _e( '<a href="http://codex.wordpress.org/Changing_File_Permissions">This page</a> explains how to change file permissions.', 'wp-super-cache' ); ?></p>
			<form action="" method="POST">
			<input type="hidden" name="action" value="dismiss_readable_warning" />
			<input type="hidden" name="page" value="wpsupercache" />
			<?php wp_nonce_field( 'wp-cache' ); ?>
			<input class='button-secondary' type='submit' value='<?php _e( 'Dismiss', 'wp-super-cache' ); ?>' />
			</form>
			<br />
			</div>
			<?php
		}
	}

	if ( function_exists( "is_main_site" ) && true == is_main_site() ) {
	$home_path = trailingslashit( get_home_path() );
	$scrules = implode( "\n", extract_from_markers( $home_path.'.htaccess', 'WPSuperCache' ) );
	if ( $cache_enabled && $wp_cache_mod_rewrite && !$wp_cache_mobile_enabled && strpos( $scrules, addcslashes( str_replace( ', ', '|', $wp_cache_mobile_browsers ), ' ' ) ) ) {
		echo '<div class="notice notice-warning"><h3>' . __( 'Mobile rewrite rules detected', 'wp-super-cache' ) . "</h3>";
		echo "<p>" . __( 'For best performance you should enable "Mobile device support" or delete the mobile rewrite rules in your .htaccess. Look for the 2 lines with the text "2.0\ MMP|240x320" and delete those.', 'wp-super-cache' ) . "</p><p>" . __( 'This will have no affect on ordinary users but mobile users will see uncached pages.', 'wp-super-cache' ) . "</p></div>";
	} elseif ( $wp_cache_mod_rewrite && $cache_enabled && $wp_cache_mobile_enabled && $scrules != '' && (
		( '' != $wp_cache_mobile_prefixes && false === strpos( $scrules, addcslashes( str_replace( ', ', '|', $wp_cache_mobile_prefixes ), ' ' ) ) ) ||
		( '' != $wp_cache_mobile_browsers && false === strpos( $scrules, addcslashes( str_replace( ', ', '|', $wp_cache_mobile_browsers ), ' ' ) ) ) )
		) {
		?>
			<div class="notice notice-warning"><h3><?php _e( 'Rewrite rules must be updated', 'wp-super-cache' ); ?></h3>
			<p><?php _e( 'The rewrite rules required by this plugin have changed or are missing. ', 'wp-super-cache' ); ?>
			<?php _e( 'Mobile support requires extra rules in your .htaccess file, or you can set the plugin to simple mode. Here are your options (in order of difficulty):', 'wp-super-cache' ); ?>
			<ol><li> <?php _e( 'Set the plugin to simple mode and enable mobile support.', 'wp-super-cache' ); ?></li>
			<li> <?php _e( 'Scroll down the Advanced Settings page and click the <strong>Update Mod_Rewrite Rules</strong> button.', 'wp-super-cache' ); ?></li>
			<li> <?php printf( __( 'Delete the plugin mod_rewrite rules in %s.htaccess enclosed by <code># BEGIN WPSuperCache</code> and <code># END WPSuperCache</code> and let the plugin regenerate them by reloading this page.', 'wp-super-cache' ), $home_path ); ?></li>
			<li> <?php printf( __( 'Add the rules yourself. Edit %s.htaccess and find the block of code enclosed by the lines <code># BEGIN WPSuperCache</code> and <code># END WPSuperCache</code>. There are two sections that look very similar. Just below the line <code>%%{HTTP:Cookie} !^.*(comment_author_|%s|wp-postpass_).*$</code> add these lines: (do it twice, once for each section)', 'wp-super-cache' ), $home_path, wpsc_get_logged_in_cookie() ); ?></p>
			<div style='padding: 2px; margin: 2px; border: 1px solid #333; width:400px; overflow: scroll'><pre><?php echo "RewriteCond %{HTTP_user_agent} !^.*(" . addcslashes( str_replace( ', ', '|', $wp_cache_mobile_browsers ), ' ' ) . ").*\nRewriteCond %{HTTP_user_agent} !^(" . addcslashes( str_replace( ', ', '|', $wp_cache_mobile_prefixes ), ' ' ) . ").*"; ?></pre></div></li></ol></div><?php
	}

	if ( $cache_enabled && $super_cache_enabled && $wp_cache_mod_rewrite && $scrules == '' ) {
		?><div class='notice notice-warning'><h3><?php _e( 'Rewrite rules must be updated', 'wp-super-cache' ); ?></h3>
		<p><?php _e( 'The rewrite rules required by this plugin have changed or are missing. ', 'wp-super-cache' ); ?>
		<?php _e( 'Scroll down the Advanced Settings page and click the <strong>Update Mod_Rewrite Rules</strong> button.', 'wp-super-cache' ); ?></p></div><?php
	}
	}

	if ( $wp_cache_mod_rewrite && $super_cache_enabled && function_exists( 'apache_get_modules' ) ) {
		$mods = apache_get_modules();
		$required_modules = array( 'mod_mime' => __( 'Required to serve compressed supercache files properly.', 'wp-super-cache' ), 'mod_headers' => __( 'Required to set caching information on supercache pages. IE7 users will see old pages without this module.', 'wp-super-cache' ), 'mod_expires' => __( 'Set the expiry date on supercached pages. Visitors may not see new pages when they refresh or leave comments without this module.', 'wp-super-cache' ) );
		foreach( $required_modules as $req => $desc ) {
			if( !in_array( $req, $mods ) ) {
				$missing_mods[ $req ] = $desc;
			}
		}
		if( isset( $missing_mods) && is_array( $missing_mods ) ) {
			?><div class='notice notice-warning'><h3><?php _e( 'Missing Apache Modules', 'wp-super-cache' ); ?></h3>
			<p><?php __( 'The following Apache modules are missing. The plugin will work in simple mode without them but in export mode, your visitors may see corrupted pages or out of date content however.', 'wp-super-cache' ); ?></p><?php
			echo "<ul>";
			foreach( $missing_mods as $req => $desc ) {
				echo "<li> $req - $desc</li>";
			}
			echo "</ul>";
			echo "</div>";
		}
	}

	if ( $valid_nonce && isset( $_POST[ 'action' ] ) && $_POST[ 'action' ] == 'dismiss_htaccess_warning' ) {
		wp_cache_replace_line('^ *\$dismiss_htaccess_warning', "\$dismiss_htaccess_warning = 1;", $wp_cache_config_file);
		$dismiss_htaccess_warning = 1;
	} elseif ( !isset( $dismiss_htaccess_warning ) ) {
		$dismiss_htaccess_warning = 0;
	}
	if ( isset( $disable_supercache_htaccess_warning ) == false )
		$disable_supercache_htaccess_warning = false;
	if ( $dismiss_htaccess_warning == 0 && $wp_cache_mod_rewrite && $super_cache_enabled && $disable_supercache_htaccess_warning == false && get_option( 'siteurl' ) != get_option( 'home' ) ) {
		?><div class="notice notice-info"><h3><?php _e( '.htaccess file may need to be moved', 'wp-super-cache' ); ?></h3>
		<p><?php _e( 'It appears you have WordPress installed in a sub directory as described <a href="http://codex.wordpress.org/Giving_WordPress_Its_Own_Directory">here</a>. Unfortunately, WordPress writes to the .htaccess in the install directory, not where your site is served from.<br />When you update the rewrite rules in this plugin you will have to copy the file to where your site is hosted. This will be fixed in the future.', 'wp-super-cache' ); ?></p>
		<form action="" method="POST">
		<input type="hidden" name="action" value="dismiss_htaccess_warning" />
		<input type="hidden" name="page" value="wpsupercache" />
		<?php wp_nonce_field( 'wp-cache' ); ?>
		<input class='button-secondary' type='submit' value='<?php _e( 'Dismiss', 'wp-super-cache' ); ?>' />
		</form>
		<br />
		</div><?php
	}

	return true;

}
add_filter( 'wp_super_cache_error_checking', 'wp_cache_manager_error_checks' );

function admin_bar_delete_page() {
	// Delete cache for a specific page
	if ( function_exists('current_user_can') && false == current_user_can('delete_others_posts') )
		return false;
	if ( isset( $_GET[ 'action' ] ) && $_GET[ 'action' ] == 'delcachepage' && ( isset( $_GET[ '_wpnonce' ] ) ? wp_verify_nonce( $_REQUEST[ '_wpnonce' ], 'delete-cache' ) : false ) ) {
		$path = realpath( trailingslashit( get_supercache_dir() . str_replace( '..', '', preg_replace( '/:.*$/', '', $_GET[ 'path' ] ) ) ) ) . '/';
		if ( $path == '/' )
			return false; // Directory not found. Probably not cached.
		if ( false == wp_cache_confirm_delete( $path ) || substr( $path, 0, strlen( get_supercache_dir() ) ) != get_supercache_dir() )
			die( "Could not delete directory" );
		wpsc_delete_files( $path );
		wp_redirect( preg_replace( '/[ <>\'\"\r\n\t\(\)]/', '', $_GET[ 'path' ] ) );
		die();
	}
}
if ( isset( $_GET[ 'action' ] ) && $_GET[ 'action' ] == 'delcachepage' )
   add_action( 'admin_init', 'admin_bar_delete_page' );

function wp_cache_manager_updates() {
	global $wp_cache_mobile_enabled, $wp_cache_mfunc_enabled, $wp_supercache_cache_list, $wp_cache_config_file, $wp_cache_hello_world, $wp_cache_clear_on_post_edit, $cache_rebuild_files, $wp_cache_mutex_disabled, $wp_cache_not_logged_in, $wp_cache_make_known_anon, $cache_path, $wp_cache_object_cache, $_wp_using_ext_object_cache, $wp_cache_refresh_single_only, $cache_compression, $wp_cache_mod_rewrite, $wp_supercache_304, $wp_super_cache_late_init, $wp_cache_front_page_checks, $cache_page_secret, $wp_cache_disable_utf8, $wp_cache_no_cache_for_get;
	global $cache_schedule_type, $cache_max_time, $cache_time_interval, $wp_cache_shutdown_gc, $wpsc_save_headers;

	if ( !wpsupercache_site_admin() )
		return false;

	if ( false == isset( $cache_page_secret ) ) {
		$cache_page_secret = md5( date( 'H:i:s' ) . mt_rand() );
		wp_cache_replace_line('^ *\$cache_page_secret', "\$cache_page_secret = '" . $cache_page_secret . "';", $wp_cache_config_file);
	}

	$valid_nonce = isset($_REQUEST['_wpnonce']) ? wp_verify_nonce($_REQUEST['_wpnonce'], 'wp-cache') : false;
	if ( $valid_nonce == false )
		return false;

	if ( isset( $_POST[ 'action' ] ) && $_POST[ 'action' ] == 'easysetup' ) {
		$_POST[ 'action' ] = 'scupdates';
		if( isset( $_POST[ 'wp_cache_easy_on' ] ) && $_POST[ 'wp_cache_easy_on' ] == 1 ) {
			$_POST[ 'wp_cache_mobile_enabled' ] = 1;
			$_POST[ 'wp_cache_enabled' ] = 1;
			$_POST[ 'super_cache_enabled' ] = 1;
			$_POST[ 'cache_rebuild_files' ] = 1;
			unset( $_POST[ 'cache_compression' ] );
			if ( $cache_path != WP_CONTENT_DIR . '/cache/' )
				$_POST[ 'wp_cache_location' ] = $cache_path;
			//
			// set up garbage collection with some default settings
			if ( ( !isset( $wp_cache_shutdown_gc ) || $wp_cache_shutdown_gc == 0 ) && false == wp_next_scheduled( 'wp_cache_gc' ) ) {
				if ( false == isset( $cache_schedule_type ) ) {
					$cache_schedule_type = 'interval';
					$cache_time_interval = 600;
					$cache_max_time = 1800;
					wp_cache_replace_line('^ *\$cache_schedule_type', "\$cache_schedule_type = '$cache_schedule_type';", $wp_cache_config_file);
					wp_cache_replace_line('^ *\$cache_time_interval', "\$cache_time_interval = '$cache_time_interval';", $wp_cache_config_file);
					wp_cache_replace_line('^ *\$cache_max_time', "\$cache_max_time = '$cache_max_time';", $wp_cache_config_file);
				}
				wp_schedule_single_event( time() + 600, 'wp_cache_gc' );
			}

		} else {
			unset( $_POST[ 'wp_cache_enabled' ] );
			wp_clear_scheduled_hook( 'wp_cache_check_site_hook' );
			wp_clear_scheduled_hook( 'wp_cache_gc' );
			wp_clear_scheduled_hook( 'wp_cache_gc_watcher' );
		}
		$advanced_settings = array( 'wp_super_cache_late_init', 'wp_cache_disable_utf8', 'wp_cache_no_cache_for_get', 'wp_supercache_304', 'wp_cache_mfunc_enabled', 'wp_cache_mobile_enabled', 'wp_cache_front_page_checks', 'wp_supercache_cache_list', 'wp_cache_hello_world', 'wp_cache_clear_on_post_edit', 'wp_cache_not_logged_in', 'wp_cache_make_known_anon','wp_cache_object_cache', 'wp_cache_refresh_single_only', 'cache_compression' );
		foreach( $advanced_settings as $setting ) {
			if ( isset( $$setting ) && $$setting == 1 ) {
				$_POST[ $setting ] = 1;
			}
		}
	}

	if( isset( $_POST[ 'action' ] ) && $_POST[ 'action' ] == 'scupdates' ) {
		if( isset( $_POST[ 'wp_cache_location' ] ) && $_POST[ 'wp_cache_location' ] != '' &&
		    ( !isset( $cache_path ) || $_POST[ 'wp_cache_location' ] != $cache_path ) ) {
			$dir = realpath( trailingslashit( dirname( $_POST[ 'wp_cache_location' ] ) ) );
			if ( $dir == false )
				$dir = WP_CONTENT_DIR . '/cache/';
			else
				$dir = trailingslashit( $dir ) . trailingslashit(wpsc_deep_replace( array( '..', '\\' ), basename( $_POST[ 'wp_cache_location' ] ) ) );
			$new_cache_path = $dir;
		} else {
			$new_cache_path = WP_CONTENT_DIR . '/cache/';
		}
		if ( $new_cache_path != $cache_path ) {
			if ( file_exists( $new_cache_path ) == false )
				rename( $cache_path, $new_cache_path );
			$cache_path = $new_cache_path;
			wp_cache_replace_line('^ *\$cache_path', "\$cache_path = '" . $cache_path . "';", $wp_cache_config_file);
		}

		if( isset( $_POST[ 'wp_super_cache_late_init' ] ) ) {
			$wp_super_cache_late_init = 1;
		} else {
			$wp_super_cache_late_init = 0;
		}
		wp_cache_replace_line('^ *\$wp_super_cache_late_init', "\$wp_super_cache_late_init = " . $wp_super_cache_late_init . ";", $wp_cache_config_file);

		if( isset( $_POST[ 'wp_cache_disable_utf8' ] ) ) {
			$wp_cache_disable_utf8 = 1;
		} else {
			$wp_cache_disable_utf8 = 0;
		}
		wp_cache_replace_line('^ *\$wp_cache_disable_utf8', "\$wp_cache_disable_utf8 = " . $wp_cache_disable_utf8 . ";", $wp_cache_config_file);

		if( isset( $_POST[ 'wp_cache_no_cache_for_get' ] ) ) {
			$wp_cache_no_cache_for_get = 1;
		} else {
			$wp_cache_no_cache_for_get = 0;
		}
		wp_cache_replace_line('^ *\$wp_cache_no_cache_for_get', "\$wp_cache_no_cache_for_get = " . $wp_cache_no_cache_for_get . ";", $wp_cache_config_file);

		if( isset( $_POST[ 'wp_supercache_304' ] ) ) {
			$wp_supercache_304 = 1;
		} else {
			$wp_supercache_304 = 0;
		}
		wp_cache_replace_line('^ *\$wp_supercache_304', "\$wp_supercache_304 = " . $wp_supercache_304 . ";", $wp_cache_config_file);

		if( isset( $_POST[ 'wp_cache_mfunc_enabled' ] ) ) {
			$wp_cache_mfunc_enabled = 1;
		} else {
			$wp_cache_mfunc_enabled = 0;
		}
		wp_cache_replace_line('^ *\$wp_cache_mfunc_enabled', "\$wp_cache_mfunc_enabled = " . $wp_cache_mfunc_enabled . ";", $wp_cache_config_file);

		if( isset( $_POST[ 'wp_cache_mobile_enabled' ] ) ) {
			$wp_cache_mobile_enabled = 1;
		} else {
			$wp_cache_mobile_enabled = 0;
		}
		wp_cache_replace_line('^ *\$wp_cache_mobile_enabled', "\$wp_cache_mobile_enabled = " . $wp_cache_mobile_enabled . ";", $wp_cache_config_file);

		if( isset( $_POST[ 'wp_cache_front_page_checks' ] ) ) {
			$wp_cache_front_page_checks = 1;
		} else {
			$wp_cache_front_page_checks = 0;
		}
		wp_cache_replace_line('^ *\$wp_cache_front_page_checks', "\$wp_cache_front_page_checks = " . $wp_cache_front_page_checks . ";", $wp_cache_config_file);

		if( isset( $_POST[ 'wp_supercache_cache_list' ] ) ) {
			$wp_supercache_cache_list = 1;
		} else {
			$wp_supercache_cache_list = 0;
		}
		wp_cache_replace_line('^ *\$wp_supercache_cache_list', "\$wp_supercache_cache_list = " . $wp_supercache_cache_list . ";", $wp_cache_config_file);

		if ( isset( $_POST[ 'wp_cache_enabled' ] ) ) {
			wp_cache_enable();
			if ( ! defined( 'DISABLE_SUPERCACHE' ) ) {
				wp_cache_debug( 'DISABLE_SUPERCACHE is not set, super_cache enabled.' );
				wp_super_cache_enable();
				$super_cache_enabled = true;
			}
		} else {
			wp_cache_disable();
			wp_super_cache_disable();
			$super_cache_enabled = false;
		}

		if ( $_POST[ 'wp_cache_mod_rewrite' ] == 1 ) {
			$wp_cache_mod_rewrite = 1;
			add_mod_rewrite_rules();
		} else {
			$wp_cache_mod_rewrite = 0; // cache files served by PHP
			remove_mod_rewrite_rules();
		}
		wp_cache_setting( 'wp_cache_mod_rewrite', $wp_cache_mod_rewrite );

		if( isset( $_POST[ 'wp_cache_hello_world' ] ) ) {
			$wp_cache_hello_world = 1;
		} else {
			$wp_cache_hello_world = 0;
		}
		wp_cache_replace_line('^ *\$wp_cache_hello_world', '$wp_cache_hello_world = ' . $wp_cache_hello_world . ";", $wp_cache_config_file);

		if( isset( $_POST[ 'wp_cache_clear_on_post_edit' ] ) ) {
			$wp_cache_clear_on_post_edit = 1;
		} else {
			$wp_cache_clear_on_post_edit = 0;
		}
		wp_cache_replace_line('^ *\$wp_cache_clear_on_post_edit', "\$wp_cache_clear_on_post_edit = " . $wp_cache_clear_on_post_edit . ";", $wp_cache_config_file);

		if( isset( $_POST[ 'cache_rebuild_files' ] ) ) {
			$cache_rebuild_files = 1;
		} else {
			$cache_rebuild_files = 0;
		}
		wp_cache_replace_line('^ *\$cache_rebuild_files', "\$cache_rebuild_files = " . $cache_rebuild_files . ";", $wp_cache_config_file);

		if ( isset( $_POST[ 'wpsc_save_headers' ] ) ) {
			$wpsc_save_headers = 1;
		} else {
			$wpsc_save_headers = 0;
		}
		wp_cache_replace_line('^ *\$wpsc_save_headers', "\$wpsc_save_headers = " . $wpsc_save_headers . ";", $wp_cache_config_file);

		if( isset( $_POST[ 'wp_cache_mutex_disabled' ] ) ) {
			$wp_cache_mutex_disabled = 0;
		} else {
			$wp_cache_mutex_disabled = 1;
		}
		if( defined( 'WPSC_DISABLE_LOCKING' ) ) {
			$wp_cache_mutex_disabled = 1;
		}
		wp_cache_replace_line('^ *\$wp_cache_mutex_disabled', "\$wp_cache_mutex_disabled = " . $wp_cache_mutex_disabled . ";", $wp_cache_config_file);

		if( isset( $_POST[ 'wp_cache_not_logged_in' ] ) ) {
			if( $wp_cache_not_logged_in == 0 && function_exists( 'prune_super_cache' ) )
				prune_super_cache ($cache_path, true);
			$wp_cache_not_logged_in = 1;
		} else {
			$wp_cache_not_logged_in = 0;
		}
		wp_cache_replace_line('^ *\$wp_cache_not_logged_in', "\$wp_cache_not_logged_in = " . $wp_cache_not_logged_in . ";", $wp_cache_config_file);

		if( isset( $_POST[ 'wp_cache_make_known_anon' ] ) ) {
			if( $wp_cache_make_known_anon == 0 && function_exists( 'prune_super_cache' ) )
				prune_super_cache ($cache_path, true);
			$wp_cache_make_known_anon = 1;
		} else {
			$wp_cache_make_known_anon = 0;
		}
		wp_cache_replace_line('^ *\$wp_cache_make_known_anon', "\$wp_cache_make_known_anon = " . $wp_cache_make_known_anon . ";", $wp_cache_config_file);

		if( $_wp_using_ext_object_cache && isset( $_POST[ 'wp_cache_object_cache' ] ) ) {
			if( $wp_cache_object_cache == 0 && function_exists( 'prune_super_cache' ) )
				prune_super_cache( $cache_path, true );
			$wp_cache_object_cache = 1;
		} else {
			$wp_cache_object_cache = 0;
		}
		wp_cache_replace_line('^ *\$wp_cache_object_cache', "\$wp_cache_object_cache = " . $wp_cache_object_cache . ";", $wp_cache_config_file);

		if( isset( $_POST[ 'wp_cache_refresh_single_only' ] ) ) {
			$wp_cache_refresh_single_only = 1;
		} else {
			$wp_cache_refresh_single_only = 0;
		}
		wp_cache_setting( 'wp_cache_refresh_single_only', $wp_cache_refresh_single_only );

		if ( defined( 'WPSC_DISABLE_COMPRESSION' ) ) {
			$cache_compression = 0;
			wp_cache_replace_line('^ *\$cache_compression', "\$cache_compression = " . $cache_compression . ";", $wp_cache_config_file);
		} else {
			if ( isset( $_POST[ 'cache_compression' ] ) ) {
				$new_cache_compression = 1;
			} else {
				$new_cache_compression = 0;
			}
			if ( 1 == ini_get( 'zlib.output_compression' ) || "on" == strtolower( ini_get( 'zlib.output_compression' ) ) ) {
				echo '<div class="notice notice-error">' . __( "<strong>Warning!</strong> You attempted to enable compression but <code>zlib.output_compression</code> is enabled. See #21 in the Troubleshooting section of the readme file.", 'wp-super-cache' ) . '</div>';
			} else {
				if ( $new_cache_compression != $cache_compression ) {
					$cache_compression = $new_cache_compression;
					wp_cache_replace_line('^ *\$cache_compression', "\$cache_compression = " . $cache_compression . ";", $wp_cache_config_file);
					if ( function_exists( 'prune_super_cache' ) )
						prune_super_cache( $cache_path, true );
					delete_option( 'super_cache_meta' );
				}
			}
		}
	}
}
if ( isset( $_GET[ 'page' ] ) && $_GET[ 'page' ] == 'wpsupercache' )
	add_action( 'admin_init', 'wp_cache_manager_updates' );

function wp_cache_manager() {
	global $wp_cache_config_file, $valid_nonce, $supercachedir, $cache_path, $cache_enabled, $cache_compression, $super_cache_enabled, $wp_cache_hello_world;
	global $wp_cache_clear_on_post_edit, $cache_rebuild_files, $wp_cache_mutex_disabled, $wp_cache_mobile_enabled, $wp_cache_mobile_browsers, $wp_cache_no_cache_for_get;
	global $wp_cache_not_logged_in, $wp_cache_make_known_anon, $wp_supercache_cache_list, $cache_page_secret;
	global $wp_super_cache_front_page_check, $wp_cache_object_cache, $_wp_using_ext_object_cache, $wp_cache_refresh_single_only, $wp_cache_mobile_prefixes;
	global $wp_cache_mod_rewrite, $wp_supercache_304, $wp_super_cache_late_init, $wp_cache_front_page_checks, $wp_cache_disable_utf8, $wp_cache_mfunc_enabled;
	global $wp_super_cache_comments, $wp_cache_home_path, $wpsc_save_headers;

	if ( !wpsupercache_site_admin() )
		return false;

	// used by mod_rewrite rules and config file
	if ( function_exists( "cfmobi_default_browsers" ) ) {
		$wp_cache_mobile_browsers = cfmobi_default_browsers( "mobile" );
		$wp_cache_mobile_browsers = array_merge( $wp_cache_mobile_browsers, cfmobi_default_browsers( "touch" ) );
	} elseif ( function_exists( 'lite_detection_ua_contains' ) ) {
		$wp_cache_mobile_browsers = explode( '|', lite_detection_ua_contains() );
	} else {
		$wp_cache_mobile_browsers = array( '2.0 MMP', '240x320', '400X240', 'AvantGo', 'BlackBerry', 'Blazer', 'Cellphone', 'Danger', 'DoCoMo', 'Elaine/3.0', 'EudoraWeb', 'Googlebot-Mobile', 'hiptop', 'IEMobile', 'KYOCERA/WX310K', 'LG/U990', 'MIDP-2.', 'MMEF20', 'MOT-V', 'NetFront', 'Newt', 'Nintendo Wii', 'Nitro', 'Nokia', 'Opera Mini', 'Palm', 'PlayStation Portable', 'portalmmm', 'Proxinet', 'ProxiNet', 'SHARP-TQ-GX10', 'SHG-i900', 'Small', 'SonyEricsson', 'Symbian OS', 'SymbianOS', 'TS21i-10', 'UP.Browser', 'UP.Link', 'webOS', 'Windows CE', 'WinWAP', 'YahooSeeker/M1A1-R2D2', 'iPhone', 'iPod', 'Android', 'BlackBerry9530', 'LG-TU915 Obigo', 'LGE VX', 'webOS', 'Nokia5800' );
	}
	if ( function_exists( "lite_detection_ua_prefixes" ) ) {
		$wp_cache_mobile_prefixes = lite_detection_ua_prefixes();
	} else {
		$wp_cache_mobile_prefixes = array( 'w3c ', 'w3c-', 'acs-', 'alav', 'alca', 'amoi', 'audi', 'avan', 'benq', 'bird', 'blac', 'blaz', 'brew', 'cell', 'cldc', 'cmd-', 'dang', 'doco', 'eric', 'hipt', 'htc_', 'inno', 'ipaq', 'ipod', 'jigs', 'kddi', 'keji', 'leno', 'lg-c', 'lg-d', 'lg-g', 'lge-', 'lg/u', 'maui', 'maxo', 'midp', 'mits', 'mmef', 'mobi', 'mot-', 'moto', 'mwbp', 'nec-', 'newt', 'noki', 'palm', 'pana', 'pant', 'phil', 'play', 'port', 'prox', 'qwap', 'sage', 'sams', 'sany', 'sch-', 'sec-', 'send', 'seri', 'sgh-', 'shar', 'sie-', 'siem', 'smal', 'smar', 'sony', 'sph-', 'symb', 't-mo', 'teli', 'tim-', 'tosh', 'tsm-', 'upg1', 'upsi', 'vk-v', 'voda', 'wap-', 'wapa', 'wapi', 'wapp', 'wapr', 'webc', 'winw', 'winw', 'xda ', 'xda-' ); // from http://svn.wp-plugins.org/wordpress-mobile-pack/trunk/plugins/wpmp_switcher/lite_detection.php
	}
	$wp_cache_mobile_browsers = apply_filters( 'cached_mobile_browsers', $wp_cache_mobile_browsers ); // Allow mobile plugins access to modify the mobile UA list
	$wp_cache_mobile_prefixes = apply_filters( 'cached_mobile_prefixes', $wp_cache_mobile_prefixes ); // Allow mobile plugins access to modify the mobile UA prefix list
	if ( function_exists( 'do_cacheaction' ) ) {
		$wp_cache_mobile_browsers = do_cacheaction( 'wp_super_cache_mobile_browsers', $wp_cache_mobile_browsers );
		$wp_cache_mobile_prefixes = do_cacheaction( 'wp_super_cache_mobile_prefixes', $wp_cache_mobile_prefixes );
	}
	$mobile_groups = apply_filters( 'cached_mobile_groups', array() ); // Group mobile user agents by capabilities. Lump them all together by default
	// mobile_groups = array( 'apple' => array( 'ipod', 'iphone' ), 'nokia' => array( 'nokia5800', 'symbianos' ) );

	$wp_cache_mobile_browsers = implode( ', ', $wp_cache_mobile_browsers );
	$wp_cache_mobile_prefixes = implode( ', ', $wp_cache_mobile_prefixes );

	if ( false == apply_filters( 'wp_super_cache_error_checking', true ) )
		return false;

	if ( function_exists( 'get_supercache_dir' ) )
		$supercachedir = get_supercache_dir();
	if( get_option( 'gzipcompression' ) == 1 )
		update_option( 'gzipcompression', 0 );
	if( !isset( $cache_rebuild_files ) )
		$cache_rebuild_files = 0;

	$valid_nonce = isset($_REQUEST['_wpnonce']) ? wp_verify_nonce($_REQUEST['_wpnonce'], 'wp-cache') : false;
	/* http://www.netlobo.com/div_hiding.html */
	?>
<script type='text/javascript'>
<!--
function toggleLayer( whichLayer ) {
  var elem, vis;
  if( document.getElementById ) // this is the way the standards work
    elem = document.getElementById( whichLayer );
  else if( document.all ) // this is the way old msie versions work
      elem = document.all[whichLayer];
  else if( document.layers ) // this is the way nn4 works
    elem = document.layers[whichLayer];
  vis = elem.style;
  // if the style.display value is blank we try to figure it out here
  if(vis.display==''&&elem.offsetWidth!=undefined&&elem.offsetHeight!=undefined)
    vis.display = (elem.offsetWidth!=0&&elem.offsetHeight!=0)?'block':'none';
  vis.display = (vis.display==''||vis.display=='block')?'none':'block';
}
// -->
//Clicking header opens fieldset options
jQuery(document).ready(function(){
	jQuery("fieldset h3").css("cursor","pointer").click(function(){
		jQuery(this).parent("fieldset").find("p,form,ul,blockquote").toggle("slow");
	});
});
</script>

<style type='text/css'>
#nav h2 {
	border-bottom: 1px solid #ccc;
	padding-bottom: 0;
	height: 2em;
}
table.wpsc-settings-table {
	clear: both;
}
</style>
<?php
	echo '<a name="top"></a>';
	echo '<div class="wrap">';
	echo '<h2>' . __( 'WP Super Cache Settings', 'wp-super-cache' ) . '</h2>';

	// set a default
	if ( $cache_enabled == false && isset( $wp_cache_mod_rewrite ) == false ) {
		$wp_cache_mod_rewrite = 0;
	} elseif ( !isset( $wp_cache_mod_rewrite ) && $cache_enabled && $super_cache_enabled ) {
		$wp_cache_mod_rewrite = 1;
	}

	if ( ! isset( $_GET[ 'tab' ] ) && $cache_enabled && ( $wp_cache_mod_rewrite || $super_cache_enabled == false ) ) {
		$_GET[ 'tab' ] = 'settings';
		echo '<div class="notice notice-info is-dismissible"><p>' .  __( 'Notice: <em>Expert mode caching enabled</em>. Showing Advanced Settings Page by default.', 'wp-super-cache' ) . '</p></div>';
	}
	if ( ! isset( $_GET[ 'tab' ] ) ) {
		$_GET[ 'tab' ] = 'easy';
	}

	wpsc_admin_tabs();

	if ( isset( $wp_super_cache_front_page_check ) && $wp_super_cache_front_page_check == 1 && !wp_next_scheduled( 'wp_cache_check_site_hook' ) ) {
		wp_schedule_single_event( time() + 360 , 'wp_cache_check_site_hook' );
		wp_cache_debug( 'scheduled wp_cache_check_site_hook for 360 seconds time.', 2 );
	}

	if(isset($_REQUEST['wp_restore_config']) && $valid_nonce) {
		unlink($wp_cache_config_file);
		echo '<strong>' . __( 'Configuration file changed, some values might be wrong. Load the page again from the "Settings" menu to reset them.', 'wp-super-cache' ) . '</strong>';
	}

	if ( substr( get_option( 'permalink_structure' ), -1 ) == '/' ) {
		wp_cache_replace_line('^ *\$wp_cache_slash_check', "\$wp_cache_slash_check = 1;", $wp_cache_config_file);
	} else {
		wp_cache_replace_line('^ *\$wp_cache_slash_check', "\$wp_cache_slash_check = 0;", $wp_cache_config_file);
	}
	$home_path = parse_url( site_url() );
	$home_path = trailingslashit( array_key_exists( 'path', $home_path ) ? $home_path[ 'path' ] : '' );
	if (! isset( $wp_cache_home_path ) ) {
		$wp_cache_home_path = '/';
		wp_cache_setting( 'wp_cache_home_path', '/' );
	}
	if ( "$home_path" != "$wp_cache_home_path" )
		wp_cache_setting( 'wp_cache_home_path', $home_path );


	if( $wp_cache_mobile_enabled == 1 ) {
		update_cached_mobile_ua_list( $wp_cache_mobile_browsers, $wp_cache_mobile_prefixes, $mobile_groups );
	}

	?> <table class="wpsc-settings-table"><td valign='top'><?php
	switch( $_GET[ 'tab' ] ) {
		case "cdn":
		scossdl_off_options();
		break;
		case "tester":
		case "contents":
		echo '<a name="test"></a>';
		wp_cache_files();
		break;
		case "preload":
		if ( !$cache_enabled )
			wp_die( __( 'Caching must be enabled to use this feature', 'wp-super-cache' ) );
		echo '<a name="preload"></a>';
		if ( $super_cache_enabled == true && false == defined( 'DISABLESUPERCACHEPRELOADING' ) ) {
			global $wp_cache_preload_interval, $wp_cache_preload_on, $wp_cache_preload_taxonomies, $wp_cache_preload_email_me, $wp_cache_preload_email_volume, $wp_cache_preload_posts, $wpdb;
			$posts_count = wp_count_posts();
			$count = $posts_count->publish;
			if ( $count > 1000 ) {
				$min_refresh_interval = 720;
			} else {
				$min_refresh_interval = 30;
			}
			$return = wpsc_preload_settings( $min_refresh_interval );
			if ( empty( $return ) == false ) {
				foreach( $return as $message ) {
					echo $message;
				}
			}
			echo '<p>' . __( 'This will cache every published post and page on your site. It will create supercache static files so unknown visitors (including bots) will hit a cached page. This will probably help your Google ranking as they are using speed as a metric when judging websites now.', 'wp-super-cache' ) . '</p>';
			echo '<p>' . __( 'Preloading creates lots of files however. Caching is done from the newest post to the oldest so please consider only caching the newest if you have lots (10,000+) of posts. This is especially important on shared hosting.', 'wp-super-cache' ) . '</p>';
			echo '<p>' . __( 'In &#8217;Preload Mode&#8217; regular garbage collection will be disabled so that old cache files are not deleted. This is a recommended setting when the cache is preloaded.', 'wp-super-cache' ) . '</p>';
			echo '<form name="cache_filler" action="" method="POST">';
			echo '<input type="hidden" name="action" value="preload" />';
			echo '<input type="hidden" name="page" value="wpsupercache" />';
			echo '<p>' . sprintf( __( 'Refresh preloaded cache files every %s minutes. (0 to disable, minimum %d minutes.)', 'wp-super-cache' ), "<input type='text' size=4 name='wp_cache_preload_interval' value='" . (int)$wp_cache_preload_interval . "' />", $min_refresh_interval ) . '</p>';
			if ( $count > 100 ) {
				$step = (int)( $count / 10 );

				$select = "<select name='wp_cache_preload_posts' size=1>";
				$select .= "<option value='all' ";
				if ( !isset( $wp_cache_preload_posts ) || $wp_cache_preload_posts == 'all' ) {
					$checked = 'selectect=1 ';
					$best = 'all';
				} else {
					$checked = ' ';
					$best = $wp_cache_preload_posts;
				}
				$select .= "{$checked}>" . __( 'all', 'wp-super-cache' ) . "</option>";

				for( $c = $step; $c < $count; $c += $step ) {
					$checked = ' ';
					if ( $best == $c )
						$checked = 'selected=1 ';
					$select .= "<option value='$c'{$checked}>$c</option>";
				}
				$checked = ' ';
				if ( $best == $count )
					$checked = 'selected=1 ';
				$select .= "<option value='$count'{$checked}>$count</option>";
				$select .= "</select>";
				echo '<p>' . sprintf( __( 'Preload %s posts.', 'wp-super-cache' ), $select ) . '</p>';
			} else {
				echo '<input type="hidden" name="wp_cache_preload_posts" value="' . $count . '" />';
			}

			echo '<input type="checkbox" name="wp_cache_preload_on" value="1" ';
			echo $wp_cache_preload_on == 1 ? 'checked=1' : '';
			echo ' /> ' . __( 'Preload mode (garbage collection disabled. Recommended.)', 'wp-super-cache' ) . '<br />';
			echo '<input type="checkbox" name="wp_cache_preload_taxonomies" value="1" ';
			echo $wp_cache_preload_taxonomies == 1 ? 'checked=1' : '';
			echo ' /> ' . __( 'Preload tags, categories and other taxonomies.', 'wp-super-cache' ) . '<br />';
			echo __( 'Send me status emails when files are refreshed.', 'wp-super-cache' ) . '<br />';
			if ( !isset( $wp_cache_preload_email_volume ) )
				$wp_cache_preload_email_volume = 'none';
			echo '<select type="select" name="wp_cache_preload_email_volume">';
			echo '<option value="none" '. selected( 'none', $wp_cache_preload_email_volume ) . '>'.  __( 'No Emails', 'wp-super-cache' ) . '</option>';
			echo '<option value="many" '. selected( 'many', $wp_cache_preload_email_volume ) . '>'.  __( 'Many emails, 2 emails per 100 posts.', 'wp-super-cache' ) . '</option>';
			echo '<option value="medium" '. selected( 'medium', $wp_cache_preload_email_volume ) . '>'.  __( 'Medium, 1 email per 100 posts.', 'wp-super-cache' ) . '</option>';
			echo '<option value="less" '. selected( 'less', $wp_cache_preload_email_volume ) . '>'.  __( 'Less emails, 1 at the start and 1 at the end of preloading all posts.', 'wp-super-cache' ) . '</option>';
			echo "</select>";

			$currently_preloading = false;

			next_preload_message( 'wp_cache_preload_hook', __( 'Refresh of cache in %d hours %d minutes and %d seconds.', 'wp-super-cache' ), 60 );
			next_preload_message( 'wp_cache_full_preload_hook', __( 'Full refresh of cache in %d hours %d minutes and %d seconds.', 'wp-super-cache' ) );

			if ( wp_next_scheduled( 'wp_cache_preload_hook' ) || wp_next_scheduled( 'wp_cache_full_preload_hook' ) ) { 
				$currently_preloading = true;
			}
			$preload_counter = get_option( 'preload_cache_counter' );
			if ( isset( $preload_counter[ 'first' ] ) ) // converted from int to array
				update_option( 'preload_cache_counter', array( 'c' => $preload_counter[ 'c' ], 't' => time() ) );
			if ( is_array( $preload_counter ) && $preload_counter[ 'c' ] > 0 ) {
				echo '<p><strong>' . sprintf( __( 'Currently caching from post %d to %d.', 'wp-super-cache' ), ( $preload_counter[ 'c' ] - 100 ), $preload_counter[ 'c' ] ) . '</strong></p>';
				$currently_preloading = true;
				if ( @file_exists( $cache_path . "preload_permalink.txt" ) ) {
					$url = file_get_contents( $cache_path . "preload_permalink.txt" );
					echo "<p>" . sprintf( __( "<strong>Page last cached:</strong> %s", 'wp-super-cache' ), $url ) . "</p>";
				}
			}
			echo '<div class="submit"><input class="button-primary" type="submit" name="preload" value="' . __( 'Update Settings', 'wp-super-cache' ) . '" />';
			echo '</div>';
			wp_nonce_field('wp-cache');
			echo '</form>';
			echo '<form name="do_preload" action="" method="POST">';
			echo '<input type="hidden" name="action" value="preload" />';
			echo '<input type="hidden" name="page" value="wpsupercache" />';
			echo '<div class="submit">';
			if ( false == $currently_preloading ) {
				echo '<input class="button-primary" type="submit" name="preload_now" value="' . __( 'Preload Cache Now', 'wp-super-cache' ) . '" />';
			} else {
				echo '<input class="button-primary" type="submit" name="preload_off" value="' . __( 'Cancel Cache Preload', 'wp-super-cache' ) . '" />';
			}
			echo '</div>';
			wp_nonce_field('wp-cache');
			echo '</form>';
		} else {
			echo '<div class="notice notice-warning"><p>' . __( 'Preloading of cache disabled. Please make sure simple or expert mode is enabled or talk to your host administrator.', 'wp-super-cache' ) . '</p></div>';
		}
		break;
		case 'plugins':
		wpsc_plugins_tab();
		break;
		case 'debug':
		wp_cache_debug_settings();
		break;
		case 'settings':
		if ( isset( $wp_cache_front_page_checks ) == false )
			$wp_cache_front_page_checks = true;
		echo '<form name="wp_manager" action="' . esc_url( add_query_arg( array( 'page' => 'wpsupercache', 'tab' => 'settings' ) ) ) . '" method="post">';
		wp_nonce_field('wp-cache');
		echo '<input type="hidden" name="action" value="scupdates" />';
		?><table class="form-table">
		<tr valign="top">
			<th scope="row"><label for="wp_cache_enabled"><?php _e( 'Caching', 'wp-super-cache' ); ?></label></th>
			<td>
				<fieldset>
				<legend class="hidden"><?php _e( 'Caching', 'wp-super-cache' ); ?></legend>
				<label><input type='checkbox' name='wp_cache_enabled' value='1' <?php if ( $cache_enabled == true ) { echo 'checked=checked'; } ?>> <?php _e( 'Enable Caching', 'wp-super-cache' ); ?><br />
				</fieldset>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="super_cache_enabled"><?php _e( 'Cache Delivery Method', 'wp-super-cache' ); ?></label></th>
			<td>
				<label><input type='radio' name='wp_cache_mod_rewrite' <?php if ( $wp_cache_mod_rewrite == 0 ) echo "checked"; ?> value='0'> <?php _e( '<acronym title="Use PHP to serve cached files">Simple</acronym>', 'wp-super-cache' ); echo " <em>(" . __( "Recommended", "wp-super-cache" ) . ")</em>"; ?></label><br />
				<label><input type='radio' name='wp_cache_mod_rewrite' <?php if ( $wp_cache_mod_rewrite == 1 ) echo "checked"; ?> value='1'> <?php _e( '<acronym title="Use mod_rewrite to serve cached files">Expert</acronym>', 'wp-super-cache' ); ?></label><br />
				<em><small class='description'><?php _e( 'Expert caching requires changes to important server files and may require manual intervention if enabled.', 'wp-super-cache' ); ?></small></em>
				</fieldset>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="wp_cache_status"><?php _e( 'Miscellaneous', 'wp-super-cache' ); ?></label></th>
			<td>
				<fieldset>
				<legend class="hidden">Miscellaneous</legend>
				<label><input type='checkbox' name='wp_cache_not_logged_in' <?php if ( $wp_cache_not_logged_in ) echo "checked"; ?> value='1'> <?php _e( 'Don&#8217;t cache pages for <acronym title="Logged in users and those that comment">known users</acronym>.', 'wp-super-cache' ); echo " <em>(" . __( "Recommended", "wp-super-cache" ) . ")</em>"; ?></label><br />
				<label><input type='checkbox' name='wp_cache_no_cache_for_get' <?php if( $wp_cache_no_cache_for_get ) echo "checked"; ?> value='1'> <?php _e( 'Don&#8217;t cache pages with GET parameters. (?x=y at the end of a url)', 'wp-super-cache' ); ?></label><br />
				<?php if ( false == defined( 'WPSC_DISABLE_COMPRESSION' ) ) { ?>
					<?php if ( false == function_exists( 'gzencode' ) ) { ?>
						<em><?php _e( 'Warning! Compression is disabled as gzencode() function was not found.', 'wp-super-cache' ); ?></em><br />
					<?php } else { ?>
						<label><input type='checkbox' name='cache_compression' <?php if( $cache_compression ) echo "checked"; ?> value='1'> <?php _e( 'Compress pages so they&#8217;re served more quickly to visitors.', 'wp-super-cache' ); echo " <em>(" . __( "Recommended", "wp-super-cache" ) . ")</em>"; ?></label><br />
						<em><?php _e( 'Compression is disabled by default because some hosts have problems with compressed files. Switching it on and off clears the cache.', 'wp-super-cache' ); ?></em><br />
					<?php }
				}
				?>
				<label><input type='checkbox' name='wpsc_save_headers' <?php if ( $wpsc_save_headers ) echo "checked"; ?> value='1' /> <?php _e( 'Cache HTTP headers with page content.', 'wp-super-cache' ); ?></label><br />
				<label><input type='checkbox' name='cache_rebuild_files' <?php if ( $cache_rebuild_files ) echo "checked"; ?> value='1'> <?php _e( 'Cache rebuild. Serve a supercache file to anonymous users while a new file is being generated.', 'wp-super-cache' ); echo " <em>(" . __( "Recommended", "wp-super-cache" ) . ")</em>"; ?></label><br />
				<?php
				$disable_304 = true;
				if ( 0 == $wp_cache_mod_rewrite )
					$disable_304 = false;
				if ( $disable_304 )
					echo "<strike>";
				?><label><input <?php if ( $disable_304 ) { echo "disabled"; } ?> type='checkbox' name='wp_supercache_304' <?php if( $wp_supercache_304 ) echo "checked"; ?> value='1'> <?php _e( '304 Not Modified browser caching. Indicate when a page has not been modified since it was last requested.', 'wp-super-cache' ); echo " <em>(" . __( "Recommended", "wp-super-cache" ) . ")</em>"; ?></label><br /><?php
				if ( $disable_304 ) {
					echo "</strike>";
					echo "<p><strong>" . __( 'Warning! 304 browser caching is only supported when mod_rewrite caching is not used.', 'wp-super-cache' ) . "</strong></p>";
				} else {
					?><em><?php _e( '304 support is disabled by default because some hosts have had problems with the headers used in the past.', 'wp-super-cache' ); ?></em><br /><?php
				}
				?>
				<label><input type='checkbox' name='wp_cache_make_known_anon' <?php if( $wp_cache_make_known_anon ) echo "checked"; ?> value='1'> <?php _e( 'Make known users anonymous so they&#8217;re served supercached static files.', 'wp-super-cache' ); ?></label><br />
				<label><input type='checkbox' name='wp_cache_hello_world' <?php if( $wp_cache_hello_world ) echo "checked"; ?> value='1'> <?php printf( __( 'Proudly tell the world your server is <a href="%s">Stephen Fry proof</a>! (places a message in your blog&#8217;s footer)', 'wp-super-cache' ), 'https://twitter.com/#!/HibbsLupusTrust/statuses/136429993059291136' ); ?></label><br />
				</legend>
				</fieldset>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="wp_cache_status"><?php _e( 'Advanced', 'wp-super-cache' ); ?></label></th>
			<td>
				<fieldset>
				<legend class="hidden">Advanced</legend>
				<label><input type='checkbox' name='wp_cache_mfunc_enabled' <?php if( $wp_cache_mfunc_enabled ) echo "checked"; ?> value='1' <?php if ( $wp_cache_mod_rewrite ) { echo "disabled='disabled'"; } ?>> <?php _e( 'Enable dynamic caching. (See <a href="http://wordpress.org/plugins/wp-super-cache/faq/">FAQ</a> or wp-super-cache/plugins/dynamic-cache-test.php for example code.)', 'wp-super-cache' ); ?></label><br />
				<label><input type='checkbox' name='wp_cache_mobile_enabled' <?php if( $wp_cache_mobile_enabled ) echo "checked"; ?> value='1'> <?php _e( 'Mobile device support. (External plugin or theme required. See the <a href="http://wordpress.org/plugins/wp-super-cache/faq/">FAQ</a> for further details.)', 'wp-super-cache' ); ?></label><br />
				<?php if ( $wp_cache_mobile_enabled ) {
					echo '<blockquote><h4>' . __( 'Mobile Browsers', 'wp-super-cache' ) . '</h4>' . esc_html( $wp_cache_mobile_browsers ) . "<br /><h4>" . __( 'Mobile Prefixes', 'wp-super-cache' ) . "</h4>" . esc_html( $wp_cache_mobile_prefixes ) . "<br /></blockquote>";
				} ?>
				<label><input type='checkbox' name='wp_cache_disable_utf8' <?php if( $wp_cache_disable_utf8 ) echo "checked"; ?> value='1'> <?php _e( 'Remove UTF8/blog charset support from .htaccess file. Only necessary if you see odd characters or punctuation looks incorrect. Requires rewrite rules update.', 'wp-super-cache' ); ?></label><br />
				<label><input type='checkbox' name='wp_cache_clear_on_post_edit' <?php if( $wp_cache_clear_on_post_edit ) echo "checked"; ?> value='1'> <?php _e( 'Clear all cache files when a post or page is published or updated.', 'wp-super-cache' ); ?></label><br />
				<label><input type='checkbox' name='wp_cache_front_page_checks' <?php if( $wp_cache_front_page_checks ) echo "checked"; ?> value='1'> <?php _e( 'Extra homepage checks. (Very occasionally stops homepage caching)', 'wp-super-cache' ); ?></label><?php echo " <em>(" . __( "Recommended", "wp-super-cache" ) . ")</em>"; ?><br />
				<label><input type='checkbox' name='wp_cache_refresh_single_only' <?php if( $wp_cache_refresh_single_only ) echo "checked"; ?> value='1'> <?php _e( 'Only refresh current page when comments made.', 'wp-super-cache' ); ?></label><br />
				<label><input type='checkbox' name='wp_supercache_cache_list' <?php if( $wp_supercache_cache_list ) echo "checked"; ?> value='1'> <?php _e( 'List the newest cached pages on this page.', 'wp-super-cache' ); ?></label><br />
			<?php if( false == defined( 'WPSC_DISABLE_LOCKING' ) ) { ?>
				<label><input type='checkbox' name='wp_cache_mutex_disabled' <?php if( !$wp_cache_mutex_disabled ) echo "checked"; ?> value='0'> <?php _e( 'Coarse file locking. You do not need this as it will slow down your website.', 'wp-super-cache' ); ?></label><br />
			<?php } ?>
				<label><input type='checkbox' name='wp_super_cache_late_init' <?php if( $wp_super_cache_late_init ) echo "checked"; ?> value='1'> <?php _e( 'Late init. Display cached files after WordPress has loaded.', 'wp-super-cache' ); ?></label><br />
			<?php if ( $_wp_using_ext_object_cache ) {
				?><label><input type='checkbox' name='wp_cache_object_cache' <?php if( $wp_cache_object_cache ) echo "checked"; ?> value='1'> <?php echo __( 'Use object cache to store cached files.', 'wp-super-cache' ) . ' ' . __( '(Experimental)', 'wp-super-cache' ); ?></label><?php
			}?>
			<?php printf( __( '<strong>DO NOT CACHE PAGE</strong> secret key: <a href="%s">%s</a>', 'wp-super-cache' ), trailingslashit( get_bloginfo( 'url' ) ) . "?donotcachepage={$cache_page_secret}", $cache_page_secret ); ?>
				</fieldset>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="wp_cache_location"><?php _e( 'Cache Location', 'wp-super-cache' ); ?></label></th>
			<td>
				<fieldset>
					<legend class="hidden">Cache Location</legend>
					<input type='text' size=80 name='wp_cache_location' value='<?php echo esc_attr( $cache_path ); ?>' />
					<p><?php printf( __( 'Change the location of your cache files. The default is WP_CONTENT_DIR . /cache/ which translates to %s.', 'wp-super-cache' ), WP_CONTENT_DIR . '/cache/' ); ?></p>
					<ol><li><?php _e( 'You must give the full path to the directory.', 'wp-super-cache' ); ?></li>
						<li><?php _e( 'If the directory does not exist, it will be created. Please make sure your web server user has write access to the parent directory. The parent directory must exist.', 'wp-super-cache' ); ?></li>
						<li><?php _e( 'If the new cache directory does not exist, it will be created and the contents of the old cache directory will be moved there. Otherwise, the old cache directory will be left where it is.', 'wp-super-cache' ); ?></li>
						<li><?php _e( 'Submit a blank entry to set it to the default directory, WP_CONTENT_DIR . /cache/.', 'wp-super-cache' ); ?></li>
						<?php if ( get_site_option( 'wp_super_cache_index_detected' ) && strlen( $cache_path ) > strlen( ABSPATH ) && ABSPATH == substr( $cache_path, 0, strlen( ABSPATH ) ) ) {
							$msg = __( 'The plugin detected a bare directory index in your cache directory, which would let visitors see your cache files directly and might expose private posts.', 'wp-super-cache' );
							if ( $super_cache_enabled && $wp_cache_mod_rewrite == 1 ) {
								$msg .= ' ' . __( 'You are using expert mode to serve cache files so the plugin has added <q>Options -Indexes</q> to the .htaccess file in the cache directory to disable indexes. However, if that does not work, you should contact your system administrator or support and ask for them to be disabled, or use simple mode and move the cache outside of the web root.', 'wp-super-cache' );
							} else {
								$msg .= ' <strong>' . sprintf( __( 'index.html files have been added in key directories, but unless directory indexes are disabled, it is probably better to store the cache files outside of the web root of %s', 'wp-super-cache' ), ABSPATH ) . '</strong>';
							}
							echo "<li>$msg</li>";
						} ?>


						<?php if ( $super_cache_enabled && $wp_cache_mod_rewrite == 1 ) { ?>
						<li><?php printf( __( 'Since you are using mod_rewrite to serve cache files, you must choose a directory in your web root which is <q>%s</q> and update the mod_rewrite rules in the .htaccess file.', 'wp-super-cache' ), ABSPATH ); ?></li>
						<?php } ?>
					</ol>
					<p><?php _e( '', 'wp-super-cache' ); ?></p>
				</fieldset>
			</td>
		</tr>
		</table>
		<h3><?php _e( 'Note:', 'wp-super-cache' ); ?></h3>
		<ol>
		<li><?php _e( 'Uninstall this plugin on the plugins page. It will automatically clean up after itself. If manual intervention is required, then simple instructions are provided.', 'wp-super-cache' ); ?></li>
		<li><?php printf( __( 'If uninstalling this plugin, make sure the directory <em>%s</em> is writeable by the webserver so the files <em>advanced-cache.php</em> and <em>cache-config.php</em> can be deleted automatically. (Making sure those files are writeable is probably a good idea!)', 'wp-super-cache' ), WP_CONTENT_DIR ); ?></li>
		<li><?php printf( __( 'Please see the <a href="%1$s/wp-super-cache/readme.txt">readme.txt</a> for instructions on uninstalling this script. Look for the heading, "How to uninstall WP Super Cache".', 'wp-super-cache' ), plugins_url() ); ?></li><?php
		echo "<li><em>" . sprintf( __( 'Need help? Check the <a href="%1$s">Super Cache readme file</a>. It includes installation documentation, a FAQ and Troubleshooting tips. The <a href="%2$s">support forum</a> is also available. Your question may already have been answered.', 'wp-super-cache' ), 'http://wordpress.org/plugins/wp-super-cache/', 'http://wordpress.org/tags/wp-super-cache?forum_id=10' ) . "</em></li>";
		echo "</ol>";

		echo "<div class='submit'><input class='button-primary' type='submit' " . SUBMITDISABLED . " value='" . __( 'Update Status', 'wp-super-cache' ) . "' /></div>";
		wp_nonce_field('wp-cache');
		?> </form> <?php
		wsc_mod_rewrite();

		wp_cache_edit_max_time();

		echo '<a name="files"></a><fieldset class="options"><h3>' . __( 'Accepted Filenames &amp; Rejected URIs', 'wp-super-cache' ) . '</h3>';
		wp_cache_edit_rejected_pages();
		echo "\n";
		wp_cache_edit_rejected();
		echo "\n";
		wp_cache_edit_accepted();
		echo '</fieldset>';

		wp_cache_edit_rejected_ua();

		wp_lock_down();

		wp_cache_restore();

		break;
		case "easy":
		default:
			echo '<form name="wp_manager" action="" method="post">';
			echo '<input type="hidden" name="action" value="easysetup" />';
			wp_nonce_field('wp-cache');
			?><table class="form-table">
				<tr valign="top">
				<th scope="row"><label for="wp_cache_status"><?php _e( 'Caching', 'wp-super-cache' ); ?></label></th>
				<td>
				<fieldset>
				<label><input type='radio' name='wp_cache_easy_on' value='1' <?php if ( $cache_enabled == true ) { echo 'checked=checked'; } ?>> <?php _e( 'Caching On', 'wp-super-cache' ); echo " <em>(" . __( "Recommended", "wp-super-cache" ) . ")</em>"; ?></label><br />
				<label><input type='radio' name='wp_cache_easy_on' value='0' <?php if ( $cache_enabled == false ) { echo 'checked=checked'; } ?>> <?php _e( 'Caching Off', 'wp-super-cache' ); ?></label><br />
				</fieldset>
				</td>
				</tr>
				</table>
			<?php
			if ( $cache_enabled && !$wp_cache_mod_rewrite ) {
				$scrules = trim( implode( "\n", extract_from_markers( trailingslashit( get_home_path() ) . '.htaccess', 'WPSuperCache' ) ) );
				if ( $scrules != '' ) {
					echo "<p><strong>" . __( 'Notice: Simple caching enabled but Supercache mod_rewrite rules from expert mode detected. Cached files will be served using those rules. If your site is working ok, please ignore this message. Otherwise, you can edit the .htaccess file in the root of your install and remove the SuperCache rules.', 'wp-super-cache' ) . '</strong></p>';
				}
			}
			echo "<div class='submit'><input class='button-primary' type='submit' " . SUBMITDISABLED . " value='" . __( 'Update Status', 'wp-super-cache' ) . "' /></div></form>";
			if ( $cache_enabled ) {
				echo "<h3>" . __( 'Cache Tester', 'wp-super-cache' ) . "</h3>";
				echo '<p>' . __( 'Test your cached website by clicking the test button below.', 'wp-super-cache' ) . '</p>';
				if ( array_key_exists('action', $_POST) && $_POST[ 'action' ] == 'test' && $valid_nonce ) {
					$url = trailingslashit( get_bloginfo( 'url' ) );
					if ( isset( $_POST[ 'httponly' ] ) )
						$url = str_replace( 'https://', 'http://', $url );
					$test_messages = array( __( 'Fetching %s to prime cache: ', 'wp-super-cache' ), __( 'Fetching first copy of %s: ', 'wp-super-cache' ), __( 'Fetching second copy of %s: ', 'wp-super-cache' ) );
					$c = 0;
					$cache_test_error = false;
					$page = array();
					foreach( $test_messages as $message ) {
						echo "<p>" . sprintf( $message, $url );
						$page[ $c ] = wp_remote_get( $url, array('timeout' => 60, 'blocking' => true ) );
						if ( !is_wp_error( $page[ $c ] ) ) {
							$fp = fopen( $cache_path . $c . ".html", "w" );
							fwrite( $fp, $page[ $c ][ 'body' ] );
							fclose( $fp );
							echo '<span style="color: #0a0; font-weight: bold;">' . __( 'OK', 'wp-super-cache' ) . "</span> (<a href='" . WP_CONTENT_URL . "/cache/" . $c . ".html'>" . $c . ".html</a>)</p>";
							sleep( 1 );
						} else {
							$cache_test_error = true;
							echo '<span style="color: #a00; font-weight: bold;">' . __( 'FAILED', 'wp-super-cache' ) . "</span></p>";
							$errors = '';
							$messages = '';
							foreach ( $page[ $c ]->get_error_codes() as $code ) {
								$severity = $page[ $c ]->get_error_data( $code );
								foreach ( $page[ $c ]->get_error_messages( $code ) as $err ) {
									$errors .= $severity . ': ' . $err . "<br />\n";
								}
							}
							if ( '' != $errors )
								echo "<p>" . sprintf( __( '<strong>Errors:</strong> %s', 'wp-super-cache' ), $errors ) . "</p>";
						}
						$c ++;
					}

					if ( false == $cache_test_error ) {
						echo '<ul><li>' . sprintf( __( 'Page %d: %d (%s)', 'wp-super-cache' ), 1, $page[ 1 ][ 'response' ][ 'code' ], $page[ 1 ][ 'response' ][ 'message' ] ) . '</li>';
						echo '<li>' . sprintf( __( 'Page %d: %d (%s)', 'wp-super-cache' ), 2, $page[ 2 ][ 'response' ][ 'code' ], $page[ 2 ][ 'response' ][ 'message' ] ) . '</li></ul>';
					}

					if ( false == $cache_test_error && preg_match( '/(Cached page generated by WP-Super-Cache on) ([0-9]*-[0-9]*-[0-9]* [0-9]*:[0-9]*:[0-9]*)/', $page[ 1 ][ 'body' ], $matches1 ) &&
							preg_match( '/(Cached page generated by WP-Super-Cache on) ([0-9]*-[0-9]*-[0-9]* [0-9]*:[0-9]*:[0-9]*)/', $page[ 2 ][ 'body' ], $matches2 ) && $matches1[2] == $matches2[2] ) {
						echo '<p>' . sprintf( __( 'Page 1: %s', 'wp-super-cache' ), $matches1[ 2 ] ) . '</p>';
						echo '<p>' . sprintf( __( 'Page 2: %s', 'wp-super-cache' ), $matches2[ 2 ] ) . '</p>';
						echo '<p><span style="color: #0a0; font-weight: bold;">' . __( 'The timestamps on both pages match!', 'wp-super-cache' ) . '</span></p>';
					} else {
						echo '<p><strong>' . __( 'The pages do not match! Timestamps differ or were not found!', 'wp-super-cache' ) . '</strong></p>';
						echo '<p>' . __( 'Things you can do:', 'wp-super-cache' ) . '</p>';
						echo '<ol><li>' . __( 'Load your homepage in a logged out browser, check the timestamp at the end of the html source. Load the page again and compare the timestamp. Caching is working if the timestamps match.', 'wp-super-cache' ) . '</li>';
						echo '<li>' . __( 'Enable logging on the Debug page here. That should help you track down the problem.', 'wp-super-cache' ) . '</li>';
						echo '<li>' . __( 'You should check Page 1 and Page 2 above for errors. Your local server configuration may not allow your website to access itself.', 'wp-super-cache' ) . '</li>';
						echo "</ol>";
					}
				}
				echo '<form name="cache_tester" action="" method="post">';
				echo '<input type="hidden" name="action" value="test" />';
				if ( isset( $_SERVER['HTTPS' ] ) && 'on' == strtolower( $_SERVER['HTTPS' ] ) )
					echo "<input type='checkbox' name='httponly' checked='checked' value='1' /> " . __( 'Send non-secure (non https) request for homepage', 'wp-super-cache' );
                if ( isset( $wp_super_cache_comments ) && $wp_super_cache_comments == 0 ) {
                    echo "<p>" . __( '<strong>Warning!</strong> Cache comments are currently disabled. Please go to the Debug page and enable Cache Status Messages there. You should clear the cache before testing.', 'wp-super-cache' ) . "</p>";
                    echo '<div class="submit"><input disabled style="color: #aaa" class="button-secondary" type="submit" name="test" value="' . __( 'Test Cache', 'wp-super-cache' ) . '" /></div>';
                } else {
                    echo '<div class="submit"><input class="button-secondary" type="submit" name="test" value="' . __( 'Test Cache', 'wp-super-cache' ) . '" /></div>';
                }
				wp_nonce_field('wp-cache');
				echo '</form>';
			}
			echo "<h3>" . __( "Delete Cached Pages", 'wp-super-cache' ) . "</h3>";
			echo "<p>" . __( "Cached pages are stored on your server as html and PHP files. If you need to delete them, use the button below.", 'wp-super-cache' ) . "</p>";
			echo '<form name="wp_cache_content_delete" action="?page=wpsupercache&tab=contents" method="post">';
			echo '<input type="hidden" name="wp_delete_cache" />';
			echo '<div class="submit"><input id="deletepost" class="button-secondary" type="submit" ' . SUBMITDISABLED . 'value="' . __( 'Delete Cache', 'wp-super-cache' ) . ' " /></div>';
			wp_nonce_field('wp-cache');
			echo "</form>\n";

			if ( ( defined( 'VHOST' ) || defined( 'SUBDOMAIN_INSTALL' ) || defined( 'SUNRISE' ) || ( defined( 'WP_ALLOW_MULTISITE' ) && constant( 'WP_ALLOW_MULTISITE' ) == true  ) ) && wpsupercache_site_admin() ) {
				echo '<form name="wp_cache_content_delete" action="#listfiles" method="post">';
				echo '<input type="hidden" name="wp_delete_all_cache" />';
				echo '<div class="submit"><input id="deleteallpost" class="button-secondary" type="submit" ' . SUBMITDISABLED . 'value="' . __( 'Delete Cache On All Blogs', 'wp-super-cache' ) . '" /></div>';
				wp_nonce_field('wp-cache');
				echo "</form><br />\n";
			}
			?>
			<h3 class="clear"><?php _e( 'Recommended Links and Plugins', 'wp-super-cache' ); ?></h3>
			<p><?php _e( 'Caching is only one part of making a website faster. Here are some other plugins that will help:', 'wp-super-cache' ); ?></p>
			<ul style="list-style: square; margin-left: 2em;">
			<li><?php printf( __( '<a href="%s">Jetpack</a> provides everything you need to build a successful WordPress website including an image/photo CDN (free) and a video hosting service (paid).', 'wp-super-cache' ), 'https://jetpack.com/redirect/?source=jitm-wpsc-recommended' ); ?></li>
			<li><?php printf( __( '<a href="%s">Yahoo! Yslow</a> is an extension for the Firefox add-on Firebug. It analyzes web pages and suggests ways to improve their performance based on a set of rules for high performance web pages. Also try the performance tools online at <a href="%s">GTMetrix</a>.', 'wp-super-cache' ), 'http://developer.yahoo.com/yslow/', 'http://gtmetrix.com/' ); ?></li>
			<li><?php printf( __( '<a href="%s">Use Google Libraries</a> allows you to load some commonly used Javascript libraries from Google webservers. Ironically, it may reduce your Yslow score.', 'wp-super-cache' ), 'http://wordpress.org/plugins/use-google-libraries/' ); ?></li>
			<li><?php printf( __( '<strong>Advanced users only:</strong> Install an object cache. Choose from <a href="%s">Memcached</a>, <a href="%s">XCache</a>, <a href="%s">eAcccelerator</a> and others.', 'wp-super-cache' ), 'http://wordpress.org/plugins/memcached/', 'http://neosmart.net/dl.php?id=12', 'http://neosmart.net/dl.php?id=13' ); ?></li>
			<li><?php printf( __( '<a href="%s">Cron View</a> is a useful plugin to use when trying to debug garbage collection and preload problems.', 'wp-super-cache' ), 'http://wordpress.org/plugins/cron-view/' ); ?></li>
			</ul>

			<?php
		break;
	}

	?>
	</fieldset>
	</td><td valign='top' style='width: 300px'>
	<div style='background: #ffc; border: 1px solid #333; margin: 2px; padding: 3px 15px'>
	<h3><?php _e( 'More Site Speed Tools', 'wp-super-cache' ); ?></h3>
	<ul style="list-style: square; margin-left: 2em;">
	<li><a href="https://jetpack.com/redirect/?source=jitm-wpsc-generic"><?php _e( 'Speed up images and photos (free)', 'wp-super-cache' ); ?></a></li>
	<li><a href="https://jetpack.com/redirect/?source=jitm-wpsc-premium"><?php _e( 'Fast video hosting (paid)', 'wp-super-cache' ); ?></a></li>
	</ul>
	<h3><?php _e( 'Need Help?', 'wp-super-cache' ); ?></h3>
	<ol>
	<li><?php printf( __( 'Use the <a href="%1$s">Debug tab</a> for diagnostics.', 'wp-super-cache' ), admin_url( 'options-general.php?page=wpsupercache&tab=debug' ) ); ?></li>
	<li><?php printf( __( 'Check out the <a href="%1$s">support forum</a> and <a href="%2$s">FAQ</a>.', 'wp-super-cache' ), 'https://wordpress.org/support/plugin/wp-super-cache', 'http://wordpress.org/plugins/wp-super-cache/#faq' ); ?></li>
	<li><?php printf( __( 'Visit the <a href="%1$s">plugin homepage</a>.', 'wp-super-cache' ), 'http://wordpress.org/plugins/wp-super-cache/' ); ?></li>
	<li><?php printf( __( 'Try out the <a href="%1$s">development version</a> for the latest fixes (<a href="%2$s">changelog</a>).', 'wp-super-cache' ), 'https://odd.blog/y/2o', 'https://plugins.trac.wordpress.org/log/wp-super-cache/' ); ?></li>
	</ol>
	<h3><?php _e( 'Rate This Plugin', 'wp-super-cache' ); ?></h3>
	<p><?php printf( __( 'Please <a href="%s">rate us</a> and give feedback.', 'wp-super-cache' ), 'https://wordpress.org/support/plugin/wp-super-cache/reviews?rate=5#new-post' ); ?></p>

	<?php
	if ( isset( $wp_supercache_cache_list ) && $wp_supercache_cache_list ) {
		$start_date = get_option( 'wpsupercache_start' );
		if ( !$start_date ) {
			$start_date = time();
		}
		?>
		<p><?php printf( __( 'Cached pages since %1$s : <strong>%2$s</strong>', 'wp-super-cache' ), date( 'M j, Y', $start_date ), number_format( get_option( 'wpsupercache_count' ) ) ); ?></p>
		<p><?php _e( 'Newest Cached Pages:', 'wp-super-cache' ); ?><ol>
		<?php
		foreach( array_reverse( (array)get_option( 'supercache_last_cached' ) ) as $url ) {
			$since = time() - strtotime( $url[ 'date' ] );
			echo "<li><a title='" . sprintf( __( 'Cached %s seconds ago', 'wp-super-cache' ), $since ) . "' href='" . site_url( $url[ 'url' ] ) . "'>" . substr( $url[ 'url' ], 0, 20 ) . "</a></li>\n";
		}
		?></ol>
		<small><?php _e( '(may not always be accurate on busy sites)', 'wp-super-cache' ); ?></small>
		</p><?php
	} elseif ( false == get_option( 'wpsupercache_start' ) ) {
			update_option( 'wpsupercache_start', time() );
			update_option( 'wpsupercache_count', 0 );
	}
	?>
	</div>
	</td></table>

	<?php

	echo "</div>\n";
}

function wpsc_plugins_tab() {
	echo '<p>' . __( 'Cache plugins are PHP scripts that live in a plugins folder inside the wp-super-cache folder. They are loaded when Supercache loads, much sooner than regular WordPress plugins.', 'wp-super-cache' ) . '</p>';
	echo '<p>' . __( 'This is strictly an advanced feature only and knowledge of both PHP and WordPress actions is required to create them.', 'wp-super-cache' ) . '</p>';
	echo '<p>' . __( '<strong>Warning</strong>! Due to the way WordPress upgrades plugins, the plugins you upload to wp-super-cache/plugins/ will be deleted when you upgrade WP Super Cache. You can avoid this by loading the plugins from elsewhere. Set <strong>$wp_cache_plugins_dir</strong> to the new location in wp-config.php and WP Super Cache will look there instead.<br />More info available in the <a href="http://ocaoimh.ie/wp-super-cache-developers/">developer documentation</a>.', 'wp-super-cache' ) . '</p>';
	ob_start();
	if( defined( 'WP_CACHE' ) ) {
		if( function_exists( 'do_cacheaction' ) ) {
			do_cacheaction( 'cache_admin_page' );
		}
	}
	$out = ob_get_contents();
	ob_end_clean();
	if( SUBMITDISABLED == ' ' && $out != '' ) {
		echo '<h3>' . __( 'Available Plugins', 'wp-super-cache' ) . '</h3>';
		echo "<ol>";
		echo $out;
		echo "</ol>";
	}

}

function wpsc_admin_tabs( $current = 0 ) {
	global $wp_db_version;
	if ( $current == 0 ) {
		if ( isset( $_GET[ 'tab' ] ) ) {
			$current = $_GET[ 'tab' ];
		} else {
			$current = 'easy';
		}
	}
	$tabs = array( 'easy' => __( 'Easy', 'wp-super-cache' ), 'settings' => __( 'Advanced', 'wp-super-cache' ), 'cdn' => __( 'CDN', 'wp-super-cache' ), 'contents' => __( 'Contents', 'wp-super-cache' ), 'preload' => __( 'Preload', 'wp-super-cache' ), 'plugins' => __( 'Plugins', 'wp-super-cache' ), 'debug' => __( 'Debug', 'wp-super-cache' ) );
	$links = array();
	foreach( $tabs as $tab => $name ) {
		if ( $current == $tab ) {
			$links[] = "<a class='nav-tab nav-tab-active' href='?page=wpsupercache&tab=$tab'>$name</a>";
		} else {
			$links[] = "<a class='nav-tab' href='?page=wpsupercache&tab=$tab'>$name</a>";
		}
	}
	if ( $wp_db_version >= 15477 ) {
		echo '<div id="nav"><h2 class="themes-php">';
		echo implode( "", $links );
		echo '</div></h2>';
	} else {
		echo implode( " | ", $links );
	}
}

function wsc_mod_rewrite() {
	global $valid_nonce, $cache_path, $wp_cache_mod_rewrite;

	if ( defined( 'WPSC_DISABLE_HTACCESS_UPDATE' ) )
		return false;

	if ( !$wp_cache_mod_rewrite )
		return false;
	?>
	<a name="modrewrite"></a><fieldset class="options">
	<h3><?php _e( 'Mod Rewrite Rules', 'wp-super-cache' ); ?></h3>
	<p><?php _e( 'When Expert cache delivery is enabled a file called <em>.htaccess</em> is modified. It should probably be in the same directory as your wp-config.php. This file has special rules that serve the cached files very quickly to visitors without ever executing PHP. The .htaccess file can be updated automatically, but if that fails, the rules will be displayed here and it can be edited by you. You will not need to update the rules unless a warning shows here.', 'wp-super-cache' ); ?></p>

	<?php
	extract( wpsc_get_htaccess_info() );
	$dohtaccess = true;
	if( strpos( $wprules, 'wordpressuser' ) ) { // Need to clear out old mod_rewrite rules
		echo "<p><strong>" . __( 'Thank you for upgrading.', 'wp-super-cache' ) . "</strong> " . sprintf( __( 'The mod_rewrite rules changed since you last installed this plugin. Unfortunately, you must remove the old supercache rules before the new ones are updated. Refresh this page when you have edited your .htaccess file. If you wish to manually upgrade, change the following line: %1$s so it looks like this: %2$s The only changes are "HTTP_COOKIE" becomes "HTTP:Cookie" and "wordpressuser" becomes "wordpress". This is a WordPress 2.5 change but it&#8217;s backwards compatible with older versions if you&#8217;re brave enough to use them.', 'wp-super-cache' ), '<blockquote><code>RewriteCond %{HTTP_COOKIE} !^.*wordpressuser.*$</code></blockquote>', '<blockquote><code>RewriteCond %{HTTP:Cookie} !^.*wordpress.*$</code></blockquote>' ) . "</p>";
		echo "</fieldset></div>";
		return;
	}
	if ( $dohtaccess && !isset( $_POST[ 'updatehtaccess' ] ) ){
		if ( $scrules == '' ) {
			wpsc_update_htaccess_form( 0 ); // don't hide the update htaccess form
		} else {
			wpsc_update_htaccess_form();
		}
	} elseif ( $valid_nonce && isset( $_POST[ 'updatehtaccess' ] ) ) {
		echo "<div style='padding:0 8px;color:#4f8a10;background-color:#dff2bf;border:1px solid #4f8a10;'>";
		if ( add_mod_rewrite_rules() ) {
			echo "<h4>" . __( 'Mod Rewrite rules updated!', 'wp-super-cache' ) . "</h4>";
			echo "<p><strong>" . sprintf( __( '%s.htaccess has been updated with the necessary mod_rewrite rules. Please verify they are correct. They should look like this:', 'wp-super-cache' ), $home_path ) . "</strong></p>\n";
		} else {
			global $update_mod_rewrite_rules_error;
			echo "<h4>" . __( 'Mod Rewrite rules must be updated!', 'wp-super-cache' ) . "</h4>";
			echo "<p><strong>" . sprintf( __( 'The plugin could not update %1$s.htaccess file: %2$s. The new rules go above the regular WordPress rules as shown in the code below:', 'wp-super-cache' ), $home_path, $update_mod_rewrite_rules_error ) . "</strong></p>\n";
		}
		echo "<p><pre>" . esc_html( $rules ) . "</pre></p>\n</div>";
	} else {
		?>
		<p><?php printf( __( 'WP Super Cache mod rewrite rules were detected in your %s.htaccess file.<br /> Click the following link to see the lines added to that file. If you have upgraded the plugin, make sure these rules match.', 'wp-super-cache' ), $home_path ); ?></p>
		<?php
		if ( $rules != $scrules ) {
			?><p style='padding:0 8px;color:#9f6000;background-color:#feefb3;border:1px solid #9f6000;'><?php _e( 'A difference between the rules in your .htaccess file and the plugin rewrite rules has been found. This could be simple whitespace differences, but you should compare the rules in the file with those below as soon as possible. Click the &#8217;Update Mod_Rewrite Rules&#8217; button to update the rules.', 'wp-super-cache' ); ?></p><?php
		}
		?><a href="javascript:toggleLayer('rewriterules');" class="button"><?php _e( 'View Mod_Rewrite Rules', 'wp-super-cache' ); ?></a><?php
		wpsc_update_htaccess_form();
		echo "<div id='rewriterules' style='display: none;'>";
		if ( $rules != $scrules )
			echo '<div style="background: #fff; border: 1px solid #333; margin: 2px;">' . wp_text_diff( $scrules, $rules, array( 'title' => __( 'Rewrite Rules', 'wp-super-cache' ), 'title_left' => __( 'Current Rules', 'wp-super-cache' ), 'title_right' => __( 'New Rules', 'wp-super-cache' ) ) ) . "</div>";
		echo "<p><pre># BEGIN WPSuperCache\n" . esc_html( $rules ) . "# END WPSuperCache</pre></p>\n";
		echo "<p>" . sprintf( __( 'Rules must be added to %s too:', 'wp-super-cache' ), WP_CONTENT_DIR . "/cache/.htaccess" ) . "</p>";
		echo "<pre># BEGIN supercache\n" . esc_html( $gziprules ) . "# END supercache</pre></p>";
		echo '</div>';
	}

	?></fieldset><?php
}

function wp_cache_restore() {
	echo '<fieldset class="options"><h3>' . __( 'Fix Configuration', 'wp-super-cache' ) . '</h3>';
	echo '<form name="wp_restore" action="#top" method="post">';
	echo '<input type="hidden" name="wp_restore_config" />';
	echo '<div class="submit"><input class="button-secondary" type="submit" ' . SUBMITDISABLED . 'id="deletepost" value="' . __( 'Restore Default Configuration', 'wp-super-cache' ) . '" /></div>';
	wp_nonce_field('wp-cache');
	echo "</form>\n";
	echo '</fieldset>';

}

function comment_form_lockdown_message() {
	?><p><?php _e( "Comment moderation is enabled. Your comment may take some time to appear.", 'wp-super-cache' ); ?></p><?php
}
if( defined( 'WPLOCKDOWN' ) && constant( 'WPLOCKDOWN' ) )
	add_action( 'comment_form', 'comment_form_lockdown_message' );

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

function wpsc_update_direct_pages() {
	global $cached_direct_pages, $valid_nonce, $cache_path, $wp_cache_config_file;

	if ( false == isset( $cached_direct_pages ) )
		$cached_direct_pages = array();
	$out = '';
	if ( $valid_nonce && array_key_exists('direct_pages', $_POST) && is_array( $_POST[ 'direct_pages' ] ) && !empty( $_POST[ 'direct_pages' ] ) ) {
		$expiredfiles = array_diff( $cached_direct_pages, $_POST[ 'direct_pages' ] );
		unset( $cached_direct_pages );
		foreach( $_POST[ 'direct_pages' ] as $page ) {
			$page = str_replace( '..', '', preg_replace('/[ <>\'\"\r\n\t\(\)]/', '', $page ) );
			if ( $page != '' ) {
				$cached_direct_pages[] = $page;
				$out .= "'$page', ";
			}
		}
		if ( false == isset( $cached_direct_pages ) )
			$cached_direct_pages = array();
	}
	if ( $valid_nonce && array_key_exists('new_direct_page', $_POST) && $_POST[ 'new_direct_page' ] && '' != $_POST[ 'new_direct_page' ] ) {
		$page = str_replace( get_option( 'siteurl' ), '', $_POST[ 'new_direct_page' ] );
		$page = str_replace( '..', '', preg_replace('/[ <>\'\"\r\n\t\(\)]/', '', $page ) );
		if ( substr( $page, 0, 1 ) != '/' )
			$page = '/' . $page;
		if ( $page != '/' || false == is_array( $cached_direct_pages ) || in_array( $page, $cached_direct_pages ) == false ) {
			$cached_direct_pages[] = $page;
			$out .= "'$page', ";

			@unlink( trailingslashit( ABSPATH . $page ) . "index.html" );
			wpsc_delete_files( get_supercache_dir() . $page );
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

function wp_lock_down() {
	global $cached_direct_pages, $cache_enabled, $super_cache_enabled;

	$wp_lock_down = wp_update_lock_down();

	?><a name='lockdown'></a>
	<fieldset class="options">
	<h3><?php _e( 'Lock Down:', 'wp-super-cache' ); ?> <?php echo $wp_lock_down == '0' ? '<span style="color:red">' . __( 'Disabled', 'wp-super-cache' ) . '</span>' : '<span style="color:green">' . __( 'Enabled', 'wp-super-cache' ) . '</span>'; ?></h3>
	<p><?php _e( 'Prepare your server for an expected spike in traffic by enabling the lock down. When this is enabled, new comments on a post will not refresh the cached static files.', 'wp-super-cache' ); ?></p>
	<p><?php _e( 'Developers: Make your plugin lock down compatible by checking the "WPLOCKDOWN" constant. The following code will make sure your plugin respects the WPLOCKDOWN setting.', 'wp-super-cache' ); ?>
	<blockquote><code>if( defined( 'WPLOCKDOWN' ) && constant( 'WPLOCKDOWN' ) ) {
		&nbsp;&nbsp;&nbsp;&nbsp;echo "<?php _e( 'Sorry. My blog is locked down. Updates will appear shortly', 'wp-super-cache' ); ?>";
		}</code></blockquote>
	<?php
	if( $wp_lock_down == '1' ) {
		?><p><?php _e( 'WordPress is locked down. Super Cache static files will not be deleted when new comments are made.', 'wp-super-cache' ); ?></p><?php
	} else {
		?><p><?php _e( 'WordPress is not locked down. New comments will refresh Super Cache static files as normal.', 'wp-super-cache' ); ?></p><?php
	}
	$new_lockdown =  $wp_lock_down == '1' ? '0' : '1';
	$new_lockdown_desc =  $wp_lock_down == '1' ? __( 'Disable', 'wp-super-cache' ) : __( 'Enable', 'wp-super-cache' );
	echo '<form name="wp_lock_down" action="#lockdown" method="post">';
	echo "<input type='hidden' name='wp_lock_down' value='{$new_lockdown}' />";
	echo "<div class='submit'><input class='button-primary' type='submit' " . SUBMITDISABLED . " value='{$new_lockdown_desc} " . __( 'Lock Down', 'wp-super-cache' ) . "' /></div>";
	wp_nonce_field('wp-cache');
	echo "</form>\n";

	?></fieldset><?php
	if( $cache_enabled == true && $super_cache_enabled == true ) {
		?><a name='direct'></a>
	<fieldset class="options">
	<h3><?php _e( 'Directly Cached Files', 'wp-super-cache' ); ?></h3><?php

	$cached_direct_pages = wpsc_update_direct_pages();

	$readonly = '';
	if( !is_writeable_ACLSafe( ABSPATH ) ) {
		$readonly = 'READONLY';
		?><p style='padding:0 8px;color:#9f6000;background-color:#feefb3;border:1px solid #9f6000;'><strong><?php _e( 'Warning!', 'wp-super-cache' ); ?></strong> <?php printf( __( 'You must make %s writable to enable this feature. As this is a security risk, please make it read-only after your page is generated.', 'wp-super-cache' ), ABSPATH ); ?></p><?php
	} else {
		$abspath_stat = stat(ABSPATH . '/');
		$abspath_mode = decoct( $abspath_stat[ 'mode' ] & 0777 );
		if ( substr( $abspath_mode, -2 ) == '77' ) {
			?><p style='padding:0 8px;color:#9f6000;background-color:#feefb3;border:1px solid #9f6000;'><strong><?php _e( 'Warning!', 'wp-super-cache' ); ?></strong> <?php printf( __( '%s is writable. Please make it readonly after your page is generated as this is a security risk.', 'wp-super-cache' ), ABSPATH ); ?></p><?php
		}
	}
	echo '<form name="direct_page" action="#direct" method="post">';
	if( is_array( $cached_direct_pages ) ) {
		$out = '';
		foreach( $cached_direct_pages as $page ) {
			if( $page == '' )
				continue;
			$generated = '';
			if( is_file( ABSPATH . $page . '/index.html' ) )
				$generated = '<input class="button-secondary" type="Submit" name="deletepage" value="' . $page . '">';
			$out .= "<tr><td><input type='text' $readonly name='direct_pages[]' size='30' value='$page' /></td><td>$generated</td></tr>";
		}
		if( $out != '' ) {
			?><table><tr><th><?php _e( 'Existing direct page', 'wp-super-cache' ); ?></th><th><?php _e( 'Delete cached file', 'wp-super-cache' ); ?></th></tr><?php
			echo "$out</table>";
		}
	}
	if( $readonly != 'READONLY' )
		echo __( "Add direct page:", 'wp-super-cache' ) . "<input type='text' $readonly name='new_direct_page' size='30' value='' />";

	echo "<p>" . sprintf( __( "Directly cached files are files created directly off %s where your blog lives. This feature is only useful if you are expecting a major Digg or Slashdot level of traffic to one post or page.", 'wp-super-cache' ), ABSPATH ) . "</p>";
	if( $readonly != 'READONLY' ) {
		echo "<p>" . sprintf( __( 'For example: to cache <em>%1$sabout/</em>, you would enter %1$sabout/ or /about/. The cached file will be generated the next time an anonymous user visits that page.', 'wp-super-cache' ), trailingslashit( get_option( 'siteurl' ) ) ) . "</p>";
		echo "<p>" . __( 'Make the textbox blank to remove it from the list of direct pages and delete the cached file.', 'wp-super-cache' ) . "</p>";
	}

	wp_nonce_field('wp-cache');
	if( $readonly != 'READONLY' )
		echo "<div class='submit'><input class='button-primary' type='submit' ' . SUBMITDISABLED . 'value='" . __( 'Update Direct Pages', 'wp-super-cache' ) . "' /></div>";
	echo "</form>\n";
	?></fieldset><?php
	} // if $super_cache_enabled
}

function RecursiveFolderDelete ( $folderPath ) { // from http://www.php.net/manual/en/function.rmdir.php
	if( trailingslashit( constant( 'ABSPATH' ) ) == trailingslashit( $folderPath ) )
		return false;
	if ( @is_dir ( $folderPath ) ) {
		$dh  = @opendir($folderPath);
		while (false !== ($value = @readdir($dh))) {
			if ( $value != "." && $value != ".." ) {
				$value = $folderPath . "/" . $value;
				if ( @is_dir ( $value ) ) {
					RecursiveFolderDelete ( $value );
				}
			}
		}
		return @rmdir ( $folderPath );
	} else {
		return FALSE;
	}
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

function wp_cache_edit_max_time() {
	global $cache_max_time, $wp_cache_config_file, $valid_nonce, $super_cache_enabled, $cache_schedule_type, $cache_scheduled_time, $cache_schedule_interval, $cache_time_interval, $cache_gc_email_me, $wp_cache_preload_on;

	$timezone_format = _x('Y-m-d G:i:s', 'timezone date format');

	wp_cache_time_update();

	?><fieldset class="options">
	<a name='expirytime'></a>
	<h3><?php _e( 'Expiry Time &amp; Garbage Collection', 'wp-super-cache' ); ?></h3><?php

	?><span id="utc-time"><?php printf( __( '<abbr title="Coordinated Universal Time">UTC</abbr> time is <code>%s</code>', 'wp-super-cache' ), date_i18n( $timezone_format, false, 'gmt' ) ); ?></span><?php
	$current_offset = get_option('gmt_offset');
	if ( get_option('timezone_string') || !empty($current_offset) ) {
		?><span id="local-time"><?php printf( __( 'Local time is <code>%1$s</code>', 'wp-super-cache' ), date_i18n( $timezone_format ) ); ?></span><?php
	}
	$next_gc = wp_next_scheduled( 'wp_cache_gc' );
	if ( $next_gc )
		echo "<p>" . sprintf( __( 'Next scheduled garbage collection will be at <strong>%s UTC</strong>', 'wp-super-cache' ), date_i18n( $timezone_format, $next_gc, 'gmt' ) ) . "</p>";


	if ( $wp_cache_preload_on )
		echo "<p>" . __( 'Warning! <strong>PRELOAD MODE</strong> activated. Supercache files will not be deleted regardless of age.', 'wp-super-cache' ) . "</p>";

	echo "<script type='text/javascript'>";
	echo "jQuery(function () {
		jQuery('#cache_interval_time').click(function () {
			jQuery('#schedule_interval').attr('checked', true);
		});
		jQuery('#cache_scheduled_time').click(function () {
			jQuery('#schedule_time').attr('checked', true);
		});
		jQuery('#cache_scheduled_select').click(function () {
			jQuery('#schedule_time').attr('checked', true);
		});
		});";
	echo "</script>";
	echo '<form name="wp_edit_max_time" action="#expirytime" method="post">';
	echo '<input name="action" value="expirytime" type="hidden" />';
	echo '<table class="form-table">';
	echo '<tr><td><label for="wp_max_time"><strong>' . __( 'Cache Timeout', 'wp-super-cache' ) . '</strong></label></td>';
	echo "<td><input type='text' id='wp_max_time' size=6 name='wp_max_time' value='" . esc_attr( $cache_max_time ) . "' /> " . __( "seconds", 'wp-super-cache' ) . "</td></tr>\n";
	echo "<tr><td></td><td>" . __( 'How long should cached pages remain fresh? Set to 0 to disable garbage collection. A good starting point is 3600 seconds.', 'wp-super-cache' ) . "</td></tr>\n";
	echo '<tr><td valign="top"><strong>' . __( 'Scheduler', 'wp-super-cache' ) . '</strong></td><td><table cellpadding=0 cellspacing=0><tr><td valign="top"><input type="radio" id="schedule_interval" name="cache_schedule_type" value="interval" ' . checked( 'interval', $cache_schedule_type, false ) . ' /></td><td valign="top"><label for="cache_interval_time">' . __( 'Timer:', 'wp-super-cache' ) . '</label></td>';
	echo "<td><input type='text' id='cache_interval_time' size=6 name='cache_time_interval' value='" . esc_attr( $cache_time_interval ) . "' /> " . __( "seconds", 'wp-super-cache' ) . '<br />' . __( 'Check for stale cached files every <em>interval</em> seconds.', 'wp-super-cache' ) . "</td></tr>";
	echo '<tr><td valign="top"><input type="radio" id="schedule_time" name="cache_schedule_type" value="time" ' . checked( 'time', $cache_schedule_type, false ) . ' /></td><td valign="top"><label for="schedule_time">' . __( 'Clock:', 'wp-super-cache' ) . '</label></td>';
	echo "<td><input type=\"text\" size=5 id='cache_scheduled_time' name='cache_scheduled_time' value=\"" . esc_attr( $cache_scheduled_time ) . "\" /> " . __( "HH:MM", 'wp-super-cache' ) . "<br />" . __( 'Check for stale cached files at this time <strong>(UTC)</strong> or starting at this time every <em>interval</em> below.', 'wp-super-cache' ) . "</td></tr>";
	$schedules = wp_get_schedules();
	echo "<tr><td><br /></td><td><label for='cache_scheduled_select'>" . __( 'Interval:', 'wp-super-cache' ) . "</label></td><td><select id='cache_scheduled_select' name='cache_schedule_interval' size=1>";
	foreach( $schedules as $desc => $details ) {
		echo "<option value='$desc' " . selected( $desc, $cache_schedule_interval, false ) . " /> {$details[ 'display' ]}</option>";
	}
	echo "</select></td></tr>";
	echo '</table></td></tr>';
	echo '<tr><td><label for="cache_gc_email_me"><strong>' . __( 'Notification Emails', 'wp-super-cache' ) . '</strong></label></td>';
	echo "<td><input type='checkbox' id='cache_gc_email_me' name='cache_gc_email_me' " . checked( $cache_gc_email_me, 1, false ) . " /> " . __( 'Email me when the garbage collection runs.', 'wp-super-cache' ) . "</td></tr>\n";
	echo "</table>\n";
	echo "<h4>" . __( 'Garbage Collection', 'wp-super-cache' ) . "</h4>";
	echo "<ol><li>" . __( '<em>Garbage collection</em> is the simple act of throwing out your garbage. For this plugin that would be old or <em>stale</em> cached files that may be out of date. New cached files are described as <em>fresh</em>.', 'wp-super-cache' ) . "</li>\n";
	echo "<li>" . __( 'Cached files are fresh for a limited length of time. You can set that time in the <em>Cache Timeout</em> text box on this page.', 'wp-super-cache' ) . "</li>\n";
	echo "<li>" . __( 'Stale cached files are not removed as soon as they become stale. They have to be removed by the garbage collecter. That is why you have to tell the plugin when the garbage collector should run.', 'wp-super-cache' ) . "</li>\n";
	echo "<li>" . __( 'Use the <em>Timer</em> or <em>Clock</em> schedulers to define when the garbage collector should run.', 'wp-super-cache' ) . "</li>\n";
	echo "<li>" . __( 'The <em>Timer</em> scheduler tells the plugin to run the garbage collector at regular intervals. When one garbage collection is done, the next run is scheduled.', 'wp-super-cache' ) . "</li>\n";
	echo "<li>" . __( 'Or, the <em>Clock</em> scheduler allows the garbage collection to run at specific times. If set to run hourly or twice daily, the garbage collector will be first scheduled for the time you enter here. It will then run again at the indicated interval. If set to run daily, it will run once a day at the time specified.', 'wp-super-cache' ) . "</li>\n";
	echo "</ol>";
	echo "<p>" . __( 'There are no best garbage collection settings but here are a few scenarios. Garbage collection is separate to other actions that clear our cached files like leaving a comment or publishing a post.', 'wp-super-cache' ) . "</p>\n";
	echo "<ol>";
	echo "<li>" . __( 'Sites that want to serve lots of newly generated data should set the <em>Cache Timeout</em> to 60 and use the <em>Timer</em> scheduler set to 90 seconds.', 'wp-super-cache' ) . "</li>\n";
	echo "<li>" . __( 'Sites with widgets and rss feeds in their sidebar should probably use a timeout of 3600 seconds and set the timer to 600 seconds. Stale files will be caught within 10 minutes of going stale.', 'wp-super-cache' ) . "</li>\n";
	echo "<li>" . __( 'Sites with lots of static content, no widgets or rss feeds in their sidebar can use a timeout of 86400 seconds or even more and set the timer to something equally long.', 'wp-super-cache' ) . "</li>\n";
	echo "<li>" . __( 'Sites where an external data source updates at a particular time every day should set the timeout to 86400 seconds and use the Clock scheduler set appropriately.', 'wp-super-cache' ) . "</li>\n";
	echo "</ol>";
	echo "<p>" . __( 'Checking for and deleting expired files is expensive, but it&#8217;s expensive leaving them there too. On a very busy site, you should set the expiry time to <em>600 seconds</em>. Experiment with different values and visit this page to see how many expired files remain at different times during the day.', 'wp-super-cache' ) . "</p>";
	echo "<p>" . __( 'Set the expiry time to 0 seconds to disable garbage collection.', 'wp-super-cache' ) . "</p>";
	echo '<div class="submit"><input class="button-primary" type="submit" ' . SUBMITDISABLED . 'value="' . __( 'Change Expiration', 'wp-super-cache' ) . '" /></div>';
	wp_nonce_field('wp-cache');
	echo "</form>\n";
	?></fieldset><?php
}

function wp_cache_sanitize_value($text, & $array) {
	$text = esc_html(strip_tags($text));
	$array = preg_split("/[\s,]+/", chop($text));
	$text = var_export($array, true);
	$text = preg_replace('/[\s]+/', ' ', $text);
	return $text;
}

// from tehjosh at gamingg dot net http://uk2.php.net/manual/en/function.apache-request-headers.php#73964
// fixed bug in second substr()
if( !function_exists('apache_request_headers') ) {
	function apache_request_headers() {
		$headers = array();
		foreach(array_keys($_SERVER) as $skey) {
			if(substr($skey, 0, 5) == "HTTP_") {
				$headername = str_replace(" ", "-", ucwords(strtolower(str_replace("_", " ", substr($skey, 5)))));
				$headers[$headername] = $_SERVER[$skey];
			}
		}
		return $headers;
	}
}

function wp_cache_update_rejected_ua() {
	global $cache_rejected_user_agent, $wp_cache_config_file, $valid_nonce;

	if ( !function_exists( 'apache_request_headers' ) ) return;

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

function wp_cache_edit_rejected_ua() {
	global $cache_rejected_user_agent, $wp_cache_config_file, $valid_nonce;

	if ( !function_exists( 'apache_request_headers' ) ) return;

	wp_cache_update_rejected_ua();

	echo '<a name="useragents"></a><fieldset class="options"><h3>' . __( 'Rejected User Agents', 'wp-super-cache' ) . '</h3>';
	echo "<p>" . __( 'Strings in the HTTP &#8217;User Agent&#8217; header that prevent WP-Cache from caching bot, spiders, and crawlers&#8217; requests. Note that super cached files are still sent to these agents if they already exists.', 'wp-super-cache' ) . "</p>\n";
	echo '<form name="wp_edit_rejected_user_agent" action="#useragents" method="post">';
	echo '<textarea name="wp_rejected_user_agent" cols="40" rows="4" style="width: 50%; font-size: 12px;" class="code">';
	foreach( $cache_rejected_user_agent as $ua ) {
		echo esc_html( $ua ) . "\n";
	}
	echo '</textarea> ';
	echo '<div class="submit"><input class="button-primary" type="submit" ' . SUBMITDISABLED . 'value="' . __( 'Save UA Strings', 'wp-super-cache' ) . '" /></div>';
	wp_nonce_field('wp-cache');
	echo '</form>';
	echo "</fieldset>\n";
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

function wp_cache_edit_rejected_pages() {
	global $wp_cache_config_file, $valid_nonce, $wp_cache_pages;

	wp_cache_update_rejected_pages();

	echo '<a name="rejectpages"></a>';
	echo '<p>' . __( 'Do not cache the following page types. See the <a href="http://codex.wordpress.org/Conditional_Tags">Conditional Tags</a> documentation for a complete discussion on each type.', 'wp-super-cache' ) . '</p>';
	echo '<form name="wp_edit_rejected_pages" action="#rejectpages" method="post">';
	echo '<input type="hidden" name="wp_edit_rejected_pages" value="1" />';
	echo '<label><input type="checkbox" value="1" name="wp_cache_pages[single]" ' . checked( 1, $wp_cache_pages[ 'single' ], false ) . ' /> ' . __( 'Single Posts', 'wp-super-cache' ) . ' (is_single)</label><br />';
	echo '<label><input type="checkbox" value="1" name="wp_cache_pages[pages]" ' . checked( 1, $wp_cache_pages[ 'pages' ], false ) . ' /> ' . __( 'Pages', 'wp-super-cache' ) . ' (is_page)</label><br />';
	echo '<label><input type="checkbox" value="1" name="wp_cache_pages[frontpage]" ' . checked( 1, $wp_cache_pages[ 'frontpage' ], false ) . ' /> ' . __( 'Front Page', 'wp-super-cache' ) . ' (is_front_page)</label><br />';
	echo '&nbsp;&nbsp;<label><input type="checkbox" value="1" name="wp_cache_pages[home]" ' . checked( 1, $wp_cache_pages[ 'home' ], false ) . ' /> ' . __( 'Home', 'wp-super-cache' ) . ' (is_home)</label><br />';
	echo '<label><input type="checkbox" value="1" name="wp_cache_pages[archives]" ' . checked( 1, $wp_cache_pages[ 'archives' ], false ) . ' /> ' . __( 'Archives', 'wp-super-cache' ) . ' (is_archive)</label><br />';
	echo '&nbsp;&nbsp;<label><input type="checkbox" value="1" name="wp_cache_pages[tag]" ' . checked( 1, $wp_cache_pages[ 'tag' ], false ) . ' /> ' . __( 'Tags', 'wp-super-cache' ) . ' (is_tag)</label><br />';
	echo '&nbsp;&nbsp;<label><input type="checkbox" value="1" name="wp_cache_pages[category]" ' . checked( 1, $wp_cache_pages[ 'category' ], false ) . ' /> ' . __( 'Category', 'wp-super-cache' ) . ' (is_category)</label><br />';
	echo '<label><input type="checkbox" value="1" name="wp_cache_pages[feed]" ' . checked( 1, $wp_cache_pages[ 'feed' ], false ) . ' /> ' . __( 'Feeds', 'wp-super-cache' ) . ' (is_feed)</label><br />';
	echo '<label><input type="checkbox" value="1" name="wp_cache_pages[search]" ' . checked( 1, $wp_cache_pages[ 'search' ], false ) . ' /> ' . __( 'Search Pages', 'wp-super-cache' ) . ' (is_search)</label><br />';
	echo '<label><input type="checkbox" value="1" name="wp_cache_pages[author]" ' . checked( 1, $wp_cache_pages[ 'author' ], false ) . ' /> ' . __( 'Author Pages', 'wp-super-cache' ) . ' (is_author)</label><br />';

	echo '<div class="submit"><input class="button-primary" type="submit" ' . SUBMITDISABLED . 'value="' . __( 'Save', 'wp-super-cache' ) . '" /></div>';
	wp_nonce_field('wp-cache');
	echo "</form>\n";

}

function wp_cache_update_rejected_strings() {
	global $cache_rejected_uri, $wp_cache_config_file, $valid_nonce;

	if ( isset($_REQUEST['wp_rejected_uri']) && $valid_nonce ) {
		$text = wp_cache_sanitize_value( str_replace( '\\\\', '\\', $_REQUEST['wp_rejected_uri'] ), $cache_rejected_uri );
		wp_cache_replace_line('^ *\$cache_rejected_uri', "\$cache_rejected_uri = $text;", $wp_cache_config_file);
	}

}

function wp_cache_edit_rejected() {
	global $cache_rejected_uri;

	wp_cache_update_rejected_strings();

	echo '<a name="rejecturi"></a>';
	echo '<form name="wp_edit_rejected" action="#rejecturi" method="post">';
	echo "<p>" . __( 'Add here strings (not a filename) that forces a page not to be cached. For example, if your URLs include year and you dont want to cache last year posts, it&#8217;s enough to specify the year, i.e. &#8217;/2004/&#8217;. WP-Cache will search if that string is part of the URI and if so, it will not cache that page.', 'wp-super-cache' ) . "</p>\n";
	echo '<textarea name="wp_rejected_uri" cols="40" rows="4" style="width: 50%; font-size: 12px;" class="code">';
	foreach ($cache_rejected_uri as $file) {
		echo esc_html( $file ) . "\n";
	}
	echo '</textarea> ';
	echo '<div class="submit"><input class="button-primary" type="submit" ' . SUBMITDISABLED . 'value="' . __( 'Save Strings', 'wp-super-cache' ) . '" /></div>';
	wp_nonce_field('wp-cache');
	echo "</form>\n";
}

function wp_cache_update_accepted_strings() {
	global $cache_acceptable_files, $wp_cache_config_file, $valid_nonce;

	if ( isset( $_REQUEST[ 'wp_accepted_files' ] ) && $valid_nonce ) {
		$text = wp_cache_sanitize_value( $_REQUEST[ 'wp_accepted_files' ], $cache_acceptable_files );
		wp_cache_replace_line( '^ *\$cache_acceptable_files', "\$cache_acceptable_files = $text;", $wp_cache_config_file );
	}
}

function wp_cache_edit_accepted() {
	global $cache_acceptable_files;

	wp_cache_update_accepted_strings();

	echo '<a name="cancache"></a>';
	echo '<div style="clear:both"></div><form name="wp_edit_accepted" action="#cancache" method="post">';
	echo "<p>" . __( 'Add here those filenames that can be cached, even if they match one of the rejected substring specified above.', 'wp-super-cache' ) . "</p>\n";
	echo '<textarea name="wp_accepted_files" cols="40" rows="8" style="width: 50%; font-size: 12px;" class="code">';
	foreach ($cache_acceptable_files as $file) {
		echo esc_html($file) . "\n";
	}
	echo '</textarea> ';
	echo '<div class="submit"><input class="button-primary" type="submit" ' . SUBMITDISABLED . 'value="' . __( 'Save Files', 'wp-super-cache' ) . '" /></div>';
	wp_nonce_field('wp-cache');
	echo "</form>\n";
}

function wpsc_create_debug_log( $filename = '', $username = '' ) {
	global $cache_path, $wp_cache_debug_username, $wp_cache_debug_log;
	if ( $filename != '' ) {
		$wp_cache_debug_log = $filename;
	} else {
		$wp_cache_debug_log = md5( time() + mt_rand() ) . ".php";
	}
	if ( $username != '' ) {
		$wp_cache_debug_username = $username;
	} else {
		$wp_cache_debug_username = md5( time() + mt_rand() );
	}

	$fp = fopen( $cache_path . $wp_cache_debug_log, 'w' );
	if ( $fp ) {
		fwrite( $fp, '<' . "?php\n" );
		$msg = '
				if ( !isset( $_SERVER[ "PHP_AUTH_USER" ] ) || ( $_SERVER[ "PHP_AUTH_USER" ] != "' . $wp_cache_debug_username . '" && $_SERVER[ "PHP_AUTH_PW" ] != "' . $wp_cache_debug_username . '" ) ) {
					header( "WWW-Authenticate: Basic realm=\"WP-Super-Cache Debug Log\"" );
					header("HTTP/1.0 401 Unauthorized");
					echo "You must login to view the debug log";
					exit;
				}';
		fwrite( $fp, $msg );
		fwrite( $fp, '?' . "><pre>" );
		fclose( $fp );
		wp_cache_setting( 'wp_cache_debug_log', $wp_cache_debug_log );
		wp_cache_setting( 'wp_cache_debug_username', $wp_cache_debug_username );
	}
	return array( 'wp_cache_debug_log' => $wp_cache_debug_log, 'wp_cache_debug_username' => $wp_cache_debug_username );
}

function wpsc_update_debug_settings() {
	global $wp_super_cache_debug, $wp_cache_debug_log, $wp_cache_debug_ip, $cache_path, $valid_nonce, $wp_cache_config_file, $wp_super_cache_comments;
	global $wp_super_cache_front_page_check, $wp_super_cache_front_page_clear, $wp_super_cache_front_page_text, $wp_super_cache_front_page_notification, $wp_super_cache_advanced_debug;
	global $wp_cache_debug_username;

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

	if ( isset( $_POST[ 'wpsc_delete_log' ] ) && $wp_cache_debug_log != '' ) {
		@unlink( $cache_path . $wp_cache_debug_log );
		extract( wpsc_create_debug_log( $wp_cache_debug_log, $wp_cache_debug_username ) );
	} elseif ( isset( $_POST[ 'wp_super_cache_debug' ] ) ) {
		$wp_super_cache_debug = (int) $_POST[ 'wp_super_cache_debug' ];
		wp_cache_setting( 'wp_super_cache_debug', $wp_super_cache_debug );
		if ( ! isset( $wp_cache_debug_log ) || $wp_cache_debug_log == '' ) {
			extract( wpsc_create_debug_log() );
		}
	}

	if ( false == isset( $wp_super_cache_comments ) )
		$wp_super_cache_comments = 1;

	if ( isset( $_POST[ 'wp_cache_debug' ] ) ) {
		wp_cache_setting( 'wp_cache_debug_username', $wp_cache_debug_username );
		wp_cache_setting( 'wp_cache_debug_log', $wp_cache_debug_log );
		$wp_super_cache_comments = isset( $_POST[ 'wp_super_cache_comments' ] ) ? 1 : 0;
		wp_cache_setting( 'wp_super_cache_comments', $wp_super_cache_comments );
		$wp_cache_debug_ip = esc_html( $_POST[ 'wp_cache_debug_ip' ] );
		wp_cache_setting( 'wp_cache_debug_ip', $wp_cache_debug_ip );
		$wp_super_cache_front_page_check = isset( $_POST[ 'wp_super_cache_front_page_check' ] ) ? 1 : 0;
		wp_cache_setting( 'wp_super_cache_front_page_check', $wp_super_cache_front_page_check );
		$wp_super_cache_front_page_clear = isset( $_POST[ 'wp_super_cache_front_page_clear' ] ) ? 1 : 0;
		wp_cache_setting( 'wp_super_cache_front_page_clear', $wp_super_cache_front_page_clear );
		$wp_super_cache_front_page_text = esc_html( $_POST[ 'wp_super_cache_front_page_text' ] );
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

function wp_cache_debug_settings() {
	global $wp_super_cache_debug, $wp_cache_debug_log, $wp_cache_debug_ip, $cache_path, $valid_nonce, $wp_cache_config_file, $wp_super_cache_comments;
	global $wp_super_cache_front_page_check, $wp_super_cache_front_page_clear, $wp_super_cache_front_page_text, $wp_super_cache_front_page_notification, $wp_super_cache_advanced_debug;
	global $wp_cache_debug_username;

	extract( wpsc_update_debug_settings() );

	echo '<a name="debug"></a>';
	echo '<fieldset class="options">';
	echo '<p>' . __( 'Fix problems with the plugin by debugging it here. It will log to a file in your cache directory.', 'wp-super-cache' ) . '</p>';
	if ( ! isset( $wp_cache_debug_log ) || $wp_cache_debug_log == '' ) {
		extract( wpsc_create_debug_log() );
	}
	$log_file_link = "<a href='" . site_url( str_replace( ABSPATH, '', "{$cache_path}{$wp_cache_debug_log}" ) ) . "'>$wp_cache_debug_log</a>";
	if ( $wp_super_cache_debug == 1 ) {
		echo "<p>" . sprintf( __( 'Currently logging to: %s', 'wp-super-cache' ), $log_file_link ) . "</p>";
	} else {
		echo "<p>" . sprintf( __( 'Last Logged to: %s', 'wp-super-cache' ), $log_file_link ) . "</p>";
	}
	echo "<p>" . sprintf( __( 'Username/Password: %s', 'wp-super-cache' ), $wp_cache_debug_username ) . "</p>";

	echo '<form name="wpsc_delete" action="" method="post">';
	wp_nonce_field('wp-cache');
	submit_button( __( 'Delete', 'wp-super-cache' ), 'delete', 'wpsc_delete_log', false );
	echo "</form>";
	echo '<form name="wpsc_delete" action="" method="post">';
	if ( ! isset( $wp_super_cache_debug ) || $wp_super_cache_debug == 0 ) {
		$debug_status_message = __( 'Enable Logging', 'wp-super-cache' );
		$not_status = 1;
	} else {
		$debug_status_message = __( 'Disable Logging', 'wp-super-cache' );
		$not_status = 0;
	}
	echo "<input type='hidden' name='wp_super_cache_debug' value='" . $not_status . "' />";
	wp_nonce_field('wp-cache');
	submit_button( $debug_status_message, 'primary', 'wpsc_log_status', true );
	echo "</form>";
	echo '<form name="wp_cache_debug" action="" method="post">';
	echo "<input type='hidden' name='wp_cache_debug' value='1' /><br />";
	echo "<table class='form-table'>";
	echo "<tr><th>" . __( 'IP Address', 'wp-super-cache' ) . "</th><td> <input type='text' size='20' name='wp_cache_debug_ip' value='{$wp_cache_debug_ip}' /> " . sprintf( __( '(only log requests from this IP address. Your IP is %s)', 'wp-super-cache' ), $_SERVER[ 'REMOTE_ADDR' ] ) . "</td></tr>";
	echo "<tr><th valign='top'>" . __( 'Cache Status Messages', 'wp-super-cache' ) . "</th><td><input type='checkbox' name='wp_super_cache_comments' value='1' " . checked( 1, $wp_super_cache_comments, false ) . " /> " . __( 'enabled', 'wp-super-cache' ) . "<br />";
	echo  __( 'Display comments at the end of every page like this:', 'wp-super-cache' ) . "<br />";
	echo "<pre>&lt;!-- Dynamic page generated in 0.450 seconds. -->
&lt;!-- Cached page generated by WP-Super-Cache on " . date( "Y-m-d H:i:s", time() ) . " -->
&lt;!-- super cache --></pre></td></tr>";
	echo "</table>\n";
	if ( isset( $wp_super_cache_advanced_debug ) ) {
	echo "<h4>" . __( 'Advanced', 'wp-super-cache' ) . "</h4><p>" . __( 'In very rare cases two problems may arise on some blogs:<ol><li> The front page may start downloading as a zip file.</li><li> The wrong page is occasionally cached as the front page if your blog uses a static front page and the permalink structure is <em>/%category%/%postname%/</em>.</li></ol>', 'wp-super-cache' ) . '</p>';
	echo "<p>" . __( 'I&#8217;m 99% certain that they aren&#8217;t bugs in WP Super Cache and they only happen in very rare cases but you can run a simple check once every 5 minutes to verify that your site is ok if you&#8217;re worried. You will be emailed if there is a problem.', 'wp-super-cache' ) . "</p>";
	echo "<table class='form-table'>";
	echo "<tr><td valign='top' colspan='2'><input type='checkbox' name='wp_super_cache_front_page_check' value='1' " . checked( 1, $wp_super_cache_front_page_check, false ) . " /> " . __( 'Check front page every 5 minutes.', 'wp-super-cache' ) . "</td></tr>";
	echo "<tr><td valign='top'>" . __( 'Front page text', 'wp-super-cache' ) . "</td><td> <input type='text' size='30' name='wp_super_cache_front_page_text' value='{$wp_super_cache_front_page_text}' /> (" . __( 'Text to search for on your front page. If this text is missing, the cache will be cleared. Leave blank to disable.', 'wp-super-cache' ) . ")</td></tr>";
	echo "<tr><td valign='top' colspan='2'><input type='checkbox' name='wp_super_cache_front_page_clear' value='1' " . checked( 1, $wp_super_cache_front_page_clear, false ) . " /> " . __( 'Clear cache on error.', 'wp-super-cache' ) . "</td></tr>";
	echo "<tr><td valign='top' colspan='2'><input type='checkbox' name='wp_super_cache_front_page_notification' value='1' " . checked( 1, $wp_super_cache_front_page_notification, false ) . " /> " . __( 'Email the blog admin when checks are made. (useful for testing)', 'wp-super-cache' ) . "</td></tr>";

	echo "</table>\n";
	}
	echo '<div class="submit"><input class="button-primary" type="submit" ' . SUBMITDISABLED . 'value="' . __( 'Save', 'wp-super-cache' ) . '" /></div>';
	wp_nonce_field('wp-cache');
	echo "</form>\n";
	echo '</fieldset>';
}

function wp_cache_enable() {
	global $wp_cache_config_file, $cache_enabled;

	if( wp_cache_replace_line('^ *\$cache_enabled', '$cache_enabled = true;', $wp_cache_config_file) ) {
		$cache_enabled = true;
	}

	if ( wpsc_set_default_gc() ) {
		// gc might not be scheduled, check and schedule
		$timestamp = wp_next_scheduled( 'wp_cache_gc' );
		if ( false == $timestamp ) {
			wp_schedule_single_event( time() + 600, 'wp_cache_gc' );
		}
	}
}

function wp_cache_disable() {
	global $wp_cache_config_file, $cache_enabled;

	wp_clear_scheduled_hook( 'wp_cache_check_site_hook' );
	wp_clear_scheduled_hook( 'wp_cache_gc' );
	wp_clear_scheduled_hook( 'wp_cache_gc_watcher' );
	if (wp_cache_replace_line('^ *\$cache_enabled', '$cache_enabled = false;', $wp_cache_config_file)) {
		$cache_enabled = false;
	}
}
function wp_super_cache_enable() {
	global $supercachedir, $wp_cache_config_file, $super_cache_enabled;

	if( is_dir( $supercachedir . ".disabled" ) )
		if( is_dir( $supercachedir ) ) {
			prune_super_cache( $supercachedir . ".disabled", true );
			@unlink( $supercachedir . ".disabled" );
		} else {
			@rename( $supercachedir . ".disabled", $supercachedir );
		}
	wp_cache_replace_line('^ *\$super_cache_enabled', '$super_cache_enabled = true;', $wp_cache_config_file);
	$super_cache_enabled = true;
}

function wp_super_cache_disable() {
	global $cache_path, $supercachedir, $wp_cache_config_file, $super_cache_enabled;

	wp_cache_replace_line('^ *\$super_cache_enabled', '$super_cache_enabled = false;', $wp_cache_config_file);
	if( is_dir( $supercachedir ) )
		@rename( $supercachedir, $supercachedir . ".disabled" );
	$super_cache_enabled = false;
	sleep( 1 ); // allow existing processes to write to the supercachedir and then delete it
	if (function_exists ('prune_super_cache') && is_dir( $supercachedir ) ) {
		prune_super_cache( $cache_path, true );
	}
}

function wp_cache_is_enabled() {
	global $wp_cache_config_file;

	if(get_option('gzipcompression')) {
		echo "<strong>" . __( 'Warning', 'wp-super-cache' ) . "</strong>: " . __( "GZIP compression is enabled in WordPress, wp-cache will be bypassed until you disable gzip compression.", 'wp-super-cache' );
		return false;
	}
	$lines = file($wp_cache_config_file);
	foreach($lines as $line) {
	 	if (preg_match('/^ *\$cache_enabled *= *true *;/', $line))
			return true;
	}
	return false;
}

function wp_cache_setting( $field, $value ) {
	global $wp_cache_config_file;
	global ${$field};
	$$field = $value;
	if ( is_numeric( $value ) ) {
		wp_cache_replace_line( '^ *\$' . $field, "\$$field = $value;", $wp_cache_config_file );
	} elseif ( is_object( $value ) || is_array( $value ) ) {
		$text = var_export( $value, true );
		$text = preg_replace( '/[\s]+/', ' ', $text );
		wp_cache_replace_line( '^ *\$' . $field, "\$$field = $text;", $wp_cache_config_file );
	} else {
		wp_cache_replace_line( '^ *\$' . $field, "\$$field = '$value';", $wp_cache_config_file );
	}
}

function wp_cache_replace_line($old, $new, $my_file) {
	if ( @is_file( $my_file ) == false ) {
		return false;
	}
	if (!is_writeable_ACLSafe($my_file)) {
		echo "Error: file $my_file is not writable.\n";
		return false;
	}

	$found = false;
	$lines = file($my_file);
	foreach( (array)$lines as $line ) {
	 	if ( preg_match("/$old/", $line)) {
			$found = true;
			break;
		}
	}
	if ($found) {
		$fd = fopen($my_file, 'w');
		foreach( (array)$lines as $line ) {
			if ( !preg_match("/$old/", $line))
				fputs($fd, $line);
			else {
				fputs($fd, "$new //Added by WP-Cache Manager\n");
			}
		}
		fclose($fd);
		return true;
	}
	$fd = fopen($my_file, 'w');
	$done = false;
	foreach( (array)$lines as $line ) {
		if ( $done || !preg_match('/^(if\ \(\ \!\ )?define|\$|\?>/', $line) ) {
			fputs($fd, $line);
		} else {
			fputs($fd, "$new //Added by WP-Cache Manager\n");
			fputs($fd, $line);
			$done = true;
		}
	}
	fclose($fd);
	return true;
}

function wp_cache_remove_index() {
	global $cache_path;
	@unlink( $cache_path . "index.html" );
	@unlink( $cache_path . "supercache/index.html" );
	@unlink( $cache_path . "blogs/index.html" );
	if ( is_dir( $cache_path . "blogs" ) ) {
		$dir = new DirectoryIterator( $cache_path . "blogs" );
		foreach( $dir as $fileinfo ) {
			if ( $fileinfo->isDot() ) {
				continue;
			}
			if ( $fileinfo->isDir() ) {
				$directory = $cache_path . "blogs/" . $fileinfo->getFilename();
				if ( is_file( $directory . "/index.html" ) ) {
					unlink( $directory . "/index.html" );
				}
				if ( is_dir( $directory . "/meta" ) ) {
					if ( is_file( $directory . "/meta/index.html" ) ) {
						unlink( $directory . "/index.html" );
					}
				}
			}
		}
	}
}

function wp_cache_index_notice() {
	global $wp_version, $cache_path;

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
		echo "<h1>" . __( 'WP Super Cache Warning!', 'wp-super-cache' ) . '</h1>';
		echo '<p>' . __( 'All users of this site have been logged out to refresh their login cookies.', 'wp-super-cache' ) . '</p>';
		echo '</div>';
		return false;
	} elseif ( get_site_option( 'wp_super_cache_index_detected' ) != 3 ) {
		echo "<div id='wpsc-index-warning' class='error notice' style='padding: 10px 10px 50px 10px'>";
		echo "<h1>" . __( 'WP Super Cache Warning!', 'wp-super-cache' ) . '</h1>';
		echo '<p>' . __( 'Your server is configured to show files and directories, which may expose sensitive data such as login cookies to attackers in the cache directories. That has been fixed by adding a file named index.html to each directory. If you use simple caching, consider moving the location of the cache directory on the Advanced Settings page.', 'wp-super-cache' ) . '</p>';
		echo "<p><strong>";
		_e( 'If you just installed WP Super Cache for the first time, you can dismiss this message. Otherwise, you should probably refresh the login cookies of all logged in WordPress users here by clicking the logout link below.', 'wp-super-cache' );
		echo "</strong></p>";
		if ( -1 == version_compare( $wp_version, '4.0' ) ) {
			echo '<p>' . __( 'Your site is using a very old version of WordPress. When you update to the latest version everyone will be logged out and cookie information updated.', 'wp-super-cache' ) . '</p>';
		} else {
			echo '<p>' . __( 'The logout link will log out all WordPress users on this site except you. Your authentication cookie will be updated, but you will not be logged out.', 'wp-super-cache' ) . '</p>';
		}
		echo "<a id='wpsc-dismiss' href='#'>" . __( 'Dismiss', 'wp-super-cache' ) . "</a>";
		if ( 1 == version_compare( $wp_version, '4.0' ) ) {
			echo "	| <a href='" . wp_nonce_url( admin_url( '?action=wpsclogout' ), 'wpsc_logout' ) . "'>" . __( 'Logout', 'wp-super-cache' ) . "</a>";
		}
		echo "</div>";
?>
		<script  type='text/javascript'>
		<!--
			jQuery(document).ready(function(){
				jQuery('#wpsc-dismiss').click(function() {
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

function wpsc_dismiss_indexhtml_warning() {
		check_ajax_referer( "wpsc-index-dismiss" );
		update_site_option( 'wp_super_cache_index_detected', 3 );
		die();
}
add_action( 'wp_ajax_wpsc-index-dismiss', 'wpsc_dismiss_indexhtml_warning' );

function wp_cache_logout_all() {
	global $current_user;
	if ( isset( $_GET[ 'action' ] ) && $_GET[ 'action' ] == 'wpsclogout' && wp_verify_nonce( $_GET[ '_wpnonce' ], 'wpsc_logout' ) ) {
		$user_id = $current_user->ID;
		WP_Session_Tokens::destroy_all_for_all_users();
		wp_set_auth_cookie( $user_id, false, is_ssl() );
		update_site_option( 'wp_super_cache_index_detected', 2 );
		wp_redirect( admin_url() );
	}
}
if ( isset( $_GET[ 'action' ] ) && $_GET[ 'action' ] == 'wpsclogout' )
   add_action( 'admin_init', 'wp_cache_logout_all' );

function wp_cache_add_index_protection() {
	global $cache_path, $blog_cache_dir;

	if ( is_dir( $cache_path ) && false == is_file( "$cache_path/index.html" ) ) {
		$page = wp_remote_get( home_url( "/wp-content/cache/" ) );
		if ( false == is_wp_error( $page ) ) {
			if ( false == get_site_option( 'wp_super_cache_index_detected' )
				&& $page[ 'response' ][ 'code' ] == 200
				&& stripos( $page[ 'body' ], 'index of' ) ) {
				add_site_option( 'wp_super_cache_index_detected', 1 ); // only show this once
			}
		}
		insert_with_markers( $cache_path . '.htaccess', "INDEX", array( 'Options -Indexes' ) );
	}

	$directories = array( $cache_path, $cache_path . '/supercache/', $cache_path . '/blogs/', $blog_cache_dir, $blog_cache_dir . "/meta" );
	foreach( $directories as $dir ) {
		if ( false == is_dir( $dir ) )
			@mkdir( $dir );
		if ( is_dir( $dir ) && false == is_file( "$dir/index.html" ) ) {
			$fp = @fopen( "$dir/index.html", 'w' );
			if ( $fp )
				fclose( $fp );
		}
	}
}

function wp_cache_add_site_cache_index() {
	global $cache_path;

	wp_cache_add_index_protection(); // root and supercache

	if ( is_dir( $cache_path . "blogs" ) ) {
		$dir = new DirectoryIterator( $cache_path . "blogs" );
		foreach( $dir as $fileinfo ) {
			if ( $fileinfo->isDot() ) {
				continue;
			}
			if ( $fileinfo->isDir() ) {
				$directory = $cache_path . "blogs/" . $fileinfo->getFilename();
				if ( false == is_file( $directory . "/index.html" ) ) {
					$fp = @fopen( $directory . "/index.html", 'w' );
					if ( $fp )
						fclose( $fp );
				}
				if ( is_dir( $directory . "/meta" ) ) {
					if ( false == is_file( $directory . "/meta/index.html" ) ) {
						$fp = @fopen( $directory . "/meta/index.html", 'w' );
						if ( $fp )
							fclose( $fp );
					}
				}
			}
		}
	}
}

function wp_cache_verify_cache_dir() {
	global $cache_path, $blog_cache_dir;

	$dir = dirname($cache_path);
	if ( !file_exists($cache_path) ) {
		if ( !is_writeable_ACLSafe( $dir ) || !($dir = mkdir( $cache_path ) ) ) {
				echo "<strong>" . __( 'Error', 'wp-super-cache' ) . ":</strong> " . sprintf( __( 'Your cache directory (<strong>%1$s</strong>) did not exist and couldn&#8217;t be created by the web server. Check %1$s permissions.', 'wp-super-cache' ), $dir );
				return false;
		}
	}
	if ( !is_writeable_ACLSafe($cache_path)) {
		echo "<strong>" . __( 'Error', 'wp-super-cache' ) . ":</strong> " . sprintf( __( 'Your cache directory (<strong>%1$s</strong>) or <strong>%2$s</strong> need to be writable for this plugin to work. Double-check it.', 'wp-super-cache' ), $cache_path, $dir );
		return false;
	}

	if ( '/' != substr($cache_path, -1)) {
		$cache_path .= '/';
	}

	if( false == is_dir( $blog_cache_dir ) ) {
		@mkdir( $cache_path . "blogs" );
		if( $blog_cache_dir != $cache_path . "blogs/" )
			@mkdir( $blog_cache_dir );
	}

	if( false == is_dir( $blog_cache_dir . 'meta' ) )
		@mkdir( $blog_cache_dir . 'meta' );

	wp_cache_add_index_protection();
	return true;
}

function wp_cache_verify_config_file() {
	global $wp_cache_config_file, $wp_cache_config_file_sample, $sem_id, $cache_path;
	global $WPSC_HTTP_HOST;

	$new = false;
	$dir = dirname($wp_cache_config_file);

	if ( file_exists($wp_cache_config_file) ) {
		$lines = join( ' ', file( $wp_cache_config_file ) );
		if( strpos( $lines, 'WPCACHEHOME' ) === false ) {
			if( is_writeable_ACLSafe( $wp_cache_config_file ) ) {
				@unlink( $wp_cache_config_file );
			} else {
				echo "<strong>" . __( 'Error', 'wp-super-cache' ) . ":</strong> " . sprintf( __( 'Your WP-Cache config file (<strong>%s</strong>) is out of date and not writable by the Web server. Please delete it and refresh this page.', 'wp-super-cache' ), $wp_cache_config_file );
				return false;
			}
		}
	} elseif( !is_writeable_ACLSafe($dir)) {
		echo "<strong>" . __( 'Error', 'wp-super-cache' ) . ":</strong> " . sprintf( __( 'Configuration file missing and %1$s  directory (<strong>%2$s</strong>) is not writable by the web server. Check its permissions.', 'wp-super-cache' ), WP_CONTENT_DIR, $dir );
		return false;
	}

	if ( !file_exists($wp_cache_config_file) ) {
		if ( !file_exists($wp_cache_config_file_sample) ) {
			echo "<strong>" . __( 'Error', 'wp-super-cache' ) . ":</strong> " . sprintf( __( 'Sample WP-Cache config file (<strong>%s</strong>) does not exist. Verify your installation.', 'wp-super-cache' ), $wp_cache_config_file_sample );
			return false;
		}
		copy($wp_cache_config_file_sample, $wp_cache_config_file);
		$dir = str_replace( str_replace( '\\', '/', WP_CONTENT_DIR ), '', str_replace( '\\', '/', dirname(__FILE__) ) );
		if( is_file( dirname(__FILE__) . '/wp-cache-config-sample.php' ) ) {
			wp_cache_replace_line('define\(\ \'WPCACHEHOME', "\tdefine( 'WPCACHEHOME', WP_CONTENT_DIR . \"{$dir}/\" );", $wp_cache_config_file);
		} elseif( is_file( dirname(__FILE__) . '/wp-super-cache/wp-cache-config-sample.php' ) ) {
			wp_cache_replace_line('define\(\ \'WPCACHEHOME', "\tdefine( 'WPCACHEHOME', WP_CONTENT_DIR . \"{$dir}/wp-super-cache/\" );", $wp_cache_config_file);
		}
		$new = true;
	}
	if( $sem_id == 5419 && $cache_path != '' ) {
		$sem_id = crc32( $WPSC_HTTP_HOST . $cache_path ) & 0x7fffffff;
		wp_cache_replace_line('sem_id', '$sem_id = ' . $sem_id . ';', $wp_cache_config_file);
	}
	if ( $new ) {
		require($wp_cache_config_file);
		wpsc_set_default_gc( true );
	}
	return true;
}

function wp_cache_create_advanced_cache() {
	global $wp_cache_link, $wp_cache_file;
	if ( file_exists( ABSPATH . 'wp-config.php') ) {
		$global_config_file = ABSPATH . 'wp-config.php';
	} else {
		$global_config_file = dirname(ABSPATH) . '/wp-config.php';
	}

	$line = 'define( \'WPCACHEHOME\', \'' . dirname( __FILE__ ) . '/\' );';
	if ( !is_writeable_ACLSafe($global_config_file) || !wp_cache_replace_line('define *\( *\'WPCACHEHOME\'', $line, $global_config_file ) ) {
			echo '<div class="notice notice-error"><h3>' . __( 'Warning', 'wp-super-cache' ) . "! <em>" . sprintf( __( 'Could not update %s!</em> WPCACHEHOME must be set in config file.', 'wp-super-cache' ), $global_config_file ) . "</h3>";
			return false;
	}
	$ret = true;

	$file = file_get_contents( $wp_cache_file );
	$fp = @fopen( $wp_cache_link, 'w' );
	if( $fp ) {
		fputs( $fp, $file );
		fclose( $fp );
	} else {
		$ret = false;
	}
	return $ret;
}

function wp_cache_check_link() {
	global $wp_cache_link, $wp_cache_file;

 	$ret = true;
	if( file_exists($wp_cache_link) ) {
		$file = file_get_contents( $wp_cache_link );
		if( strpos( $file, "WP SUPER CACHE 0.8.9.1" ) || strpos( $file, "WP SUPER CACHE 1.2" ) ) {
			return true;
		} else {
			if( !@unlink($wp_cache_link) ) {
				$ret = false;
			} else {
				$ret = wp_cache_create_advanced_cache();
			}
		}
	} else {
		$ret = wp_cache_create_advanced_cache();
	}

	if( false == $ret ) {
		echo '<div class="notice notice-error"><h3>' . __( 'Warning', 'wp-super-cache' ) . "! <em>" . sprintf( __( '%s/advanced-cache.php</em> does not exist or cannot be updated.', 'wp-super-cache' ), WP_CONTENT_DIR ) . "</h3>";
		echo "<p><ul><li>" . __( '1. If it already exists, please delete the file first.', 'wp-super-cache' ) . "</li>";
		echo "<li>" . sprintf( __( '2. Make %1$s writable using the chmod command through your ftp or server software. (<em>chmod 777 %1$s</em>) and refresh this page. This is only a temporary measure and you&#8217;ll have to make it read only afterwards again. (Change 777 to 755 in the previous command)', 'wp-super-cache' ), WP_CONTENT_DIR ) . "</li>";
		echo "<li>" . sprintf( __( '3. Refresh this page to update <em>%s/advanced-cache.php</em>', 'wp-super-cache' ), WP_CONTENT_DIR ) . "</li></ul>";
		echo sprintf( __( 'If that doesn&#8217;t work, make sure the file <em>%s/advanced-cache.php</em> doesn&#8217;t exist:', 'wp-super-cache' ), WP_CONTENT_DIR ) . "<ol>";
		printf( __( '<li>1. Open <em>%1$s$wp_cache_file</em> in a text editor.</li><li>2. Change the text <em>CACHEHOME</em> to <em>%2$s</em></li><li>3. Save the file and copy it to <em>%3$s</em> and refresh this page.</li>', 'wp-super-cache' ), $wp_cache_file, WPCACHEHOME, $wp_cache_link );
		echo "</div>";
		return false;
	}
	return true;
}

function wp_cache_check_global_config() {
	global $wp_cache_check_wp_config;

	if ( !isset( $wp_cache_check_wp_config ) )
		return true;


	if ( file_exists( ABSPATH . 'wp-config.php') ) {
		$global = ABSPATH . 'wp-config.php';
	} else {
		$global = dirname(ABSPATH) . '/wp-config.php';
	}

	$line = 'define(\'WP_CACHE\', true);';
	if (!is_writeable_ACLSafe($global) || !wp_cache_replace_line('define *\( *\'WP_CACHE\'', $line, $global) ) {
		if ( defined( 'WP_CACHE' ) && constant( 'WP_CACHE' ) == false ) {
			echo '<div class="notice notice-error">' . __( "<h3>WP_CACHE constant set to false</h3><p>The WP_CACHE constant is used by WordPress to load the code that serves cached pages. Unfortunately, it is set to false. Please edit your wp-config.php and add or edit the following line above the final require_once command:<br /><br /><code>define('WP_CACHE', true);</code></p>", 'wp-super-cache' ) . "</div>";
		} else {
			echo '<div class="notice notice-error"><p>' . __( "<strong>Error: WP_CACHE is not enabled</strong> in your <code>wp-config.php</code> file and I couldn&#8217;t modify it.", 'wp-super-cache' ) . "</p>";
			echo "<p>" . sprintf( __( "Edit <code>%s</code> and add the following line:<br /> <code>define('WP_CACHE', true);</code><br />Otherwise, <strong>WP-Cache will not be executed</strong> by WordPress core. ", 'wp-super-cache' ), $global ) . "</p></div>";
		}
		return false;
	}  else {
		echo "<div class='notice notice-warning'>" . __( '<h3>WP_CACHE constant added to wp-config.php</h3><p>If you continue to see this warning message please see point 5 of the <a href="http://wordpress.org/plugins/wp-super-cache/faq/">Troubleshooting Guide</a>. The WP_CACHE line must be moved up.', 'wp-super-cache' ) . "</p></div>";
	}
	return true;
}

function wpsc_generate_sizes_array() {
	$sizes = array();
	$cache_types  = apply_filters( 'wpsc_cache_types', array( 'supercache', 'wpcache' ) );
	$cache_states = apply_filters( 'wpsc_cache_state', array( 'expired', 'cached' ) );
	foreach( $cache_types as $type ) {
		reset( $cache_states );
		foreach( $cache_states as $state ) {
			$sizes[ $type ][ $state ] = 0;
		}
		$sizes[ $type ][ 'fsize' ] = 0;
		$sizes[ $type ][ 'cached_list' ] = array();
		$sizes[ $type ][ 'expired_list' ] = array();
	}
	return $sizes;
}

function wp_cache_format_fsize( $fsize ) {
	if ( $fsize > 1024 ) {
		$fsize = number_format( $fsize / 1024, 2 ) . "MB";
	} elseif ( $fsize != 0 ) {
		$fsize = number_format( $fsize, 2 ) . "KB";
	} else {
		$fsize = "0KB";
	}
	return $fsize;
}

function wp_cache_regenerate_cache_file_stats() {
	global $supercachedir, $file_prefix, $wp_cache_preload_on, $cache_max_time;

	if ( $supercachedir == '' )
		$supercachedir = get_supercache_dir();

	$sizes = wpsc_generate_sizes_array();
	$now = time();
	if (is_dir( $supercachedir ) ) {
		if ( $dh = opendir( $supercachedir ) ) {
			while ( ( $entry = readdir( $dh ) ) !== false ) {
				if ( $entry != '.' && $entry != '..' ) {
					$sizes = wpsc_dirsize( trailingslashit( $supercachedir ) . $entry, $sizes );
				}
			}
			closedir( $dh );
		}
	}
	foreach( $sizes as $cache_type => $list ) {
		foreach( array( 'cached_list', 'expired_list' ) as $status ) {
			$cached_list = array();
			foreach( $list[ $status ] as $dir => $details ) {
				if ( $details[ 'files' ] == 2 && !isset( $details[ 'upper_age' ] ) ) {
					$details[ 'files' ] = 1;
				}
				$cached_list[ $dir ] = $details;
			}
			$sizes[ $cache_type ][ $status ] = $cached_list;
		}
	}
	$cache_stats = array( 'generated' => time(), 'supercache' => $sizes[ 'supercache' ], 'wpcache' => $sizes[ 'wpcache' ] );
	update_option( 'supercache_stats', $cache_stats );
	return $cache_stats;
}

function wp_cache_files() {
	global $cache_path, $file_prefix, $cache_max_time, $valid_nonce, $supercachedir, $super_cache_enabled, $blog_cache_dir, $cache_compression;
	global $wp_cache_object_cache, $wp_cache_preload_on;

	if ( '/' != substr($cache_path, -1)) {
		$cache_path .= '/';
	}

	if ( $valid_nonce ) {
		if(isset($_REQUEST['wp_delete_cache'])) {
			wp_cache_clean_cache($file_prefix);
			$_GET[ 'action' ] = 'regenerate_cache_stats';
		}
		if ( isset( $_REQUEST[ 'wp_delete_all_cache' ] ) ) {
			wp_cache_clean_cache( $file_prefix, true );
			$_GET[ 'action' ] = 'regenerate_cache_stats';
		}
		if(isset($_REQUEST['wp_delete_expired'])) {
			wp_cache_clean_expired($file_prefix);
			$_GET[ 'action' ] = 'regenerate_cache_stats';
		}
	}
	echo "<a name='listfiles'></a>";
	echo '<fieldset class="options" id="show-this-fieldset"><h3>' . __( 'Cache Contents', 'wp-super-cache' ) . '</h3>';

	if ( $wp_cache_object_cache ) {
		echo "<p>" . __( "Object cache in use. No cache listing available.", 'wp-super-cache' ) . "</p>";
		wp_cache_delete_buttons();
		echo "</fieldset>";
		return false;
	}

	$cache_stats = get_option( 'supercache_stats' );
	if ( !is_array( $cache_stats ) || ( isset( $_GET[ 'listfiles' ] ) ) || ( $valid_nonce && array_key_exists('action', $_GET) && $_GET[ 'action' ] == 'regenerate_cache_stats' ) ) {
	$count = 0;
	$expired = 0;
	$now = time();
	if ( ( $handle = @opendir( $blog_cache_dir ) ) ) {
		$wp_cache_fsize = 0;
		if ( $valid_nonce && isset( $_GET[ 'action' ] ) && $_GET[ 'action' ] == 'deletewpcache' ) {
			$deleteuri = wpsc_deep_replace( array( '..', '\\', 'index.php' ), preg_replace( '/[ <>\'\"\r\n\t\(\)]/', '', base64_decode( $_GET[ 'uri' ] ) ) );
		} else {
			$deleteuri = '';
		}

		if ( $valid_nonce && isset( $_GET[ 'action' ] ) && $_GET[ 'action' ] == 'deletesupercache' ) {
			$supercacheuri = wpsc_deep_replace( array( '..', '\\', 'index.php' ), preg_replace( '/[ <>\'\"\r\n\t\(\)]/', '', preg_replace("/(\?.*)?$/", '', base64_decode( $_GET[ 'uri' ] ) ) ) );
			$supercacheuri = trailingslashit( realpath( $cache_path . 'supercache/' . $supercacheuri ) );
			if ( wp_cache_confirm_delete( $supercacheuri ) ) {
				printf( __( "Deleting supercache file: <strong>%s</strong><br />", 'wp-super-cache' ), $supercacheuri );
				wpsc_delete_files( $supercacheuri );
				prune_super_cache( $supercacheuri . 'page', true );
				@rmdir( $supercacheuri );
			} else {
				wp_die( __( 'Warning! You are not allowed to delete that file', 'wp-super-cache' ) );
			}
		}
		while( false !== ( $file = readdir( $handle ) ) ) {
			if ( strpos( $file, $file_prefix ) !== false && substr( $file, -4 ) == '.php' ) {
				if ( false == file_exists( $blog_cache_dir . 'meta/' . $file ) ) {
					@unlink( $blog_cache_dir . $file );
					continue; // meta does not exist
				}
				$mtime = filemtime( $blog_cache_dir . 'meta/' . $file );
				$fsize = @filesize( $blog_cache_dir . $file );
				if ( $fsize > 0 )
					$fsize = $fsize - 15; // die() command takes 15 bytes at the start of the file

				$age = $now - $mtime;
				if ( $valid_nonce && isset( $_GET[ 'listfiles' ] ) ) {
					$meta = json_decode( wp_cache_get_legacy_cache( $blog_cache_dir . 'meta/' . $file ), true );
					if ( $deleteuri != '' && $meta[ 'uri' ] == $deleteuri ) {
						printf( __( "Deleting wp-cache file: <strong>%s</strong><br />", 'wp-super-cache' ), esc_html( $deleteuri ) );
						@unlink( $blog_cache_dir . 'meta/' . $file );
						@unlink( $blog_cache_dir . $file );
						continue;
					}
					$meta[ 'age' ] = $age;
					foreach( $meta as $key => $val )
						$meta[ $key ] = esc_html( $val );
					if ( $cache_max_time > 0 && $age > $cache_max_time ) {
						$expired_list[ $age ][] = $meta;
					} else {
						$cached_list[ $age ][] = $meta;
					}
				}

				if ( $cache_max_time > 0 && $age > $cache_max_time ) {
					$expired++;
				} else {
					$count++;
				}
				$wp_cache_fsize += $fsize;
			}
		}
		closedir($handle);
	}
	if( $wp_cache_fsize != 0 ) {
		$wp_cache_fsize = $wp_cache_fsize/1024;
	} else {
		$wp_cache_fsize = 0;
	}
	if( $wp_cache_fsize > 1024 ) {
		$wp_cache_fsize = number_format( $wp_cache_fsize / 1024, 2 ) . "MB";
	} elseif( $wp_cache_fsize != 0 ) {
		$wp_cache_fsize = number_format( $wp_cache_fsize, 2 ) . "KB";
	} else {
		$wp_cache_fsize = '0KB';
	}
	$cache_stats = wp_cache_regenerate_cache_file_stats();
	} else {
		echo "<p>" . __( 'Cache stats are not automatically generated. You must click the link below to regenerate the stats on this page.', 'wp-super-cache' ) . "</p>";
		echo "<a href='" . wp_nonce_url( add_query_arg( array( 'page' => 'wpsupercache', 'tab' => 'contents', 'action' => 'regenerate_cache_stats' ) ), 'wp-cache' ) . "'>" . __( 'Regenerate cache stats', 'wp-super-cache' ) . "</a>";
		if ( is_array( $cache_stats ) ) {
			echo "<p>" . sprintf( __( 'Cache stats last generated: %s minutes ago.', 'wp-super-cache' ), number_format( ( time() - $cache_stats[ 'generated' ] ) / 60 ) ) . "</p>";
		}
		$cache_stats = get_option( 'supercache_stats' );
	}// regerate stats cache

	if ( is_array( $cache_stats ) ) {
		$fsize = wp_cache_format_fsize( $cache_stats[ 'wpcache' ][ 'fsize' ] / 1024 );
		echo "<p><strong>" . __( 'WP-Cache', 'wp-super-cache' ) . " ({$fsize})</strong></p>";
		echo "<ul><li>" . sprintf( __( '%s Cached Pages', 'wp-super-cache' ), $cache_stats[ 'wpcache' ][ 'cached' ] ) . "</li>";
		echo "<li>" . sprintf( __( '%s Expired Pages', 'wp-super-cache' ),    $cache_stats[ 'wpcache' ][ 'expired' ] ) . "</li></ul>";
		$divisor = $cache_compression == 1 ? 2 : 1;
		if ( array_key_exists('fsize', (array)$cache_stats[ 'supercache' ]) )
			$fsize = $cache_stats[ 'supercache' ][ 'fsize' ] / 1024;
		else
			$fsize = 0;
		$fsize = wp_cache_format_fsize( $fsize );
		echo "<p><strong>" . __( 'WP-Super-Cache', 'wp-super-cache' ) . " ({$fsize})</strong></p>";
		echo "<ul><li>" . sprintf( __( '%s Cached Pages', 'wp-super-cache' ), intval( $cache_stats[ 'supercache' ][ 'cached' ] / $divisor ) ) . "</li>";
		if ( isset( $now ) && isset( $cache_stats ) )
			$age = intval( ( $now - $cache_stats['generated'] ) / 60 );
		else
			$age = 0;
		echo "<li>" . sprintf( __( '%s Expired Pages', 'wp-super-cache' ), intval( $cache_stats[ 'supercache' ][ 'expired' ] / $divisor ) ) . "</li></ul>";
		if ( $valid_nonce && array_key_exists('listfiles', $_GET) && $_GET[ 'listfiles' ] ) {
			echo "<div style='padding: 10px; border: 1px solid #333; height: 400px; width: 90%; overflow: auto'>";
			$cache_description = array( 'supercache' => __( 'Super Cached Files', 'wp-super-cache' ), 'wpcache' => __( 'Full Cache Files', 'wp-super-cache' ) );
			foreach( $cache_stats as $type => $details ) {
				if ( is_array( $details ) == false )
					continue;
				foreach( array( 'cached_list' => 'Fresh', 'expired_list' => 'Stale' ) as $list => $description ) {
					if ( is_array( $details[ $list ] ) & !empty( $details[ $list ] ) ) {
						echo "<h4>" . sprintf( __( '%s %s Files', 'wp-super-cache' ), $description, $cache_description[ $type ] ) . "</h4>";
						echo "<table class='widefat'><tr><th>#</th><th>" . __( 'URI', 'wp-super-cache' ) . "</th><th>" . __( 'Files', 'wp-super-cache' ) . "</th><th>" . __( 'Age', 'wp-super-cache' ) . "</th><th>" . __( 'Delete', 'wp-super-cache' ) . "</th></tr>";
						$c = 1;
						$flip = 1;

						ksort( $details[ $list ] );
						foreach( $details[ $list ] as $directory => $d ) {
							if ( isset( $d[ 'upper_age' ] ) ) {
								$age = "{$d[ 'lower_age' ]} - {$d[ 'upper_age' ]}";
							} else {
								$age = $d[ 'lower_age' ];
							}
							$bg = $flip ? 'style="background: #EAEAEA;"' : '';
							echo "<tr $bg><td>$c</td><td> <a href='http://{$directory}'>{$directory}</a></td><td>{$d[ 'files' ]}</td><td>{$age}</td><td><a href='" . wp_nonce_url( add_query_arg( array( 'page' => 'wpsupercache', 'action' => 'deletesupercache', 'uri' => base64_encode( $directory ) ) ), 'wp-cache' ) . "#listfiles'>X</a></td></tr>\n";
							$flip = !$flip;
							$c++;
						}
						echo "</table>";
					}
				}
			}
			echo "</div>";
			echo "<p><a href='?page=wpsupercache&tab=contents#top'>" . __( 'Hide file list', 'wp-super-cache' ) . "</a></p>";
		} elseif ( $cache_stats[ 'supercache' ][ 'cached' ] > 500 || $cache_stats[ 'supercache' ][ 'expired' ] > 500 || ( $cache_stats[ 'wpcache' ][ 'cached' ] / $divisor ) > 500 || ( $cache_stats[ 'wpcache' ][ 'expired' ] / $divisor) > 500 ) {
			echo "<p><em>" . __( 'Too many cached files, no listing possible.', 'wp-super-cache' ) . "</em></p>";
		} else {
			echo "<p><a href='" . wp_nonce_url( add_query_arg( array( 'page' => 'wpsupercache', 'listfiles' => '1' ) ), 'wp-cache' ) . "#listfiles'>" . __( 'List all cached files', 'wp-super-cache' ) . "</a></p>";
		}
		if ( $cache_max_time > 0 )
			echo "<p>" . sprintf( __( 'Expired files are files older than %s seconds. They are still used by the plugin and are deleted periodically.', 'wp-super-cache' ), $cache_max_time ) . "</p>";
		if ( $wp_cache_preload_on )
			echo "<p>" . __( 'Preload mode is enabled. Supercache files will never be expired.', 'wp-super-cache' ) . "</p>";
	} // cache_stats
	wp_cache_delete_buttons();

	echo '</fieldset>';
}

function wp_cache_delete_buttons() {

	echo '<form name="wp_cache_content_expired" action="#listfiles" method="post">';
	echo '<input type="hidden" name="wp_delete_expired" />';
	echo '<div class="submit" style="float:left"><input class="button-primary" type="submit" ' . SUBMITDISABLED . 'value="' . __( 'Delete Expired', 'wp-super-cache' ) . '" /></div>';
	wp_nonce_field('wp-cache');
	echo "</form>\n";

	echo '<form name="wp_cache_content_delete" action="#listfiles" method="post">';
	echo '<input type="hidden" name="wp_delete_cache" />';
	echo '<div class="submit" style="float:left;margin-left:10px"><input id="deletepost" class="button-secondary" type="submit" ' . SUBMITDISABLED . 'value="' . __( 'Delete Cache', 'wp-super-cache' ) . '" /></div>';
	wp_nonce_field('wp-cache');
	echo "</form>\n";
	if ( ( defined( 'VHOST' ) || defined( 'SUBDOMAIN_INSTALL' ) || defined( 'SUNRISE' ) || ( defined( 'WP_ALLOW_MULTISITE' ) && constant( 'WP_ALLOW_MULTISITE' ) == true ) ) && wpsupercache_site_admin() ) {
		echo '<form name="wp_cache_content_delete" action="#listfiles" method="post">';
		echo '<input type="hidden" name="wp_delete_all_cache" />';
		echo '<div class="submit" style="float:left;margin-left:10px"><input id="deleteallpost" class="button-secondary" type="submit" ' . SUBMITDISABLED . 'value="' . __( 'Delete Cache On All Blogs', 'wp-super-cache' ) . '" /></div>';
		wp_nonce_field('wp-cache');
		echo "</form>\n";
	}
}

function delete_cache_dashboard() {
	if ( false == wpsupercache_site_admin() )
		return false;

	if ( function_exists('current_user_can') && !current_user_can('manage_options') )
		return false;

	echo "<li><a href='" . wp_nonce_url( 'options-general.php?page=wpsupercache&wp_delete_cache=1', 'wp-cache' ) . "' target='_blank' title='" . __( 'Delete Super Cache cached files (opens in new window)', 'wp-super-cache' ) . "'>" . __( 'Delete Cache', 'wp-super-cache' ) . "</a></li>";
}
add_action( 'dashmenu', 'delete_cache_dashboard' );

function wpsc_dirsize($directory, $sizes) {
	global $cache_max_time, $cache_path, $valid_nonce, $wp_cache_preload_on, $file_prefix;
	$now = time();

	if (is_dir($directory)) {
		if( $dh = opendir( $directory ) ) {
			while( ( $entry = readdir( $dh ) ) !== false ) {
				if ($entry != '.' && $entry != '..') {
					$sizes = wpsc_dirsize( trailingslashit( $directory ) . $entry, $sizes );
				}
			}
			closedir($dh);
		}
	} else {
		if ( is_file( $directory ) && strpos( $directory, 'meta-' . $file_prefix ) === false ) {
			if ( strpos( $directory, '/' . $file_prefix ) !== false ) {
				$cache_type = 'wpcache';
			} else {
				$cache_type = 'supercache';
			}
			$keep_fresh = false;
			if ( $cache_type == 'supercache' && $wp_cache_preload_on )
				$keep_fresh = true;
			$filem = filemtime( $directory );
			if ( $keep_fresh == false && $cache_max_time > 0 && $filem + $cache_max_time <= $now ) {
				$cache_status = 'expired';
			} else {
				$cache_status = 'cached';
			}
			$sizes[ $cache_type ][ $cache_status ]+=1;
			if ( $valid_nonce && isset( $_GET[ 'listfiles' ] ) ) {
				$dir = str_replace( $cache_path . 'supercache/' , '', dirname( $directory ) );
				$age = $now - $filem;
				if ( false == isset( $sizes[ $cache_type ][ $cache_status . '_list' ][ $dir ] ) ) {
					$sizes[ $cache_type ][ $cache_status . '_list' ][ $dir ][ 'lower_age' ] = $age;
					$sizes[ $cache_type ][ $cache_status . '_list' ][ $dir ][ 'files' ] = 1;
				} else {
					$sizes[ $cache_type ][ $cache_status . '_list' ][ $dir ][ 'files' ] += 1;
					if ( $age <= $sizes[ $cache_type ][ $cache_status . '_list' ][ $dir ][ 'lower_age' ] ) {

						if ( $age < $sizes[ $cache_type ][ $cache_status . '_list' ][ $dir ][ 'lower_age' ] && !isset( $sizes[ $cache_type ][ $cache_status . '_list' ][ $dir ][ 'upper_age' ] ) )
							$sizes[ $cache_type ][ $cache_status . '_list' ][ $dir ][ 'upper_age' ] = $sizes[ $cache_type ][ $cache_status . '_list' ][ $dir ][ 'lower_age' ];

						$sizes[ $cache_type ][ $cache_status . '_list' ][ $dir ][ 'lower_age' ] = $age;

					} elseif ( !isset( $sizes[ $cache_type ][ $cache_status . '_list' ][ $dir ][ 'upper_age' ] ) || $age > $sizes[ $cache_type ][ $cache_status . '_list' ][ $dir ][ 'upper_age' ] ) {

						$sizes[ $cache_type ][ $cache_status . '_list' ][ $dir ][ 'upper_age' ] = $age;

					}
				}
			}
			if ( ! isset( $sizes[ 'fsize' ] ) )
				$sizes[ $cache_type ][ 'fsize' ] = @filesize( $directory );
			else
				$sizes[ $cache_type ][ 'fsize' ] += @filesize( $directory );
		}
	}
	return $sizes;
}

function wp_cache_clean_cache( $file_prefix, $all = false ) {
	global $cache_path, $supercachedir, $blog_cache_dir, $wp_cache_object_cache;

	if ( $wp_cache_object_cache && function_exists( "reset_oc_version" ) )
		reset_oc_version();

	if ( $all == true && wpsupercache_site_admin() && function_exists( 'prune_super_cache' ) ) {
		prune_super_cache( $cache_path, true );
		return true;
	}
	if ( $supercachedir == '' )
		$supercachedir = get_supercache_dir();

	if (function_exists ('prune_super_cache')) {
		if( is_dir( $supercachedir ) ) {
			prune_super_cache( $supercachedir, true );
		} elseif( is_dir( $supercachedir . '.disabled' ) ) {
			prune_super_cache( $supercachedir . '.disabled', true );
		}
		$_POST[ 'super_cache_stats' ] = 1; // regenerate super cache stats;
	} else {
		wp_cache_debug( 'Warning! prune_super_cache() not found in wp-cache.php', 1 );
	}

	wp_cache_clean_legacy_files( $blog_cache_dir, $file_prefix );
	wp_cache_clean_legacy_files( $cache_path, $file_prefix );

}

function wpsc_delete_url_cache( $url ) {
	$dir = str_replace( get_option( 'home' ), '', $url );
	if ( $dir != '' ) {
		$supercachedir = get_supercache_dir();
		wpsc_delete_files( $supercachedir . $dir );
		prune_super_cache( $supercachedir . $dir . '/page', true );
		return true;
	} else {
		return false;
	}
}

function wpsc_delete_post_cache( $id ) {
	$post = get_post( $id );
	wpsc_delete_url_cache( get_author_posts_url( $post->post_author ) );
	$permalink = get_permalink( $id );
	if ( $permalink != '' ) {
		wpsc_delete_url_cache( $permalink );
		return true;
	} else {
		return false;
	}
}

function wp_cache_clean_legacy_files( $dir, $file_prefix ) {
	global $wpdb;

	$dir = trailingslashit( $dir );
	if ( @is_dir( $dir . 'meta' ) == false )
		return false;

	if ( $handle = @opendir( $dir ) ) {
		while ( false !== ( $file = readdir( $handle ) ) ) {
			if ( is_file( $dir . $file ) == false || $file == 'index.html' ) {
				continue;
			}

			if ( strpos( $file, $file_prefix ) !== false ) {
				if ( strpos( $file, '.html' ) ) {
					// delete old WPCache files immediately
					@unlink( $dir . $file);
					@unlink( $dir . 'meta/' . str_replace( '.html', '.meta', $file ) );
				} else {
					$meta = json_decode( wp_cache_get_legacy_cache( $dir . 'meta/' . $file ), true );
					if ( ( defined( 'VHOST' ) || defined( 'SUBDOMAIN_INSTALL' ) || defined( 'SUNRISE' ) || ( defined( 'WP_ALLOW_MULTISITE' ) && constant( 'WP_ALLOW_MULTISITE' ) == true ) ) && $meta[ 'blog_id' ] != $wpdb->blogid )
						continue;
					@unlink( $dir . $file);
					@unlink( $dir . 'meta/' . $file);
				}
			}
		}
		closedir($handle);
	}
}

function wp_cache_clean_expired($file_prefix) {
	global $cache_max_time, $blog_cache_dir, $wp_cache_preload_on;

	if ( $cache_max_time == 0 ) {
		return false;
	}

	// If phase2 was compiled, use its function to avoid race-conditions
	if(function_exists('wp_cache_phase2_clean_expired')) {
		if ( $wp_cache_preload_on != 1 && function_exists ('prune_super_cache')) {
			$dir = get_supercache_dir();
			if( is_dir( $dir ) ) {
				prune_super_cache( $dir );
			} elseif( is_dir( $dir . '.disabled' ) ) {
				prune_super_cache( $dir . '.disabled' );
			}
			$_POST[ 'super_cache_stats' ] = 1; // regenerate super cache stats;
		}
		return wp_cache_phase2_clean_expired($file_prefix);
	}

	$now = time();
	if ( $handle = @opendir( $blog_cache_dir ) ) {
		while ( false !== ( $file = readdir( $handle ) ) ) {
			if ( strpos( $file, $file_prefix ) !== false ) {
				if ( strpos( $file, '.html' ) ) {
					@unlink( $blog_cache_dir . $file);
					@unlink( $blog_cache_dir . 'meta/' . str_replace( '.html', '.meta', $file ) );
				} elseif ( ( filemtime( $blog_cache_dir . $file ) + $cache_max_time ) <= $now ) {
					@unlink( $blog_cache_dir . $file );
					@unlink( $blog_cache_dir . 'meta/' . $file );
				}
			}
		}
		closedir($handle);
	}
}

function wpsc_remove_marker( $filename, $marker ) {
	if (!file_exists( $filename ) || is_writeable_ACLSafe( $filename ) ) {
		if (!file_exists( $filename ) ) {
			return '';
		} else {
			$markerdata = explode( "\n", implode( '', file( $filename ) ) );
		}

		$f = fopen( $filename, 'w' );
		if ( $markerdata ) {
			$state = true;
			foreach ( $markerdata as $n => $markerline ) {
				if (strpos($markerline, '# BEGIN ' . $marker) !== false)
					$state = false;
				if ( $state ) {
					if ( $n + 1 < count( $markerdata ) )
						fwrite( $f, "{$markerline}\n" );
					else
						fwrite( $f, "{$markerline}" );
				}
				if (strpos($markerline, '# END ' . $marker) !== false) {
					$state = true;
				}
			}
		}
		return true;
	} else {
		return false;
	}
}

function wp_super_cache_footer() {
	?><p id='supercache'><?php printf( __( '%1$s is Stephen Fry proof thanks to caching by %2$s', 'wp-super-cache' ), get_bloginfo( 'name', 'display' ), '<a href="https://odd.blog/wp-super-cache/">WP Super Cache</a>' ); ?></p><?php
}
if( isset( $wp_cache_hello_world ) && $wp_cache_hello_world )
	add_action( 'wp_footer', 'wp_super_cache_footer' );

if( get_option( 'gzipcompression' ) )
	update_option( 'gzipcompression', 0 );

// Catch 404 requests. Themes that use query_posts() destroy $wp_query->is_404
function wp_cache_catch_404() {
	global $wp_cache_404;
	$wp_cache_404 = false;
	if( is_404() )
		$wp_cache_404 = true;
}
add_action( 'template_redirect', 'wp_cache_catch_404' );

function wp_cache_favorite_action( $actions ) {
	if ( false == wpsupercache_site_admin() )
		return $actions;

	if ( function_exists('current_user_can') && !current_user_can('manage_options') )
		return $actions;

	$actions[ wp_nonce_url( 'options-general.php?page=wpsupercache&wp_delete_cache=1&tab=contents', 'wp-cache' ) ] = array( __( 'Delete Cache', 'wp-super-cache' ), 'manage_options' );

	return $actions;
}
add_filter( 'favorite_actions', 'wp_cache_favorite_action' );

function wp_cache_plugin_notice( $plugin ) {
	global $cache_enabled;
 	if( $plugin == 'wp-super-cache/wp-cache.php' && !$cache_enabled && function_exists( "admin_url" ) )
		echo '<td colspan="5" class="plugin-update">' . sprintf( __( 'WP Super Cache must be configured. Go to <a href="%s">the admin page</a> to enable and configure the plugin.', 'wp-super-cache' ), admin_url( 'options-general.php?page=wpsupercache' ) ) . '</td>';
}
add_action( 'after_plugin_row', 'wp_cache_plugin_notice' );

function wp_cache_plugin_actions( $links, $file ) {
 	if( $file == 'wp-super-cache/wp-cache.php' && function_exists( "admin_url" ) ) {
		$settings_link = '<a href="' . admin_url( 'options-general.php?page=wpsupercache' ) . '">' . __( 'Settings', 'wp-super-cache' ) . '</a>';
		array_unshift( $links, $settings_link ); // before other links
	}
	return $links;
}
add_filter( 'plugin_action_links', 'wp_cache_plugin_actions', 10, 2 );

function wp_cache_admin_notice() {
	global $cache_enabled, $wp_cache_phase1_loaded;
	if( substr( $_SERVER["PHP_SELF"], -11 ) == 'plugins.php' && !$cache_enabled && function_exists( "admin_url" ) )
		echo '<div class="notice notice-info"><p><strong>' . sprintf( __('WP Super Cache is disabled. Please go to the <a href="%s">plugin admin page</a> to enable caching.', 'wp-super-cache' ), admin_url( 'options-general.php?page=wpsupercache' ) ) . '</strong></p></div>';

	if ( defined( 'WP_CACHE' ) && WP_CACHE == true && ( defined( 'ADVANCEDCACHEPROBLEM' ) || ( $cache_enabled && false == isset( $wp_cache_phase1_loaded ) ) ) ) {
		echo '<div class="notice notice-error"><p>' . sprintf( __( 'Warning! WP Super Cache caching <strong>was</strong> broken but has been <strong>fixed</strong>! The script advanced-cache.php could not load wp-cache-phase1.php.<br /><br />The file %1$s/advanced-cache.php has been recreated and WPCACHEHOME fixed in your wp-config.php. Reload to hide this message.', 'wp-super-cache' ), WP_CONTENT_DIR ) . '</p></div>';
		wp_cache_create_advanced_cache();
	}
}
add_action( 'admin_notices', 'wp_cache_admin_notice' );

function wp_cache_check_site() {
	global $wp_super_cache_front_page_check, $wp_super_cache_front_page_clear, $wp_super_cache_front_page_text, $wp_super_cache_front_page_notification, $wpdb;

	if ( !isset( $wp_super_cache_front_page_check ) || ( isset( $wp_super_cache_front_page_check ) && $wp_super_cache_front_page_check == 0 ) ) {
		return false;
	}

	if ( function_exists( "wp_remote_get" ) == false ) {
		return false;
	}
	$front_page = wp_remote_get( site_url(), array('timeout' => 60, 'blocking' => true ) );
	if( is_array( $front_page ) ) {
		// Check for gzipped front page
                if ( $front_page[ 'headers' ][ 'content-type' ] == 'application/x-gzip' ) {
                        if ( !isset( $wp_super_cache_front_page_clear ) || ( isset( $wp_super_cache_front_page_clear ) && $wp_super_cache_front_page_clear == 0 ) ) {
                                wp_mail( get_option( 'admin_email' ), sprintf( __( '[%s] Front page is gzipped! Please clear cache!', 'wp-super-cache' ), home_url() ), sprintf( __( "Please visit %s to clear the cache as the front page of your site is now downloading!", 'wp-super-cache' ), admin_url( 'options-general.php?page=wpsupercache' ) ) );
                        } else {
                                wp_cache_clear_cache( $wpdb->blogid );
                                wp_mail( get_option( 'admin_email' ), sprintf( __( '[%s] Front page is gzipped! Cache Cleared!', 'wp-super-cache' ), home_url() ), sprintf( __( "The cache on your blog has been cleared because the front page of your site is now downloading. Please visit %s to verify the cache has been cleared.", 'wp-super-cache' ), admin_url( 'options-general.php?page=wpsupercache' ) ) );
                        }
                }

		// Check for broken front page
		if ( isset( $wp_super_cache_front_page_text ) && $wp_super_cache_front_page_text != '' && false === strpos( $front_page[ 'body' ], $wp_super_cache_front_page_text ) ) {
			if ( !isset( $wp_super_cache_front_page_clear ) || ( isset( $wp_super_cache_front_page_clear ) && $wp_super_cache_front_page_clear == 0 ) ) {
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

function update_cached_mobile_ua_list( $mobile_browsers, $mobile_prefixes = 0, $mobile_groups = 0 ) {
	global $wp_cache_config_file, $wp_cache_mobile_browsers, $wp_cache_mobile_prefixes, $wp_cache_mobile_groups;
	wp_cache_setting( 'wp_cache_mobile_browsers', $mobile_browsers );
	wp_cache_setting( 'wp_cache_mobile_prefixes', $mobile_prefixes );
	if ( is_array( $mobile_groups ) ) {
		$wp_cache_mobile_groups = $mobile_groups;
		wp_cache_replace_line('^ *\$wp_cache_mobile_groups', "\$wp_cache_mobile_groups = '" . implode( ', ', $mobile_groups ) . "';", $wp_cache_config_file);
	}

	return true;
}

function wpsc_update_htaccess() {
	extract( wpsc_get_htaccess_info() );
	wpsc_remove_marker( $home_path.'.htaccess', 'WordPress' ); // remove original WP rules so SuperCache rules go on top
	if( insert_with_markers( $home_path.'.htaccess', 'WPSuperCache', explode( "\n", $rules ) ) && insert_with_markers( $home_path.'.htaccess', 'WordPress', explode( "\n", $wprules ) ) ) {
		return true;
	} else {
		return false;
	}
}

function wpsc_update_htaccess_form( $short_form = true ) {
	global $wpmu_version;

	extract( wpsc_get_htaccess_info() );
	if( !is_writeable_ACLSafe( $home_path . ".htaccess" ) ) {
		echo "<div style='padding:0 8px;color:#9f6000;background-color:#feefb3;border:1px solid #9f6000;'><h4>" . __( 'Cannot update .htaccess', 'wp-super-cache' ) . "</h4><p>" . sprintf( __( 'The file <code>%s.htaccess</code> cannot be modified by the web server. Please correct this using the chmod command or your ftp client.', 'wp-super-cache' ), $home_path ) . "</p><p>" . __( 'Refresh this page when the file permissions have been modified.' ) . "</p><p>" . sprintf( __( 'Alternatively, you can edit your <code>%s.htaccess</code> file manually and add the following code (before any WordPress rules):', 'wp-super-cache' ), $home_path ) . "</p>";
		echo "<p><pre># BEGIN WPSuperCache\n" . esc_html( $rules ) . "# END WPSuperCache</pre></p></div>";
	} else {
		if ( $short_form == false ) {
			echo "<div style='padding:0 8px;color:#9f6000;background-color:#feefb3;border:1px solid #9f6000;'><p>" . sprintf( __( 'To serve static html files your server must have the correct mod_rewrite rules added to a file called <code>%s.htaccess</code>', 'wp-super-cache' ), $home_path ) . " ";
			_e( "You can edit the file yourself. Add the following rules.", 'wp-super-cache' );
			echo __( " Make sure they appear before any existing WordPress rules. ", 'wp-super-cache' ) . "</p>";
			echo "<pre># BEGIN WPSuperCache\n" . esc_html( $rules ) . "# END WPSuperCache</pre></p>";
			echo "<p>" . sprintf( __( 'Rules must be added to %s too:', 'wp-super-cache' ), WP_CONTENT_DIR . "/cache/.htaccess" ) . "</p>";
			echo "<pre># BEGIN supercache\n" . esc_html( $gziprules ) . "# END supercache</pre></p>";
		}
		if ( !isset( $wpmu_version ) || $wpmu_version == '' ) {
			echo '<form name="updatehtaccess" action="#modrewrite" method="post">';
			echo '<input type="hidden" name="updatehtaccess" value="1" />';
			echo '<div class="submit"><input class="button-primary" type="submit" ' . SUBMITDISABLED . 'id="updatehtaccess" value="' . __( 'Update Mod_Rewrite Rules', 'wp-super-cache' ) . '" /></div>';
			wp_nonce_field('wp-cache');
			echo "</form></div>\n";
		}
	}
}

/*
 * Return LOGGED_IN_COOKIE if it doesn't begin with wordpress_logged_in
 * to avoid having people update their .htaccess file
 */
function wpsc_get_logged_in_cookie() {
	$logged_in_cookie = 'wordpress_logged_in';
	if ( defined( 'LOGGED_IN_COOKIE' ) && substr( constant( 'LOGGED_IN_COOKIE' ), 0, 19 ) != 'wordpress_logged_in' )
		$logged_in_cookie = constant( 'LOGGED_IN_COOKIE' );
	return $logged_in_cookie;
}

function wpsc_get_htaccess_info() {
	global $wp_cache_mobile_enabled, $wp_cache_mobile_prefixes, $wp_cache_mobile_browsers, $wp_cache_disable_utf8;
	if ( isset( $_SERVER[ "PHP_DOCUMENT_ROOT" ] ) ) {
		$document_root = $_SERVER[ "PHP_DOCUMENT_ROOT" ];
		$apache_root = $_SERVER[ "PHP_DOCUMENT_ROOT" ];
	} else {
		$document_root = $_SERVER[ "DOCUMENT_ROOT" ];
		$apache_root = '%{DOCUMENT_ROOT}';
	}
	$content_dir_root = $document_root;
	if ( strpos( $document_root, '/kunden/homepages/' ) === 0 ) {
		// http://wordpress.org/support/topic/plugin-wp-super-cache-how-to-get-mod_rewrite-working-on-1and1-shared-hosting?replies=1
		// On 1and1, PHP's directory structure starts with '/homepages'. The
		// Apache directory structure has an extra '/kunden' before it.
		// Also 1and1 does not support the %{DOCUMENT_ROOT} variable in
		// .htaccess files.
		// This prevents the $inst_root from being calculated correctly and
		// means that the $apache_root is wrong.
		//
		// e.g. This is an example of how Apache and PHP see the directory
		// structure on	1and1:
		// Apache: /kunden/homepages/xx/dxxxxxxxx/htdocs/site1/index.html
		// PHP:           /homepages/xx/dxxxxxxxx/htdocs/site1/index.html
		// Here we fix up the paths to make mode_rewrite work on 1and1 shared hosting.
		$content_dir_root = substr( $content_dir_root, 7 );
		$apache_root = $document_root;
	}
	$home_path = get_home_path();
	$home_root = parse_url(get_bloginfo('url'));
	$home_root = isset( $home_root[ 'path' ] ) ? trailingslashit( $home_root[ 'path' ] ) : '/';
	if (
		$home_root == '/' &&
		$home_path != $_SERVER[ 'DOCUMENT_ROOT' ]
	) {
		$home_path = $_SERVER[ 'DOCUMENT_ROOT' ];
	} elseif (
		$home_root != '/' &&
		$home_path != str_replace( '//', '/', $_SERVER[ 'DOCUMENT_ROOT' ] . $home_root ) &&
		is_dir( $_SERVER[ 'DOCUMENT_ROOT' ] . $home_root )
	) {
		$home_path = str_replace( '//', '/', $_SERVER[ 'DOCUMENT_ROOT' ] . $home_root );
	}
	$home_root_lc = str_replace( '//', '/', strtolower( $home_root ) );
	$inst_root = str_replace( '//', '/', '/' . trailingslashit( str_replace( $content_dir_root, '', str_replace( '\\', '/', WP_CONTENT_DIR ) ) ) );
	$wprules = implode( "\n", extract_from_markers( $home_path.'.htaccess', 'WordPress' ) );
	$wprules = str_replace( "RewriteEngine On\n", '', $wprules );
	$wprules = str_replace( "RewriteBase $home_root\n", '', $wprules );
	$scrules = implode( "\n", extract_from_markers( $home_path.'.htaccess', 'WPSuperCache' ) );

	if( substr( get_option( 'permalink_structure' ), -1 ) == '/' ) {
		$condition_rules[] = "RewriteCond %{REQUEST_URI} !^.*[^/]$";
		$condition_rules[] = "RewriteCond %{REQUEST_URI} !^.*//.*$";
	}
	$condition_rules[] = "RewriteCond %{REQUEST_METHOD} !POST";
	$condition_rules[] = "RewriteCond %{QUERY_STRING} !.*=.*";
	$condition_rules[] = "RewriteCond %{HTTP:Cookie} !^.*(comment_author_|" . wpsc_get_logged_in_cookie() . "|wp-postpass_).*$";
	$condition_rules[] = "RewriteCond %{HTTP:X-Wap-Profile} !^[a-z0-9\\\"]+ [NC]";
	$condition_rules[] = "RewriteCond %{HTTP:Profile} !^[a-z0-9\\\"]+ [NC]";
	if ( $wp_cache_mobile_enabled ) {
		if ( isset( $wp_cache_mobile_browsers ) && "" != $wp_cache_mobile_browsers )
			$condition_rules[] = "RewriteCond %{HTTP_USER_AGENT} !^.*(" . addcslashes( str_replace( ', ', '|', $wp_cache_mobile_browsers ), ' ' ) . ").* [NC]";
		if ( isset( $wp_cache_mobile_prefixes ) && "" != $wp_cache_mobile_prefixes )
			$condition_rules[] = "RewriteCond %{HTTP_USER_AGENT} !^(" . addcslashes( str_replace( ', ', '|', $wp_cache_mobile_prefixes ), ' ' ) . ").* [NC]";
	}
	$condition_rules = apply_filters( 'supercacherewriteconditions', $condition_rules );

	$rules = "<IfModule mod_rewrite.c>\n";
	$rules .= "RewriteEngine On\n";
	$rules .= "RewriteBase $home_root\n"; // props Chris Messina
	$rules .= "#If you serve pages from behind a proxy you may want to change 'RewriteCond %{HTTPS} on' to something more sensible\n";
	if ( isset( $wp_cache_disable_utf8 ) == false || $wp_cache_disable_utf8 == 0 ) {
		$charset = get_option('blog_charset') == '' ? 'UTF-8' : get_option('blog_charset');
		$rules .= "AddDefaultCharset {$charset}\n";
	}

	$rules .= "CONDITION_RULES";
	$rules .= "RewriteCond %{HTTP:Accept-Encoding} gzip\n";
	$rules .= "RewriteCond %{HTTPS} on\n";
	$rules .= "RewriteCond {$apache_root}{$inst_root}cache/supercache/%{SERVER_NAME}{$home_root_lc}$1/index-https.html.gz -f\n";
	$rules .= "RewriteRule ^(.*) \"{$inst_root}cache/supercache/%{SERVER_NAME}{$home_root_lc}$1/index-https.html.gz\" [L]\n\n";

	$rules .= "CONDITION_RULES";
	$rules .= "RewriteCond %{HTTP:Accept-Encoding} gzip\n";
	$rules .= "RewriteCond %{HTTPS} !on\n";
	$rules .= "RewriteCond {$apache_root}{$inst_root}cache/supercache/%{SERVER_NAME}{$home_root_lc}$1/index.html.gz -f\n";
	$rules .= "RewriteRule ^(.*) \"{$inst_root}cache/supercache/%{SERVER_NAME}{$home_root_lc}$1/index.html.gz\" [L]\n\n";

	$rules .= "CONDITION_RULES";
	$rules .= "RewriteCond %{HTTPS} on\n";
	$rules .= "RewriteCond {$apache_root}{$inst_root}cache/supercache/%{SERVER_NAME}{$home_root_lc}$1/index-https.html -f\n";
	$rules .= "RewriteRule ^(.*) \"{$inst_root}cache/supercache/%{SERVER_NAME}{$home_root_lc}$1/index-https.html\" [L]\n\n";

	$rules .= "CONDITION_RULES";
	$rules .= "RewriteCond %{HTTPS} !on\n";
	$rules .= "RewriteCond {$apache_root}{$inst_root}cache/supercache/%{SERVER_NAME}{$home_root_lc}$1/index.html -f\n";
	$rules .= "RewriteRule ^(.*) \"{$inst_root}cache/supercache/%{SERVER_NAME}{$home_root_lc}$1/index.html\" [L]\n";
	$rules .= "</IfModule>\n";
	$rules = apply_filters( 'supercacherewriterules', $rules );

	$rules = str_replace( "CONDITION_RULES", implode( "\n", $condition_rules ) . "\n", $rules );

	$gziprules =  "<IfModule mod_mime.c>\n  <FilesMatch \"\\.html\\.gz\$\">\n    ForceType text/html\n    FileETag None\n  </FilesMatch>\n  AddEncoding gzip .gz\n  AddType text/html .gz\n</IfModule>\n";
	$gziprules .= "<IfModule mod_deflate.c>\n  SetEnvIfNoCase Request_URI \.gz$ no-gzip\n</IfModule>\n";
	$gziprules .= "<IfModule mod_headers.c>\n  Header set Vary \"Accept-Encoding, Cookie\"\n  Header set Cache-Control 'max-age=3, must-revalidate'\n</IfModule>\n";
	$gziprules .= "<IfModule mod_expires.c>\n  ExpiresActive On\n  ExpiresByType text/html A3\n</IfModule>\n";
	$gziprules .= "Options -Indexes\n";
	return array( "document_root" => $document_root, "apache_root" => $apache_root, "home_path" => $home_path, "home_root" => $home_root, "home_root_lc" => $home_root_lc, "inst_root" => $inst_root, "wprules" => $wprules, "scrules" => $scrules, "condition_rules" => $condition_rules, "rules" => $rules, "gziprules" => $gziprules );
}

function clear_post_supercache( $post_id ) {
	$dir = get_current_url_supercache_dir( $post_id );
	if ( false == @is_dir( $dir ) )
		return false;

	if ( !function_exists( 'prune_super_cache' ) )
		include_once( 'wp-cache-phase2.php' );

	wp_cache_debug( "clear_post_supercache: deleting files in $dir", 2 );
	prune_super_cache( $dir, true );
}

function wp_cron_preload_cache() {
	global $wpdb, $wp_cache_preload_interval, $wp_cache_preload_posts, $wp_cache_preload_email_me, $wp_cache_preload_email_volume, $cache_path, $wp_cache_preload_taxonomies;

	if ( get_option( 'preload_cache_stop' ) ) {
		delete_option( 'preload_cache_stop' );
		wp_cache_debug( "wp_cron_preload_cache: preload cancelled", 1 );
		return true;
	}

	if ( !function_exists( 'wp_cache_debug' ) )
		include_once( 'wp-cache-phase1.php' );
	if ( !function_exists( 'prune_super_cache' ) )
		include_once( 'wp-cache-phase2.php' );

	$mutex = $cache_path . "preload_mutex.tmp";
	sleep( 3 + mt_rand( 1, 5 ) );
	if ( @file_exists( $mutex ) ) {
		if ( @filemtime( $mutex ) > ( time() - 600 ) ) {
			wp_cache_debug( "wp_cron_preload_cache: preload mutex found and less than 600 seconds old. Aborting preload.", 1 );
			return true;
		} else {
			wp_cache_debug( "wp_cron_preload_cache: old preload mutex found and deleted. Preload continues.", 1 );
			@unlink( $mutex );
		}
	}
	$fp = @fopen( $mutex, 'w' );
	@fclose( $fp );

	$counter = get_option( 'preload_cache_counter' );
	if ( is_array( $counter ) == false ) {
		wp_cache_debug( "wp_cron_preload_cache: setting up preload for the first time!", 5 );
		$counter = array( 'c' => 0, 't' => time() );
		update_option( 'preload_cache_counter', $counter );
	}
	$c = $counter[ 'c' ];

	update_option( 'preload_cache_counter', array( 'c' => ( $c + 100 ), 't' => time() ) );

	if ( $wp_cache_preload_email_volume == 'none' && $wp_cache_preload_email_me == 1 ) {
		$wp_cache_preload_email_me = 0;
		wp_cache_setting( 'wp_cache_preload_email_me', 0 );
	}
	if ( $wp_cache_preload_email_me && $c == 0 )
		wp_mail( get_option( 'admin_email' ), sprintf( __( '[%1$s] Cache Preload Started', 'wp-super-cache' ), home_url(), '' ), ' ' );

	if ( $wp_cache_preload_posts == 'all' || $c < $wp_cache_preload_posts ) {
		wp_cache_debug( "wp_cron_preload_cache: doing taxonomy preload.", 5 );
		$permalink_counter_msg = $cache_path . "preload_permalink.txt";
		if ( isset( $wp_cache_preload_taxonomies ) && $wp_cache_preload_taxonomies ) {
			$taxonomies = apply_filters( 'wp_cache_preload_taxonomies', array( 'post_tag' => 'tag', 'category' => 'category' ) );
			foreach( $taxonomies as $taxonomy => $path ) {
				$taxonomy_filename = $cache_path . "taxonomy_" . $taxonomy . ".txt";
				if ( $c == 0 )
					@unlink( $taxonomy_filename );

				if ( false == @file_exists( $taxonomy_filename ) ) {
					$out = '';
					$records = get_terms( $taxonomy );
					foreach( $records as $term ) {
						$out .= get_term_link( $term ). "\n";
					}
					$fp = fopen( $taxonomy_filename, 'w' );
					if ( $fp ) {
						fwrite( $fp, $out );
						fclose( $fp );
					}
					$details = explode( "\n", $out );
				} else {
					$details = explode( "\n", file_get_contents( $taxonomy_filename ) );
				}
				if ( count( $details ) != 1 && $details[ 0 ] != '' ) {
					$rows = array_splice( $details, 0, 50 );
					if ( $wp_cache_preload_email_me && $wp_cache_preload_email_volume == 'many' )
						wp_mail( get_option( 'admin_email' ), sprintf( __( '[%1$s] Refreshing %2$s taxonomy from %3$d to %4$d', 'wp-super-cache' ), home_url(), $taxonomy, $c, ($c+100) ), 'Refreshing: ' . print_r( $rows, 1 ) );
					foreach( (array)$rows as $url ) {
						set_time_limit( 60 );
						if ( $url == '' )
							continue;
						$url_info = parse_url( $url );
						$dir = get_supercache_dir() . $url_info[ 'path' ];
						wp_cache_debug( "wp_cron_preload_cache: delete $dir", 5 );
						prune_super_cache( $dir );
						$fp = @fopen( $permalink_counter_msg, 'w' );
						if ( $fp ) {
							@fwrite( $fp, "$taxonomy: $url" );
							@fclose( $fp );
						}
						wp_remote_get( $url, array('timeout' => 60, 'blocking' => true ) );
						wp_cache_debug( "wp_cron_preload_cache: fetched $url", 5 );
						sleep( 1 );
					}
					$fp = fopen( $taxonomy_filename, 'w' );
					if ( $fp ) {
						fwrite( $fp, implode( "\n", $details ) );
						fclose( $fp );
					}
				}
			}
		}
	}

	if ( $wp_cache_preload_posts == 'all' || $c < $wp_cache_preload_posts ) {
		$types = get_post_types( array( 'public' => true, 'publicly_queryable' => true ), 'names', 'or' );
		$types = array_map( 'esc_sql', $types );
		$types = "'" . implode( "','", $types ) . "'";
		$posts = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE ( post_type IN ( $types ) ) AND post_status = 'publish' ORDER BY ID DESC LIMIT $c, 100" );
		wp_cache_debug( "wp_cron_preload_cache: got 100 posts from position $c.", 5 );
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
		if ( $wp_cache_preload_email_me && $wp_cache_preload_email_volume == 'many' )
			wp_mail( get_option( 'admin_email' ), sprintf( __( '[%1$s] Refreshing posts from %2$d to %3$d', 'wp-super-cache' ), home_url(), $c, ($c+100) ), ' ' );
		$msg = '';
		$count = $c + 1;
		$permalink_counter_msg = $cache_path . "preload_permalink.txt";
		foreach( $posts as $post_id ) {
			set_time_limit( 60 );
			if ( $page_on_front != 0 && ( $post_id == $page_on_front || $post_id == $page_for_posts ) )
				continue;
			clear_post_supercache( $post_id );
			$url = get_permalink( $post_id );
			$fp = @fopen( $permalink_counter_msg, 'w' );
			if ( $fp ) {
				@fwrite( $fp, $count . " " . $url );
				@fclose( $fp );
			}
			if ( @file_exists( $cache_path . "stop_preload.txt" ) ) {
				wp_cache_debug( "wp_cron_preload_cache: cancelling preload. stop_preload.txt found.", 5 );
				@unlink( $mutex );
				@unlink( $cache_path . "stop_preload.txt" );
				update_option( 'preload_cache_counter', array( 'c' => 0, 't' => time() ) );
				if ( $wp_cache_preload_email_me )
					wp_mail( get_option( 'admin_email' ), sprintf( __( '[%1$s] Cache Preload Stopped', 'wp-super-cache' ), home_url(), '' ), ' ' );
				return true;
			}
			$msg .= "$url\n";
			wp_remote_get( $url, array('timeout' => 60, 'blocking' => true ) );
			wp_cache_debug( "wp_cron_preload_cache: fetched $url", 5 );
			sleep( 1 );
			$count++;
		}
		if ( $wp_cache_preload_email_me && ( $wp_cache_preload_email_volume == 'medium' || $wp_cache_preload_email_volume == 'many' ) )
			wp_mail( get_option( 'admin_email' ), sprintf( __( '[%1$s] %2$d posts refreshed', 'wp-super-cache' ), home_url(), ($c+100) ), __( "Refreshed the following posts:", 'wp-super-cache' ) . "\n$msg" );
		if ( defined( 'DOING_CRON' ) ) {
			wp_cache_debug( "wp_cron_preload_cache: scheduling the next preload in 30 seconds.", 5 );
			wp_schedule_single_event( time() + 30, 'wp_cache_preload_hook' );
		}
	} else {
		$msg = '';
		update_option( 'preload_cache_counter', array( 'c' => 0, 't' => time() ) );
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
		wp_cache_debug( "wp_cron_preload_cache: clean expired cache files older than $cache_max_time seconds.", 5 );
		wp_cache_phase2_clean_expired( $file_prefix, true ); // force cleanup of old files.
	}
	@unlink( $mutex );
}
add_action( 'wp_cache_preload_hook', 'wp_cron_preload_cache' );
add_action( 'wp_cache_full_preload_hook', 'wp_cron_preload_cache' );

function next_preload_message( $hook, $text, $limit = 0 ) {
	global $currently_preloading, $wp_cache_preload_interval;
	if ( $next_preload = wp_next_scheduled( $hook ) ) {
		$next_time = $next_preload - time();
		if ( $limit != 0 && $next_time > $limit )
			return false;
		$h = $m = $s = 0;
		if ( $next_time > 0 ) {
			$m = (int)($next_time / 60);
			$s = $next_time % 60;
			$h = (int)($m / 60); $m = $m % 60;
		}
		if ( $next_time > 0 && $next_time < ( 60 * $wp_cache_preload_interval ) )
			echo '<p><strong>' . sprintf( $text, $h, $m, $s ) . '</strong></p>';
		if ( ( $next_preload - time() ) <= 60 )
			$currently_preloading = true;
	}
}

function option_preload_cache_counter( $value ) {
	if ( false == is_array( $value ) ) {
		$ret = array( 'c' => $value, 't' => time(), 'first' => 1 );
		return $ret;
	}

	return $value;
}
add_filter( 'option_preload_cache_counter', 'option_preload_cache_counter' );

function check_up_on_preloading() {
	$value = get_option( 'preload_cache_counter' );
	if ( $value[ 'c' ] > 0 && ( time() - $value[ 't' ] ) > 3600 && false == wp_next_scheduled( 'wp_cache_preload_hook' ) ) {
		if ( is_admin() )
			wp_mail( get_option( 'admin_email' ), sprintf( __( '[%s] Preload may have stalled.', 'wp-super-cache' ), get_bloginfo( 'url' ) ), sprintf( __( "Preload has been restarted.\n%s", 'wp-super-cache' ), admin_url( "options-general.php?page=wpsupercache" ) ) );
		wp_schedule_single_event( time() + 30, 'wp_cache_preload_hook' );
	}
}
add_action( 'init', 'check_up_on_preloading' ); // sometimes preloading stops working. Kickstart it.

function wp_cache_disable_plugin( $delete_config_file = true ) {
	global $wp_rewrite;
	if ( file_exists( ABSPATH . 'wp-config.php') ) {
		$global_config_file = ABSPATH . 'wp-config.php';
	} else {
		$global_config_file = dirname(ABSPATH) . '/wp-config.php';
	}
	$line = 'define(\'WP_CACHE\', true);';
	if ( strpos( file_get_contents( $global_config_file ), $line ) && ( !is_writeable_ACLSafe( $global_config_file ) || !wp_cache_replace_line( 'define *\( *\'WP_CACHE\'', '//' . $line, $global_config_file ) ) )
		wp_die( "Could not remove WP_CACHE define from $global_config_file. Please edit that file and remove the line containing the text 'WP_CACHE'. Then refresh this page." );

	uninstall_supercache( WP_CONTENT_DIR . '/cache' );
	$file_not_deleted = false;
	if ( @file_exists( WP_CONTENT_DIR . "/advanced-cache.php" ) ) {
		if ( false == @unlink( WP_CONTENT_DIR . "/advanced-cache.php" ) )
			$file_not_deleted[] = 'advanced-cache.php';
	}
	if ( $delete_config_file && @file_exists( WP_CONTENT_DIR . "/wp-cache-config.php" ) ) {
		if ( false == unlink( WP_CONTENT_DIR . "/wp-cache-config.php" ) )
			$file_not_deleted[] = 'wp-cache-config.php';
	}
	if ( $file_not_deleted ) {
		$msg = "<p>One or more files could not be deleted. These files and directories must be made writeable:</p>\n <ol><li>" . WP_CONTENT_DIR . "</li>\n";
		$code = "<ul>\n";
		foreach( (array)$file_not_deleted as $filename ) {
			$msg .= "<li>" . WP_CONTENT_DIR . "/{$filename}</li>";
			$code .= "<li><code>chmod 666 " . WP_CONTENT_DIR . "/{$filename}</code></li>\n";
		}
		$code .= "</ul>\n";

		$msg .= "</ol>\n<p>First try fixing the directory permissions with this command and refresh this page:<br /><br /><code>chmod 777 " . WP_CONTENT_DIR . "</code><br /><br />If you still see this error, you have to fix the permissions on the files themselves and refresh this page again:</p> {$code}\n<p>Don't forgot to fix things later:<br /><code>chmod 755 " . WP_CONTENT_DIR . "</code></p><p>If you don't know what <strong>chmod</strong> is use <a href='http://www.google.ie/search?hl=en&q=ftp+chmod+777'>this Google search</a> to find out all about it.</p><p>Please refresh this page when the permissions have been modified.</p>";
		wp_die( $msg );
	}
	extract( wpsc_get_htaccess_info() );
	if ( $scrules != '' && insert_with_markers( $home_path.'.htaccess', 'WPSuperCache', array() ) ) {
		$wp_rewrite->flush_rules();
	} elseif( $scrules != '' ) {
		wp_mail( get_option( 'admin_email' ), __( 'Supercache Uninstall Problems', 'wp-super-cache' ), sprintf( __( "Dear User,\n\nWP Super Cache was removed from your blog but the mod_rewrite rules\nin your .htaccess were not.\n\nPlease edit the following file and remove the code\nbetween 'BEGIN WPSuperCache' and 'END WPSuperCache'. Please backup the file first!\n\n%s\n\nRegards,\nWP Super Cache Plugin\nhttp://wordpress.org/plugins/wp-super-cache/", 'wp-super-cache' ), ABSPATH . '/.htaccess' ) );
	}
}

function uninstall_supercache( $folderPath ) { // from http://www.php.net/manual/en/function.rmdir.php
	if ( trailingslashit( constant( 'ABSPATH' ) ) == trailingslashit( $folderPath ) )
		return false;
	if ( @is_dir ( $folderPath ) ) {
		$dh  = @opendir($folderPath);
		while( false !== ( $value = @readdir( $dh ) ) ) {
			if ( $value != "." && $value != ".." ) {
				$value = $folderPath . "/" . $value;
				if ( @is_dir ( $value ) ) {
					uninstall_supercache( $value );
				} else {
					@unlink( $value );
				}
			}
		}
		return @rmdir( $folderPath );
	} else {
		return false;
	}
}

function supercache_admin_bar_render() {
	global $wp_admin_bar, $wp_cache_not_logged_in;
	if ( !is_user_logged_in() || !$wp_cache_not_logged_in )
		return false;

	if ( function_exists('current_user_can') && false == current_user_can('delete_others_posts') )
		return false;

	$wp_admin_bar->add_menu( array(
				'parent' => '',
				'id' => 'delete-cache',
				'title' => __( 'Delete Cache', 'wp-super-cache' ),
				'meta' => array( 'title' => __( 'Delete cache of the current page', 'wp-super-cache' ) ),
				'href' => wp_nonce_url( admin_url( 'index.php?action=delcachepage&path=' . urlencode( preg_replace( '/[ <>\'\"\r\n\t\(\)]/', '', $_SERVER[ 'REQUEST_URI' ] ) ) ), 'delete-cache' )
				) );
}
add_action( 'wp_before_admin_bar_render', 'supercache_admin_bar_render' );

function wpsc_cancel_preload() {
	global $cache_path;
	$next_preload = wp_next_scheduled( 'wp_cache_preload_hook' );
	if ( $next_preload ) {
		wp_cache_debug( 'wpsc_cancel_preload: unscheduling wp_cache_preload_hook' );
		update_option( 'preload_cache_counter', array( 'c' => 0, 't' => time() ) );
		wp_unschedule_event( $next_preload, 'wp_cache_preload_hook' );
	}
	$next_preload = wp_next_scheduled( 'wp_cache_full_preload_hook' );
	if ( $next_preload ) {
		update_option( 'preload_cache_counter', array( 'c' => 0, 't' => time() ) );
		wp_cache_debug( 'wpsc_cancel_preload: unscheduling wp_cache_full_preload_hook' );
		wp_unschedule_event( $next_preload, 'wp_cache_full_preload_hook' );
	}
	wp_cache_debug( 'wpsc_cancel_preload: creating stop_preload.txt' );
	$fp = @fopen( $cache_path . "stop_preload.txt", 'w' );
	@fclose( $fp );
}

function wpsc_enable_preload() {
	global $cache_path;

	@unlink( $cache_path . "preload_mutex.tmp" );
	update_option( 'preload_cache_counter', array( 'c' => 0, 't' => time() ) );
	wp_schedule_single_event( time() + 10, 'wp_cache_full_preload_hook' );
}

function wpsc_preload_settings( $min_refresh_interval = 'NA' ) {
	global $wp_cache_preload_interval, $wp_cache_preload_on, $wp_cache_preload_taxonomies, $wp_cache_preload_email_me, $wp_cache_preload_email_volume, $wp_cache_preload_posts, $wpdb;

	$return = array();

	if ( isset( $_POST[ 'action' ] ) == false || $_POST[ 'action' ] != 'preload' )
		return $return;

	if ( isset( $_POST[ 'preload_off' ] ) ) {
		wpsc_cancel_preload();
		$return[] = "<p><strong>" . __( 'Scheduled preloading of cache almost cancelled. It may take up to a minute for it to cancel completely.', 'wp-super-cache' ) . "</strong></p>";
		return $return;
	} elseif ( isset( $_POST[ 'preload_now' ] ) ) {
		wpsc_enable_preload();
		return $return;
	} 
	
	if ( $min_refresh_interval == 'NA' ) {
		$posts_count = wp_count_posts();
		$count = $posts_count->publish;
		if ( $count > 1000 ) {
			$min_refresh_interval = 720;
		} else {
			$min_refresh_interval = 30;
		}
	}
	if ( isset( $_POST[ 'wp_cache_preload_interval' ] ) && ( $_POST[ 'wp_cache_preload_interval' ] == 0 || $_POST[ 'wp_cache_preload_interval' ] >= $min_refresh_interval ) ) {
		// if preload interval changes than unschedule any preload jobs and schedule any new one.
		$_POST[ 'wp_cache_preload_interval' ] = (int)$_POST[ 'wp_cache_preload_interval' ];
		if ( $wp_cache_preload_interval != $_POST[ 'wp_cache_preload_interval' ] ) {
			$next_preload = wp_next_scheduled( 'wp_cache_full_preload_hook' );
			if ( $next_preload ) {
				update_option( 'preload_cache_counter', array( 'c' => 0, 't' => time() ) );
				add_option( 'preload_cache_stop', 1 );
				wp_unschedule_event( $next_preload, 'wp_cache_full_preload_hook' );
				if ( $wp_cache_preload_interval == 0 ) {
					$return[] = "<p><strong>" . __( 'Scheduled preloading of cache cancelled.', 'wp-super-cache' ) . "</strong></p>";
				}
				if ( $_POST[ 'wp_cache_preload_interval' ] != 0 )
					wp_schedule_single_event( time() + ( $_POST[ 'wp_cache_preload_interval' ] * 60 ), 'wp_cache_full_preload_hook' );
			}
		}

		$wp_cache_preload_interval = (int)$_POST[ 'wp_cache_preload_interval' ];
		wp_cache_setting( "wp_cache_preload_interval", $wp_cache_preload_interval );
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

	return $return;
}

function wpsc_is_preloading() {
	if ( wp_next_scheduled( 'wp_cache_preload_hook' ) || wp_next_scheduled( 'wp_cache_full_preload_hook' ) ) { 
		return true;
	} else {
		return false;
	}
}

function wpsc_set_default_gc( $force = false ) {
	global $cache_path, $wp_cache_shutdown_gc, $cache_schedule_type;

	if ( isset( $wp_cache_shutdown_gc ) && $wp_cache_shutdown_gc == 1 ) {
		return false;
	}

	if ( $force ) {
		unset( $cache_schedule_type );
		$timestamp = wp_next_scheduled( 'wp_cache_gc' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'wp_cache_gc' );
		}
	}

	// set up garbage collection with some default settings
	if ( false == isset( $cache_schedule_type ) && false == wp_next_scheduled( 'wp_cache_gc' ) ) {
		$cache_schedule_type     = 'interval';
		$cache_time_interval     = 600;
		$cache_max_time          = 1800;
		$cache_schedule_interval = 'hourly';
		$cache_gc_email_me       = 0;
		wp_cache_setting( 'cache_schedule_type', $cache_schedule_type );
		wp_cache_setting( 'cache_time_interval', $cache_time_interval );
		wp_cache_setting( 'cache_max_time', $cache_max_time );
		wp_cache_setting( 'cache_schedule_interval', $cache_schedule_interval );
		wp_cache_setting( 'cache_gc_email_me', $cache_gc_email_me );

		wp_schedule_single_event( time() + 600, 'wp_cache_gc' );
	}

	return true;

}

function add_mod_rewrite_rules() {
	return update_mod_rewrite_rules();
}

function remove_mod_rewrite_rules() {
	return update_mod_rewrite_rules( false );
}

function update_mod_rewrite_rules( $add_rules = true ) {
	global $cache_path, $update_mod_rewrite_rules_error;

	$update_mod_rewrite_rules_error = false;

	if ( defined( "DO_NOT_UPDATE_HTACCESS" ) ) {
		$update_mod_rewrite_rules_error = ".htaccess update disabled by admin: DO_NOT_UPDATE_HTACCESS defined";
		return false;
	}

	if ( ! function_exists( 'get_home_path' ) ) {
		include_once( ABSPATH . 'wp-admin/includes/file.php' ); // get_home_path()
		include_once( ABSPATH . 'wp-admin/includes/misc.php' ); // extract_from_markers()
	}
	$home_path = trailingslashit( get_home_path() );
	$home_root = parse_url( get_bloginfo( 'url' ) );
	$home_root = isset( $home_root[ 'path' ] ) ? trailingslashit( $home_root[ 'path' ] ) : '/';
	if (
		$home_root == '/' &&
		$home_path != $_SERVER[ 'DOCUMENT_ROOT' ]
	) {
		$home_path = $_SERVER[ 'DOCUMENT_ROOT' ];
	} elseif (
		$home_root != '/' &&
		$home_path != str_replace( '//', '/', $_SERVER[ 'DOCUMENT_ROOT' ] . $home_root ) &&
		is_dir( $_SERVER[ 'DOCUMENT_ROOT' ] . $home_root )
	) {
		$home_path = str_replace( '//', '/', $_SERVER[ 'DOCUMENT_ROOT' ] . $home_root );
	}

	if ( ! file_exists( $home_path . ".htaccess" ) ) {
		$update_mod_rewrite_rules_error = ".htaccess not found: {$home_path}.htaccess";
		return false;
	}

	$generated_rules = wpsc_get_htaccess_info();

	if ( $add_rules ) {
		$rules = $generated_rules[ 'rules' ];
	}  else {
		$rules = '';
	}

	$existing_rules = implode( "\n", extract_from_markers( $home_path . '.htaccess', 'WPSuperCache' ) );

	if ( $existing_rules == $rules ) {
		$update_mod_rewrite_rules_error = "rules have not changed";
		return true;
	}

	if ( $generated_rules[ 'wprules' ] == '' ) {
		$update_mod_rewrite_rules_error = "WordPress rules empty";
		return false;
	}

	$url = trailingslashit( get_bloginfo( 'url' ) );
	$original_page = wp_remote_get( $url, array( 'timeout' => 60, 'blocking' => true ) );
	if ( is_wp_error( $original_page ) ) {
		$update_mod_rewrite_rules_error = "Problem loading page";
		return false;
	}

	$backup_filename = $cache_path . 'htaccess.' . mt_rand() . ".php";
	$backup_file_contents = file_get_contents( $home_path . '.htaccess' );
	file_put_contents( $backup_filename, "<" . "?php die(); ?" . ">" . $backup_file_contents );
	$existing_gzip_rules = implode( "\n", extract_from_markers( $cache_path . '.htaccess', 'supercache' ) );
	if ( $existing_gzip_rules != $generated_rules[ 'gziprules' ] ) {
		insert_with_markers( $cache_path . '.htaccess', 'supercache', explode( "\n", $generated_rules[ 'gziprules' ] ) );
	}
	$wprules = extract_from_markers( $home_path . '.htaccess', 'WordPress' );
	wpsc_remove_marker( $home_path . '.htaccess', 'WordPress' ); // remove original WP rules so SuperCache rules go on top
	if ( insert_with_markers( $home_path . '.htaccess', 'WPSuperCache', explode( "\n", $rules ) ) && insert_with_markers( $home_path . '.htaccess', 'WordPress', $wprules ) ) {
		$new_page = wp_remote_get( $url, array( 'timeout' => 60, 'blocking' => true ) );
		if ( is_wp_error( $new_page ) || $new_page[ 'body' ] != $original_page[ 'body' ] ) {
			file_put_contents( $home_path . '.htaccess', $backup_file_contents );
			unlink( $backup_filename );
			$update_mod_rewrite_rules_error = "page error or pages do not match and original .htaccess restored";
			return false;
		}
	} else {
		file_put_contents( $home_path . '.htaccess', $backup_file_contents );
		unlink( $backup_filename );
		$update_mod_rewrite_rules_error = "problem inserting rules in .htaccess and original .htaccess restored";
		return false;
	}

	return true;
}
