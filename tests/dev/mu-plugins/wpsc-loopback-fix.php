<?php
/**
 * Plugin Name: WP Super Cache dev loopback fix
 *
 * The @wordpress/env container exposes WordPress on port 80 internally but
 * is mapped to http://localhost:8888 on the host. WordPress therefore stores
 * its site URL as http://localhost:8888, and any PHP code doing a loopback
 * HTTP request (WP Super Cache preloader, WP-Cron, REST self-calls, etc.)
 * will try to reach :8888 inside the container — where nothing is listening.
 *
 * This mu-plugin intercepts outgoing HTTP requests aimed at the host-side
 * URL and retries them against the docker-compose service hostname, so
 * loopback works the same way in Docker as it does on a normal host — both
 * when PHP runs inside the `wordpress` container (cron, preloader) and
 * when wp-cli triggers loopback from the separate `cli` container.
 *
 * @package WP_Super_Cache
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

add_filter(
	'pre_http_request',
	function ( $preempt, $args, $url ) {
		static $rewriting = false;

		if ( $rewriting || ! is_string( $url ) ) {
			return $preempt;
		}

		$host_url = 'http://localhost:8888';
		if ( strncasecmp( $url, $host_url, strlen( $host_url ) ) !== 0 ) {
			return $preempt;
		}

		// Route the request to the internal docker service (port 80) but
		// preserve the Host header so WordPress does not canonical-redirect
		// back to http://localhost:8888.
		$rewritten = 'http://wordpress' . substr( $url, strlen( $host_url ) );

		if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			$args['headers'] = array();
		}
		$args['headers']['Host'] = 'localhost:8888';

		$rewriting = true;
		$response  = wp_remote_request( $rewritten, $args );
		$rewriting = false;

		return $response;
	},
	10,
	3
);
