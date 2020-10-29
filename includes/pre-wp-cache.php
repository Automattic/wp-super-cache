<?php
/**
 *
 * Check if the plugin should cache the current page and then create the
 * output buffer for the cache file.
 *
 * @link       https://automattic.com/
 * @since      2.0.0
 *
 * @package    Wp_Super_Cache
 * @subpackage Wp_Super_Cache/includes
 */

ob_start( 'wp_super_cache_create_cache' );
