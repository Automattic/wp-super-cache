<?php
/**
 * .htaccess and mod_rewrite rule management for Super Cache expert mode.
 *
 * Relocated from wp-cache.php (issue #1061) as a pure move: identical function
 * signatures and hook registrations, no behaviour change.
 *
 * @package WP_Super_Cache
 */

function wpsc_remove_marker( $filename, $marker ) {
	if (!file_exists( $filename ) || is_writeable_ACLSafe( $filename ) ) {
		if (!file_exists( $filename ) ) {
			return '';
		} else {
			$markerdata = explode( "\n", implode( '', file( $filename ) ) );
		}

		$f = fopen( $filename, 'w' );
		$state = true;
		foreach ( $markerdata as $n => $markerline ) {
			if ( strpos( $markerline, '# BEGIN ' . $marker ) !== false ) {
				$state = false;
			}
			if ( $state ) {
				if ( $n + 1 < count( $markerdata ) ) {
					fwrite( $f, "{$markerline}\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
				} else {
					fwrite( $f, "{$markerline}" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
				}
			}
			if ( strpos( $markerline, '# END ' . $marker ) !== false ) {
				$state = true;
			}
		}
		return true;
	} else {
		return false;
	}
}

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
	extract( wpsc_get_htaccess_info() ); // $document_root, $apache_root, $home_path, $home_root, $home_root_lc, $inst_root, $wprules, $scrules, $condition_rules, $rules, $gziprules
	// @phan-suppress-next-line PhanTypeSuspiciousStringExpression -- $home_path is set via extract()
	wpsc_remove_marker( $home_path.'.htaccess', 'WordPress' ); // remove original WP rules so SuperCache rules go on top
	// @phan-suppress-next-line PhanTypeSuspiciousStringExpression -- $home_path is set via extract()
	if( insert_with_markers( $home_path.'.htaccess', 'WPSuperCache', explode( "\n", $rules ) ) && insert_with_markers( $home_path.'.htaccess', 'WordPress', explode( "\n", $wprules ) ) ) {
		return true;
	} else {
		return false;
	}
}

function wpsc_update_htaccess_form( $short_form = true ) {
	global $wpmu_version;

	$admin_url = admin_url( 'options-general.php?page=wpsupercache' );
	extract( wpsc_get_htaccess_info() ); // $document_root, $apache_root, $home_path, $home_root, $home_root_lc, $inst_root, $wprules, $scrules, $condition_rules, $rules, $gziprules
	// @phan-suppress-next-line PhanTypeSuspiciousStringExpression -- $home_path is set via extract()
	if( !is_writeable_ACLSafe( $home_path . ".htaccess" ) ) {
		echo "<div style='padding:0 8px;color:#9f6000;background-color:#feefb3;border:1px solid #9f6000;'><h5>" . __( 'Cannot update .htaccess', 'wp-super-cache' ) . "</h5><p>" . sprintf( __( 'The file <code>%s.htaccess</code> cannot be modified by the web server. Please correct this using the chmod command or your ftp client.', 'wp-super-cache' ), $home_path ) . "</p><p>" . __( 'Refresh this page when the file permissions have been modified.' ) . "</p><p>" . sprintf( __( 'Alternatively, you can edit your <code>%s.htaccess</code> file manually and add the following code (before any WordPress rules):', 'wp-super-cache' ), $home_path ) . "</p>";
		echo "<p><pre># BEGIN WPSuperCache\n" . esc_html( $rules ) . "# END WPSuperCache</pre></p></div>";
	} else {
		if ( $short_form == false ) {
			echo "<p>" . sprintf( __( 'To serve static html files your server must have the correct mod_rewrite rules added to a file called <code>%s.htaccess</code>', 'wp-super-cache' ), $home_path ) . " ";
			_e( "You can edit the file yourself. Add the following rules.", 'wp-super-cache' );
			echo __( " Make sure they appear before any existing WordPress rules. ", 'wp-super-cache' ) . "</p>";
			echo "<div style='overflow: auto; width: 800px; height: 400px; padding:0 8px;color:#9f6000;background-color:#feefb3;border:1px solid #9f6000;'>";
			echo "<pre># BEGIN WPSuperCache\n" . esc_html( $rules ) . "# END WPSuperCache</pre></p>";
			echo "</div>";
			echo "<h5>" . sprintf( __( 'Rules must be added to %s too:', 'wp-super-cache' ), WP_CONTENT_DIR . "/cache/.htaccess" ) . "</h5>";
			echo "<div style='overflow: auto; width: 800px; height: 400px; padding:0 8px;color:#9f6000;background-color:#feefb3;border:1px solid #9f6000;'>";
			echo "<pre># BEGIN supercache\n" . esc_html( $gziprules ) . "# END supercache</pre></p>";
			echo "</div>";
		}
		if ( !isset( $wpmu_version ) || $wpmu_version == '' ) {
			echo '<form name="updatehtaccess" action="' . esc_url_raw( add_query_arg( 'tab', 'settings', $admin_url ) . '#modrewrite' ) . '" method="post">';
			echo '<input type="hidden" name="updatehtaccess" value="1" />';
			echo '<div class="submit"><input class="button-primary" type="submit" ' . SUBMITDISABLED . 'id="updatehtaccess" value="' . __( 'Update Mod_Rewrite Rules', 'wp-super-cache' ) . '" /></div>';
			wp_nonce_field('wp-cache');
			echo "</form>\n";
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
	global $htaccess_path;

	if ( isset( $_SERVER[ "PHP_DOCUMENT_ROOT" ] ) ) {
		$document_root = $_SERVER[ "PHP_DOCUMENT_ROOT" ];
		$apache_root = $_SERVER[ "PHP_DOCUMENT_ROOT" ];
	} else {
		$document_root = $_SERVER[ "DOCUMENT_ROOT" ];
		$apache_root = '%{DOCUMENT_ROOT}';
	}
	$content_dir_root = $document_root;
	if ( strpos( $document_root, '/kunden/homepages/' ) === 0 ) {
		// https://wordpress.org/support/topic/plugin-wp-super-cache-how-to-get-mod_rewrite-working-on-1and1-shared-hosting?replies=1
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
	if ( isset( $htaccess_path ) ) {
		$home_path = $htaccess_path;
	} elseif (
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

	$home_path = trailingslashit( $home_path );
	// The rewrite rules point at supercache directories, so the home root has to
	// be spelled the way those directories are. See #1081.
	$home_root_lc = str_replace( '//', '/', wpsc_normalize_uri_case( $home_root ) );
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
	$condition_rules[] = "RewriteCond %{QUERY_STRING} ^$";
	$condition_rules[] = "RewriteCond %{HTTP:Cookie} !^.*(comment_author_|" . wpsc_get_logged_in_cookie() . wpsc_get_extra_cookies() . "|wp-postpass_).*$";
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

	// Default headers.
	$headers = array(
		'Vary'          => 'Accept-Encoding, Cookie',
		'Cache-Control' => 'max-age=3, must-revalidate',
	);

	// Allow users to override the Vary header with WPSC_VARY_HEADER.
	if ( defined( 'WPSC_VARY_HEADER' ) && ! empty( WPSC_VARY_HEADER ) ) {
		$headers['Vary'] = WPSC_VARY_HEADER;
	}

	// Allow users to override Cache-control header with WPSC_CACHE_CONTROL_HEADER
	if ( defined( 'WPSC_CACHE_CONTROL_HEADER' ) && ! empty( WPSC_CACHE_CONTROL_HEADER ) ) {
		$headers['Cache-Control'] = WPSC_CACHE_CONTROL_HEADER;
	}

	// Allow overriding headers with a filter.
	$headers = apply_filters( 'wpsc_htaccess_mod_headers', $headers );

	// Combine headers into a block of text.
	$headers_text = implode(
		"\n",
		array_map(
			function ( $key, $value ) {
				return "  Header set $key '" . addcslashes( $value, "'" ) . "'";
			},
			array_keys( $headers ),
			array_values( $headers )
		)
	);

	// Pack headers into gziprules (for historic reasons) - TODO refactor the values
	// returned to better reflect the blocks being written.
	if ( $headers_text != '' ) {
		$gziprules .= "<IfModule mod_headers.c>\n$headers_text\n</IfModule>\n";
	}

	// Deafult mod_expires rules.
	$expires_rules = array(
		'ExpiresActive On',
		'ExpiresByType text/html A3',
	);

	// Allow overriding mod_expires rules with a filter.
	$expires_rules = apply_filters( 'wpsc_htaccess_mod_expires', $expires_rules );

	$gziprules .= "<IfModule mod_expires.c>\n";
	$gziprules .= implode(
		"\n",
		array_map(
			function ( $line ) {
				return "  $line";
			},
			$expires_rules
		)
	);
	$gziprules .= "\n</IfModule>\n";

	$gziprules .= "Options -Indexes\n";

	return array(
		'document_root'   => $document_root,
		'apache_root'     => $apache_root,
		'home_path'       => $home_path,
		'home_root'       => $home_root,
		'home_root_lc'    => $home_root_lc,
		'inst_root'       => $inst_root,
		'wprules'         => $wprules,
		'scrules'         => $scrules,
		'condition_rules' => $condition_rules,
		'rules'           => $rules,
		'gziprules'       => $gziprules,
	);
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
	$home_path = trailingslashit( $home_path );

	if ( ! file_exists( $home_path . ".htaccess" ) ) {
		$update_mod_rewrite_rules_error = ".htaccess not found: {$home_path}.htaccess";
		return false;
	}

	$generated_rules = wpsc_get_htaccess_info();
	$existing_rules  = implode( "\n", extract_from_markers( $home_path . '.htaccess', 'WPSuperCache' ) );

	$rules = $add_rules ? $generated_rules[ 'rules' ] : '';

	if ( $existing_rules == $rules ) {
		$update_mod_rewrite_rules_error = "rules have not changed";
		return true;
	}

	if ( $generated_rules[ 'wprules' ] == '' ) {
		$update_mod_rewrite_rules_error = "WordPress rules empty";
		return false;
	}

	if ( empty( $rules ) ) {
		return insert_with_markers( $home_path . '.htaccess', 'WPSuperCache', array() );
	}

	$url = trailingslashit( get_bloginfo( 'url' ) );
	$original_page = wp_remote_get( $url, array( 'timeout' => 60, 'blocking' => true ) );
	if ( is_wp_error( $original_page ) ) {
		$update_mod_rewrite_rules_error = "Problem loading page";
		return false;
	}

	$backup_filename      = $cache_path . 'htaccess.' . wp_rand() . '.php';
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
		$restore_backup = false;
		if ( is_wp_error( $new_page ) ) {
			$restore_backup = true;
			$update_mod_rewrite_rules_error = "Error testing page with new .htaccess rules: " . $new_page->get_error_message() . ".";
			wp_cache_debug( 'update_mod_rewrite_rules: failed to update rules. error fetching second page: ' . $new_page->get_error_message() );
		} elseif ( $new_page[ 'body' ] != $original_page[ 'body' ] ) {
			$restore_backup = true;
			$update_mod_rewrite_rules_error = "Page test failed as pages did not match with new .htaccess rules.";
			wp_cache_debug( 'update_mod_rewrite_rules: failed to update rules. page test failed as pages did not match. Files dumped in ' . $cache_path . ' for inspection.' );
			wp_cache_debug( 'update_mod_rewrite_rules: original page: 1-' . md5( $original_page[ 'body' ] ) . '.txt' );
			wp_cache_debug( 'update_mod_rewrite_rules: new page: 1-' . md5( $new_page[ 'body' ] ) . '.txt' );
			file_put_contents( $cache_path . '1-' . md5( $original_page[ 'body' ] ) . '.txt', $original_page[ 'body' ] );
			file_put_contents( $cache_path . '2-' . md5( $new_page[ 'body' ] ) . '.txt', $new_page[ 'body' ] );
		}

		if ( $restore_backup ) {
			global $wp_cache_debug;
			file_put_contents( $home_path . '.htaccess', $backup_file_contents );
			unlink( $backup_filename );
			if ( $wp_cache_debug ) {
				$update_mod_rewrite_rules_error .= "<br />See debug log for further details";
			} else {
				$update_mod_rewrite_rules_error .= "<br />Enable debug log on Debugging page for further details and try again";
			}

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
