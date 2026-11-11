<?php
/**
 * WordPress test-suite configuration for the integration tier.
 *
 * Loaded by wp-phpunit when WP_PHPUNIT__TESTS_CONFIG points here (set by the
 * `make test-integration` target). Database settings come from the environment
 * so this works against the wp-env database; the fallbacks are the wp-env
 * defaults for a developer running it directly.
 *
 * WARNING: the WordPress test suite installs and DROPS tables using $table_prefix
 * on DB_NAME. It is pointed at a throwaway test database with a dedicated prefix
 * — never point it at a database you care about.
 *
 * @package automattic/wp-super-cache
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress core constants.

/**
 * Read an environment variable, returning a default when it is not set.
 *
 * @param string $name    Environment variable name.
 * @param string $default Value to use when the variable is unset.
 * @return string
 */
if ( ! function_exists( 'wpsc_test_env' ) ) {
	function wpsc_test_env( $name, $default ) {
		$value = getenv( $name );
		return ( false === $value ) ? $default : $value;
	}
}

define( 'DB_NAME', wpsc_test_env( 'WP_PHPUNIT__DB_NAME', 'tests-wordpress' ) );
define( 'DB_USER', wpsc_test_env( 'WP_PHPUNIT__DB_USER', 'root' ) );
define( 'DB_PASSWORD', wpsc_test_env( 'WP_PHPUNIT__DB_PASSWORD', 'password' ) );
define( 'DB_HOST', wpsc_test_env( 'WP_PHPUNIT__DB_HOST', 'tests-mysql' ) );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

// A dedicated prefix so the suite never touches the live wp-env site tables.
// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'localhost' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'WP Super Cache Integration Tests' );

define( 'WP_PHP_BINARY', 'php' );

// Path to the WordPress core install. Inside the wp-env container this is the
// document root; override with WP_PHPUNIT__ABSPATH if your core lives elsewhere.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', wpsc_test_env( 'WP_PHPUNIT__ABSPATH', '/var/www/html/' ) );
}
