<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://automattic.com/
 * @since             2.0.0
 * @package           Wp_Super_Cache
 *
 * @wordpress-plugin
 * Plugin Name:       WP Super Cache
 * Plugin URI:        https://github.com/Automattic/wp-super-cache
 * Description:       This is a short description of what the plugin does. It's displayed in the WordPress admin area.
 * Version:           2.0.0
 * Author:            Automattic
 * Author URI:        https://automattic.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-super-cache
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 2.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'WP_SUPER_CACHE_VERSION', '2.0.0' );

if ( is_multisite() && isset( $active_plugins[ plugin_basename( __FILE__ ) ] ) ) {
	define( 'WPSC_IS_NETWORK', true );
} else {
	define( 'WPSC_IS_NETWORK', false );
}
/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wp-super-cache-activator.php
 */
function activate_wp_super_cache() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-super-cache-activator.php';
	Wp_Super_Cache_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wp-super-cache-deactivator.php
 */
function deactivate_wp_super_cache() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-super-cache-deactivator.php';
	Wp_Super_Cache_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wp_super_cache' );
register_deactivation_hook( __FILE__, 'deactivate_wp_super_cache' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-wp-super-cache.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    2.0.0
 */
function run_wp_super_cache() {

	$plugin = new Wp_Super_Cache();
	$plugin->run();

}
run_wp_super_cache();
