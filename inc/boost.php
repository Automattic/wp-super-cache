<?php
/**
 * Code to handle migration from WP Super Cache to Jetpack Boost.
 *
 * @package WP_Super_Cache
 */

// Minimum version of Jetpack Boost required for compatibility.
if ( ! defined( 'MINIMUM_BOOST_VERSION' ) ) {
	define( 'MINIMUM_BOOST_VERSION', '3.4.5' );
}

/**
 * Get shared configuration for each migration button.
 */
function wpsc_get_boost_migration_config() {
	return array(
		'install_url'  => wp_nonce_url( admin_url( 'update.php?action=install-plugin&plugin=jetpack-boost' ), 'install-plugin_jetpack-boost' ),
		'activate_url' => admin_url( 'plugins.php' ),
		'is_installed' => wpsc_is_boost_installed(),
	);
}

/**
 * Display an admin notice to install Jetpack Boost.
 */
function wpsc_jetpack_boost_notice() {
	if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'wpsupercache' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	// hide the admin notice if Jetpack Boost Cache is already used.
	if ( 'BOOST' === wpsc_identify_advanced_cache() ) {
		return;
	}

	// Don't show the banner if the banner has been dismissed.
	$is_dismissed = '1' === get_user_option( 'wpsc_dismissed_boost_admin_notice' );
	if ( $is_dismissed ) {
		return;
	}

	// Don't show the admin notice if Jetpack Boost is too old.
	if ( ! wpsc_is_boost_current() ) {
		return;
	}

	// Don't show the banner if Super Cache is using features that Boost doesn't support.
	if ( ! wpsc_is_boost_compatible() ) {
		return;
	}

	$config       = wpsc_get_boost_migration_config();
	$button_url   = $config['is_installed'] ? $config['activate_url'] : $config['install_url'];
	$button_class = $config['is_installed'] ? 'wpsc-activate-boost-button' : 'wpsc-install-boost-button';

	?>
	<div id="wpsc-notice-boost-migrate" class="notice boost-notice notice-success is-dismissible">
	<h3>
		<?php esc_html_e( 'Migrate to Jetpack Boost', 'wp-super-cache' ); ?>
	</h3>
	<p>
		<?php esc_html_e( 'Your WP Super Cache setup is compatible with Boost\'s new caching feature. Continue to cache as you currently do and enhance your site\'s speed using our highly-rated performance solutions.', 'wp-super-cache' ); ?>
	</p>

	<p>
		<div class="wpsc-boost-migration-error" style="display:none; color:red; margin-bottom: 20px;"></div>
		<a data-source='notice' class='wpsc-boost-migration-button button button-primary <?php echo esc_attr( $button_class ); ?>' href="<?php echo esc_url( $button_url ); ?>">
			<div class="spinner" style="display:none; margin-top: 8px"></div>
			<label><?php esc_html_e( 'Migrate now', 'wp-super-cache' ); ?></label>
		</a>
	</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'wpsc_jetpack_boost_notice' );

/**
 * Dismiss the migration admin notice by setting a user option flag.
 */
function wpsc_dismiss_boost_notice() {
	update_user_option( get_current_user_id(), 'wpsc_dismissed_boost_admin_notice', '1' );
}

/**
 * Handler called by AJAX to dismiss the admin notice.
 */
function wpsc_dismiss_boost_notice_ajax_handler() {
	check_ajax_referer( 'wpsc_dismiss_boost_notice', 'nonce' );
	wpsc_dismiss_boost_notice();
	wp_die();
}
add_action( 'wp_ajax_wpsc_dismiss_boost_notice', 'wpsc_dismiss_boost_notice_ajax_handler' );

/**
 * Dismiss the admin notice if the Jetpack Boost plugin is activated.
 */
function wpsc_dismiss_notice_on_activation() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	wpsc_dismiss_boost_notice();
}
add_action( 'wp_ajax_wpsc_activate_boost', 'wpsc_dismiss_notice_on_activation' );
/**
 * Add a notice to the settings page if the Jetpack Boost cache module is detected.
 * The notice contains instructions on how to disable the Boost Cache module.
 */
function wpsc_deactivate_boost_cache_notice() {
	global $wpsc_advanced_cache_filename;
	?>
	<div style="width: 50%" class="notice notice-error"><h2><?php esc_html_e( 'Warning! Jetpack Boost Cache Detected', 'wp-super-cache' ); ?></h2>
		<?php // translators: %s is the filename of the advanced-cache.php file ?>
		<p><?php printf( esc_html__( 'The file %s was created by the Jetpack Boost plugin.', 'wp-super-cache' ), esc_html( $wpsc_advanced_cache_filename ) ); ?></p>
		<p><?php esc_html_e( 'You can use Jetpack Boost and WP Super Cache at the same time but only if the Cache Site Pages module in Boost is disabled. To use WP Super Cache for caching:', 'wp-super-cache' ); ?></p>
		<ol>
			<?php // translators: %s is a html link to the Boost settings page ?>
			<li><?php printf( esc_html__( 'Deactivate the "Cache Site Pages" module of Jetpack Boost on the %s page.', 'wp-super-cache' ), '<a href="' . esc_url( admin_url( 'admin.php?page=jetpack-boost' ) ) . '">' . esc_html__( 'Boost Settings', 'wp-super-cache' ) . '</a>' ); ?></li>
			<li><?php esc_html_e( 'Reload this page to configure WP Super Cache.', 'wp-super-cache' ); ?></li>
			<li><?php esc_html_e( 'You can continue to use the other features of Jetpack Boost.', 'wp-super-cache' ); ?></li>
		</ol>
	</div>
	<?php
	set_transient( 'wpsc_boost_cache_notice_displayed', true, WEEK_IN_SECONDS );
}

