<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
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
	<h1><?php esc_html_e( 'WP Super Cache Settings', 'simple-cache' ); ?></h1>
	<form action="" method="post">
		<?php wp_nonce_field( 'wp-super-cache_update_settings', 'wp-super-cache_settings_nonce' ); ?>
		<input type='checkbox' name='caching' value='1' <?php echo checked( $config['caching'] ); ?> /> <?php esc_html_e( 'Enable Caching', 'wp-super-cache' ); ?>
		<input type="hidden" name="action" value="wp-super-cache_update" />
		<p class="submit">
			<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_html_e( 'Save Changes', 'simple-cache' ); ?>">
		</p>
	</form>
</div>
