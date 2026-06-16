<?php
/**
 * Bootstrap.
 *
 * @package automattic/wp-super-cache
 */

// apply_filters() is a WP core function not defined in wp-cache-phase2.php;
// stub it here so test files can require that file without a full WP bootstrap.
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		return $value;
	}
}

/**
 * Include the composer autoloader.
 */
require_once __DIR__ . '/../../vendor/autoload.php';
