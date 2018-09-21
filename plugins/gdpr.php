<?php

global $cache_gdpr, $wp_cache_not_logged_in, $wp_cache_saved_not_logged_in;

if ( 1 === $cache_gdpr ) {
	add_cacheaction( 'wp_cache_get_cookies_values', 'wpsc_gdpr_get_cookies_values' );

	/**
	 * Forces caching (for "known" users) because it doesn't work if get_cookies returns non-empty string.
	 * PR #616 fixes this issue. It's temporary workaround for testing purpose.
	 */
	if ( ! is_admin() && isset( $_COOKIE['gdpr'] ) ) {
		$wp_cache_saved_not_logged_in = $wp_cache_not_logged_in;
		$wp_cache_not_logged_in       = 0;
	}
}

function wpsc_gdpr_actions() {
	global $cache_gdpr, $wp_cache_not_logged_in, $wp_cache_saved_not_logged_in;

	if ( 1 !== $cache_gdpr ) {
		return;
	}

	// Set previous value - caching for "know" users.
	if ( is_user_logged_in() && ! empty( $wp_cache_saved_not_logged_in ) ) {
		$wp_cache_not_logged_in = $wp_cache_saved_not_logged_in;
	}
}
add_cacheaction( 'add_cacheaction', 'wpsc_gdpr_actions' );

function wpsc_gdpr_get_cookies_values( $string ) {
	global $super_cache_enabled;

	// Extracts consent_types.
	if ( isset( $_COOKIE['gdpr']['consent_types'] ) ) {
		$cookie_consents = (array) json_decode( stripslashes( $_COOKIE['gdpr']['consent_types'] ) ); // WPCS: Input var ok, sanitization ok.

		if ( ! empty( $cookie_consents ) ) {
			$string             .= 'gdpr_consents_types=' . implode( '|', $cookie_consents );
			$super_cache_enabled = false; // Create only wp-cache file.
		}
	}

	// Extracts allowed_cookies.
	if ( isset( $_COOKIE['gdpr']['allowed_cookies'] ) ) {
		$allowed_cookies = (array) json_decode( stripslashes( $_COOKIE['gdpr']['allowed_cookies'] ) ); // WPCS: Input var ok, sanitization ok.

		if ( ! empty( $allowed_cookies ) ) {
			$string             .= 'gdpr_allowed_cookies =' . implode( '|', $allowed_cookies );
			$super_cache_enabled = false; // Create only wp-cache file.
		}
	}

	return $string;
}

function wp_supercache_gdpr_admin() {
	global $cache_gdpr, $wp_cache_config_file, $valid_nonce;

	$requested_state = isset( $_POST['cache_gdpr'] ) ? (int) $_POST['cache_gdpr'] : null; // WPCS: CSRF ok.
	$cache_gdpr      = (int) $cache_gdpr;

	$changed = false;
	if ( null !== $requested_state && $valid_nonce ) {
		$cache_gdpr = $requested_state;

		wp_cache_replace_line( '^\s*\$cache_gdpr\s*=', '$cache_gdpr = ' . intval( $cache_gdpr ) . ';', $wp_cache_config_file );
		$changed = true;
	}

	$id = 'gdpr-section';
	?>
	<fieldset id="<?php echo esc_attr( $id ); ?>" class="options">

		<h4><?php esc_html_e( 'GDPR', 'wp-super-cache' ); ?></h4>

		<form name="wp_manager" action="" method="post">
		<label><input type="radio" name="cache_gdpr" value="1" <?php checked( $cache_gdpr ); ?>/> <?php esc_html_e( 'Enabled', 'wp-super-cache' ); ?></label>
		<label><input type="radio" name="cache_gdpr" value="0" <?php checked( ! $cache_gdpr ); ?>/> <?php esc_html_e( 'Disabled', 'wp-super-cache' ); ?></label>
		<?php
		echo '<p>' . esc_html__( 'Provides support for GDPR plugin', 'wp-super-cache' ) . '</p>';

		if ( $changed ) {
			echo '<p><strong>' . sprintf(
				esc_html__( 'GDPR support is now %s', 'wp-super-cache' ),
				esc_html( $cache_gdpr ? __( 'enabled', 'wp-super-cache' ) : __( 'disabled', 'wp-super-cache' ) )
			) . '</strong></p>';
		}

		echo '<div class="submit"><input class="button-primary" ' . SUBMITDISABLED . 'type="submit" value="' . esc_html__( 'Update', 'wp-super-cache' ) . '" /></div>';
		wp_nonce_field( 'wp-cache' );
		?>
		</form>

	</fieldset>
	<?php
}
add_cacheaction( 'cache_admin_page', 'wp_supercache_gdpr_admin' );

function wpsc_gdpr_list( $list ) {
	$list['gdpr'] = array(
		'key'   => 'gdpr',
		'url'   => 'https://wordpress.org/plugins/gdpr/',
		'title' => esc_html__( 'GDPR', 'wp-super-cache' ),
		'desc'  => esc_html__( 'Provides support for GDPR plugin', 'wp-super-cache' ),
	);
	return $list;
}
add_cacheaction( 'wpsc_filter_list', 'wpsc_gdpr_list' );
