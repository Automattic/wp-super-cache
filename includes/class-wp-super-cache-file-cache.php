<?php
/**
 * File containing the class Wp_Super_Cache_File_Cache
 *
 * @package wp-super-cache
 *
 * @since   2.0.0
 */

/**
 * The engine of the plugin.
 *
 * @since 2.0.0
 */
class Wp_Super_Cache_File_Cache {

	/**
	 * Configuration variables
	 *
	 * @since 1.0.1
	 * @var   array
	 */
	public $config;

	/**
	 * Initialize the cache.
	 *
	 * @since    2.0.0
	 */
	public function __construct() {
		$this->config     = Wp_Super_Cache_Config::instance();
	}

	/**
	 * Get cache directory
	 *
	 * @since  2.0
	 * @return string
	 */
	public function get_cache_dir() {
		if ( isset( $this->config->config['cache_path'] ) ) {
			return $this->config->config['cache_path'];
		} else {
			return ( defined( 'WPSC_CACHE_DIR' ) ) ? rtrim( WPSC_CACHE_DIR, '/' ) : rtrim( WP_CONTENT_DIR, '/' ) . '/cache';
		}
	}

	/**
	 * Cache the query vars that may get destroyed by PHP garbage collection
	 *
	 * @since  2.0
	 * @return string
	 */
	public function get_query_vars() {
		global $wp_query;

		if ( ! empty( $this->config->query_vars ) ) {
			return $this->config->query_vars;
		}

		if ( ! is_object( $wp_query ) || ! method_exists( $wp_query, 'get' ) ) {
			return false;
		}

		if ( is_search() ) {
			$this->config->query_vars['is_search'] = 1;
		}
		if ( is_page() ) {
			$this->config->query_vars['is_page'] = 1;
		}
		if ( is_archive() ) {
			$this->config->query_vars['is_archive'] = 1;
		}
		if ( is_tag() ) {
			$this->config->query_vars['is_tag'] = 1;
		}
		if ( is_single() ) {
			$this->config->query_vars['is_single'] = 1;
		}
		if ( is_category() ) {
			$this->config->query_vars['is_category'] = 1;
		}
		if ( is_front_page() ) {
			$this->config->query_vars['is_front_page'] = 1;
		}
		if ( is_home() ) {
			$this->config->query_vars['is_home'] = 1;
		}
		if ( is_author() ) {
			$this->config->query_vars['is_author'] = 1;
		}

		// REST API.
		if (
			( defined( 'REST_REQUEST' ) && REST_REQUEST ) ||
			( defined( 'JSON_REQUEST' ) && JSON_REQUEST ) ||
			( defined( 'WC_API_REQUEST' ) && WC_API_REQUEST )
		) {
			$this->config->query_vars['is_rest'] = 1;
		}

		// Feeds, sitemaps and robots.txt.
		if ( is_feed() ) {
			$this->config->query_vars['is_feed'] = 1;
			if ( 'sitemap' === get_query_var( 'feed' ) ) {
				$this->config->query_vars['is_sitemap'] = 1;
			}
		} elseif ( get_query_var( 'sitemap' ) || get_query_var( 'xsl' ) || get_query_var( 'xml_sitemap' ) ) {
			$this->config->query_vars['is_feed']    = 1;
			$this->config->query_vars['is_sitemap'] = 1;
		} elseif ( is_robots() ) {
			$this->config->query_vars['is_robots'] = 1;
		}

		// Reset everything if it's 404.
		if ( is_404() ) {
			$this->config->query_vars = array( 'is_404' => 1 );
		}

		return $this->config->query_vars;
	}

	/**
	 * Was there a fatal error in the page?
	 *
	 * @since  2.0
	 */
	private function is_fatal_error() {
		$error = error_get_last();
		if ( null === $error ) {
			return false;
		}

		if ( $error['type'] & ( E_ERROR | E_CORE_ERROR | E_PARSE | E_COMPILE_ERROR | E_USER_ERROR ) ) {
			$this->config->query_vars['is_fatal_error'] = 1;
			return true;
		}

		return false;
	}

	/**
	 * Create cache file from buffer
	 *
	 * @param int $status status code of the current HTTTP request.
	 * @since  2.0
	 */
	private function catch_http_status_code( $status ) {
		if ( in_array( intval( $status ), array( 301, 302, 303, 307 ), true ) ) {
			$this->config->query_vars['is_redirect'] = 1;
		} elseif ( 304 === $status ) {
			$this->config->query_vars['is_304'] = 1;
		} elseif ( 303 === $status ) {
			$this->config->query_vars['is_404'] = 1;
		}

		return $status;
	}