/**
 * Tell Jetpack when the cache is moved from Jetpack Boost to WP Super Cache.
 */
function wpsc_track_move_from_boost() {
	if ( ! get_transient( 'wpsc_boost_cache_notice_displayed' ) ) {
		return;
	}
	delete_transient( 'wpsc_boost_cache_notice_displayed' );

	do_action( 'jb_cache_moved_to_wpsc' );
}
add_action( 'wpsc_created_advanced_cache', 'wpsc_track_move_from_boost' );

/**
 * Notify Jetpack Boost that Boost Cache will be used instead of WP Super Cache.
 *
 * @param string $source The source of the migration: 'notice', 'banner', 'try_button'.
 */
function wpsc_notify_migration_to_boost( $source ) {
	if ( ! in_array( $source, array( 'notice', 'banner', 'try_button' ), true ) ) {
		return;
	}
	set_transient( 'jb_cache_moved_to_boost', $source, WEEK_IN_SECONDS );
}

/**
 * Check if Jetpack Boost is compatible with WP Super Cache.
 *
 * @return bool
 */
function wpsc_is_boost_compatible() {
	if ( ! empty( $GLOBALS['wp_cache_mobile_enabled'] ) ) {
		return false;
	}

	if ( isset( $GLOBALS['wp_super_cache_late_init'] ) && $GLOBALS['wp_super_cache_late_init'] === 1 ) {
		return false;
	}

	if ( ! empty( $GLOBALS['wpsc_rejected_cookies'] ) ) {
		return false;
	}

	if ( isset( $GLOBALS['wp_cache_not_logged_in'] ) && $GLOBALS['wp_cache_not_logged_in'] !== 2 ) {
		return false;
	}

	if ( isset( $GLOBALS['wp_cache_preload_on'] ) && $GLOBALS['wp_cache_preload_on'] === 1 ) {
		return false;
	}

	if ( ! empty( $GLOBALS['wp_cache_no_cache_for_get'] ) ) {
		return false;
	}

	if ( ! empty( $GLOBALS['wpsc_save_headers'] ) ) {
		return false;
	}

	if ( isset( $GLOBALS['wp_cache_make_known_anon'] ) && $GLOBALS['wp_cache_make_known_anon'] === 1 ) {
		return false;
	}

	if ( ! empty( $GLOBALS['wp_cache_mfunc_enabled'] ) ) {
		return false;
	}

	if ( isset( $GLOBALS['wp_cache_clear_on_post_edit'] ) && $GLOBALS['wp_cache_clear_on_post_edit'] === 1 ) {
		return false;
	}

	if ( ! empty( $GLOBALS['wp_cache_front_page_checks'] ) ) {
		return false;
	}

	if ( is_array( $GLOBALS['wp_cache_pages'] ) && array_sum( $GLOBALS['wp_cache_pages'] ) ) {
		return false;
	}

	$default_cache_acceptable_files = array( 'wp-comments-popup.php', 'wp-links-opml.php', 'wp-locations.php' );
	if ( is_array( $GLOBALS['cache_acceptable_files'] ) && array_diff( $GLOBALS['cache_acceptable_files'], $default_cache_acceptable_files ) ) {
		return false;
	}

	$default_cache_rejected_uri = array( 'wp-.*\\.php', 'index\\.php' );
	if ( is_array( $GLOBALS['cache_rejected_uri'] ) && array_diff( $GLOBALS['cache_rejected_uri'], $default_cache_rejected_uri ) ) {
		return false;
	}

	if ( is_array( $GLOBALS['cache_rejected_user_agent'] ) && array_diff( $GLOBALS['cache_rejected_user_agent'], array( '' ) ) ) {
		return false;
	}

	return true;
}

/**
 * Check if the Jetpack Boost that is installed is current.
 *
 * @return bool True if Jetpack Boost is same as or newer than version 3.4.0
 */
function wpsc_is_boost_current() {
	if ( defined( 'JETPACK_BOOST_VERSION' ) ) {
		return version_compare( (string) JETPACK_BOOST_VERSION, MINIMUM_BOOST_VERSION, '>=' );
	} else {
		return true; // don't care if Boost is not installed
	}
}

/**
 * Check if Jetpack Boost has been installed.
 */
