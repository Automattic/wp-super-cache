<?php
/**
 * File containing the class Wp_Super_Cache_Setup
 *
 * @package wp-super-cache
 *
 * @since   2.0.0
 */

/**
 * Setup the cache, advanced-cache.php, WP_CACHE constant.
 *
 * @since 2.0.0
 */
class Wp_Super_Cache_Setup {

	/**
	 * Configuration object
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      object $config.
	 */
	private $config;

	/**
	 * Configuration filename
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      string $config_filename
	 */
	private $config_filename;

	/**
	 * Filename of advanced-cache.php
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      string $advanced_cache_filename
	 */
	private $advanced_cache_filename;

	/**
	 * Filename of plugin config file.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      string $advanced_cache_filename
	 */
	private $plugin_config_filename;

	/**
	 * Initialize the setup
	 *
	 * @since    2.0.0
	 */
	public function __construct() {
		$this->advanced_cache_filename = untrailingslashit( WP_CONTENT_DIR ) . '/advanced-cache.php';
		$this->plugin_config_filename  = untrailingslashit( WP_CONTENT_DIR ) . '/wp-cache-config.php';
		$this->config                  = Wp_Super_Cache_Config::instance();

		if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
			$this->config_filename = ABSPATH . 'wp-config.php';
		} else {
			$this->config_filename = dirname( ABSPATH ) . '/wp-config.php';
		}

	}

	/**
	 * Create WP_CONTENT/advanced_cache.php
	 *
	 * @since    2.0.0
	 */
	public function create_advanced_cache() {
		// Old plugin loads wp-cache-phase1.php, we load includes/pre-wp-functions.php includes/pre-wp-cache.php.

		// phpcs:disable
		$code = <<<ADVANCEDCACHE
<?php
// WP SUPER CACHE 1.2
function wpcache_broken_message() {
	global \$wp_cache_config_file;
	if ( isset( \$wp_cache_config_file ) == false ) {
		return '';
	}

	\$doing_ajax     = defined( 'DOING_AJAX' ) && DOING_AJAX;
	\$xmlrpc_request = defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST;
	\$rest_request   = defined( 'REST_REQUEST' ) && REST_REQUEST;
	\$robots_request = strpos( \$_SERVER['REQUEST_URI'], 'robots.txt' ) !== false;

	\$skip_output = ( \$doing_ajax || \$xmlrpc_request || \$rest_request || \$robots_request );

	if ( false === strpos( \$_SERVER['REQUEST_URI'], 'wp-admin' ) && ! \$skip_output ) {
		echo '<!-- WP Super Cache is installed but broken. The constant WPCACHEHOME must be set in the file wp-config.php and point at the WP Super Cache plugin directory. -->';
	}
}

defined( 'ABSPATH' ) || exit;
if ( is_admin() ) {
	return;
}

if ( ! defined( "WPCACHEHOME" ) ) {
	define( "WPCACHEHOME", ABSPATH . "wp-content/plugins/wp-super-cache/" );
}

if ( false === defined( 'WPCACHEHOME' ) ) {
	define( 'ADVANCEDCACHEPROBLEM', 1 );
} elseif ( ! file_exists( WPCACHEHOME . 'includes/pre-wp-functions.php' ) ) {
		define( 'ADVANCEDCACHEPROBLEM', 1 );
}
if ( defined( 'ADVANCEDCACHEPROBLEM' ) ) {
	register_shutdown_function( 'wpcache_broken_message' );
	exit;
}
include_once WPCACHEHOME . '/includes/pre-wp-functions.php';
include_once WPCACHEHOME . '/includes/pre-wp-cache.php';
ADVANCEDCACHE;
		// phpcs:enable

		if ( ! file_put_contents( $this->advanced_cache_filename, $code ) ) { // phpcs:ignore
			return false;
		} else {
			return true;
		}

	}

	/**
	 * Create WP_CONTENT/wp-cache-config.php
	 *
	 * @since    2.0.0
	 */
	public function create_config_file() {
		$code = <<<CONFIGFILE
<?php
/*
WP Super Cache Config File

See https://wordpress.org/plugins/wp-super-cache/
*/

if ( ! defined('WPCACHEHOME') ) { define( 'WPCACHEHOME', WP_PLUGIN_DIR . '/wp-super-cache/' ); }

\$cache_compression = 0; // Super cache compression
\$cache_enabled = false;
\$super_cache_enabled = true;
\$cache_max_time = 3600; //in seconds
//\$use_flock = true; // Set it true or false if you know what to use
\$cache_path = WP_CONTENT_DIR . '/cache/';
\$file_prefix = 'wp-cache-';
\$ossdlcdn = 0;

// Array of files that have 'wp-' but should still be cached
\$cache_acceptable_files = array( 'wp-comments-popup.php', 'wp-links-opml.php', 'wp-locations.php' );

\$cache_rejected_uri = array('wp-.*\\.php', 'index\\.php');
\$cache_rejected_user_agent = array ( 0 => 'bot', 1 => 'ia_archive', 2 => 'slurp', 3 => 'crawl', 4 => 'spider', 5 => 'Yandex' );

\$cache_rebuild_files = 1;

// Disable the file locking system.
// If you are experiencing problems with clearing or creating cache files
// uncommenting this may help.
\$wp_cache_mutex_disabled = 1;

// Just modify it if you have conflicts with semaphores
\$sem_id = 5419;

if ( '/' != substr( \$cache_path, -1)) { \$cache_path .= '/'; }

\$wp_cache_mobile = 0;
\$wp_cache_mobile_whitelist = 'Stand Alone/QNws';
\$wp_cache_mobile_browsers = 'Android, 2.0 MMP, 240x320, AvantGo, BlackBerry, Blazer, Cellphone, Danger, DoCoMo, Elaine/3.0, EudoraWeb, hiptop, IEMobile, iPhone, iPod, KYOCERA/WX310K, LG/U990, MIDP-2.0, MMEF20, MOT-V, NetFront, Newt, Nintendo Wii, Nitro, Nokia, Opera Mini, Palm, Playstation Portable, portalmmm, Proxinet, ProxiNet, SHARP-TQ-GX10, Small, SonyEricsson, Symbian OS, SymbianOS, TS21i-10, UP.Browser, UP.Link, Windows CE, WinWAP';

// change to relocate the supercache plugins directory
\$wp_cache_plugins_dir = WPCACHEHOME . 'plugins';
// set to 1 to do garbage collection during normal process shutdown instead of wp-cron
\$wp_cache_shutdown_gc = 0;
\$wp_super_cache_late_init = 0;

// uncomment the next line to enable advanced debugging features
\$wp_super_cache_advanced_debug = 0;
\$wp_super_cache_front_page_text = '';
\$wp_super_cache_front_page_clear = 0;
\$wp_super_cache_front_page_check = 0;
\$wp_super_cache_front_page_notification = '0';

\$wp_cache_anon_only = 0;
\$wp_supercache_cache_list = 0;
\$wp_cache_debug_to_file = 0;
\$wp_super_cache_debug = 0;
\$wp_cache_debug_level = 5;
\$wp_cache_debug_ip = '';
\$wp_cache_debug_log = '';
\$wp_cache_debug_email = '';
\$wp_cache_pages['search'] = 0;
\$wp_cache_pages['feed'] = 0;
\$wp_cache_pages['category'] = 0;
\$wp_cache_pages['home'] = 0;
\$wp_cache_pages['frontpage'] = 0;
\$wp_cache_pages['tag'] = 0;
\$wp_cache_pages['archives'] = 0;
\$wp_cache_pages['pages'] = 0;
\$wp_cache_pages['single'] = 0;
\$wp_cache_pages['author'] = 0;
\$wp_cache_hide_donation = 0;
\$wp_cache_not_logged_in = 0;
\$wp_cache_clear_on_post_edit = 0;
\$wp_cache_hello_world = 0;
\$wp_cache_mobile_enabled = 0;
\$wp_cache_cron_check = 0;
\$wp_cache_mfunc_enabled = 0;
\$wp_cache_make_known_anon = 0;
\$wp_cache_refresh_single_only = 0;
\$wp_cache_mod_rewrite = 0;
\$wp_supercache_304 = 0;
\$wp_cache_front_page_checks = 0;
\$wp_cache_disable_utf8 = 0;
\$wp_cache_no_cache_for_get = 0;
\$cache_scheduled_time = "00:00";
\$wp_cache_preload_interval = 600;
\$cache_schedule_type = 'interval';
\$wp_cache_preload_posts = 0;
\$wp_cache_preload_on = 0;
\$wp_cache_preload_taxonomies = 0;
\$wp_cache_preload_email_me = 0;
\$wp_cache_preload_email_volume = 'none';
\$wp_cache_mobile_prefixes = '';
\$cached_direct_pages = array();
\$wpsc_served_header = false;
\$cache_gc_email_me = 0;
\$wpsc_save_headers = 0;
\$cache_schedule_interval = 'daily';
\$wp_super_cache_comments = 1;
\$wpsc_version = 169;

CONFIGFILE;
		if ( ! file_put_contents( $this->plugin_config_filename, $code ) ) { // phpcs:ignore
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Set the homepath setting
	 *
	 * @since    2.0.0
	 */
	public function set_home_path() {
		$home_path = wp_parse_url( site_url() );
		$home_path = trailingslashit( array_key_exists( 'path', $home_path ) ? $home_path['path'] : '' );

		if ( ! isset( $this->config->config['wp_cache_home_path'] ) ) {
			$this->config->update_setting( 'wp_cache_home_path', '/' );
		}

		if ( "$home_path" !== "$wp_cache_home_path" ) {
			$this->config->update_setting( 'wp_cache_home_path', $home_path );
		}

		return true;
	}


	/**
	 * Add WP_CACHE to wp-config.php
	 *
	 * @since    2.0.0
	 */
	public function add_wp_cache_constant() {
		$line = "define( 'WP_CACHE', true );";
		if ( ! defined( 'WP_CACHE' ) ) {
			define( 'WP_CACHE', true );
		}
		return $this->config->replace_line_in_file( 'define *\( *\'WP_CACHE\'', $line, $this->config_filename );
	}

	/**
	 * Add WPCACHEHOME to wp-config.php
	 *
	 * @since    2.0.0
	 */
	public function add_wpcachehome_constant() {
		if ( ! defined( 'WPCACHEHOME' ) ) {
			define( 'WPCACHEHOME', trailingslashit( dirname( __FILE__ ) ) );
		}
		$line = "define( 'WPCACHEHOME', '" . trailingslashit( dirname( dirname( __FILE__ ) ) ) . "' );";
		return $this->config->replace_line_in_file( 'define *\( *\'WPCACHEHOME\'', $line, $this->config_filename );
	}

	/**
	 * Check if WP_CACHE defined in wp-config.php
	 *
	 * @since    2.0.0
	 */
	public function is_wp_cache_constant_defined() {
		if ( ! defined( 'WP_CACHE' ) ) {
			return false;
		}
		if ( ! strpos( file_get_contents( $this->config_filename ), 'WP_CACHE' ) ) { // phpcs:ignore
			return false;
		}

		return true;
	}

	/**
	 * Check if WPCACHEHOME defined in wp-config.php
	 *
	 * @since    2.0.0
	 */
	public function is_wpcachehome_constant_defined() {
		if ( ! defined( 'WPCACHEHOME' ) ) {
			return false;
		}
		if ( ! strpos( file_get_contents( $this->config_filename ), 'WPCACHEHOME' ) ) { // phpcs:ignore
			return false;
		}

		return true;
	}

	/**
	 * Check if advanced-cache.php created.
	 *
	 * @since    2.0.0
	 */
	public function advanced_cache_exists() {
		if ( file_exists( $this->advanced_cache_filename ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Check if wp-cache-config.php created.
	 *
	 * @since    2.0.0
	 */
	public function plugin_config_exists() {
		if ( file_exists( $this->plugin_config_filename ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Return an instance of the current class, create one if it doesn't exist
	 *
	 * @since  2.0
	 * @return Wp_Super_Cache_Setup
	 */
	public static function instance() {

		static $instance;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}
}