	/**
	 * Will we be allowed serve gzip content?
	 *
	 * @since  2.0
	 */
	public function gzip_encoding() {
		static $gzip_accepted = 1;

		if ( 1 !== $gzip_accepted ) {
			return $gzip_accepted;
		}

		if ( ! $this->config->config['cache_compression'] ) {
			$gzip_accepted = false;
			return $gzip_accepted;
		}

		if ( 1 === ini_get( 'zlib.output_compression' ) || 'on' === strtolower( ini_get( 'zlib.output_compression' ) ) ) { // Don't compress WP-Cache data files when PHP is already doing it.
			$gzip_accepted = false;
			return $gzip_accepted;
		}

		if ( ! isset( $_SERVER['HTTP_ACCEPT_ENCODING'] ) || ( isset( $_SERVER['HTTP_ACCEPT_ENCODING'] ) && false === strpos( $_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip' ) ) ) {
			$gzip_accepted = false;
			return $gzip_accepted;
		}

		$gzip_accepted = 'gzip';
		return $gzip_accepted;
	}

	/**
	 * Get meta information about new cache file.
	 *
	 * @since  2.0
	 * @return array
	 */
	private function get_cache_meta_information() {
		if ( ! function_exists( 'wpsc_init' ) ) {
			/*
			 * If a server has multiple networks the plugin may not have been activated
			 * on all of them. Give feeds on those blogs a short TTL.
			 * * ref: https://wordpress.org/support/topic/fatal-error-while-updating-post-or-publishing-new-one/
			 */
			$wpsc_feed_ttl = 1;
			wp_cache_debug( 'wp_cache_shutdown_callback: Plugin not loaded. Setting feed ttl to 60 seconds.' );
		}

		$wp_cache_meta['uri']     = WPSC_HTTP_HOST . preg_replace('/[ <>\'\"\r\n\t\(\)]/', '', WPSC_URI ); // To avoid XSS attacks
		$wp_cache_meta['blog_id'] = $blog_id;
		$wp_cache_meta['post']    = wp_cache_post_id();
		$wp_cache_meta['key']     = $wp_cache_key;

		$wp_cache_meta = apply_filters( 'wp_cache_meta', $wp_cache_meta );

		$response = wp_cache_get_response_headers();
		foreach( $response as $key => $value ) {
			$wp_cache_meta['headers'][ $key ] = "$key: $value";
		}

		wp_cache_debug( 'wp_cache_shutdown_callback: collecting meta data.', 2 );

		if (!isset( $response['Last-Modified'] )) {
			$value = gmdate('D, d M Y H:i:s') . ' GMT';
			/* Dont send this the first time */
			/* @header('Last-Modified: ' . $value); */
			$wp_cache_meta['headers']['Last-Modified'] = "Last-Modified: $value";
		}
		$is_feed = false;
		if ( !isset( $response['Content-Type'] ) && !isset( $response['Content-type'] ) ) {
			// On some systems, headers set by PHP can't be fetched from
			// the output buffer. This is a last ditch effort to set the
			// correct Content-Type header for feeds, if we didn't see
			// it in the response headers already. -- dougal
			if ( isset( $this->config->query_vars['is_feed'] ) ) {
				if ( isset( $this->config->query_vars['is_sitemap'] ) )  {
					$type  = 'sitemap';
					$value = 'text/xml';
				} else {
					$type = get_query_var( 'feed' );
					$type = str_replace('/','',$type);
					switch ($type) {
						case 'atom':
							$value = 'application/atom+xml';
							break;
						case 'rdf':
							$value = 'application/rdf+xml';
							break;
						case 'rss':
						case 'rss2':
						default:
							$value = 'application/rss+xml';
					}
				}
				$is_feed = true;

				if ( isset( $wpsc_feed_ttl ) && $wpsc_feed_ttl == 1 ) {
					$wp_cache_meta['ttl'] = 60;
				}
				$is_feed = true;

				wp_cache_debug( "wp_cache_shutdown_callback: feed is type: $type - $value" );
			} elseif ( isset( $this->config->query_vars['is_rest'] ) ) { // json
				$value = 'application/json';
			} else { // not a feed
				$value = get_option( 'html_type' );
				if( $value == '' )
					$value = 'text/html';
			}
			if ( defined( 'WPSC_BLOG_CHARSET' ) ) {
				$value .=  "; charset=\"" . constant( 'WPSC_BLOG_CHARSET' ) . "\"";
			}

			$wp_cache_meta['headers']['Content-Type'] = "Content-Type: $value";
		}

		if ( $cache_enabled && !$supercacheonly && $new_cache ) {
			if( !isset( $wp_cache_meta['dynamic'] ) && $wp_cache_gzip_encoding && !in_array( 'Content-Encoding: ' . $wp_cache_gzip_encoding, $wp_cache_meta['headers'] ) ) {
				wp_cache_debug( 'Sending gzip headers.', 2 );
				$wp_cache_meta['headers']['Content-Encoding'] = 'Content-Encoding: ' . $wp_cache_gzip_encoding;
				if ( defined( 'WPSC_VARY_HEADER' ) ) {
					if ( WPSC_VARY_HEADER != '' ) {
						$vary_header = WPSC_VARY_HEADER;
					} else {
						$vary_header = '';
					}
				} else {
					$vary_header = 'Accept-Encoding, Cookie';
				}
				if ( $vary_header ) {
					$wp_cache_meta['headers']['Vary'] = 'Vary: ' . $vary_header;
				}
			}

			$serial = '<?php die(); ?>' . json_encode( $wp_cache_meta );
			$dir = get_current_url_supercache_dir();
			if( @is_dir( $dir ) == false )
				@wp_mkdir_p( $dir );

			if( wp_cache_writers_entry() ) {
				wp_cache_debug( "Writing meta file: {$dir}meta-{$meta_file}", 2 );

				$tmp_meta_filename = $dir . uniqid( mt_rand(), true ) . '.tmp';
				$final_meta_filename = $dir . "meta-" . $meta_file;
				$fr = @fopen( $tmp_meta_filename, 'w');
				if ( $fr ) {
					fputs($fr, $serial);
					fclose($fr);
					@chmod( $tmp_meta_filename, 0666 & ~umask());
					if( !@rename( $tmp_meta_filename, $final_meta_filename ) ) {
						@unlink( $dir . $final_meta_filename );
						@rename( $tmp_meta_filename, $final_meta_filename );
					}
				} else {
					wp_cache_debug( "Problem writing meta file: {$final_meta_filename}" );
				}
				wp_cache_writers_exit();

				// record locations of archive feeds to be updated when the site is updated.
				// Only record a maximum of 50 feeds to avoid bloating database.
				if ( ( isset( $this->config->query_vars['is_feed'] ) || $is_feed ) && ! isset( $this->config->query_vars['is_single'] ) ) {
					$wpsc_feed_list = (array) get_option( 'wpsc_feed_list' );
					if ( count( $wpsc_feed_list ) <= 50 ) {
						$wpsc_feed_list[] = $dir . $meta_file;
						update_option( 'wpsc_feed_list', $wpsc_feed_list );
					}
				}
			}
		} else {
			wp_cache_debug( "Did not write meta file: meta-{$meta_file}\nsupercacheonly: $supercacheonly\nwp_cache_not_logged_in: $wp_cache_not_logged_in\nnew_cache:$new_cache" );
		}
		global $time_to_gc_cache;
		if ( isset( $time_to_gc_cache ) && $time_to_gc_cache == 1 ) {
			wp_cache_debug( 'Executing wp_cache_gc action.', 3 );
			do_action( 'wp_cache_gc' );
		}

		return $wp_cache_meta;
	}

	/**
	 * Get headers for newly created cache file.
	 *
	 * @since  2.0
	 * @return array
	 */
	private function send_cache_headers( $wp_cache_meta ) {
		if ( isset( $wp_cache_meta['headers']['Content-Type'] ) ) {
			wp_cache_debug( "Sending header: $value" );
			@header( $value );
		}

		return true;
	}

	/**
	 * Create cache file from buffer
	 *
	 * @param string $buffer the output buffer containing the current page.
	 * @since  2.0
	 */
	public function ob_handler( $buffer ) {

		$cache_this_page = true;

		if ( mb_strlen( $buffer ) < 255 ) {
			wp_cache_debug( 'ob_handler: not caching a small page.' );
			$cache_this_page = false;
		}

		if ( $this->is_fatal_error() ) {
			wp_cache_debug( 'ob_handler: PHP Fatal error occurred. Not caching incomplete page.' );
			$cache_this_page = false;
		} elseif ( empty( $this->config->query_vars ) && ! empty( $buffer ) ) {
			$this->get_query_vars();
		} elseif ( empty( $this->config->query_vars ) && function_exists( 'http_response_code' ) ) {
			$this->catch_http_status_code( http_response_code() );
		}
		$buffer = apply_filters( 'ob_handler_filter', $buffer );

		if ( $cache_this_page ) {
			$cache_this_page = WP_Super_cache_Page::instance()->post_cache_checks();
		}

		if ( isset( $this->config->config['wpsc_save_headers'] ) && $this->config->config['wpsc_save_headers'] ) {
			$this->config->config['super_cache_enabled'] = false; // use standard caching to record headers.
		}

		if ( $cache_this_page ) {

			wp_cache_debug( 'Output buffer callback' );

			$buffer = $this->write_buffer_to_file( $buffer );
			// TODO.
			//wp_cache_shutdown_callback();
			$meta_info = $this->get_cache_meta_information();

			$this->send_cache_headers( $meta_info );

			/*
			 * TODO - rebuild system
			 */
			if ( ! empty( $wpsc_file_mtimes ) && is_array( $wpsc_file_mtimes ) ) {
				foreach ( $wpsc_file_mtimes as $cache_file => $old_mtime ) {
					if ( $old_mtime === @filemtime( $cache_file ) ) { // phpcs:ignore
						wp_cache_debug( "wp_cache_ob_callback deleting unmodified rebuilt cache file: $cache_file" );
						if ( wp_cache_confirm_delete( $cache_file ) ) {
							@unlink( $cache_file ); // phpcs:ignore
						}
					}
				}
			}
			return $buffer;
		} else {
			if ( ! empty( $do_rebuild_list ) && is_array( $do_rebuild_list ) ) {
				foreach ( $do_rebuild_list as $dir => $n ) {
					if ( wp_cache_confirm_delete( $dir ) ) {
						wp_cache_debug( 'wp_cache_ob_callback clearing rebuilt files in ' . $dir );
						wpsc_delete_files( $dir );
					}
				}
			}
			return $this->wp_cache_maybe_dynamic( $buffer );
		}

		return $buffer;
	}

	/**
	 * Add text to the buffer
	 *
	 * @param string $buffer the output buffer containing the current page.
	 * @param string $text text to add to the buffer.
	 * @since  2.0
	 * @return bool
	 */
	private function add_to_buffer( &$buffer, $text ) {
		if ( ! isset( $this->config->config['wp_super_cache_debug'] ) || $this->config->config['wp_super_cache_debug'] ) {
			return false;
		}

		if ( false === isset( $this->config->config['wp_super_cache_comments'] ) ) {
			$this->config->config['wp_super_cache_comments'] = 1;
		}

		if ( 0 === $this->config->config['wp_super_cache_comments'] ) {
			return false;
		}

		if ( false === strpos( $buffer, '<html' ) ) {
			wp_cache_debug( site_url( $_SERVER['REQUEST_URI'] ) . ' - ' . $text ); // phpcs:ignore
			return false;
		}

		$buffer .= "\n<!-- $text -->";
	}

	/**
	 * Return an md5 of the cookies found for the current visitor.
	 *
	 * @since  2.0
	 * @return bool
	 */
	public function wp_cache_get_cookies_values() {
		global $wpsc_cookies;
		static $string = '';

		if ( '' !== $string ) {
			wp_cache_debug( "wp_cache_get_cookies_values: cache key: $string" );
			return $string;
		}

		if ( defined( 'COOKIEHASH' ) ) {
			$cookiehash = preg_quote( constant( 'COOKIEHASH' ) ); // phpcs:ignore
		} else {
			$cookiehash = '';
		}

		$regex = "/^wp-postpass_$cookiehash|^comment_author_$cookiehash";
		if ( defined( 'LOGGED_IN_COOKIE' ) ) {
			$regex .= '|^' . preg_quote( constant( 'LOGGED_IN_COOKIE' ) ); // phpcs:ignore
		} else {
			$regex .= '|^wordpress_logged_in_' . $cookiehash;
		}
		$regex .= '/';

		while ( $key = key( $_COOKIE ) ) { // phpcs:ignore
			if ( isset( $_COOKIE[ $key ] ) && preg_match( $regex, $key ) ) {
				wp_cache_debug( 'wp_cache_get_cookies_values: Login/postpass cookie detected' );
				$string .= wp_unslash( $_COOKIE[ $key ] ) . ','; // phpcs:ignore
			}
			next( $_COOKIE );
		}
		reset( $_COOKIE );

		// If you use this hook, make sure you update your .htaccess rules with the same conditions.
		$string = do_cacheaction( 'wp_cache_get_cookies_values', $string );

		if (
			isset( $wpsc_cookies ) &&
			is_array( $wpsc_cookies ) &&
			! empty( $wpsc_cookies )
		) {
			foreach ( $wpsc_cookies as $name ) {
				if ( isset( $_COOKIE[ $name ] ) ) {
					wp_cache_debug( "wp_cache_get_cookies_values - found extra cookie: $name" );
					$string .= $name . '=' . $_COOKIE[ $name ] . ','; //phpcs:ignore
				}
			}
		}

		if ( '' !== $string ) {
			$string = md5( $string );
		}

		wp_cache_debug( "wp_cache_get_cookies_values: return: $string" );

		return $string;
	}

	/**
	 * Get the filename of the supercache cache file.
	 *
	 * @since  2.0
	 * @return string
	 */
	public function supercache_filename() {
		// Add support for https and http caching.
		// Also supports https requests coming from an nginx reverse proxy.
		$is_https = ( ( isset( $_SERVER['HTTPS'] ) && 'on' == strtolower( $_SERVER['HTTPS'] ) ) || ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' == strtolower( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) ); // phpcs:ignore
		$extra_str = $is_https ? '-https' : '';

		if ( function_exists( 'apply_filters' ) ) {
			$extra_str = apply_filters( 'supercache_filename_str', $extra_str );
		} else {
			$extra_str = do_cacheaction( 'supercache_filename_str', $extra_str );
		}

		if ( is_array( $this->config->config['cached_direct_pages'] ) && in_array( $_SERVER['REQUEST_URI'], $this->config->config['cached_direct_pages'] ) ) { // phpcs:ignore
			$extra_str = '';
		}
		$filename = 'index' . $extra_str . '.html';

		return $filename;
	}

	/**
	 * If dynamic caching is enabled then run buffer through wpsc_cachedata filter before returning it.
	 * or we'll return template tags to visitors.
	 *
	 * @param string $buffer the output buffer containing the current page.
	 * @since  2.0
	 * @return string
	 */
	private function wp_cache_maybe_dynamic( &$buffer ) {
		if ( 1 === $this->config->config['wp_cache_mfunc_enabled'] && 1 === do_cacheaction( 'wpsc_cachedata_safety', 0 ) ) {
			wp_cache_debug( 'wp_cache_maybe_dynamic: filtered $buffer through wpsc_cachedata' );
			return do_cacheaction( 'wpsc_cachedata', $buffer ); // dynamic content for display.
		} else {
			wp_cache_debug( 'wp_cache_maybe_dynamic: returned $buffer' );
			return $buffer;
		}
	}

	/**
	 * Get the supercache directory for the current url or post ID.
	 *
	 * @param int $post_id The post_id of the post being queried, or 0 to get URL.
	 * @since  2.0
	 * @return string
	 */
	public function get_current_url_supercache_dir( $post_id = 0 ) {
		global $wpsc_http_host;
		static $saved_supercache_dir = array();

		if ( isset( $saved_supercache_dir[ $post_id ] ) ) {
			return $saved_supercache_dir[ $post_id ];
		}

		$do_not_remember = 0;
		if ( 0 !== $post_id ) {
			$site_url  = site_url();
			$permalink = get_permalink( $post_id );
			if ( false === strpos( $permalink, $site_url ) ) {
				/*
				 * Sometimes site_url doesn't return the siteurl. See https://wordpress.org/support/topic/wp-super-cache-not-refreshing-post-after-comments-made
				 */
				$do_not_remember = 1;
				wp_cache_debug( "get_current_url_supercache_dir: WARNING! site_url ($site_url) not found in permalink ($permalink).", 1 );
				if ( preg_match( '`^(https?:)?//([^/]+)(/.*)?$`i', $permalink, $matches ) ) {
					if ( $wpsc_http_host !== $matches[2] ) {
						wp_cache_debug( "get_current_url_supercache_dir: WARNING! SERVER_NAME ({$wpsc_http_host}) not found in permalink ($permalink).", 1 );
					}
					wp_cache_debug( "get_current_url_supercache_dir: Removing SERVER_NAME ({$matches[2]}) from permalink ($permalink). Is the url right?", 1 );
					$uri = isset( $matches[3] ) ? $matches[3] : '';
				} elseif ( preg_match( '`^/([^/]+)(/.*)?$`i', $permalink, $matches ) ) {
					wp_cache_debug( "get_current_url_supercache_dir: WARNING! Permalink ($permalink) looks as absolute path. Is the url right?", 1 );
					$uri = $permalink;
				} else {
					wp_cache_debug( "get_current_url_supercache_dir: WARNING! Permalink ($permalink) could not be understood by parsing url. Using front page.", 1 );
					$uri = '';
				}
			} else {
				$uri = str_replace( $site_url, '', $permalink );
				if ( strpos( $uri, $wp_cache_home_path ) !== 0 ) {
					$uri = rtrim( $wp_cache_home_path, '/' ) . $uri;
				}
			}
		} else {
			$uri = strtolower( WPSC_URI );
		}
		$uri      = wpsc_deep_replace(
			array( '..', '\\', 'index.php' ),
			preg_replace( '/[ <>\'\"\r\n\t\(\)]/', '', preg_replace( '/(\?.*)?(#.*)?$/', '', $uri ) )
		);
		$hostname = $wpsc_http_host;
		// Get hostname from wp options for wp-cron, wp-cli and similar requests.
		if ( empty( $hostname ) && function_exists( 'get_option' ) ) {
			$hostname = (string) wp_parse_url( get_option( 'home' ), PHP_URL_HOST );
		}
		$dir = preg_replace( '/:.*$/', '', $hostname ) . $uri; // To avoid XSS attacks.
		if ( function_exists( 'apply_filters' ) ) {
			$dir = apply_filters( 'supercache_dir', $dir );
		} else {
			$dir = do_cacheaction( 'supercache_dir', $dir );
		}
		$dir = $this->config->config['cache_path'] . 'supercache/' . $dir . '/';
		if ( is_array( $this->config->config['cached_direct_pages'] ) && in_array( $_SERVER['REQUEST_URI'], $this->config->config['cached_direct_pages'] ) ) { // phpcs:ignore
			$dir = ABSPATH . $uri . '/';
		}
		$dir = str_replace( '..', '', str_replace( '//', '/', $dir ) );
		wp_cache_debug( "supercache dir: $dir" );
		if ( 0 === $do_not_remember ) {
			$saved_supercache_dir[ $post_id ] = $dir;
		}
		return $dir;
	}

	/**
	 * Create mutex flag for file locking
	 *
	 * @since  2.0
	 * @return string
	 */
	public function wp_cache_mutex_init() {
		global $mutex, $wp_cache_mutex_disabled, $use_flock, $blog_cache_dir, $mutex_filename, $sem_id;

		if ( defined( 'WPSC_DISABLE_LOCKING' ) || ( isset( $wp_cache_mutex_disabled ) && $wp_cache_mutex_disabled ) ) {
			return true;
		}

		if ( ! is_bool( $use_flock ) ) {
			if ( function_exists( 'sem_get' ) ) {
				$use_flock = false;
			} else {
				$use_flock = true;
			}
		}

		$mutex = false;
		if ( $use_flock ) {
			setup_blog_cache_dir();
			wp_cache_debug( "Created mutex lock on filename: {$blog_cache_dir}{$mutex_filename}" );
			$mutex = @fopen( $blog_cache_dir . $mutex_filename, 'w' ); // phpcs:ignore
		} else {
			wp_cache_debug( "Created mutex lock on semaphore: {$sem_id}" );
			$mutex = @sem_get( $sem_id, 1, 0666, 1 ); // phpcs:ignore
		}
	}

	/**
	 * Entry to writer's lock
	 *
	 * @since  2.0
	 * @return string
	 */
	private function wp_cache_writers_entry() {
		global $mutex, $wp_cache_mutex_disabled, $use_flock;

		if ( defined( 'WPSC_DISABLE_LOCKING' ) || ( isset( $wp_cache_mutex_disabled ) && $wp_cache_mutex_disabled ) ) {
			return true;
		}

		if ( ! $mutex ) {
			wp_cache_debug( '(writers entry) mutex lock not created. not caching.' );
			return false;
		}

		if ( $use_flock ) {
			wp_cache_debug( 'grabbing lock using flock()' );
			flock( $mutex, LOCK_EX );
		} else {
			wp_cache_debug( 'grabbing lock using sem_acquire()' );
			@sem_acquire( $mutex ); // phpcs:ignore
		}

		return true;
	}

	/**
	 * Exit to writer's lock
	 *
	 * @since  2.0
	 * @return string
	 */
	private function wp_cache_writers_exit() {
		global $mutex, $wp_cache_mutex_disabled, $use_flock;

		if ( defined( 'WPSC_DISABLE_LOCKING' ) || ( isset( $wp_cache_mutex_disabled ) && $wp_cache_mutex_disabled ) ) {
			return true;
		}

		if ( ! $mutex ) {
			wp_cache_debug( '(writers exit) mutex lock not created. not caching.' );
			return false;
		}

		if ( $use_flock ) {
			wp_cache_debug( 'releasing lock using flock()', 5 );
			flock( $mutex, LOCK_UN );
		} else {
			wp_cache_debug( 'releasing lock using sem_release() and sem_remove()' );
			@sem_release( $mutex ); // phpcs:ignore
			if ( defined( 'WPSC_REMOVE_SEMAPHORE' ) ) {
				@sem_remove( $mutex ); // phpcs:ignore
			}
		}
	}


	/**
	 * Count the micro seconds between $a and $b
	 *
	 * @param string $a the start time.
	 * @param string $b the end time.
	 * @since  2.0
	 * @return string
	 */
	private function wp_cache_microtime_diff( $a, $b ) {
		list( $a_dec, $a_sec ) = explode( ' ', $a );
		list( $b_dec, $b_sec ) = explode( ' ', $b );
		return (float) $b_sec - (float) $a_sec + (float) $b_dec - (float) $a_dec;
	}

	/**
	 * Write buffer to the cache file.
	 *
	 * @param string $buffer the output buffer containing the current page.
	 * @since  2.0
	 * @return string
	 */
	private function write_buffer_to_file( &$buffer ) {
		global $wp_super_cache_start_time;

		if ( false === isset( $this->config->config['wp_cache_mfunc_enabled'] ) ) {
			$this->config->config['wp_cache_mfunc_enabled'] = 0;
		}

		$new_cache     = true;
		$wp_cache_meta = array();

		if ( '' === $buffer ) {
			$new_cache = false;
			wp_cache_debug( "Buffer is blank. Output buffer may have been corrupted by another plugin or this is a redirected URL. Look for text 'ob_start' in the files of your plugins directory." );
			$this->add_to_buffer( $buffer, 'Page not cached by WP Super Cache. Blank Page. Check output buffer usage by plugins.' );
		}

		if ( isset( $this->config->query_vars['is_404'] ) && false === apply_filters( 'wpsupercache_404', false ) ) {
			$new_cache = false;
			wp_cache_debug( '404 file not found not cached' );
			$this->add_to_buffer( $buffer, 'Page not cached by WP Super Cache. 404.' );
		}

		if ( ! preg_match( apply_filters( 'wp_cache_eof_tags', '/(<\/html>|<\/rss>|<\/feed>|<\/urlset|<\?xml)/i' ), $buffer ) ) {
			$new_cache = false;
			wp_cache_debug( 'No closing html tag. Not caching.', 2 );
			$this->add_to_buffer( $buffer, 'Page not cached by WP Super Cache. No closing HTML tag. Check your theme.' );
		}

		if ( ! $new_cache ) {
			return $this->wp_cache_maybe_dynamic( $buffer );
		}

		$duration = $this->wp_cache_microtime_diff( $wp_super_cache_start_time, microtime() );
		$duration = sprintf( '%0.3f', $duration );
		$this->add_to_buffer( $buffer, "Dynamic page generated in $duration seconds." );

		if ( ! $this->wp_cache_writers_entry() ) {
			$this->add_to_buffer( $buffer, 'Page not cached by WP Super Cache. Could not get mutex lock.' );
			wp_cache_debug( 'Could not get mutex lock. Not caching.' );
			return $this->wp_cache_maybe_dynamic( $buffer );
		}

		if ( $this->config->config['wp_cache_not_logged_in'] && isset( $this->config->query_vars['is_feed'] ) ) {
			wp_cache_debug( 'Feed detected. Writing wpcache cache files.' );
			$wp_cache_not_logged_in = false;
		}

		$home_url      = wp_parse_url( trailingslashit( get_bloginfo( 'url' ) ) );
		$dir           = get_current_url_supercache_dir();
		$supercachedir = $this->config->config['cache_path'] . 'supercache/' . preg_replace( '/:.*$/', '', $home_url['host'] );

		if (
			! empty( $_GET ) || // phpcs:ignore
			isset( $this->config->query_vars['is_feed'] ) ||
			(
				$this->config->config['super_cache_enabled'] &&
				is_dir( substr( $supercachedir, 0, -1 ) . '.disabled' )
			)
		) {
			wp_cache_debug( 'Supercache disabled: GET or feed detected or disabled by config.' );
			$this->config->config['super_cache_enabled'] = false;
		}

		$tmp_wpcache_filename = $this->config->config['cache_path'] . uniqid( wp_rand(), true ) . '.tmp';

		if ( defined( 'WPSC_SUPERCACHE_ONLY' ) ) {
			$supercacheonly = true;
			wp_cache_debug( 'wp_cache_get_ob: WPSC_SUPERCACHE_ONLY defined. Only creating supercache files.' );
		} else {
			$supercacheonly = false;
		}

		if ( $this->config->config['super_cache_enabled'] ) {
			if ( '' === $this->wp_cache_get_cookies_values() && empty( $_GET ) ) { // phpcs:ignore
				wp_cache_debug( 'Anonymous user detected. Only creating Supercache file.' );
				$supercacheonly = true;
			}
		}
		$cache_error = '';
		if ( wpsc_is_caching_user_disabled() ) {
			$super_cache_enabled = false;
			$cache_enabled       = false;
			$cache_error         = 'Not caching requests by known users. (See Advanced Settings page)';
			wp_cache_debug( 'Not caching for known user.' );
		}

		if ( ! $cache_enabled ) {
			wp_cache_debug( 'Cache is not enabled. Sending buffer to browser.' );
			$this->wp_cache_writers_exit();
			$this->add_to_buffer( $buffer, "Page not cached by WP Super Cache. Check your settings page. $cache_error" );
			if ( 1 === $this->config->config['wp_cache_mfunc_enabled'] ) {
				if (
					false === isset( $this->config->config['wp_super_cache_late_init'] ) ||
					( isset( $this->config->config['wp_super_cache_late_init'] ) && 0 === $this->config->config['wp_super_cache_late_init'] )
				) {
					$this->add_to_buffer( $buffer, 'Super Cache dynamic page detected but $wp_super_cache_late_init not set. See the readme.txt for further details.' );
				}
			}

			return $this->wp_cache_maybe_dynamic( $buffer );
		}

		if ( false === @is_dir( $dir ) ) { // phpcs:ignore
			@wp_mkdir_p( $dir ); // phpcs:ignore
		}
		// TODO.
		$dir = wpsc_get_realpath( $dir );

		if ( ! $dir ) {
			wp_cache_debug( 'wp_cache_get_ob: not caching as directory does not exist.' );
			return $buffer;
		}

		$dir = trailingslashit( $dir );

		// TODO.
		if ( ! wpsc_is_in_cache_directory( $dir ) ) {
			wp_cache_debug( "wp_cache_get_ob: not caching as directory is not in cache_path: $dir" );
			return $buffer;
		}

		$fr  = false;
		$fr2 = false;
		$gz  = false;

		// Open wp-cache cache file.
		if ( ! $supercacheonly ) {
			$fr = @fopen( $tmp_wpcache_filename, 'w' ); //phpcs:ignore
			if ( ! $fr ) {
				wp_cache_debug( 'Error. Supercache could not write to ' . str_replace( ABSPATH, '', $this->config->config['cache_path'] ) . $cache_filename );
				$this->add_to_buffer( $buffer, "File not cached! Super Cache Couldn't write to: " . str_replace( ABSPATH, '', $this->config->config['cache_path'] ) . $cache_filename );
				$this->wp_cache_writers_exit();
				return $this->wp_cache_maybe_dynamic( $buffer );
			}
		} else {
			$user_info = $this->wp_cache_get_cookies_values();
			$do_cache  = apply_filters( 'do_createsupercache', $user_info );
			if (
				$super_cache_enabled &&
				(
					'' === $user_info ||
					true === $do_cache
				)
			) {
				$cache_fname        = $dir . $this->supercache_filename();
				$tmp_cache_filename = $dir . uniqid( wp_rand(), true ) . '.tmp';

				$fr2 = @fopen( $tmp_cache_filename, 'w' ); // phpcs:ignore
				if ( ! $fr2 ) {
					wp_cache_debug( 'Error. Supercache could not write to ' . str_replace( ABSPATH, '', $tmp_cache_filename ) );
					$this->add_to_buffer( $buffer, "File not cached! Super Cache Couldn't write to: " . str_replace( ABSPATH, '', $tmp_cache_filename ) );
					@fclose( $fr ); // phpcs:ignore
					@unlink( $tmp_wpcache_filename ); // phpcs:ignore
					$this->wp_cache_writers_exit();
					return $this->wp_cache_maybe_dynamic( $buffer );
				} elseif (
					$this->gzip_encoding() &&
					0 === $wp_cache_mfunc_enabled
				) { // don't want to store compressed files if using dynamic content.
					$gz = @fopen( $tmp_cache_filename . '.gz', 'w' ); // phpcs:ignore
					if ( ! $gz ) {
						wp_cache_debug( 'Error. Supercache could not write to ' . str_replace( ABSPATH, '', $tmp_cache_filename ) . '.gz' );
						$this->add_to_buffer( $buffer, "File not cached! Super Cache Couldn't write to: " . str_replace( ABSPATH, '', $tmp_cache_filename ) . '.gz' );
						@fclose( $fr ); // phpcs:ignore
						@unlink( $tmp_wpcache_filename ); //phpcs:ignore
						@fclose( $fr2 ); // phpcs:ignore
						@unlink( $tmp_cache_filename ); //phpcs:ignore
						$this->wp_cache_writers_exit();
						return $this->wp_cache_maybe_dynamic( $buffer );
					}
				}
			}
		}

		// TODO.
		$added_cache = 0;
		$oc_key      = get_oc_key();
		$buffer      = apply_filters( 'wpsupercache_buffer', $buffer );
		wp_cache_append_tag( $buffer );

		/*
		 * Dynamic content enabled: write the buffer to a file and then process any templates found using
		 * the wpsc_cachedata filter. Buffer is then returned to the visitor.
		 */
		if ( 1 === $this->config->config['wp_cache_mfunc_enabled'] ) {
			if ( preg_match( '/<!--mclude|<!--mfunc|<!--dynamic-cached-content-->/', $buffer ) ) { // Dynamic content.
				wp_cache_debug( 'mfunc/mclude/dynamic-cached-content tags have been retired. Please update your theme. See docs for updates.' );
				$this->add_to_buffer( $buffer, 'Warning! Obsolete mfunc/mclude/dynamic-cached-content tags found. Please update your theme. See http://ocaoimh.ie/y/5b for more information.' );
			}

			if ( false === isset( $this->config->config['wp_super_cache_late_init'] ) || ( isset( $this->config->config['wp_super_cache_late_init'] ) && 0 === $this->config->config['wp_super_cache_late_init'] ) ) {
				$this->add_to_buffer( $buffer, 'Super Cache dynamic page detected but late init not set. See the readme.txt for further details.' );
			}

			if ( $fr ) { // wpcache caching.
				wp_cache_debug( 'Writing dynamic buffer to wpcache file.' );
				$this->add_to_buffer( $buffer, 'Dynamic WPCache Super Cache' );
				fputs( $fr, '<?php die(); ?>' . $buffer );
			} elseif ( isset( $fr2 ) ) { // supercache active.
				wp_cache_debug( 'Writing dynamic buffer to supercache file.' );
				$this->add_to_buffer( $buffer, 'Dynamic Super Cache' );
				fputs( $fr2, $buffer );
			}
			$wp_cache_meta['dynamic'] = true;
			if ( 1 === $wp_cache_mfunc_enabled && 1 === do_cacheaction( 'wpsc_cachedata_safety', 0 ) ) {
				$buffer = do_cacheaction( 'wpsc_cachedata', $buffer ); // dynamic content for display.
			}

			if ( $this->gzip_encoding() ) {
				wp_cache_debug( 'Gzipping dynamic buffer for display.', 5 );
				$this->add_to_buffer( $buffer, 'Compression = gzip' );
				$gzdata = gzencode( $buffer, 6, FORCE_GZIP );
				$gzsize = function_exists( 'mb_strlen' ) ? mb_strlen( $gzdata, '8bit' ) : strlen( $gzdata );
			}
		} else {
			if ( defined( 'WPSC_VARY_HEADER' ) ) {
				if ( '' !== WPSC_VARY_HEADER ) {
					$vary_header = WPSC_VARY_HEADER;
				} else {
					$vary_header = '';
				}
			} else {
				$vary_header = 'Accept-Encoding, Cookie';
			}
			if ( $vary_header ) {
				$wp_cache_meta['headers']['Vary'] = 'Vary: ' . $vary_header;
			}
			if ( $gz || $this->gzip_encoding() ) {
				wp_cache_debug( 'Gzipping buffer.' );
				$this->add_to_buffer( $buffer, 'Compression = gzip' );
				$gzdata = gzencode( $buffer, 6, FORCE_GZIP );
				$gzsize = function_exists( 'mb_strlen' ) ? mb_strlen( $gzdata, '8bit' ) : strlen( $gzdata );

				$wp_cache_meta['headers']['Content-Encoding'] = 'Content-Encoding: ' . $this->gzip_encoding();
				// Return uncompressed data & store compressed for later use.
				if ( $fr ) {
					wp_cache_debug( 'Writing gzipped buffer to wp-cache cache file.', 5 );
					fputs( $fr, '<?php die(); ?>' . $gzdata );
				}
			} else { // no compression.
				if ( $fr ) {
					wp_cache_debug( 'Writing non-gzipped buffer to wp-cache cache file.' );
					fputs( $fr, '<?php die(); ?>' . $buffer );
				}
			}
			if ( $fr2 ) {
				wp_cache_debug( 'Writing non-gzipped buffer to supercache file.' );
				$this->add_to_buffer( $buffer, 'super cache' );
				fputs( $fr2, $buffer );
			}
			if ( isset( $gzdata ) && $gz ) {
				wp_cache_debug( 'Writing gzipped buffer to supercache file.' );
				fwrite( $gz, $gzdata ); // phpcs:ignore
			}
		}

		$new_cache = true;
		if ( $fr ) {
			$supercacheonly = false;
			fclose( $fr ); // phpcs:ignore
			if ( 0 === filesize( $tmp_wpcache_filename ) ) {
				wp_cache_debug( "Warning! The file $tmp_wpcache_filename was empty. Did not rename to {$dir}{$cache_filename}", 5 );
				@unlink( $tmp_wpcache_filename ); //phpcs:ignore
			} else {
				if ( ! @rename( $tmp_wpcache_filename, $dir . $cache_filename ) ) { // phpcs:ignore
					if ( false === is_dir( $dir ) ) {
						@wp_mkdir_p( $dir ); //phpcs:ignore
					}
					@unlink( $dir . $cache_filename ); // phpcs:ignore
					@rename( $tmp_wpcache_filename, $dir . $cache_filename ); // phpcs:ignore
				}
				if ( file_exists( $dir . $cache_filename ) ) {
					wp_cache_debug( "Renamed temp wp-cache file to {$dir}{$cache_filename}", 5 );
				} else {
					wp_cache_debug( "FAILED to rename temp wp-cache file to {$dir}{$cache_filename}", 5 );
				}
				$added_cache = 1;
			}
		}

		if ( $fr2 ) {
			fclose( $fr2 ); //phpcs:ignore
			if ( $wp_cache_front_page_checks && $cache_fname === $supercachedir . $home_url['path'] . $this->supercache_filename() && ! $wp_cache_is_home ) {
				$this->wp_cache_writers_exit();
				wp_cache_debug( 'Warning! Not writing another page to front page cache.', 1 );
				return $buffer;
			} elseif ( 0 === filesize( $tmp_cache_filename ) ) {
				wp_cache_debug( "Warning! The file $tmp_cache_filename was empty. Did not rename to {$cache_fname}", 5 );
				@unlink( $tmp_cache_filename ); // phpcs:ignore
			} else {
				if ( ! @rename( $tmp_cache_filename, $cache_fname ) ) { // phpcs:ignore
					@unlink( $cache_fname ); // phpcs:ignore
					@rename( $tmp_cache_filename, $cache_fname ); // phpcs:ignore
				}
				wp_cache_debug( "Renamed temp supercache file to $cache_fname", 5 );
				$added_cache = 1;
			}
		}
		if ( $gz ) {
			fclose( $gz ); // phpcs:ignore
			if ( 0 === filesize( $tmp_cache_filename . '.gz' ) ) {
				wp_cache_debug( "Warning! The file {$tmp_cache_filename}.gz was empty. Did not rename to {$cache_fname}.gz" );
				@unlink( $tmp_cache_filename . '.gz' ); // phpcs:ignore
			} else {
				if ( ! @rename( $tmp_cache_filename . '.gz', $cache_fname . '.gz' ) ) { // phpcs:ignore
					@unlink( $cache_fname . '.gz' ); // phpcs:ignore
					@rename( $tmp_cache_filename . '.gz', $cache_fname . '.gz' ); // phpcs:ignore
				}
				wp_cache_debug( "Renamed temp supercache gz file to {$cache_fname}.gz" );
				$added_cache = 1;
			}
		}

		if ( $added_cache && isset( $wp_supercache_cache_list ) && $wp_supercache_cache_list ) {
			update_option( 'wpsupercache_count', ( get_option( 'wpsupercache_count' ) + 1 ) );
			$last_urls = (array) get_option( 'supercache_last_cached' );
			if ( count( $last_urls ) >= 10 ) {
				$last_urls = array_slice( $last_urls, 1, 9 );
			}
			$last_urls[] = array(
				'url' => preg_replace( '/[ <>\'\"\r\n\t\(\)]/', '', $_SERVER['REQUEST_URI'] ), // phpcs:ignore
				'date' => gmdate( 'Y-m-d H:i:s' ),
			);
			update_option( 'supercache_last_cached', $last_urls );
		}
		$this->wp_cache_writers_exit();
		if ( ! headers_sent() && $this->gzip_encoding() && $gzdata ) {
			wp_cache_debug( 'Writing gzip content headers. Sending buffer to browser', 5 );
			header( 'Content-Encoding: ' . $this->gzip_encoding() );
			if ( defined( 'WPSC_VARY_HEADER' ) ) {
				if ( '' !== WPSC_VARY_HEADER ) {
					$vary_header = WPSC_VARY_HEADER;
				} else {
					$vary_header = '';
				}
			} else {
				$vary_header = 'Accept-Encoding, Cookie';
			}
			if ( $vary_header ) {
				header( 'Vary: ' . $vary_header );
			}
			header( 'Content-Length: ' . $gzsize );
			return $gzdata;
		} else {
			wp_cache_debug( 'Sending buffer to browser', 5 );
			return $buffer;
		}
	}

	/**
	 * Return an instance of the current class, create one if it doesn't exist
	 *
	 * @since  2.0
	 * @return Wp_Super_Cache_File_Cache
	 */
	public static function instance() {

		static $instance;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

}

/**
 * Create cache file from buffer.
 *
 * @param string $buffer the output buffer containing the current page.
 * @since  2.0
 */
function wp_super_cache_ob_handler( $buffer ) {
	$caching = Wp_Super_cache_File_Cache::instance();
	$buffer  = $caching->ob_handler();

}
