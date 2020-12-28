<?php
/**
 * File containing the class Wp_Super_Cache_Page
 *
 * @package wp-super-cache
 *
 * @since   2.0.0
 */

/**
 * Class representing a web page.
 *
 * @since 2.0.0
 */
class Wp_Super_Cache_Page {

	/**
	 * Configuration variables
	 *
	 * @since 1.0.1
	 * @var   array
	 */
	public $config;

	/**
	 * Cache key
	 *
	 * @since 2.0
	 * @var   string
	 */
	public $key;

	/**
	 * Cache Engine
	 *
	 * @since 2.0
	 * @var   string
	 */
	public $cache;

	/**
	 * Set up the page.
	 *
	 * @since    2.0.0
	 */
	public function __construct() {
		$this->config = Wp_Super_Cache_Config::instance();

		// In the future we might have different caching engines.
		$this->cache = WP_Super_Cache_File_Cache::instance();

		// remove authentication cookies so page can be super cached for admin users.
		if ( $this->config->config['wp_cache_make_known_anon'] ) {
			$this->make_anonymous();
		}

		do_cacheaction( 'cache_init' );
	}

	/**
	 * Can page be cached?
	 *
	 * @since  2.0
	 * @return bool
	 */
	public function is_cacheable() {
		if ( ! $this->config->config['cache_enabled'] ) {
			return false;
		}

		if ( isset( $_GET['customize_changeset_uuid'] ) ) { //phpcs:ignore
			return false;
		}

		if ( $this->is_backend() ) {
			wp_cache_debug( 'Not caching backend request.' );
			return false;
		}

		if ( $wp_super_cache_config['cache_enabled'] ) {
			wp_cache_debug( 'Caching disabled.' );
			return false;
		}

		if ( ! $this->pre_cache_checks() ) {
			return false;
		}

		if ( ! $this->is_user_agent_rejected() ) {
			return false;
		}

		if ( $this->url_is_rejected() ) {
			return false;
		}

		return false;
	}

	/**
	 * Is page cached?
	 *
	 * @since  2.0
	 * @return bool
	 */
	public function is_cached() {
		return false;
	}

	/**
	 * Serve the current page from a cache file.
	 *
	 * @since  2.0
	 * @return bool
	 */
	public function serve_page() {
		return true;
	}

	/**
	 * Store the current page in a cache file.
	 *
	 * @since  2.0
	 * @return bool
	 */
	public function cache_page() {
		ob_start( array( Wp_Super_Cache_File_Cache::instance(), 'ob_handler' ) );

		return true;
	}

	/**
	 * Make the current request anonymouse for every type of visitor.
	 *
	 * @since  2.0
	 * @return bool
	 */
	public function make_anonymous() {

		// Don't remove cookies for some requests.
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return true;
		}

		if ( $this->is_backend() ) {
			return true;
		}

		if ( isset( $_GET['preview'], $_GET['customize_changeset_uuid'] ) ) { // phpcs:ignore
			return true;
		}

