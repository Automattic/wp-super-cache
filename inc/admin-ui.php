<?php
/**
 * The WP Super Cache admin settings screen: menu / network-menu registration,
 * style and script enqueue (+ install-plugin AJAX), the error-checks filter,
 * the admin_init update handler, the main settings-page renderer (including its
 * inline toggleLayer JavaScript), the tabs, the admin-bar render, and the
 * partial / header / footer render helpers.
 *
 * Relocated from wp-cache.php (issue #1061) as a pure move: identical function
 * signatures and hook registrations, no behaviour change.
 *
 * @package WP_Super_Cache
 */

function wpsc_enqueue_styles() {
	wp_enqueue_style(
		'wpsc_styles',
		plugins_url( 'styling/dashboard.css', dirname( __DIR__ ) . '/wp-cache.php' ),
		array(),
		filemtime( plugin_dir_path( dirname( __DIR__ ) . '/wp-cache.php' ) . 'styling/dashboard.css' )
	);
}

// Check for the page parameter to see if we're on a WPSC page.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( isset( $_GET['page'] ) && $_GET['page'] === 'wpsupercache' ) {
	add_action( 'admin_enqueue_scripts', 'wpsc_enqueue_styles' );
}


function wp_cache_add_pages() {
	if ( wpsupercache_site_admin() ) {
		// In single or MS mode add this menu item too, but only for superadmins in MS mode.
		add_options_page( 'WP Super Cache', 'WP Super Cache', 'manage_options', 'wpsupercache', 'wp_cache_manager' );
	}
}
add_action( 'admin_menu', 'wp_cache_add_pages' );


function wp_cache_network_pages() {
	add_submenu_page( 'settings.php', 'WP Super Cache', 'WP Super Cache', 'manage_options', 'wpsupercache', 'wp_cache_manager' );
}
add_action( 'network_admin_menu', 'wp_cache_network_pages' );

/**
 * Load JavaScript on admin pages.
 */
