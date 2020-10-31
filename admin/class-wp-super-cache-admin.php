<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://automattic.com/
 * @since      2.0.0
 *
 * @package    Wp_Super_Cache
 * @subpackage Wp_Super_Cache/admin
 */

/**
 * The class responsible for setting up required files for caching.
 */
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wp-super-cache-setup.php';


/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Wp_Super_Cache
 * @subpackage Wp_Super_Cache/admin
 * @author     Automattic <doc.wp-super-cache@ocaoimh.ie>
 */
class Wp_Super_Cache_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Configuration object
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      object $config.
	 */
	private $config;

	/**
	 * Setup object
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      object $config.
	 */
	private $setup;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    2.0.0
	 * @param    string $plugin_name       The name of this plugin.
	 * @param    string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->config      = Wp_Super_cache_Config::instance();
		$this->setup       = Wp_Super_cache_Setup::instance();
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    2.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Wp_Super_Cache_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Wp_Super_Cache_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wp-super-cache-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    2.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Wp_Super_Cache_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Wp_Super_Cache_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wp-super-cache-admin.js', array( 'jquery' ), $this->version, false );

	}

	/**
	 * Output network setting menu option
	 *
	 * @since  1.7
	 */
	public function network_admin_menu() {
		add_submenu_page( 'settings.php', esc_html__( 'WP Super Cache', 'wp-super-cache' ), esc_html__( 'WP Super Cache', 'wp-super-cache' ), 'manage_options', 'wp-super-cache', array( $this, 'screen_options' ) );
	}

	/**
	 * Add options page
	 *
	 * @since 1.0
	 */
	public function action_admin_menu() {
		add_submenu_page( 'options-general.php', esc_html__( 'WP Super Cache', 'wp-super-cache' ), esc_html__( 'WP Super Cache', 'wp-super-cache' ), 'manage_options', 'wp-super-cache', array( $this, 'screen_options' ) );
	}

	/**
	 * Add purge cache button to admin bar
	 *
	 * @since 1,3
	 */
	public function admin_bar_menu() {
		global $wp_admin_bar;

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ( is_singular() || is_archive() || is_front_page() || is_search() ) && current_user_can( 'delete_others_posts' ) ) {
			$site_regex = preg_quote( rtrim( (string) wp_parse_url( get_option( 'home' ), PHP_URL_PATH ), '/' ), '`' );
			$req_uri    = preg_replace( '/[ <>\'\"\r\n\t\(\)]/', '', $_SERVER[ 'REQUEST_URI' ] ); // phpcs:ignore
			$path       = preg_replace( '`^' . $site_regex . '`', '', $req_uri );

			$wp_admin_bar->add_menu(
				array(
					'parent' => '',
					'id'     => 'delete-cache',
					'title'  => __( 'Delete Cache', 'wp-super-cache' ),
					'meta'   => array( 'title' => __( 'Delete cache of the current page', 'wp-super-cache' ) ),
					'href'   => wp_nonce_url( admin_url( 'index.php?action=delcachepage&path=' . rawurlencode( $path ) ), 'delete-cache' ),
				)
			);
		}
	}

	/**
	 * Output settings
	 *
	 * @since 1.0
	 */
	public function screen_options() {
		$config = $this->config->get();
		// TODO - setup screen to create cache dir.
		$setup_done = true;
		if ( ! $this->setup->is_wp_cache_constant_defined() ) {
			$setup_done = $this->setup->add_wp_cache_constant();
		}

		if ( ! $this->setup->is_wpcachehome_constant_defined() ) {
			$setup_done = $this->setup->add_wpcachehome_constant();
		}

		if ( $setup_done && ! $this->setup->advanced_cache_exists() ) {
			$setup_done = $this->setup->create_advanced_cache();
		}

		if ( $setup_done && ! $this->setup->plugin_config_exists() ) {
			$setup_done = $this->setup->create_config_file();
		}

		if ( ! $setup_done ) {
			include_once 'partials/wp-super-cache-admin-setup.php';
		} else {
			include_once 'partials/wp-super-cache-admin-screen.php';
		}
	}

	/**
	 * Update settings
	 *
	 * @since 2.0
	 */
	public function update() {
		if ( ! isset( $_GET['page'] ) || 'wp-super-cache' !== $_GET['page'] ) {
			return false;
		}

		if ( ! isset( $_POST['wp-super-cache_settings_nonce'] ) ) {
			return false;
		}

		if (
			! wp_verify_nonce(
				sanitize_key( $_POST['wp-super-cache_settings_nonce'] ),
				'wp-super-cache_update_settings'
			)
		) {
			return false;
		}

		// TODO - switch to update settings for various pages.

		$this->config->update_setting(
			'caching',
			isset( $_POST['caching'] ) ? 1 : 0
		);

		if ( false === isset( $this->config->config['cache_page_secret'] ) ) {
			$cache_page_secret = md5( gmdate( 'H:i:s' ) . wp_rand() );
			$this->config->update_setting( 'cache_page_secret', $cache_page_secret );
		}

		if ( isset( $_POST['action'] ) && 'easysetup' === $_POST['action'] ) {
			$_POST['action'] = 'scupdates';
			if ( isset( $_POST['wp_cache_easy_on'] ) && 1 === $_POST['wp_cache_easy_on'] ) {
				$_POST['wp_cache_enabled']    = 1;
				$_POST['super_cache_enabled'] = 1;
				$_POST['cache_rebuild_files'] = 1;
				unset( $_POST['cache_compression'] );
				if ( WP_CONTENT_DIR . '/cache/' !== $cache_path ) {
					$_POST['wp_cache_location'] = $cache_path;
				}

				// set up garbage collection with some default settings.
				if ( ( ! isset( $wp_cache_shutdown_gc ) || 0 === $wp_cache_shutdown_gc ) && false === wp_next_scheduled( 'wp_cache_gc' ) ) {
					if ( false === isset( $cache_schedule_type ) ) {
						$cache_schedule_type = 'interval';
						$cache_time_interval = 600;
						$cache_max_time      = 1800;
						$this->config->update_setting( 'cache_schedule_type', $cache_schedule_type );
						$this->config->update_setting( 'cache_time_interval', $cache_time_interval );
						$this->config->update_setting( 'cache_max_time', $cache_max_time );
					}
					wp_schedule_single_event( time() + 600, 'wp_cache_gc' );
				}
			} else {
				unset( $_POST['wp_cache_enabled'] );
				wp_clear_scheduled_hook( 'wp_cache_check_site_hook' );
				wp_clear_scheduled_hook( 'wp_cache_gc' );
				wp_clear_scheduled_hook( 'wp_cache_gc_watcher' );
			}
			$advanced_settings = array( 'wp_super_cache_late_init', 'wp_cache_disable_utf8', 'wp_cache_no_cache_for_get', 'wp_supercache_304', 'wp_cache_mfunc_enabled', 'wp_cache_front_page_checks', 'wp_supercache_cache_list', 'wp_cache_clear_on_post_edit', 'wp_cache_make_known_anon', 'wp_cache_refresh_single_only', 'cache_compression' );
			foreach ( $advanced_settings as $setting ) {
				if ( isset( $GLOBALS[ $setting ] ) && 1 === $GLOBALS[ $setting ] ) {
					$_POST[ $setting ] = 1;
				}
			}
			$_POST['wp_cache_not_logged_in'] = 2;
		}

	}

	/**
	 * Check if user can use admin page.
	 *
	 * @since 1.0
	 */
	private function is_super_admin() {
		global $wp_version;

		if ( version_compare( $wp_version, '4.8', '>=' ) ) {
			return current_user_can( 'setup_network' );
		}

		return is_super_admin();
	}

}