		if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( stripslashes( $_SERVER['REQUEST_URI'] ), '/wp-json/' ) !== false ) { // WPCS: sanitization ok.
			return true;
		}

		if ( false === do_cacheaction( 'wp_supercache_remove_cookies', true ) ) {
			return true;
		}

		$this->removed_cookies = array();
		foreach ( WP_Super_Cache_User::instance()->get_auth_cookies() as $cookie ) {

			$cookies = is_array( $cookie ) ? $cookie : array( $cookie );

			foreach ( $cookies as $cookie_key ) {
				unset( $_COOKIE[ $cookie_key ] );
				$this->removed_cookies[] = $cookie_key;
			}
		}

		if ( ! empty( $this->removed_cookies ) ) {
			wp_cache_debug( 'Removing auth from $_COOKIE to allow caching for logged in user ( ' . implode( ', ', $this->removed_cookies ) . ' )' );
		}
	}


	/**
	 * Return true if in wp-admin or other admin non cacheable page.
	 *
	 * @since  2.0
	 * @return bool
	 */
	public function is_backend() {
		static $is_backend;

		if ( isset( $is_backend ) ) {
			return $is_backend;
		}

		$is_backend = is_admin();
		if ( $is_backend ) {
			return $is_backend;
		}

		$script = isset( $_SERVER['PHP_SELF'] ) ? basename( $_SERVER['PHP_SELF'] ) : ''; // phpcs:ignore
		if ( 'index.php' !== $script ) {
			if ( in_array( $script, array( 'wp-login.php', 'xmlrpc.php', 'wp-cron.php' ), true ) ) {
				$is_backend = true;
			} elseif ( defined( 'DOING_CRON' ) && DOING_CRON ) {
				$is_backend = true;
			} elseif ( 'cli' === PHP_SAPI || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
				$is_backend = true;
			}
		}

		return $is_backend;
	}

	/**
	 * Check if url is on the rejected list.
	 *
	 * @param string $url the url to be checked.
	 * @since  2.0
	 */
	public function url_is_rejected( $url ) {
		$auto_rejected = array( '/wp-admin/', 'xmlrpc.php', 'wp-app.php' );
		foreach ( $auto_rejected as $u ) {
			if ( strstr( $url, $u ) ) {
				return true; // we don't allow caching of wp-admin for security reasons.
			}
		}

		if ( false === is_array( $this->config->config['cache_rejected_uri'] ) ) {
			return false;
		}
		foreach ( $this->config->config['cache_rejected_uri'] as $expr ) {
			if ( '' !== $expr && @preg_match( "~$expr~", $uri ) ) { // phpcs:ignore
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if user agent is on the rejected list.
	 *
	 * @since  2.0
	 */
	public function is_user_agent_rejected() {
		if ( empty( $this->config->config['cache_rejected_user_agent'] ) || ! is_array( $this->config->config['cache_rejected_user_agent'] ) ) {
			return false;
		}

		$headers = apache_request_headers();
		if ( empty( $headers['User-Agent'] ) ) {
			return false;
		}

		foreach ( $this->config->config['cache_rejected_user_agent'] as $user_agent ) {
			if ( ! empty( $user_agent ) && stristr( $headers['User-Agent'], $user_agent ) ) {
				wp_cache_debug( 'is_user_agent_rejected: found user-agent in list: ' . $headers['User-Agent'] );
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if we can cache this page. Runs before caching.
	 *
	 * @since  2.0
	 */
	private function pre_cache_checks() {
		global $wp_super_cache_request_uri;

		$cache_this_page = true;

		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) ) {
			$_SERVER['REQUEST_METHOD'] = 'POST';
		}

		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$_SERVER['HTTP_USER_AGENT'] = '';
		}

		if ( defined( 'DONOTCACHEPAGE' ) ) {
			wp_cache_debug( 'DONOTCACHEPAGE defined. Caching disabled.' );
			$cache_this_page = false;
		} elseif ( $this->config->config['wp_cache_no_cache_for_get'] && ! empty( $_GET ) ) { // phpcs:ignore
			wp_cache_debug( 'Non empty GET request. Caching disabled on settings page. ' . wpsc_dump_get_request() );
			$cache_this_page = false;
		} elseif ( 'POST' === $_SERVER['REQUEST_METHOD'] || ! empty( $_POST ) ) { // phpcs:ignore
			wp_cache_debug( 'Not caching POST request.' );
			$cache_this_page = false;
		} elseif ( 'PUT' === $_SERVER['REQUEST_METHOD'] ) {
			wp_cache_debug( 'Not caching PUT request.' );
			$cache_this_page = false;
		} elseif ( 'DELETE' === $_SERVER['REQUEST_METHOD'] ) {
			wp_cache_debug( 'Not caching DELETE request.' );
			$cache_this_page = false;
		} elseif ( isset( $_GET['preview'] ) ) { // phpcs:ignore
			wp_cache_debug( 'Not caching preview post.' );
			$cache_this_page = false;
		} elseif ( ! in_array( $script, (array) $this->config->config['cache_acceptable_files'], true ) && $this->url_is_rejected( $wp_super_cache_request_uri ) ) {
			wp_cache_debug( 'URI rejected. Not Caching' );
			$cache_this_page = false;
		} elseif ( $this->is_user_agent_rejected() ) {
			wp_cache_debug( 'USER AGENT (' . esc_html( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) . ') rejected. Not Caching' );
			$cache_this_page = false;
		}

		return $cache_this_page;
	}

	/**
	 * Check if we can cache this page. Uses functions only available after WordPress is loaded.
	 *
	 * @since  2.0
	 */
	private function post_cache_checks() {

		$cache_this_page = true;
		$query_vars      = WP_Super_cache_File_Cache::instance()->get_query_vars();

		if ( isset( $this->config->config['wp_cache_pages']['single'] ) && 1 === $this->config->config['wp_cache_pages']['single'] && isset( $query_vars['is_single'] ) ) {
			wp_cache_debug( 'Not caching single post.' );
			$cache_this_page = false;
		} elseif ( isset( $this->config->config['wp_cache_pages']['pages'] ) && 1 === $this->config->config['wp_cache_pages']['pages'] && isset( $query_vars['is_page'] ) ) {
			wp_cache_debug( 'Not caching single page.' );
			$cache_this_page = false;
		} elseif ( isset( $this->config->config['wp_cache_pages']['archives'] ) && 1 === $this->config->config['wp_cache_pages']['archives'] && isset( $query_vars['is_archive'] ) ) {
			wp_cache_debug( 'Not caching archive page.' );
			$cache_this_page = false;
		} elseif ( isset( $this->config->config['wp_cache_pages']['tag'] ) && 1 === $this->config->config['wp_cache_pages']['tag'] && isset( $query_vars['is_tag'] ) ) {
			wp_cache_debug( 'Not caching tag page.' );
			$cache_this_page = false;
		} elseif ( isset( $this->config->config['wp_cache_pages']['category'] ) && 1 === $this->config->config['wp_cache_pages']['category'] && isset( $query_vars['is_category'] ) ) {
			wp_cache_debug( 'Not caching category page.' );
			$cache_this_page = false;
		} elseif ( isset( $this->config->config['wp_cache_pages']['frontpage'] ) && 1 === $this->config->config['wp_cache_pages']['frontpage'] && isset( $query_vars['is_front_page'] ) ) {
			wp_cache_debug( 'Not caching front page.' );
			$cache_this_page = false;
		} elseif ( isset( $this->config->config['wp_cache_pages']['home'] ) && 1 === $this->config->config['wp_cache_pages']['home'] && isset( $query_vars['is_home'] ) ) {
			wp_cache_debug( 'Not caching home page.' );
			$cache_this_page = false;
		} elseif ( isset( $this->config->config['wp_cache_pages']['search'] ) && 1 === $this->config->config['wp_cache_pages']['search'] && isset( $query_vars['is_search'] ) ) {
			wp_cache_debug( 'Not caching search page.' );
			$cache_this_page = false;
		} elseif ( isset( $this->config->config['wp_cache_pages']['author'] ) && 1 === $this->config->config['wp_cache_pages']['author'] && isset( $query_vars['is_author'] ) ) {
			wp_cache_debug( 'Not caching author page.' );
			$cache_this_page = false;
		} elseif ( isset( $this->config->config['wp_cache_pages']['feed'] ) && 1 === $this->config->config['wp_cache_pages']['feed'] && isset( $query_vars['is_feed'] ) ) {
			wp_cache_debug( 'Not caching feed.' );
			$cache_this_page = false;
		} elseif ( isset( $query_vars['is_rest'] ) ) {
			wp_cache_debug( 'REST API detected. Caching disabled.' );
			$cache_this_page = false;
		} elseif ( isset( $query_vars['is_robots'] ) ) {
			wp_cache_debug( 'robots.txt detected. Caching disabled.' );
			$cache_this_page = false;
		} elseif ( isset( $query_vars['is_redirect'] ) ) {
			wp_cache_debug( 'Redirect detected. Caching disabled.' );
			$cache_this_page = false;
		} elseif ( isset( $query_vars['is_304'] ) ) {
			wp_cache_debug( 'HTTP 304 (Not Modified) sent. Caching disabled.' );
			$cache_this_page = false;
		} elseif ( empty( $query_vars ) && apply_filters( 'wpsc_only_cache_known_pages', 1 ) ) {
			wp_cache_debug( 'ob_handler: query_vars is empty. Not caching unknown page type. Return 0 to the wpsc_only_cache_known_pages filter to cache this page.' );
			$cache_this_page = false;
		} elseif ( Wp_Super_Cache_User::instance()->is_caching_disabled() ) {
			wp_cache_debug( 'ob_handler: Caching disabled for known user. User logged in or cookie found.' );
			$cache_this_page = false;
		}

		return $cache_this_page;
	}


	/**
	 * Return an instance of the current class, create one if it doesn't exist
	 *
	 * @since  2.0
	 * @return Wp_Super_Cache_Page
	 */
	public static function instance() {

		static $instance;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

}
