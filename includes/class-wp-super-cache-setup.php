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
	 * Initialize the setup
	 *
	 * @since    2.0.0
	 */
	public function __construct() {
		$this->advanced_cache_filename = untrailingslashit( WP_CONTENT_DIR ) . '/advanced-cache.php';
		$this->config                  = Wp_Super_cache_Config::instance();

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
		$code = '<?php' .
		"\r\n" . '// WP SUPER CACHE 1.2' .
		"\r\n" . 'function wpcache_broken_message() {' .
		"\r\n" . '	global $wp_cache_config_file;' .
		"\r\n" . '	if ( isset( $wp_cache_config_file ) == false ) {' .
		"\r\n" . '		return \'\';' .
		"\r\n" . '	}' .
		"\r\n" . '' .
		"\r\n" . '	$doing_ajax     = defined( \'DOING_AJAX\' ) && DOING_AJAX;' .
		"\r\n" . '	$xmlrpc_request = defined( \'XMLRPC_REQUEST\' ) && XMLRPC_REQUEST;' .
		"\r\n" . '	$rest_request   = defined( \'REST_REQUEST\' ) && REST_REQUEST;' .
		"\r\n" . '	$robots_request = strpos( $_SERVER[\'REQUEST_URI\'], \'robots.txt\' ) != false;' .
		"\r\n" . '' .
		"\r\n" . '	$skip_output = ( $doing_ajax || $xmlrpc_request || $rest_request || $robots_request );' .
		"\r\n" . '	if ( false == strpos( $_SERVER[\'REQUEST_URI\'], \'wp-admin\' ) && ! $skip_output ) {' .
		"\r\n" . '		echo \'<!-- WP Super Cache is installed but broken. The constant WPCACHEHOME must be set in the file wp-config.php and point at the WP Super Cache plugin directory. -->\';' .
		"\r\n" . '	}' .
		"\r\n" . '}' .
		"\r\n" . '' .
		"\r\n" . 'defined( \'ABSPATH\' ) || exit;' .
		"\r\n" . 'if ( is_admin() ) {' .
		"\r\n" . '	return;' .
		"\r\n" . '}' .
		"\r\n" . '' .
		"\r\n" . 'if ( false == defined( \'WPCACHEHOME\' ) ) {' .
		"\r\n" . '	define( \'ADVANCEDCACHEPROBLEM\', 1 );' .
		"\r\n" . '} elseif ( ! file_exists( WPCACHEHOME . \'includes/pre-wp-functions.php\' ) ) {' .
		"\r\n" . '		define( \'ADVANCEDCACHEPROBLEM\', 1 );' .
		"\r\n" . '}' .
		"\r\n" . 'if ( defined( \'ADVANCEDCACHEPROBLEM\' ) ) {' .
		"\r\n" . '	register_shutdown_function( \'wpcache_broken_message\' );' .
		"\r\n" . '	exit;' .
		"\r\n" . '}' .
		"\r\n" . 'include_once WPCACHEHOME . \'/includes/pre-wp-functions.php\';' .
		"\r\n" . 'include_once WPCACHEHOME . \'/includes/pre-wp-cache.php\';';
		// phpcs:enable

		if ( ! file_put_contents( $this->advanced_cache_filename, $code ) ) {
			return false;
		} else {
			return true;
		}

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
	 * Check if WP_CACHE defined in wp-config.php
	 *
	 * @since    2.0.0
	 */
	public function is_wp_cache_constant_defined() {
		if ( ! defined( 'WP_CACHE' ) ) {
			return false;
		}
		if ( ! strpos( file_get_contents( $this->config_filename ), 'WP_CACHE' ) ) {
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
