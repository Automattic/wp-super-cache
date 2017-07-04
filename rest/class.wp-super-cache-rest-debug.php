<?php

class WP_Super_Cache_Rest_Debug extends WP_REST_Controller {

	/**
	 * Update the cache settings.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function callback( $request ) {
		global $cache_path, $wp_cache_debug_log, $wp_cache_debug_username;

		$parameters = $request->get_json_params();

		$settings = array ( 
			'wp_super_cache_debug',
			'wp_cache_debug_log',
			'wp_cache_debug_ip',
			'wp_super_cache_comments',
			'wp_super_cache_front_page_check',
			'wp_super_cache_front_page_clear',
			'wp_super_cache_front_page_text',
			'wp_super_cache_front_page_notification',
			'wpsc_delete_log',
			'wpsc_disable_log',
			'wpsc_reset_log',
		);

		foreach( $settings as $setting ) {
			if ( isset( $parameters[ $setting ] ) ) {
				if ( $parameters[ $setting ] != false ) {
					$_POST[ $setting ] = $parameters[ $setting ];
				}
				$_POST[ 'wp_cache_debug' ] = 1;
			} else {
				global $$setting;
				$_POST[ $setting ] = $$setting;
			}
		}
		global $valid_nonce;
		$valid_nonce = true;

		$settings = wpsc_update_debug_settings();
		return( rest_ensure_response( array( 'updated' => true, "settings" => $settings ) ) );
	}
}
