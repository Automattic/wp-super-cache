<?php
/**
 * Admin-side cache-file management: stats generation, directory sizing, the
 * cache contents listing, and cache cleaning.
 *
 * Relocated from wp-cache.php (issue #1061) as a pure move: identical function
 * signatures and hook registrations, no behaviour change.
 *
 * @package WP_Super_Cache
 */

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
	global $cache_compression, $supercachedir, $file_prefix, $wp_cache_preload_on, $cache_max_time;

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
	if ( $cache_compression ) {
		$sizes[ 'supercache' ][ 'cached' ]  = intval( $sizes[ 'supercache' ][ 'cached' ] / 2 );
		$sizes[ 'supercache' ][ 'expired' ] = intval( $sizes[ 'supercache' ][ 'expired' ] / 2 );
	}
	$cache_stats = array( 'generated' => time(), 'supercache' => $sizes[ 'supercache' ], 'wpcache' => $sizes[ 'wpcache' ] );
	update_option( 'supercache_stats', $cache_stats );
	return $cache_stats;
}

function wp_cache_files() {
	global $cache_path, $file_prefix, $cache_max_time, $valid_nonce, $supercachedir, $super_cache_enabled, $blog_cache_dir, $cache_compression;
	global $wp_cache_preload_on;

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
	echo '<div class="wpsc-card">';
	echo '<fieldset class="options" id="show-this-fieldset"><h4>' . __( 'Cache Contents', 'wp-super-cache' ) . '</h4>';

	$cache_stats = get_option( 'supercache_stats' );
	if ( !is_array( $cache_stats ) || ( isset( $_GET[ 'listfiles' ] ) ) || ( $valid_nonce && array_key_exists('action', $_GET) && $_GET[ 'action' ] == 'regenerate_cache_stats' ) ) {
	$count = 0;
	$expired = 0;
	$now = time();
	$wp_cache_fsize = 0;
	if ( ( $handle = @opendir( $blog_cache_dir ) ) ) {
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
			if ( // phpcs:ignore Generic.WhiteSpace.ScopeIndent.IncorrectExact
				str_contains( $file, $file_prefix )
				&& substr( $file, -4 ) == '.php' // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
			) { // phpcs:ignore Generic.WhiteSpace.ScopeIndent.Incorrect
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
						++$expired;
				} else {
						++$count;
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
		if ( ! empty( $cache_stats['generated'] ) ) {
			echo "<p>" . sprintf( __( 'Cache stats last generated: %s minutes ago.', 'wp-super-cache' ), number_format( ( time() - $cache_stats[ 'generated' ] ) / 60 ) ) . "</p>";
		}
		$cache_stats = get_option( 'supercache_stats' );
	}// regerate stats cache

	if ( is_array( $cache_stats ) ) {
		$fsize = wp_cache_format_fsize( $cache_stats[ 'wpcache' ][ 'fsize' ] / 1024 );
		echo "<p><strong>" . __( 'WP-Cache', 'wp-super-cache' ) . " ({$fsize})</strong></p>";
		echo "<ul><li>" . sprintf( __( '%s Cached Pages', 'wp-super-cache' ), $cache_stats[ 'wpcache' ][ 'cached' ] ) . "</li>";
		echo "<li>" . sprintf( __( '%s Expired Pages', 'wp-super-cache' ),    $cache_stats[ 'wpcache' ][ 'expired' ] ) . "</li></ul>";
		if ( array_key_exists('fsize', (array)$cache_stats[ 'supercache' ]) )
			$fsize = $cache_stats[ 'supercache' ][ 'fsize' ] / 1024;
		else
			$fsize = 0;
		$fsize = wp_cache_format_fsize( $fsize );
		echo "<p><strong>" . __( 'WP-Super-Cache', 'wp-super-cache' ) . " ({$fsize})</strong></p>";
		echo "<ul><li>" . sprintf( __( '%s Cached Pages', 'wp-super-cache' ), $cache_stats[ 'supercache' ][ 'cached' ] ) . "</li>";
		if ( isset( $now ) && ! empty( $cache_stats['generated'] ) ) {
			$age = intval( ( $now - $cache_stats['generated'] ) / 60 );
		} else {
			$age = 0;
		}
		echo "<li>" . sprintf( __( '%s Expired Pages', 'wp-super-cache' ), $cache_stats[ 'supercache' ][ 'expired' ] ) . "</li></ul>";
		if ( $valid_nonce && array_key_exists('listfiles', $_GET) && isset( $_GET[ 'listfiles' ] ) ) {
			echo "<div style='padding: 10px; border: 1px solid #333; height: 400px; width: 90%; overflow: auto'>";
			$cache_description = array( 'supercache' => __( 'WP-Super-Cached', 'wp-super-cache' ), 'wpcache' => __( 'WP-Cached', 'wp-super-cache' ) );
			foreach( $cache_stats as $type => $details ) {
				if ( is_array( $details ) == false )
					continue;
				foreach( array( 'cached_list' => 'Fresh', 'expired_list' => 'Stale' ) as $list => $description ) {
					if ( is_array( $details[ $list ] ) & !empty( $details[ $list ] ) ) {
						echo "<h5>" . sprintf( __( '%s %s Files', 'wp-super-cache' ), $description, $cache_description[ $type ] ) . "</h5>";
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
							++$c;
						}
						echo "</table>";
					}
				}
			}
			echo "</div>";
			echo "<p><a href='?page=wpsupercache&tab=contents#top'>" . __( 'Hide file list', 'wp-super-cache' ) . "</a></p>";
		} elseif ( $cache_stats[ 'supercache' ][ 'cached' ] > 500 || $cache_stats[ 'supercache' ][ 'expired' ] > 500 || $cache_stats[ 'wpcache' ][ 'cached' ] > 500 || $cache_stats[ 'wpcache' ][ 'expired' ] > 500 ) {
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
	echo '</div>';
}

function wp_cache_delete_buttons() {

	$admin_url = admin_url( 'options-general.php?page=wpsupercache' );

	echo '<form name="wp_cache_content_expired" action="' . esc_url_raw( add_query_arg( 'tab', 'contents', $admin_url ) . '#listfiles' ) . '" method="post">';
	echo '<input type="hidden" name="wp_delete_expired" />';
	echo '<div class="submit" style="float:left"><input class="button-primary" type="submit" ' . SUBMITDISABLED . 'value="' . __( 'Delete Expired', 'wp-super-cache' ) . '" /></div>';
	wp_nonce_field('wp-cache');
	echo "</form>\n";

	echo '<form name="wp_cache_content_delete" action="' . esc_url_raw( add_query_arg( 'tab', 'contents', $admin_url ) . '#listfiles' ) . '" method="post">';
	echo '<input type="hidden" name="wp_delete_cache" />';
	echo '<div class="submit" style="float:left;margin-left:10px"><input id="deletepost" class="button-secondary" type="submit" ' . SUBMITDISABLED . 'value="' . __( 'Delete Cache', 'wp-super-cache' ) . '" /></div>';
	wp_nonce_field('wp-cache');
	echo "</form>\n";
	if ( is_multisite() && wpsupercache_site_admin() ) {
		echo '<form name="wp_cache_content_delete" action="' . esc_url_raw( add_query_arg( 'tab', 'contents', $admin_url ) . '#listfiles' ) . '" method="post">';
		echo '<input type="hidden" name="wp_delete_all_cache" />';
		echo '<div class="submit" style="float:left;margin-left:10px"><input id="deleteallpost" class="button-secondary" type="submit" ' . SUBMITDISABLED . 'value="' . __( 'Delete Cache On All Blogs', 'wp-super-cache' ) . '" /></div>';
		wp_nonce_field('wp-cache');
		echo "</form>\n";
	}
}

function delete_cache_dashboard() {
	if ( function_exists( '_deprecated_function' ) ) {
		_deprecated_function( __FUNCTION__, 'WP Super Cache 1.6.4' );
	}

	if ( false == wpsupercache_site_admin() )
		return false;

	if ( function_exists('current_user_can') && !current_user_can('manage_options') )
		return false;

	echo "<li><a href='" . wp_nonce_url( 'options-general.php?page=wpsupercache&wp_delete_cache=1', 'wp-cache' ) . "' target='_blank' title='" . __( 'Delete Super Cache cached files (opens in new window)', 'wp-super-cache' ) . "'>" . __( 'Delete Cache', 'wp-super-cache' ) . "</a></li>";
}
//add_action( 'dashmenu', 'delete_cache_dashboard' );

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
	} elseif ( is_file( $directory ) && strpos( $directory, 'meta-' . $file_prefix ) === false ) {
		if ( strpos( $directory, '/' . $file_prefix ) !== false ) {
			$cache_type = 'wpcache';
		} else {
			$cache_type = 'supercache';
		}
		$keep_fresh = false;
		if ( $cache_type === 'supercache' && $wp_cache_preload_on ) {
			$keep_fresh = true;
		}
		$filem = filemtime( $directory );
		if ( ! $keep_fresh && $cache_max_time > 0 && $filem + $cache_max_time <= $now ) {
			$cache_status = 'expired';
		} else {
			$cache_status = 'cached';
		}
		$sizes[ $cache_type ][ $cache_status ] += 1;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presumably the caller should handle it if necessary.
		if ( $valid_nonce && isset( $_GET['listfiles'] ) ) {
			$dir = str_replace( $cache_path . 'supercache/', '', dirname( $directory ) );
			$age = $now - $filem;
			if ( ! isset( $sizes[ $cache_type ][ $cache_status . '_list' ][ $dir ] ) ) {
				$sizes[ $cache_type ][ $cache_status . '_list' ][ $dir ]['lower_age'] = $age;
				$sizes[ $cache_type ][ $cache_status . '_list' ][ $dir ]['files']     = 1;
			} else {
				$sizes[ $cache_type ][ $cache_status . '_list' ][ $dir ]['files'] += 1;
				if ( $age <= $sizes[ $cache_type ][ $cache_status . '_list' ][ $dir ]['lower_age'] ) {

					if ( $age < $sizes[ $cache_type ][ $cache_status . '_list' ][ $dir ]['lower_age'] && ! isset( $sizes[ $cache_type ][ $cache_status . '_list' ][ $dir ]['upper_age'] ) ) {
						$sizes[ $cache_type ][ $cache_status . '_list' ][ $dir ]['upper_age'] = $sizes[ $cache_type ][ $cache_status . '_list' ][ $dir ]['lower_age'];
					}

					$sizes[ $cache_type ][ $cache_status . '_list' ][ $dir ]['lower_age'] = $age;

				} elseif ( ! isset( $sizes[ $cache_type ][ $cache_status . '_list' ][ $dir ]['upper_age'] ) || $age > $sizes[ $cache_type ][ $cache_status . '_list' ][ $dir ]['upper_age'] ) {

					$sizes[ $cache_type ][ $cache_status . '_list' ][ $dir ]['upper_age'] = $age;

				}
			}
		}
		if ( ! isset( $sizes['fsize'] ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$sizes[ $cache_type ]['fsize'] = @filesize( $directory );
		} else {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$sizes[ $cache_type ]['fsize'] += @filesize( $directory );
		}
	}
	return $sizes;
}

function wp_cache_clean_cache( $file_prefix, $all = false ) {
	global $cache_path, $supercachedir, $blog_cache_dir;

	do_action( 'wp_cache_cleared' );

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

function wpsc_delete_post_cache( $id ) {
	$post = get_post( $id );
	wpsc_delete_url_cache( get_author_posts_url( (int) $post->post_author ) );
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
		$curr_blog_id = is_multisite() ? get_current_blog_id() : false;

		while ( false !== ( $file = readdir( $handle ) ) ) {
			if ( is_file( $dir . $file ) == false || $file == 'index.html' ) {
				continue;
			}

			if ( str_contains( $file, $file_prefix ) ) {
				if ( strpos( $file, '.html' ) ) {
					// delete old WPCache files immediately
					@unlink( $dir . $file);
					@unlink( $dir . 'meta/' . str_replace( '.html', '.meta', $file ) );
				} else {
					$meta = json_decode( wp_cache_get_legacy_cache( $dir . 'meta/' . $file ), true );
					if ( $curr_blog_id && $curr_blog_id !== (int) $meta['blog_id'] ) {
						continue;
					}
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
			if ( str_contains( $file, $file_prefix ) ) {
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

// Catch 404 requests. Themes that use query_posts() destroy $wp_query->is_404
function wp_cache_catch_404() {
	global $wp_cache_404;
	if ( function_exists( '_deprecated_function' ) )
		_deprecated_function( __FUNCTION__, 'WP Super Cache 1.5.6' );
	$wp_cache_404 = false;
	if( is_404() )
		$wp_cache_404 = true;
}
//More info - https://github.com/Automattic/wp-super-cache/pull/373
//add_action( 'template_redirect', 'wp_cache_catch_404' );

/*
 * clear_post_supercache() relocated here from wp-cache.php (#1061): post-level
 * supercache invalidation belongs with cache-file management.
 */
function clear_post_supercache( $post_id ) {
	$dir = get_current_url_supercache_dir( $post_id );
	if ( false == @is_dir( $dir ) )
		return false;

	if ( get_supercache_dir() == $dir ) {
		wp_cache_debug( "clear_post_supercache: not deleting post_id $post_id as it points at homepage: $dir" );
		return false;
	}

	wp_cache_debug( "clear_post_supercache: post_id: $post_id. deleting files in $dir" );
	if ( get_post_type( $post_id ) != 'page') { // don't delete child pages if they exist
		prune_super_cache( $dir, true );
	} else {
		wpsc_delete_files( $dir );
	}
}
