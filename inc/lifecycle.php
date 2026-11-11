<?php
/**
 * Plugin lifecycle: activation / deactivation / uninstall, enable / disable for
 * both caching modes, advanced-cache.php management, config-file and cache-dir
 * verification, index.html protection, logout-all, recursive deletion, the
 * update check, and default GC scheduling.
 *
 * Relocated from wp-cache.php (issue #1061) as a pure move: identical function
 * signatures and hook registrations, no behaviour change. The wpsc_init()
 * bootstrap and its directly-coupled init wiring remain in wp-cache.php.
 *
 * @package WP_Super_Cache
 */

function get_wpcachehome() {
	if ( function_exists( '_deprecated_function' ) ) {
		_deprecated_function( __FUNCTION__, 'WP Super Cache 1.6.5' );
	}

	if ( ! defined( 'WPCACHEHOME' ) ) {
		if ( is_file( dirname( __DIR__ ) . '/wp-cache-config-sample.php' ) ) {
			define( 'WPCACHEHOME', trailingslashit( dirname( __DIR__ ) ) );
		} elseif ( is_file( dirname( __DIR__ ) . '/wp-super-cache/wp-cache-config-sample.php' ) ) {
			define( 'WPCACHEHOME', dirname( __DIR__ ) . '/wp-super-cache/' );
		} else {
			die( sprintf( esc_html__( 'Please create %s/wp-cache-config.php from wp-super-cache/wp-cache-config-sample.php', 'wp-super-cache' ), esc_attr( WPCACHECONFIGPATH ) ) );
		}
	}
}

function wpsc_remove_advanced_cache() {
	global $wpsc_advanced_cache_filename;
	if ( file_exists( $wpsc_advanced_cache_filename ) ) {
		$file = file_get_contents( $wpsc_advanced_cache_filename );
		if (
			strpos( $file, "WP SUPER CACHE 0.8.9.1" ) ||
			strpos( $file, "WP SUPER CACHE 1.2" )
		) {
			unlink( $wpsc_advanced_cache_filename );
		}
	}
}

function wpsupercache_uninstall() {
	global $wp_cache_config_file, $cache_path;

	wpsc_remove_advanced_cache();

	if ( file_exists( $wp_cache_config_file ) ) {
		unlink( $wp_cache_config_file );
	}

	wp_cache_remove_index();

	if ( ! empty( $cache_path ) ) {
		@unlink( $cache_path . '.htaccess' );
		@unlink( $cache_path . 'meta' );
		@unlink( $cache_path . 'supercache' );
	}

	wp_clear_scheduled_hook( 'wp_cache_check_site_hook' );
	wp_clear_scheduled_hook( 'wp_cache_gc' );
	wp_clear_scheduled_hook( 'wp_cache_gc_watcher' );
	wp_cache_disable_plugin();
	delete_site_option( 'wp_super_cache_index_detected' );
}

function wpsupercache_deactivate() {
	global $wp_cache_config_file, $wpsc_advanced_cache_filename, $cache_path;

	wpsc_remove_advanced_cache();

	if ( ! empty( $cache_path ) ) {
		prune_super_cache( $cache_path, true );
		wp_cache_remove_index();
		@unlink( $cache_path . '.htaccess' );
		@unlink( $cache_path . 'meta' );
		@unlink( $cache_path . 'supercache' );
	}

	wp_clear_scheduled_hook( 'wp_cache_check_site_hook' );
	wp_clear_scheduled_hook( 'wp_cache_gc' );
	wp_clear_scheduled_hook( 'wp_cache_gc_watcher' );
	wp_cache_replace_line('^ *\$cache_enabled', '$cache_enabled = false;', $wp_cache_config_file);
	wp_cache_disable_plugin( false ); // don't delete configuration file
	delete_user_option( get_current_user_id(), 'wpsc_dismissed_boost_banner' );
}

