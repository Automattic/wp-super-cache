<?php

class WP_Super_Cache_Rest_Delete_Cache extends WP_REST_Controller {

	/**
	 * Get a collection of items
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function callback( $request ) {
		$params = $request->get_json_params();

		if ( isset( $params['id'] ) && is_numeric( $params['id'] ) ) {
			wpsc_delete_post_cache( $params['id'] );

		} elseif ( !empty( $params['expired'] ) ) {
			global $file_prefix;
			wp_cache_clean_expired( $file_prefix );

		} elseif ( isset( $params['url'] ) ) {
			global $cache_path;

			// This is the one caller that hands the helpers raw JSON, so the value is
			// checked here rather than trusted. An empty result would build the site's
			// supercache root, which wpsc_delete_files() protects, but it is clearer to
			// refuse than to rely on that.
			$url = wpsc_sanitize_cache_path( $params['url'] );
			if ( '' === $url ) {
				return new WP_Error( 'invalid_url', __( 'Not a path this cache can delete.', 'wp-super-cache' ), array( 'status' => 400 ) );
			}

			// The caller sends whatever spelling it has, but the directory on disk
			// is lowercase with the percent escapes uppercased. See #1081.
			$directory = $cache_path . 'supercache/' . wpsc_normalize_uri_case( $url );
			wpsc_delete_files( $directory );
			prune_super_cache( $directory . '/page', true );

		} else {
			global $file_prefix;
			wp_cache_clean_cache( $file_prefix, !empty( $params['all'] ) );
		}

		return rest_ensure_response( array( 'Cache Cleared' => true ) );
	}
}
