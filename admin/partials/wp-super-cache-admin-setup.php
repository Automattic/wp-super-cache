<?php
/**
 * Provide a setup area view for the plugin
 *
 * This file is used when the necessary files for caching are not present.
 *
 * @link       https://automattic.com/
 * @since      2.0.0
 *
 * @package    Wp_Super_Cache
 * @subpackage Wp_Super_Cache/admin/partials
 */

?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<div class="wrap">
	<h1><?php esc_html_e( 'WP Super Cache Setup', 'wp-super-cache' ); ?></h1>
	<p><?php esc_html_e( 'The plugin is not configured yet. The following changes must be made to finish installation but the plugin could not do one or both of them.', 'wp-super-cache' ); ?></p>
	<p><ul>
	<ol><?php esc_html_e( 'The file wp-content/advanced-cache.php must be created.', 'wp-super-cache' ); ?></ol>
	<ol><?php esc_html_e( 'The constant WP_CACHE must be added to the WordPress wp-config.php', 'wp-super-cache' ); ?></ol>
	</ul>
	</p>
	<p><?php esc_html_e( 'Please examine the permissions on the wp-content directory or your wp-config.php', 'wp-super-cache' ); ?></p>
</div>