function wpsc_is_boost_installed() {
	$plugins = array_keys( get_plugins() );

	foreach ( $plugins as $plugin ) {
		if ( str_contains( $plugin, 'jetpack-boost/jetpack-boost.php' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Check if Jetpack Boost is active.
 */
function wpsc_is_boost_active() {
	return class_exists( '\Automattic\Jetpack_Boost\Jetpack_Boost' );
}

/**
 * Admin ajax action: hide the Boost Banner.
 */
function wpsc_hide_boost_banner() {
	check_ajax_referer( 'wpsc_dismiss_boost_banner', 'nonce' );
	update_user_option( get_current_user_id(), 'wpsc_dismissed_boost_banner', '1' );

	wp_die();
}
add_action( 'wp_ajax_wpsc-hide-boost-banner', 'wpsc_hide_boost_banner' );

/**
 * Admin ajax action: activate Jetpack Boost.
 */
function wpsc_ajax_activate_boost() {
	check_ajax_referer( 'activate-boost' );

	if ( ! isset( $_POST['source'] ) ) {
		wp_send_json_error( 'no source specified', null, JSON_UNESCAPED_SLASHES );
	}

	$source = sanitize_text_field( wp_unslash( $_POST['source'] ) );
	$result = activate_plugin( 'jetpack-boost/jetpack-boost.php' );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message(), null, JSON_UNESCAPED_SLASHES );
	}

	wpsc_notify_migration_to_boost( $source );

	wp_send_json_success( null, null, JSON_UNESCAPED_SLASHES );
}
add_action( 'wp_ajax_wpsc_activate_boost', 'wpsc_ajax_activate_boost' );

/**
 * Show a Jetpack Boost installation banner (unless dismissed or installed)
 */
function wpsc_jetpack_boost_install_banner() {
	if ( ! wpsc_is_boost_current() ) {
		return;
	}
	// Don't show the banner if Boost is installed, or the banner has been dismissed.
	$is_dismissed = '1' === get_user_option( 'wpsc_dismissed_boost_banner' );
	if ( wpsc_is_boost_active() || $is_dismissed ) {
		return;
	}

	$config       = wpsc_get_boost_migration_config();
	$button_url   = $config['is_installed'] ? $config['activate_url'] : $config['install_url'];
	$button_label = $config['is_installed'] ? __( 'Set up Jetpack Boost', 'wp-super-cache' ) : __( 'Install Jetpack Boost', 'wp-super-cache' );
	$button_class = $config['is_installed'] ? 'wpsc-activate-boost-button' : 'wpsc-install-boost-button';
	$plugin_url   = plugin_dir_url( dirname( __DIR__ ) . '/wp-cache.php' );

	?>
		<div class="wpsc-boost-banner">
			<div class="wpsc-boost-banner-inner">
				<div class="wpsc-boost-banner-content">
					<img style="width:282px" src="<?php echo esc_url( $plugin_url . '/assets/jetpack-logo.svg' ); ?>" height="36" />

					<h3>
						<?php esc_html_e( 'Speed up your site with our top&#8209;rated performance tool', 'wp-super-cache' ); ?>
					</h3>

					<p id="wpsc-install-invitation">
						<?php
							esc_html_e(
								'Caching is a great start, but there is more to maximize your site speed. Find out how much your cache speeds up your site and make it blazing fast with Jetpack Boost, the easiest WordPress speed optimization plugin developed by WP Super Cache engineers.',
								'wp-super-cache'
							);
						?>
					</p>

					<div class="wpsc-boost-migration-error" style="display:none; color:red; margin-bottom: 20px;"></div>

					<div style="display: flex; gap: 24px;">
						<a style="font-weight: 500; line-height: 1; padding: 10px 20px 15px;" data-source='banner' href="<?php echo esc_url( $button_url ); ?>" class="wpsc-boost-migration-button button button-primary <?php echo esc_attr( $button_class ); ?>">
							<div class='spinner' style='display:none; margin-top: 8px'></div>
							<label><?php echo esc_html( $button_label ); ?></label>
						</a>
						<a style="display: flex; align-items: center; font-weight: 500; color: #000; " href="https://jetpack.com/blog/discover-how-to-improve-your-site-performance-with-jetpack-boost/">
							Learn More
						</a>
					</div>
				</div>

				<div class="wpsc-boost-banner-image-container">
					<img
						width="350"
						height="452"
						src="<?php echo esc_url( $plugin_url . 'assets/boost-install-card-main.png' ); ?>"
						title="<?php esc_attr_e( 'Check how your web site performance scores for desktop and mobile.', 'wp-super-cache' ); ?>"
						alt="<?php esc_attr_e( 'An image showing the Jetpack Boost dashboard.', 'wp-super-cache' ); ?>"
						srcset="<?php echo esc_url( $plugin_url . 'assets/boost-install-card-main.png' ); ?> 400w, <?php echo esc_url( $plugin_url . 'assets/boost-install-card-main-2x.png' ); ?> 800w"
						sizes="(max-width: 782px) 350px, 700px"
					>
				</div>
			</div>

			<span class="wpsc-boost-dismiss dashicons dashicons-dismiss"></span>
		</div>
	<?php
}