function wp_super_cache_admin_enqueue_scripts( $hook ) {
	if ( 'settings_page_wpsupercache' !== $hook ) {
		return;
	}

	wp_enqueue_script(
		'wp-super-cache-admin',
		trailingslashit( plugin_dir_url( dirname( __DIR__ ) . '/wp-cache.php' ) ) . 'js/admin.js',
		array( 'jquery' ),
		WPSC_VERSION_ID,
		false
	);

	wp_localize_script(
		'wp-super-cache-admin',
		'wpscAdmin',
		array(
			'boostNoticeDismissNonce' => wp_create_nonce( 'wpsc_dismiss_boost_notice' ),
			'boostDismissNonce'       => wp_create_nonce( 'wpsc_dismiss_boost_banner' ),
			'boostInstallNonce'       => wp_create_nonce( 'updates' ),
			'boostActivateNonce'      => wp_create_nonce( 'activate-boost' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'wp_super_cache_admin_enqueue_scripts' );

/**
 * Use the standard WordPress plugin installation ajax handler.
 */
add_action( 'wp_ajax_wpsc_install_plugin', 'wp_ajax_install_plugin' );

function wp_cache_manager_error_checks() {
	global $wp_cache_debug, $wp_cache_cron_check, $cache_enabled, $super_cache_enabled, $wp_cache_config_file, $wp_cache_mobile_browsers, $wp_cache_mobile_prefixes, $wp_cache_mobile_browsers, $wp_cache_mobile_enabled, $wp_cache_mod_rewrite;
	global $dismiss_htaccess_warning, $dismiss_readable_warning, $dismiss_gc_warning, $wp_cache_shutdown_gc, $is_nginx;
	global $htaccess_path;

	if ( ! wpsupercache_site_admin() ) {
		return false;
	}

	// phpcs:ignore PHPCompatibility.IniDirectives.RemovedIniDirectives.safe_modeDeprecatedRemoved -- Version is checked before access.
	if ( PHP_VERSION_ID < 50300 && ( ini_get( 'safe_mode' ) === '1' || strtolower( ini_get( 'safe_mode' ) ) === 'on' ) ) { // @codingStandardsIgnoreLine
		echo '<div class="notice notice-error"><h4>' . esc_html__( 'Warning! PHP Safe Mode Enabled!', 'wp-super-cache' ) . '</h4>';
		echo '<p>' . esc_html__( 'You may experience problems running this plugin because SAFE MODE is enabled.', 'wp-super-cache' ) . '<br />';

		// phpcs:ignore PHPCompatibility.IniDirectives.RemovedIniDirectives.safe_mode_gidDeprecatedRemoved -- Version is checked before access.
		if ( ! ini_get( 'safe_mode_gid' ) ) { // @codingStandardsIgnoreLine
			esc_html_e( 'Your server is set up to check the owner of PHP scripts before allowing them to read and write files.', 'wp-super-cache' );
			echo '<br />';
			printf( __( 'You or an administrator may be able to make it work by changing the group owner of the plugin scripts to match that of the web server user. The group owner of the %s/cache/ directory must also be changed. See the  <a href="http://php.net/features.safe-mode">safe mode manual page</a> for further details.', 'wp-super-cache' ), esc_attr( WP_CONTENT_DIR ) );
		} else {
			_e( 'You or an administrator must disable this. See the <a href="http://php.net/features.safe-mode">safe mode manual page</a> for further details. This cannot be disabled in a .htaccess file unfortunately. It must be done in the php.ini config file.', 'wp-super-cache' );
		}
		echo '</p></div>';
	}

	if ( '' == get_option( 'permalink_structure' ) ) {
		echo '<div class="notice notice-error"><h4>' . __( 'Permlink Structure Error', 'wp-super-cache' ) . '</h4>';
		echo "<p>" . __( 'A custom url or permalink structure is required for this plugin to work correctly. Please go to the <a href="options-permalink.php">Permalinks Options Page</a> to configure your permalinks.', 'wp-super-cache' ) . "</p>";
		echo '</div>';
		return false;
	}

	if ( $wp_cache_debug || ! $wp_cache_cron_check ) {
		if ( defined( 'DISABLE_WP_CRON' ) && constant( 'DISABLE_WP_CRON' ) ) {
			?>
			<div class="notice notice-error"><h4><?php _e( 'CRON System Disabled', 'wp-super-cache' ); ?></h4>
			<p><?php _e( 'The WordPress CRON jobs system is disabled. This means the garbage collection system will not work unless you run the CRON system manually.', 'wp-super-cache' ); ?></p>
			</div>
			<?php
		} elseif ( function_exists( "wp_remote_get" ) == false ) {
			$hostname = str_replace( 'http://', '', str_replace( 'https://', '', get_option( 'siteurl' ) ) );
			if( strpos( $hostname, '/' ) )
				$hostname = substr( $hostname, 0, strpos( $hostname, '/' ) );
			$ip = gethostbyname( $hostname );
			if( substr( $ip, 0, 3 ) == '127' || substr( $ip, 0, 7 ) == '192.168' ) {
				?><div class="notice notice-warning"><h4><?php printf( __( 'Warning! Your hostname "%s" resolves to %s', 'wp-super-cache' ), $hostname, $ip ); ?></h4>
					<p><?php printf( __( 'Your server thinks your hostname resolves to %s. Some services such as garbage collection by this plugin, and WordPress scheduled posts may not operate correctly.', 'wp-super-cache' ), $ip ); ?></p>
					<p><?php printf( __( 'Please see entry 16 in the <a href="%s">Troubleshooting section</a> of the readme.txt', 'wp-super-cache' ), 'https://wordpress.org/plugins/wp-super-cache/faq/' ); ?></p>
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
					?><div class="notice notice-error"><h4>Warning! wp-cron.php not found!</h4>
					<p><?php _e( 'Unfortunately, WordPress cannot find the file wp-cron.php. This script is required for the correct operation of garbage collection by this plugin, WordPress scheduled posts as well as other critical activities.', 'wp-super-cache' ); ?></p>
					<p><?php printf( __( 'Please see entry 16 in the <a href="%s">Troubleshooting section</a> of the readme.txt', 'wp-super-cache' ), 'https://wordpress.org/plugins/wp-super-cache/faq/' ); ?></p>
					</div>
					<?php
				} else {
					wp_cache_replace_line('^ *\$wp_cache_cron_check', "\$wp_cache_cron_check = 1;", $wp_cache_config_file);
				}
			}
		}
	}

	if (
		! wpsc_check_advanced_cache() ||
		! wp_cache_verify_config_file() ||
		! wp_cache_verify_cache_dir()
	) {
		echo '<p>' . __( "Cannot continue... fix previous problems and retry.", 'wp-super-cache' ) . '</p>';
		return false;
	}

	if ( false == function_exists( 'wpsc_deep_replace' ) ) {
		$msg = __( 'Warning! You must set WP_CACHE and WPCACHEHOME in your wp-config.php for this plugin to work correctly:' ) . '<br />';
		$msg .= "<code>define( 'WP_CACHE', true );</code><br />";
		$msg .= "<code>define( 'WPCACHEHOME', '" . dirname( __DIR__ ) . "/' );</code><br />";
		wp_die( $msg );
	}

	if (!wp_cache_check_global_config()) {
		return false;
	}

	if ( 1 == ini_get( 'zlib.output_compression' ) || "on" == strtolower( ini_get( 'zlib.output_compression' ) ) ) {
		?><div class="notice notice-warning"><h4><?php _e( 'Zlib Output Compression Enabled!', 'wp-super-cache' ); ?></h4>
		<p><?php _e( 'PHP is compressing the data sent to the visitors of your site. Disabling this is recommended as the plugin caches the compressed output once instead of compressing the same page over and over again. Also see #21 in the Troubleshooting section. See <a href="http://php.net/manual/en/zlib.configuration.php">this page</a> for instructions on modifying your php.ini.', 'wp-super-cache' ); ?></p></div><?php
	}

	if (
		$cache_enabled == true &&
		$super_cache_enabled == true &&
		$wp_cache_mod_rewrite &&
		! got_mod_rewrite() &&
		! $is_nginx
	) {
		?><div class="notice notice-warning"><h4><?php _e( 'Mod rewrite may not be installed!', 'wp-super-cache' ); ?></h4>
		<p><?php _e( 'It appears that mod_rewrite is not installed. Sometimes this check isn&#8217;t 100% reliable, especially if you are not using Apache. Please verify that the mod_rewrite module is loaded. It is required for serving Super Cache static files in expert mode. You will still be able to simple mode.', 'wp-super-cache' ); ?></p></div><?php
	}

	if( !is_writeable_ACLSafe( $wp_cache_config_file ) ) {
		if ( !defined( 'SUBMITDISABLED' ) )
			define( "SUBMITDISABLED", 'disabled style="color: #aaa" ' );
		?><div class="notice notice-error"><h4><?php _e( 'Read Only Mode. Configuration cannot be changed.', 'wp-super-cache' ); ?></h4>
		<p><?php printf( __( 'The WP Super Cache configuration file is <code>%s/wp-cache-config.php</code> and cannot be modified. That file must be writeable by the web server to make any changes.', 'wp-super-cache' ), WPCACHECONFIGPATH ); ?>
		<?php _e( 'A simple way of doing that is by changing the permissions temporarily using the CHMOD command or through your ftp client. Make sure it&#8217;s globally writeable and it should be fine.', 'wp-super-cache' ); ?></p>
		<p><?php _e( '<a href="https://codex.wordpress.org/Changing_File_Permissions">This page</a> explains how to change file permissions.', 'wp-super-cache' ); ?></p>
		<?php _e( 'Writeable:', 'wp-super-cache' ); ?> <code>chmod 666 <?php echo WPCACHECONFIGPATH; ?>/wp-cache-config.php</code><br />
		<?php _e( 'Read-only:', 'wp-super-cache' ); ?> <code>chmod 644 <?php echo WPCACHECONFIGPATH; ?>/wp-cache-config.php</code></p>
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
				?><div class="notice notice-warning"><h4><?php _e( 'Warning! Garbage collection is not scheduled!', 'wp-super-cache' ); ?></h4>
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
		$wp_content_mode = decoct( fileperms( WP_CONTENT_DIR . '/' ) & 0777 );
		if( substr( $wp_content_mode, -2 ) == '77' ) {
			?><div class="notice notice-warning"><h4><?php printf( __( 'Warning! %s is writeable!', 'wp-super-cache' ), WP_CONTENT_DIR ); ?></h4>
			<p><?php printf( __( 'You should change the permissions on %s and make it more restrictive. Use your ftp client, or the following command to fix things:', 'wp-super-cache' ), WP_CONTENT_DIR ); ?> <code>chmod 755 <?php echo WP_CONTENT_DIR; ?>/</code></p>
			<p><?php _e( '<a href="https://codex.wordpress.org/Changing_File_Permissions">This page</a> explains how to change file permissions.', 'wp-super-cache' ); ?></p>
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

	if ( ! $is_nginx && function_exists( "is_main_site" ) && true == is_main_site() ) {
		if ( ! isset( $htaccess_path ) ) {
			$home_path = trailingslashit( get_home_path() );
		} else {
			$home_path = $htaccess_path;
		}

		$scrules = implode( "\n", extract_from_markers( $home_path . '.htaccess', 'WPSuperCache' ) );
		if (
			$cache_enabled
			&& $wp_cache_mod_rewrite
			&& ! $wp_cache_mobile_enabled
			&& strpos( $scrules, addcslashes( str_replace( ', ', '|', $wp_cache_mobile_browsers ), ' ' ) )
		) {
			echo '<div class="notice notice-warning"><h4>' . esc_html__( 'Mobile rewrite rules detected', 'wp-super-cache' ) . '</h4>';
			echo '<p>' . esc_html__( 'For best performance you should enable "Mobile device support" or delete the mobile rewrite rules in your .htaccess. Look for the 2 lines with the text "2.0\ MMP|240x320" and delete those.', 'wp-super-cache' ) . '</p><p>' . esc_html__( 'This will have no affect on ordinary users but mobile users will see uncached pages.', 'wp-super-cache' ) . '</p></div>';
		} elseif (
			$wp_cache_mod_rewrite
			&& $cache_enabled
			&& $wp_cache_mobile_enabled
			&& $scrules != '' // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
			&& (
				(
					'' != $wp_cache_mobile_prefixes // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
					&& ! str_contains( $scrules, addcslashes( str_replace( ', ', '|', $wp_cache_mobile_prefixes ), ' ' ) )
				)
				|| (
					'' != $wp_cache_mobile_browsers // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
					&& ! str_contains( $scrules, addcslashes( str_replace( ', ', '|', $wp_cache_mobile_browsers ), ' ' ) )
				)
			)
		) {
			?>
			<div class="notice notice-warning"><h4><?php esc_html_e( 'Rewrite rules must be updated', 'wp-super-cache' ); ?></h4>
			<p><?php esc_html_e( 'The rewrite rules required by this plugin have changed or are missing. ', 'wp-super-cache' ); ?>
			<?php esc_html_e( 'Mobile support requires extra rules in your .htaccess file, or you can set the plugin to simple mode. Here are your options (in order of difficulty):', 'wp-super-cache' ); ?></p>
			<ol><li> <?php esc_html_e( 'Set the plugin to simple mode and enable mobile support.', 'wp-super-cache' ); ?></li>
			<li> <?php _e( 'Scroll down the Advanced Settings page and click the <strong>Update Mod_Rewrite Rules</strong> button.', 'wp-super-cache' ); ?></li>
			<li>
			<?php
			// translators: %s is the path to the .htaccess file.
			printf( wp_kses( __( 'Delete the plugin mod_rewrite rules in %s.htaccess enclosed by <code># BEGIN WPSuperCache</code> and <code># END WPSuperCache</code> and let the plugin regenerate them by reloading this page.', 'wp-super-cache' ), array( 'code' => array() ) ), esc_html( $home_path ) );
			?>
			</li>
			<li>
			<?php
			// translators: %1$s is the path to the .htaccess file, %2$s is the logged-in cookie name.
			printf( wp_kses( __( 'Add the rules yourself. Edit %1$s.htaccess and find the block of code enclosed by the lines <code># BEGIN WPSuperCache</code> and <code># END WPSuperCache</code>. There are two sections that look very similar. Just below the line <code>%%{HTTP:Cookie} !^.*(comment_author_|%2$s|wp-postpass_).*$</code> add these lines: (do it twice, once for each section)', 'wp-super-cache' ), array( 'code' => array() ) ), esc_html( $home_path ), esc_html( wpsc_get_logged_in_cookie() ) );
			?>
			</p>
			<div style='padding: 2px; margin: 2px; border: 1px solid #333; width:400px; overflow: scroll'><pre><?php echo "RewriteCond %{HTTP_user_agent} !^.*(" . addcslashes( str_replace( ', ', '|', $wp_cache_mobile_browsers ), ' ' ) . ").*\nRewriteCond %{HTTP_user_agent} !^(" . addcslashes( str_replace( ', ', '|', $wp_cache_mobile_prefixes ), ' ' ) . ").*"; ?></pre></div></li></ol></div><?php
		}

		if (
			$cache_enabled
			&& $super_cache_enabled
			&& $wp_cache_mod_rewrite
			&& $scrules == '' // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
		) {
			?>
			<div class='notice notice-warning'><h4><?php esc_html_e( 'Rewrite rules must be updated', 'wp-super-cache' ); ?></h4>
			<p><?php esc_html_e( 'The rewrite rules required by this plugin have changed or are missing. ', 'wp-super-cache' ); ?>
			<?php esc_html_e( 'Scroll down the Advanced Settings page and click the <strong>Update Mod_Rewrite Rules</strong> button.', 'wp-super-cache' ); ?></p></div>
			<?php
		}
	}

	if ( ! $is_nginx && $wp_cache_mod_rewrite && $super_cache_enabled && function_exists( 'apache_get_modules' ) ) {
		$mods = apache_get_modules();
		$required_modules = array( 'mod_mime' => __( 'Required to serve compressed supercache files properly.', 'wp-super-cache' ), 'mod_headers' => __( 'Required to set caching information on supercache pages. IE7 users will see old pages without this module.', 'wp-super-cache' ), 'mod_expires' => __( 'Set the expiry date on supercached pages. Visitors may not see new pages when they refresh or leave comments without this module.', 'wp-super-cache' ) );
		foreach( $required_modules as $req => $desc ) {
			if( !in_array( $req, $mods ) ) {
				$missing_mods[ $req ] = $desc;
			}
		}
		if( isset( $missing_mods) && is_array( $missing_mods ) ) {
			?><div class='notice notice-warning'><h4><?php _e( 'Missing Apache Modules', 'wp-super-cache' ); ?></h4>
			<p><?php __( 'The following Apache modules are missing. The plugin will work in simple mode without them but in expert mode, your visitors may see corrupted pages or out of date content however.', 'wp-super-cache' ); ?></p><?php
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
	if ( ! $is_nginx && $dismiss_htaccess_warning == 0 && $wp_cache_mod_rewrite && $super_cache_enabled && get_option( 'siteurl' ) != get_option( 'home' ) ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual,Universal.Operators.StrictComparisons.LooseNotEqual
		?>
		<div class="notice notice-info"><h4><?php esc_html_e( '.htaccess file may need to be moved', 'wp-super-cache' ); ?></h4>
		<p><?php _e( 'It appears you have WordPress installed in a sub directory as described <a href="https://codex.wordpress.org/Giving_WordPress_Its_Own_Directory">here</a>. Unfortunately, WordPress writes to the .htaccess in the install directory, not where your site is served from.<br />When you update the rewrite rules in this plugin you will have to copy the file to where your site is hosted. This will be fixed in the future.', 'wp-super-cache' ); ?></p>
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

function wp_cache_manager_updates() {
	global $wp_cache_mobile_enabled, $wp_cache_mfunc_enabled, $wp_supercache_cache_list, $wp_cache_config_file, $wp_cache_clear_on_post_edit, $cache_rebuild_files, $wp_cache_mutex_disabled, $wp_cache_not_logged_in, $wp_cache_make_known_anon, $cache_path, $wp_cache_refresh_single_only, $cache_compression, $wp_cache_mod_rewrite, $wp_supercache_304, $wp_super_cache_late_init, $wp_cache_front_page_checks, $cache_page_secret, $wp_cache_disable_utf8, $wp_cache_no_cache_for_get;
	global $cache_schedule_type, $cache_max_time, $cache_time_interval, $wp_cache_shutdown_gc, $wpsc_save_headers;

	if ( !wpsupercache_site_admin() )
		return false;

	if ( false == isset( $cache_page_secret ) ) {
		$cache_page_secret = md5( gmdate( 'H:i:s' ) . wp_rand() );
		wp_cache_replace_line('^ *\$cache_page_secret', "\$cache_page_secret = '" . $cache_page_secret . "';", $wp_cache_config_file);
	}

	$valid_nonce = isset($_REQUEST['_wpnonce']) ? wp_verify_nonce($_REQUEST['_wpnonce'], 'wp-cache') : false;
	if ( $valid_nonce == false )
		return false;

	if ( isset( $_POST[ 'action' ] ) && $_POST[ 'action' ] == 'easysetup' ) {
		$_POST[ 'action' ] = 'scupdates';
		if( isset( $_POST[ 'wp_cache_easy_on' ] ) && $_POST[ 'wp_cache_easy_on' ] == 1 ) {
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
		$advanced_settings = array( 'wp_super_cache_late_init', 'wp_cache_disable_utf8', 'wp_cache_no_cache_for_get', 'wp_supercache_304', 'wp_cache_mfunc_enabled', 'wp_cache_front_page_checks', 'wp_supercache_cache_list', 'wp_cache_clear_on_post_edit', 'wp_cache_make_known_anon', 'wp_cache_refresh_single_only', 'cache_compression' );
		foreach( $advanced_settings as $setting ) {
			if ( isset( $GLOBALS[ $setting ] ) && $GLOBALS[ $setting ] == 1 ) {
				$_POST[ $setting ] = 1;
			}
		}
		$_POST['wp_cache_not_logged_in'] = 2;
	}

	if( isset( $_POST[ 'action' ] ) && $_POST[ 'action' ] == 'scupdates' ) {
		if( isset( $_POST[ 'wp_cache_location' ] ) && $_POST[ 'wp_cache_location' ] != '' ) {
			$dir = realpath( trailingslashit( dirname( $_POST[ 'wp_cache_location' ] ) ) );
			if ( $dir === realpath( '.' ) || false === $dir ) {
				$dir = WP_CONTENT_DIR . '/cache/';
			} else {
				$dir = trailingslashit( $dir ) . trailingslashit(wpsc_deep_replace( array( '..', '\\' ), basename( $_POST[ 'wp_cache_location' ] ) ) );
			}
			$new_cache_path = $dir;
		} else {
			$new_cache_path = WP_CONTENT_DIR . '/cache/';
		}
		if ( $new_cache_path != $cache_path ) {
			if ( file_exists( $new_cache_path ) == false )
				rename( $cache_path, $new_cache_path );
			$cache_path = preg_replace('/[ <>\'\"\r\n\t\(\)\$\[\];#]/', '', $new_cache_path );
			wp_cache_replace_line('^ *\$cache_path', "\$cache_path = " . var_export( $cache_path, true ) . ";", $wp_cache_config_file);
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

		if ( isset( $_POST[ 'wp_cache_mod_rewrite' ] ) && $_POST[ 'wp_cache_mod_rewrite' ] == 1 ) {
			$wp_cache_mod_rewrite = 1;
			add_mod_rewrite_rules();
		} else {
			$wp_cache_mod_rewrite = 0; // cache files served by PHP
			remove_mod_rewrite_rules();
		}
		wp_cache_setting( 'wp_cache_mod_rewrite', $wp_cache_mod_rewrite );

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

		if ( isset( $_POST['wp_cache_not_logged_in'] ) && $_POST['wp_cache_not_logged_in'] != 0 ) {
			if ( $wp_cache_not_logged_in == 0 && function_exists( 'prune_super_cache' ) ) {
				prune_super_cache( $cache_path, true );
			}
			$wp_cache_not_logged_in = (int)$_POST['wp_cache_not_logged_in'];
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
			} elseif ( $new_cache_compression !== (int) $cache_compression ) {
				$cache_compression = $new_cache_compression;
				wp_cache_replace_line( '^ *\$cache_compression', "\$cache_compression = $cache_compression;", $wp_cache_config_file );
				if ( function_exists( 'prune_super_cache' ) ) {
					prune_super_cache( $cache_path, true );
				}
				delete_option( 'super_cache_meta' );
			}
		}
	}
}
if ( isset( $_GET[ 'page' ] ) && $_GET[ 'page' ] == 'wpsupercache' )
	add_action( 'admin_init', 'wp_cache_manager_updates' );

function wp_cache_manager() {
	global $wp_cache_config_file, $valid_nonce, $supercachedir, $cache_path, $cache_enabled, $cache_compression, $super_cache_enabled;
	global $wp_cache_clear_on_post_edit, $cache_rebuild_files, $wp_cache_mutex_disabled, $wp_cache_mobile_enabled, $wp_cache_mobile_browsers, $wp_cache_no_cache_for_get;
	global $wp_cache_not_logged_in, $wp_cache_make_known_anon, $wp_supercache_cache_list, $cache_page_secret;
	global $wp_super_cache_front_page_check, $wp_cache_refresh_single_only, $wp_cache_mobile_prefixes;
	global $wp_cache_mod_rewrite, $wp_supercache_304, $wp_super_cache_late_init, $wp_cache_front_page_checks, $wp_cache_disable_utf8, $wp_cache_mfunc_enabled;
	global $wp_super_cache_comments, $wp_cache_home_path, $wpsc_save_headers, $is_nginx;
	global $wpsc_promo_links;

	if ( !wpsupercache_site_admin() )
		return false;

	// used by mod_rewrite rules and config file
	if ( function_exists( "cfmobi_default_browsers" ) ) {
		$wp_cache_mobile_browsers = cfmobi_default_browsers( "mobile" );
		$wp_cache_mobile_browsers = array_merge( $wp_cache_mobile_browsers, cfmobi_default_browsers( "touch" ) );
	} elseif ( function_exists( 'lite_detection_ua_contains' ) ) {
		$wp_cache_mobile_browsers = explode( '|', lite_detection_ua_contains() );
	} else {
		$wp_cache_mobile_browsers = array( '2.0 MMP', '240x320', '400X240', 'AvantGo', 'BlackBerry', 'Blazer', 'Cellphone', 'Danger', 'DoCoMo', 'Elaine/3.0', 'EudoraWeb', 'Googlebot-Mobile', 'hiptop', 'IEMobile', 'KYOCERA/WX310K', 'LG/U990', 'MIDP-2.', 'MMEF20', 'MOT-V', 'NetFront', 'Newt', 'Nintendo Wii', 'Nitro', 'Nokia', 'Opera Mini', 'Palm', 'PlayStation Portable', 'portalmmm', 'Proxinet', 'ProxiNet', 'SHARP-TQ-GX10', 'SHG-i900', 'Small', 'SonyEricsson', 'Symbian OS', 'SymbianOS', 'TS21i-10', 'UP.Browser', 'UP.Link', 'webOS', 'Windows CE', 'WinWAP', 'YahooSeeker/M1A1-R2D2', 'iPhone', 'iPod', 'iPad', 'Android', 'BlackBerry9530', 'LG-TU915 Obigo', 'LGE VX', 'webOS', 'Nokia5800' );
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
	jQuery("fieldset h4").css("cursor","pointer").on("click",function(){
		jQuery(this).parent("fieldset").find("p,form,ul,blockquote").toggle("slow");
	});
});
</script>

<style type='text/css'>
#nav h3 {
	border-bottom: 1px solid #ccc;
	padding-bottom: 0;
	height: 1.5em;
}
table.wpsc-settings-table {
	clear: both;
}
</style>
<div id="wpsc-dashboard">
<?php
	wpsc_render_header();

	echo '<div class="wpsc-body">';
	echo '<a name="top"></a>';

	// Set a default.
	if ( false === $cache_enabled && ! isset( $wp_cache_mod_rewrite ) ) {
		$wp_cache_mod_rewrite = 0;
	} elseif ( ! isset( $wp_cache_mod_rewrite ) && $cache_enabled && $super_cache_enabled ) {
		$wp_cache_mod_rewrite = 1;
	}

	$admin_url = admin_url( 'options-general.php?page=wpsupercache' );
	$curr_tab  = ! empty( $_GET['tab'] ) ? sanitize_text_field( stripslashes( $_GET['tab'] ) ) : ''; // WPCS: sanitization ok.
	if ( empty( $curr_tab ) ) {
		$curr_tab = 'easy';
		if ( $wp_cache_mod_rewrite ) {
			$curr_tab = 'settings';
			echo '<div class="notice notice-info is-dismissible"><p>' .  __( 'Notice: <em>Expert mode caching enabled</em>. Showing Advanced Settings Page by default.', 'wp-super-cache' ) . '</p></div>';
		}
	}

	if ( 'preload' === $curr_tab ) {
		if ( true == $super_cache_enabled && ! defined( 'DISABLESUPERCACHEPRELOADING' ) ) {
			global $wp_cache_preload_interval, $wp_cache_preload_on, $wp_cache_preload_taxonomies, $wp_cache_preload_email_me, $wp_cache_preload_email_volume, $wp_cache_preload_posts, $wpdb;
			wpsc_preload_settings();
			$currently_preloading = false;

			echo '<div id="wpsc-preload-status"></div>';
		}
	}

	wpsc_admin_tabs( $curr_tab );
	echo '<div class="wpsc-body-content wrap">';

	if ( isset( $wp_super_cache_front_page_check ) && $wp_super_cache_front_page_check == 1 && ! wp_next_scheduled( 'wp_cache_check_site_hook' ) ) {
		wp_schedule_single_event( time() + 360, 'wp_cache_check_site_hook' );
		wp_cache_debug( 'scheduled wp_cache_check_site_hook for 360 seconds time.', 2 );
	}

	if ( isset( $_REQUEST['wp_restore_config'] ) && $valid_nonce ) {
		unlink( $wp_cache_config_file );
		echo '<strong>' . esc_html__( 'Configuration file changed, some values might be wrong. Load the page again from the "Settings" menu to reset them.', 'wp-super-cache' ) . '</strong>';
	}

	if ( substr( get_option( 'permalink_structure' ), -1 ) == '/' ) {
		wp_cache_replace_line( '^ *\$wp_cache_slash_check', "\$wp_cache_slash_check = 1;", $wp_cache_config_file );
	} else {
		wp_cache_replace_line( '^ *\$wp_cache_slash_check', "\$wp_cache_slash_check = 0;", $wp_cache_config_file );
	}
	$home_path = parse_url( site_url() );
	$home_path = trailingslashit( array_key_exists( 'path', $home_path ) ? $home_path['path'] : '' );
	if ( ! isset( $wp_cache_home_path ) ) {
		$wp_cache_home_path = '/';
		wp_cache_setting( 'wp_cache_home_path', '/' );
	}
	if ( "$home_path" != "$wp_cache_home_path" ) {
		wp_cache_setting( 'wp_cache_home_path', $home_path );
	}

	if ( $wp_cache_mobile_enabled == 1 ) {
		update_cached_mobile_ua_list( $wp_cache_mobile_browsers, $wp_cache_mobile_prefixes, $mobile_groups );
	}

	?>
	<style>
		.wpsc-boost-banner {
			margin: 2px 1.25rem 1.25rem 0;
			box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03), 0 1px 2px rgba(0, 0, 0, 0.03);
			border: 1px solid #d5d5d5;
			position: relative;
		}

		.wpsc-boost-banner-inner {
			display: flex;
			grid-template-columns: minmax(auto, 750px) 500px;
			justify-content: space-between;
			min-height: 300px;
			background: #fff;
			overflow: hidden;
		}

		.wpsc-boost-banner-content {
			display: inline-flex;
			flex-direction: column;
			padding: 2.5rem;
			text-align: left;
		}

		.wpsc-boost-banner-image-container {
			position: relative;
			background-image: url( <?php echo esc_url( plugin_dir_url( dirname( __DIR__ ) . '/wp-cache.php' ) . '/assets/jetpack-colors.svg' ); ?> );
			background-size: cover;
			min-width: 40%;
			max-width: 40%;
			overflow: hidden;
			text-align: right;
		}

		.wpsc-boost-banner-image-container img {
			position: relative;
			top: 50%;
			transform: translateY(-50%);
		}

		.wpsc-boost-banner h3 {
			font-size: 24px;
			line-height: 32px;
		}

		.wpsc-boost-banner p {
			font-size: 14px;
			line-height: 24px;
			margin: 0 0 1.9rem;
		}

		.wpsc-boost-banner .wpsc-boost-dismiss {
			position: absolute;
			top: 10px;
			right: 10px;
			color: black;
			cursor:pointer;
		}

		.wpsc-boost-banner .button-primary {
			background: black;
			border-color: black;
			color: #fff;
			width: fit-content;
			padding: 0.4rem 1rem;
			font-size: 16px;
			line-height: 23px;
		}

		.wpsc-boost-banner .button-primary:hover {
			background-color: #333;
		}

		.wpsc-boost-banner .button-primary:visited {
			background-color: black;
			border-color: black;
		}
	</style>

	<table class="wpsc-settings-table"><td valign="top">

	<?php
	wpsc_jetpack_boost_install_banner();

	switch ( $curr_tab ) {
		case 'cdn':
			scossdl_off_options();
			break;
		case 'tester':
		case 'contents':
			echo '<a name="test"></a>';
			wp_cache_files();
			break;
		case 'preload':
			wpsc_render_partial(
				'preload',
				compact(
					'cache_enabled',
					'super_cache_enabled',
					'admin_url',
					'wp_cache_preload_interval',
					'wp_cache_preload_on',
					'wp_cache_preload_taxonomies',
					'wp_cache_preload_email_me',
					'wp_cache_preload_email_volume',
					'currently_preloading',
					'wp_cache_preload_posts'
				)
			);

			break;
		case 'plugins':
			wpsc_plugins_tab();
			break;
		case 'debug':
			global $wp_super_cache_debug, $wp_cache_debug_log, $wp_cache_debug_ip, $wp_cache_debug_ip;
			global $wp_super_cache_front_page_text, $wp_super_cache_front_page_notification;
			global $wp_super_cache_advanced_debug, $wp_cache_debug_username, $wp_super_cache_front_page_clear;
			wpsc_render_partial(
				'debug',
				compact( 'wp_super_cache_debug', 'wp_cache_debug_log', 'wp_cache_debug_ip', 'cache_path', 'valid_nonce', 'wp_cache_config_file', 'wp_super_cache_comments', 'wp_super_cache_front_page_check', 'wp_super_cache_front_page_clear', 'wp_super_cache_front_page_text', 'wp_super_cache_front_page_notification', 'wp_super_cache_advanced_debug', 'wp_cache_debug_username', 'wp_cache_home_path' )
			);
			break;
		case 'settings':
			global $cache_acceptable_files, $wpsc_rejected_cookies, $cache_rejected_uri, $wp_cache_pages;
			global $cache_max_time, $wp_cache_config_file, $valid_nonce, $super_cache_enabled, $cache_schedule_type, $cache_scheduled_time, $cache_schedule_interval, $cache_time_interval, $cache_gc_email_me, $wp_cache_preload_on;

			wp_cache_update_rejected_pages();
			wp_cache_update_rejected_cookies();
			wp_cache_update_rejected_strings();
			wp_cache_update_accepted_strings();
			wp_cache_time_update();

			wpsc_render_partial(
				'advanced',
				compact(
					'wp_cache_front_page_checks',
					'admin_url',
					'cache_enabled',
					'super_cache_enabled',
					'wp_cache_mod_rewrite',
					'is_nginx',
					'wp_cache_not_logged_in',
					'wp_cache_no_cache_for_get',
					'cache_compression',
					'cache_rebuild_files',
					'wpsc_save_headers',
					'wp_supercache_304',
					'wp_cache_make_known_anon',
					'wp_cache_mfunc_enabled',
					'wp_cache_mobile_enabled',
					'wp_cache_mobile_browsers',
					'wp_cache_disable_utf8',
					'wp_cache_clear_on_post_edit',
					'wp_cache_front_page_checks',
					'wp_cache_refresh_single_only',
					'wp_supercache_cache_list',
					'wp_cache_mutex_disabled',
					'wp_super_cache_late_init',
					'cache_page_secret',
					'cache_path',
					'cache_acceptable_files',
					'wpsc_rejected_cookies',
					'cache_rejected_uri',
					'wp_cache_pages',
					'cache_max_time',
					'valid_nonce',
					'super_cache_enabled',
					'cache_schedule_type',
					'cache_scheduled_time',
					'cache_schedule_interval',
					'cache_time_interval',
					'cache_gc_email_me',
					'wp_cache_mobile_prefixes',
					'wp_cache_preload_on'
				)
			);

			wpsc_edit_tracking_parameters();
			wpsc_edit_rejected_ua();
			wpsc_lockdown();
			wpsc_restore_settings();

			break;
		case 'easy':
		default:
			wpsc_render_partial(
				'easy',
				array(
					'admin_url'     => $admin_url,
					'cache_enabled' => $cache_enabled,
					'is_nginx'      => $is_nginx,
					'wp_cache_mod_rewrite' => $wp_cache_mod_rewrite,
					'valid_nonce' => $valid_nonce,
					'cache_path'              => $cache_path,
					'wp_super_cache_comments' => $wp_super_cache_comments,
				)
			);
			break;
	}
	?>

	</fieldset>
	</td><td valign='top' style='width: 300px'>
	<!-- TODO: Hide #wpsc-callout from all pages except the Easy tab -->
	<div class="wpsc-card" id="wpsc-callout">
	<?php if ( ! empty( $wpsc_promo_links ) && is_array( $wpsc_promo_links ) ) : ?>
	<h4><?php esc_html_e( 'Other Site Tools', 'wp-super-cache' ); ?></h4>
	<ul style="list-style: square; margin-left: 2em;">
		<li><a href="<?php echo esc_url( $wpsc_promo_links['boost'] ); ?>"><?php esc_html_e( 'Boost your page speed scores', 'wp-super-cache' ); ?></a></li>
		<li><a href="<?php echo esc_url( $wpsc_promo_links['photon'] ); ?>"><?php esc_html_e( 'Speed up images and photos (free)', 'wp-super-cache' ); ?></a></li>
		<li><a href="<?php echo esc_url( $wpsc_promo_links['videopress'] ); ?>"><?php esc_html_e( 'Fast video hosting (paid)', 'wp-super-cache' ); ?></a></li>
		<li><a href="<?php echo esc_url( $wpsc_promo_links['crowdsignal'] ); ?>"><?php esc_html_e( 'Add Surveys and Polls to your site', 'wp-super-cache' ); ?></a></li>
	</ul>
	<?php endif; ?>
	<h4><?php _e( 'Need Help?', 'wp-super-cache' ); ?></h4>
	<ol>
	<li><?php printf( __( 'Use the <a href="%1$s">Debug tab</a> for diagnostics.', 'wp-super-cache' ), admin_url( 'options-general.php?page=wpsupercache&tab=debug' ) ); ?></li>
	<li>
		<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %s is the URL for the documentation. */
					__( 'Check out the <a href="%s">plugin documentation</a>.', 'wp-super-cache' ),
					'https://jetpack.com/support/wp-super-cache/'
				)
			);
		?>
	</li>
	<li>
		<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %1$s is the URL for the support forum. */
					__( 'Visit the <a href="%1$s">support forum</a>.', 'wp-super-cache' ),
					'https://wordpress.org/support/plugin/wp-super-cache/'
				)
			);
		?>
	</li>
	<li><?php printf( __( 'Try out the <a href="%1$s">development version</a> for the latest fixes (<a href="%2$s">changelog</a>).', 'wp-super-cache' ), 'https://odd.blog/y/2o', 'https://plugins.trac.wordpress.org/log/wp-super-cache/' ); ?></li>
	</ol>
	<h4><?php esc_html_e( 'Rate This Plugin', 'wp-super-cache' ); ?></h4>
	<p><?php printf( __( 'Please <a href="%s">rate us</a> and give feedback.', 'wp-super-cache' ), 'https://wordpress.org/support/plugin/wp-super-cache/reviews?rate=5#new-post' ); ?></p>

	<?php
	if ( isset( $wp_supercache_cache_list ) && $wp_supercache_cache_list ) {
		$start_date = get_option( 'wpsupercache_start' );
		if ( ! $start_date ) {
			$start_date = time();
		}
		?>
		<p><?php printf( __( 'Cached pages since %1$s : <strong>%2$s</strong>', 'wp-super-cache' ), date( 'M j, Y', $start_date ), number_format( get_option( 'wpsupercache_count' ) ) ); ?></p>
		<?php
		$supercache_last_cached_option = get_option( 'supercache_last_cached' );
		if ( is_array( $supercache_last_cached_option ) ) {
			?>
		<p><?php _e( 'Newest Cached Pages:', 'wp-super-cache' ); ?><ol>
			<?php
			foreach ( array_reverse( $supercache_last_cached_option ) as $url ) {
				if ( ! is_array( $url ) ) {
					continue;
				}
				$since = time() - strtotime( $url['date'] ?? '' );
				// translators: %s is the number of seconds since the page was cached.
				echo "<li><a title='" . sprintf( esc_html__( 'Cached %s seconds ago', 'wp-super-cache' ), (int) $since ) . "' href='" . esc_url( site_url( $url['url'] ?? '' ) ) . "'>" . esc_html( substr( $url['url'] ?? '', 0, 20 ) ) . "</a></li>\n";
			}
			?>
			</ol>
			<small><?php esc_html_e( '(may not always be accurate on busy sites)', 'wp-super-cache' ); ?></small>
		</p>
			<?php
		}
	} elseif ( false == get_option( 'wpsupercache_start' ) ) {
			update_option( 'wpsupercache_start', time() );
			update_option( 'wpsupercache_count', 0 );
	}
	?>
	</div>
	</td></table>
	</div>
	</div>
	<?php wpsc_render_footer(); ?>
	</div>
	<?php
}

function wpsc_plugins_tab() {
	echo '<div class="wpsc-card">';
	echo '<p>' . esc_html__( 'Cache plugins are PHP scripts you\'ll find in a dedicated folder inside the WP Super Cache folder (wp-super-cache/plugins/). They load at the same time as WP Super Cache, and before regular WordPress plugins.', 'wp-super-cache' ) . '</p>';
	echo '<p>' . esc_html__( 'Keep in mind that cache plugins are for advanced users only. To create and manage them, you\'ll need extensive knowledge of both PHP and WordPress actions.', 'wp-super-cache' ) . '</p>';
	echo '<p>' . sprintf( __( '<strong>Warning</strong>! Due to the way WordPress upgrades plugins, the ones you upload to the WP Super Cache folder (wp-super-cache/plugins/) will be deleted when you upgrade WP Super Cache. To avoid this loss, load your cache plugins from a different location. When you set <strong>$wp_cache_plugins_dir</strong> to the new location in wp-config.php, WP Super Cache will look there instead. <br />You can find additional details in the <a href="%s">developer documentation</a>.', 'wp-super-cache' ), 'https://odd.blog/wp-super-cache-developers/' ) . '</p>';
	echo '</div>';
	echo '<div class="wpsc-card">';
	ob_start();
	if ( defined( 'WP_CACHE' ) ) {
		if ( function_exists( 'do_cacheaction' ) ) {
			do_cacheaction( 'cache_admin_page' );
		}
	}
	$out = ob_get_contents();
	ob_end_clean();

	if ( SUBMITDISABLED == ' ' && $out != '' ) {
		echo '<h4>' . esc_html__( 'Available Plugins', 'wp-super-cache' ) . '</h4>';
		echo '<ol>';
		echo $out;
		echo '</ol>';
	}
	echo '</div>';
}

function wpsc_admin_tabs( $current = '' ) {
	global $cache_enabled, $super_cache_enabled, $wp_cache_mod_rewrite;

	if ( '' === $current ) {
		$current = ! empty( $_GET['tab'] ) ? stripslashes( $_GET['tab'] ) : ''; // WPCS: CSRF ok, sanitization ok.
	}

	$admin_url  = admin_url( 'options-general.php?page=wpsupercache' );
	$admin_tabs = array(
		'easy'     => __( 'Easy', 'wp-super-cache' ),
		'settings' => __( 'Advanced', 'wp-super-cache' ),
		'cdn'      => __( 'CDN', 'wp-super-cache' ),
		'contents' => __( 'Contents', 'wp-super-cache' ),
		'preload'  => __( 'Preload', 'wp-super-cache' ),
		'plugins'  => __( 'Plugins', 'wp-super-cache' ),
		'debug'    => __( 'Debug', 'wp-super-cache' ),
	);

	echo '<div class="wpsc-nav-container"><ul class="wpsc-nav">';

	foreach ( $admin_tabs as $tab => $name ) {
		printf(
			'<li class="%s"><a href="%s">%s</a></li>',
			esc_attr( $tab === $current ? 'wpsc-nav-tab wpsc-nav-tab-selected' : 'wpsc-nav-tab' ),
			esc_url_raw( add_query_arg( 'tab', $tab, $admin_url ) ),
			esc_html( $name )
		);
	}

	echo '</ul></div>';
}

function supercache_admin_bar_render() {
	global $wp_admin_bar;

	if ( function_exists( '_deprecated_function' ) ) {
		_deprecated_function( __FUNCTION__, 'WP Super Cache 1.6.4' );
	}

	wpsc_admin_bar_render( $wp_admin_bar );
}

/**
 * Renders a partial/template.
 *
 * The global $current_user is made available for any rendered template.
 *
 * @param string $partial  - Filename under ./partials directory, with or without .php (appended if absent).
 * @param array  $page_vars - Variables made available for the template.
 */
function wpsc_render_partial( $partial, array $page_vars = array() ) {
	if ( ! str_ends_with( $partial, '.php' ) ) {
		$partial .= '.php';
	}

	if ( strpos( $partial, 'partials/' ) !== 0 ) {
		$partial = 'partials/' . $partial;
	}

	$path = dirname( __DIR__ ) . '/' . $partial;
	if ( ! file_exists( $path ) ) {
		return;
	}

	foreach ( $page_vars as $key => $val ) {
		$$key = $val;
	}
	global $current_user;
	include $path;
}

/**
 * Render common header
 */
function wpsc_render_header() {
	?>
		<div class="header">
			<img class="wpsc-icon" src="<?php echo esc_url( plugin_dir_url( dirname( __DIR__ ) . '/wp-cache.php' ) . '/assets/super-cache-icon.png' ); ?>" />
			<span class="wpsc-name"><?php echo esc_html( 'WP Super Cache' ); ?></span>
		</div>
	<?php
}

/**
 * Render common footer
 */
function wpsc_render_footer() {
	?>
	<div class="footer">
		<div class="wp-super-cache-version">
			<img class="wpsc-icon" src="<?php echo esc_url( plugin_dir_url( dirname( __DIR__ ) . '/wp-cache.php' ) . '/assets/super-cache-icon.png' ); ?>" />
			<span class="wpsc-name"><?php echo esc_html( 'WP Super Cache' ); ?></span>
		</div>
		<div class="automattic-airline">
			<img class="wpsc-icon" src="<?php echo esc_url( plugin_dir_url( dirname( __DIR__ ) . '/wp-cache.php' ) . '/assets/automattic-airline.svg' ); ?>" />
		</div>
	</div>
	<?php
}