function wpsupercache_activate() {
	global $cache_path;
	if ( ! isset( $cache_path ) || $cache_path == '' )
		$cache_path = WP_CONTENT_DIR . '/cache/'; // from sample config file

	ob_start();
	wpsc_init();

	if (
		! wp_cache_verify_cache_dir() ||
		! wpsc_check_advanced_cache() ||
		! wp_cache_verify_config_file()
	) {
		$text = ob_get_contents();
		ob_end_clean();
		return false;
	}
	$text = ob_get_contents();
	wp_cache_check_global_config();
	ob_end_clean();
	wp_schedule_single_event( time() + 10, 'wp_cache_add_site_cache_index' );
}

function wpsupercache_site_admin() {
	return current_user_can( 'setup_network' );
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

function wp_cache_enable() {
	global $wp_cache_config_file, $cache_enabled;

	if ( $cache_enabled ) {
		wp_cache_debug( 'wp_cache_enable: already enabled' );
		return true;
	}

	wp_cache_setting( 'cache_enabled', true );
	wp_cache_debug( 'wp_cache_enable: enable cache' );

	$cache_enabled = true;

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

	if ( ! $cache_enabled ) {
		wp_cache_debug( 'wp_cache_disable: already disabled' );
		return true;
	}

	wp_cache_setting( 'cache_enabled', false );
	wp_cache_debug( 'wp_cache_disable: disable cache' );

	$cache_enabled = false;

	wp_clear_scheduled_hook( 'wp_cache_check_site_hook' );
	wp_clear_scheduled_hook( 'wp_cache_gc' );
	wp_clear_scheduled_hook( 'wp_cache_gc_watcher' );
}

function wp_super_cache_enable() {
	global $supercachedir, $wp_cache_config_file, $super_cache_enabled;

	if ( $super_cache_enabled ) {
		wp_cache_debug( 'wp_super_cache_enable: already enabled' );
		return true;
	}

	wp_cache_setting( 'super_cache_enabled', true );
	wp_cache_debug( 'wp_super_cache_enable: enable cache' );

	$super_cache_enabled = true;

	if ( ! $supercachedir ) {
		$supercachedir = get_supercache_dir();
	}

	if ( is_dir( $supercachedir . '.disabled' ) ) {
		if ( is_dir( $supercachedir ) ) {
			prune_super_cache( $supercachedir . '.disabled', true );
			@unlink( $supercachedir . '.disabled' );
		} else {
			@rename( $supercachedir . '.disabled', $supercachedir );
		}
	}
}

function wp_super_cache_disable() {
	global $cache_path, $supercachedir, $wp_cache_config_file, $super_cache_enabled;

	if ( ! $super_cache_enabled ) {
		wp_cache_debug( 'wp_super_cache_disable: already disabled' );
		return true;
	}

	wp_cache_setting( 'super_cache_enabled', false );
	wp_cache_debug( 'wp_super_cache_disable: disable cache' );

	$super_cache_enabled = false;

	if ( ! $supercachedir ) {
		$supercachedir = get_supercache_dir();
	}

	if ( is_dir( $supercachedir ) ) {
		@rename( $supercachedir, $supercachedir . '.disabled' );
	}
	sleep( 1 ); // allow existing processes to write to the supercachedir and then delete it
	if ( function_exists( 'prune_super_cache' ) && is_dir( $supercachedir ) ) {
		prune_super_cache( $cache_path, true );
	}

	if ( $GLOBALS['wp_cache_mod_rewrite'] === 1 ) {
		remove_mod_rewrite_rules();
	}
}

function wp_cache_is_enabled() {
	global $wp_cache_config_file;

	if ( get_option( 'gzipcompression' ) ) {
		echo '<strong>' . __( 'Warning', 'wp-super-cache' ) . '</strong>: ' . __( 'GZIP compression is enabled in WordPress, wp-cache will be bypassed until you disable gzip compression.', 'wp-super-cache' );
		return false;
	}

	$lines = file( $wp_cache_config_file );
	foreach ( $lines as $line ) {
		if ( preg_match( '/^\s*\$cache_enabled\s*=\s*true\s*;/', $line ) ) {
			return true;
		}
	}

	return false;
}

function wp_cache_remove_index() {
	global $cache_path;

	if ( empty( $cache_path ) ) {
		return;
	}

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
						unlink( $directory . "/meta/index.html" );
					}
				}
			}
		}
	}
}

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
		if ( ! function_exists( 'insert_with_markers' ) ) {
                        include_once( ABSPATH . 'wp-admin/includes/misc.php' );
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
		$lines = implode( ' ', file( $wp_cache_config_file ) );
		if ( ! str_contains( $lines, 'WPCACHEHOME' ) ) {
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
		$dir = str_replace( str_replace( '\\', '/', WP_CONTENT_DIR ), '', str_replace( '\\', '/', dirname( __DIR__ ) ) );
		if ( is_file( dirname( __DIR__ ) . '/wp-cache-config-sample.php' ) ) {
			wp_cache_replace_line('define\(\ \'WPCACHEHOME', "\tdefine( 'WPCACHEHOME', WP_CONTENT_DIR . \"{$dir}/\" );", $wp_cache_config_file);
		} elseif ( is_file( dirname( __DIR__ ) . '/wp-super-cache/wp-cache-config-sample.php' ) ) {
			wp_cache_replace_line('define\(\ \'WPCACHEHOME', "\tdefine( 'WPCACHEHOME', WP_CONTENT_DIR . \"{$dir}/wp-super-cache/\" );", $wp_cache_config_file);
		}
		$new = true;
	}
	if ( $sem_id == 5419 && $cache_path != '' && $WPSC_HTTP_HOST != '' ) {
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
	global $wpsc_advanced_cache_filename, $wpsc_advanced_cache_dist_filename;
	if ( file_exists( ABSPATH . 'wp-config.php') ) {
		$global_config_file = ABSPATH . 'wp-config.php';
	} elseif ( file_exists( dirname( ABSPATH ) . '/wp-config.php' ) ) {
		$global_config_file = dirname( ABSPATH ) . '/wp-config.php';
	} elseif ( defined( 'DEBIAN_FILE' ) && file_exists( DEBIAN_FILE ) ) {
		$global_config_file = DEBIAN_FILE;
	} else {
		die('Cannot locate wp-config.php');
	}

	$line = 'define( \'WPCACHEHOME\', \'' . dirname( __DIR__ ) . '/\' );';

	if ( ! apply_filters( 'wpsc_enable_wp_config_edit', true ) ) {
		echo '<div class="notice notice-error"><h4>' . __( 'Warning', 'wp-super-cache' ) . "! " . sprintf( __( 'Not allowed to edit %s per configuration.', 'wp-super-cache' ), $global_config_file ) . "</h4></div>";
		return false;
	}

	if (
		! strpos( file_get_contents( $global_config_file ), "WPCACHEHOME" ) ||
		(
			defined( 'WPCACHEHOME' ) &&
			(
				constant( 'WPCACHEHOME' ) == '' ||
				(
					constant( 'WPCACHEHOME' ) != '' &&
					! file_exists( constant( 'WPCACHEHOME' ) . '/wp-cache.php' )
				)
			)
		)
	) {
		if (
			! is_writeable_ACLSafe( $global_config_file ) ||
			! wp_cache_replace_line( 'define *\( *\'WPCACHEHOME\'', $line, $global_config_file )
		) {
			echo '<div class="notice notice-error"><h4>' . __( 'Warning', 'wp-super-cache' ) . "! <em>" . sprintf( __( 'Could not update %s!</em> WPCACHEHOME must be set in config file.', 'wp-super-cache' ), $global_config_file ) . "</h4></div>";
			return false;
		}
	}
	$ret = true;

	if ( file_exists( $wpsc_advanced_cache_filename ) ) {
		$file = file_get_contents( $wpsc_advanced_cache_filename );
		if (
			! strpos( $file, "WP SUPER CACHE 0.8.9.1" ) &&
			! strpos( $file, "WP SUPER CACHE 1.2" )
		) {
			return false;
		}
	}

	$file = file_get_contents( $wpsc_advanced_cache_dist_filename );
	$fp = @fopen( $wpsc_advanced_cache_filename, 'w' );
	if( $fp ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fwrite( $fp, $file );
		fclose( $fp );
		do_action( 'wpsc_created_advanced_cache' );
	} else {
		$ret = false;
	}
	return $ret;
}

/**
 * Identify the advanced cache plugin used
 *
 * @return string The name of the advanced cache plugin, BOOST, WPSC or OTHER.
 */
function wpsc_identify_advanced_cache() {
	global $wpsc_advanced_cache_filename;
	if ( ! file_exists( $wpsc_advanced_cache_filename ) ) {
		return 'NONE';
	}
	$contents = file_get_contents( $wpsc_advanced_cache_filename ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

	if ( false !== str_contains( $contents, 'Boost Cache Plugin' ) ) {
		return 'BOOST';
	}

	if ( str_contains( $contents, 'WP SUPER CACHE 0.8.9.1' ) || str_contains( $contents, 'WP SUPER CACHE 1.2' ) ) {
		return 'WPSC';
	}

	return 'OTHER';
}

function wpsc_check_advanced_cache() {
	global $wpsc_advanced_cache_filename;

	$ret                  = false;
	$other_advanced_cache = false;
	if ( file_exists( $wpsc_advanced_cache_filename ) ) {
		$cache_type = wpsc_identify_advanced_cache();
		switch ( $cache_type ) {
			case 'WPSC':
				return true;
			case 'BOOST':
				$other_advanced_cache = 'BOOST';
				break;
			default:
				$other_advanced_cache = true;
				break;
		}
	} else {
		$ret = wp_cache_create_advanced_cache();
	}

	if ( false == $ret ) {
		if ( $other_advanced_cache === 'BOOST' ) {
			wpsc_deactivate_boost_cache_notice();
		} elseif ( $other_advanced_cache ) {
			echo '<div style="width: 50%" class="notice notice-error"><h2>' . __( 'Warning! You may not be allowed to use this plugin on your site.', 'wp-super-cache' ) . "</h2>";
			echo '<p>' .
				sprintf(
					__( 'The file %s was created by another plugin or by your system administrator. Please examine the file carefully by FTP or SSH and consult your hosting documentation. ', 'wp-super-cache' ),
					$wpsc_advanced_cache_filename
				) .
				'</p>';
			echo '<p>' .
				__( 'If it was created by another caching plugin please uninstall that plugin first before activating WP Super Cache. If the file is not removed by that action you should delete the file manually.', 'wp-super-cache' ),
				'</p>';
			echo '<p><strong>' .
				__( 'If you need support for this problem contact your hosting provider.', 'wp-super-cache' ),
				'</strong></p>';
			echo '</div>';
		} elseif ( ! is_writeable_ACLSafe( $wpsc_advanced_cache_filename ) ) {
			echo '<div class="notice notice-error"><h2>' . __( 'Warning', 'wp-super-cache' ) . "! <em>" . sprintf( __( '%s/advanced-cache.php</em> cannot be updated.', 'wp-super-cache' ), WP_CONTENT_DIR ) . "</h2>";
			echo '<ol>';
			echo "<li>" .
				sprintf(
					__( 'Make %1$s writable using the chmod command through your ftp or server software. (<em>chmod 777 %1$s</em>) and refresh this page. This is only a temporary measure and you&#8217;ll have to make it read only afterwards again. (Change 777 to 755 in the previous command)', 'wp-super-cache' ),
					WP_CONTENT_DIR
				) .
				"</li>";
			echo "<li>" . sprintf( __( 'Refresh this page to update <em>%s/advanced-cache.php</em>', 'wp-super-cache' ), WP_CONTENT_DIR ) . "</li></ol>";
			echo sprintf( __( 'If that doesn&#8217;t work, make sure the file <em>%s/advanced-cache.php</em> doesn&#8217;t exist:', 'wp-super-cache' ), WP_CONTENT_DIR ) . "<ol>";
			echo "</ol>";
			echo '</div>';
		}
		return false;
	}
	return true;
}

function wp_cache_check_global_config() {
	global $wp_cache_check_wp_config;

	if ( !isset( $wp_cache_check_wp_config ) )
		return true;


	if ( file_exists( ABSPATH . 'wp-config.php') ) {
		$global_config_file = ABSPATH . 'wp-config.php';
	} else {
		$global_config_file = dirname( ABSPATH ) . '/wp-config.php';
	}

	if ( preg_match( '#^\s*(define\s*\(\s*[\'"]WP_CACHE[\'"]|const\s+WP_CACHE\s*=)#m', file_get_contents( $global_config_file ) ) === 1 ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( defined( 'WP_CACHE' ) && ! constant( 'WP_CACHE' ) ) {
			?>
			<div class="notice notice-error"><h4><?php esc_html_e( 'WP_CACHE constant set to false', 'wp-super-cache' ); ?></h4>
			<p><?php esc_html_e( 'The WP_CACHE constant is used by WordPress to load the code that serves cached pages. Unfortunately, it is set to false. Please edit your wp-config.php and add or edit the following line above the final require_once command:', 'wp-super-cache' ); ?></p>
			<p><code>define('WP_CACHE', true);</code></p></div>
			<?php
			return false;
		} else {
			return true;
		}
	}

	$line = 'define(\'WP_CACHE\', true);';
	if (
		! is_writeable_ACLSafe( $global_config_file ) ||
		! wp_cache_replace_line( 'define *\( *\'WP_CACHE\'', $line, $global_config_file )
	) {
		if ( defined( 'WP_CACHE' ) && constant( 'WP_CACHE' ) == false ) {
			echo '<div class="notice notice-error">' . __( "<h4>WP_CACHE constant set to false</h4><p>The WP_CACHE constant is used by WordPress to load the code that serves cached pages. Unfortunately, it is set to false. Please edit your wp-config.php and add or edit the following line above the final require_once command:<br /><br /><code>define('WP_CACHE', true);</code></p>", 'wp-super-cache' ) . "</div>";
		} else {
			echo '<div class="notice notice-error"><p>' . __( "<strong>Error: WP_CACHE is not enabled</strong> in your <code>wp-config.php</code> file and I couldn&#8217;t modify it.", 'wp-super-cache' ) . "</p>";
			echo "<p>" . sprintf( __( "Edit <code>%s</code> and add the following line:<br /> <code>define('WP_CACHE', true);</code><br />Otherwise, <strong>WP-Cache will not be executed</strong> by WordPress core. ", 'wp-super-cache' ), $global_config_file ) . "</p></div>";
		}
		return false;
	}  else {
		echo "<div class='notice notice-warning'>" . __( '<h4>WP_CACHE constant added to wp-config.php</h4><p>If you continue to see this warning message please see point 5 of the <a href="https://wordpress.org/plugins/wp-super-cache/faq/">Troubleshooting Guide</a>. The WP_CACHE line must be moved up.', 'wp-super-cache' ) . "</p></div>";
	}
	return true;
}

function wp_cache_disable_plugin( $delete_config_file = true ) {
	global $wp_rewrite;
	if ( file_exists( ABSPATH . 'wp-config.php') ) {
		$global_config_file = ABSPATH . 'wp-config.php';
	} else {
		$global_config_file = dirname(ABSPATH) . '/wp-config.php';
	}

	if ( apply_filters( 'wpsc_enable_wp_config_edit', true ) ) {
		$line = 'define(\'WP_CACHE\', true);';
		if (
			strpos( file_get_contents( $global_config_file ), $line ) &&
			(
				! is_writeable_ACLSafe( $global_config_file ) ||
				! wp_cache_replace_line( 'define*\(*\'WP_CACHE\'', '', $global_config_file )
			)
		) {
			wp_die( "Could not remove WP_CACHE define from $global_config_file. Please edit that file and remove the line containing the text 'WP_CACHE'. Then refresh this page." );
		}
		$line = 'define( \'WPCACHEHOME\',';
		if (
			strpos( file_get_contents( $global_config_file ), $line ) &&
			(
				! is_writeable_ACLSafe( $global_config_file ) ||
				! wp_cache_replace_line( 'define *\( *\'WPCACHEHOME\'', '', $global_config_file )
			)
		) {
			wp_die( "Could not remove WPCACHEHOME define from $global_config_file. Please edit that file and remove the line containing the text 'WPCACHEHOME'. Then refresh this page." );
		}
	} elseif ( function_exists( 'wp_cache_debug' ) ) {
		wp_cache_debug( 'wp_cache_disable_plugin: not allowed to edit wp-config.php per configuration.' );
	}

	uninstall_supercache( WP_CONTENT_DIR . '/cache' );
	$file_not_deleted = array();
	wpsc_remove_advanced_cache();
	if ( @file_exists( WP_CONTENT_DIR . "/advanced-cache.php" ) ) {
		$file_not_deleted[] = WP_CONTENT_DIR . '/advanced-cache.php';
	}
	if ( $delete_config_file && @file_exists( WPCACHECONFIGPATH . "/wp-cache-config.php" ) ) {
		if ( false == unlink( WPCACHECONFIGPATH . "/wp-cache-config.php" ) )
			$file_not_deleted[] = WPCACHECONFIGPATH . '/wp-cache-config.php';
	}
	if ( ! empty( $file_not_deleted ) ) {
		$msg = __( "Dear User,\n\nWP Super Cache was removed from your blog or deactivated but some files could\nnot be deleted.\n\n", 'wp-super-cache' );
		foreach ( $file_not_deleted as $path ) {
			$msg .=  "{$path}\n";
		}
		$msg .= "\n";
		$msg .= sprintf( __( "You should delete these files manually.\nYou may need to change the permissions of the files or parent directory.\nYou can read more about this in the Codex at\n%s\n\nThank you.", 'wp-super-cache' ), 'https://codex.wordpress.org/Changing_File_Permissions#About_Chmod' );

		if ( apply_filters( 'wpsc_send_uninstall_errors', 1 ) ) {
			wp_mail( get_option( 'admin_email' ), __( 'WP Super Cache: could not delete files', 'wp-super-cache' ), $msg );
		}
	}
	extract( wpsc_get_htaccess_info() ); // $document_root, $apache_root, $home_path, $home_root, $home_root_lc, $inst_root, $wprules, $scrules, $condition_rules, $rules, $gziprules
	// @phan-suppress-next-line PhanTypeSuspiciousStringExpression -- $home_path is set via extract()
	if ( $scrules != '' && insert_with_markers( $home_path.'.htaccess', 'WPSuperCache', array() ) ) {
		$wp_rewrite->flush_rules();
	} elseif( $scrules != '' ) {
		wp_mail( get_option( 'admin_email' ), __( 'Supercache Uninstall Problems', 'wp-super-cache' ), sprintf( __( "Dear User,\n\nWP Super Cache was removed from your blog but the mod_rewrite rules\nin your .htaccess were not.\n\nPlease edit the following file and remove the code\nbetween 'BEGIN WPSuperCache' and 'END WPSuperCache'. Please backup the file first!\n\n%s\n\nRegards,\nWP Super Cache Plugin\nhttps://wordpress.org/plugins/wp-super-cache/", 'wp-super-cache' ), ABSPATH . '/.htaccess' ) );
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

function wpsc_update_check() {
	global $wpsc_version;

	if (
		! isset( $wpsc_version ) ||
		$wpsc_version != 169
	) {
		wp_cache_setting( 'wpsc_version', 169 );
		global $wp_cache_debug_log, $cache_path;
		$log_file = $cache_path . str_replace('/', '', str_replace('..', '', $wp_cache_debug_log));
		if ( ! file_exists( $log_file ) ) {
			return false;
		}
		@unlink( $log_file );
		wp_cache_debug( 'wpsc_update_check: Deleted old log file on plugin update.' );
	}
}
add_action( 'admin_init', 'wpsc_update_check' );
