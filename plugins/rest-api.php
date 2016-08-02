<?php

/*
 * Add support for caching REST API v2+ endpoints
 *
 * URLs without parameters -- e.g., https://example.org/wp-json/wp/v2/posts/ -- can be served cached files by
 * mod_rewrite. URLs with parameters -- e.g., https://example.org/wp-json/wp/v2/posts?per_page=10 -- are served
 * cached files by PHP.
 *
 */

// Prevent direct access
if ( ! defined( 'WPCACHEHOME' ) ) {
	return;
}

/*
 * Testing:
 *
 * The JSON spec doesn't allow comments, so we can't use those like we do with HTML pages. Instead, we rely on
 * HTTP headers, and a few other signals.
 *
 * Start by adding this code to an mu-plugin, to setup a test endpoint:

add_action( 'rest_api_init', function() {
	register_rest_route( 'test/v1', '/foo', array(
		'methods'  => 'GET',
		'callback' => 'test_foo',
	) );
} );

function test_foo( $request ) {
	sleep( 2 ); // this helps make it obvious whether a response is cached or not

	$bar = $request->get_param( 'bar' );

	return array(
		'bar' => $bar,
		'time' => time(),
	);
}

 * Then make a request to that endpoint. The response body will contain the timestamp, and the value of the `bar`
 * parameter (or `null` if you don't pass one). The headers will reveal how the response was generated and served.
 *
 * curl -i http://example.localhost/wp-json/test/v1/foo
 *
 * Uncached responses will contain:                 Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages
 * Cached responses served via PHP will contain:    WP-Super-Cache: Served supercache file from PHP
 * Cached responses served via Apache will contain: Last-Modified: Tue, 23 Aug 2016 18:12:48 GMT
 *
 * The headers can vary a bit depending on the environment, but you should see some consistent differences between
 * cached and uncached requests.
 *
 * You can also check the following signals:
 *
 * - Has the value of `time` in the response body changed?
 * - If you manually edit the endpoint's .json file in `wp-content/cache`, do you see the change?
 * - Was the response slow (2+ seconds), or fast?
 * - Do you get a fresh response if you delete the endpoint's .json file in `wp-content/cache`?
 */


/*
 * Future development:
 *
 * See if there's a way to serve gzipped json while still sending the correct content-type header
 *      can use Apache's ModMime?
 *          Add filter to $gziprules in wpsc_get_htaccess_info
 *          Add `<FilesMatch "\.json(\.gz)?$">` to cache/.htaccess
 *          Add `RewriteCond %{HTTP:Accept-Encoding} gzip` rules to /.htaccess
 *		maybe detect if server is configured to send application/json for gzipped
 *          if it's not, then don't generate the corresponding rules?
 *      could maybe put AddType into htaccess to set gzip mime type
 *          thought not all servers supported, but w3 total cache does it. maybe they detect support first?
 *
 * When disabling the plugin, clear all cached files to get rid of the API cache, then run the preloader to regenerate everything else (if it's enabled)
 *
 * Need to update the cache when underlying data changes, rather than just waiting for manual expiration?
 *      would be bad if deleted post continue to show up in cache, etc
 *      how to tell that updating a post or category maps to an API endpoint?
 *      worst case, could just flush all endpoint cache files when any post/comment/taxterm/etc is updated
 *
 * JSON spec doesn't allow comments, but still want the helpful info from $wp_super_cache_comments
 *    so instead, maybe send X-WPSC-REST headers with that info
 *    maybe can't get that b/c has to be set in phase2, but can at least output whether loading from cache or not. already has a header for that, though
 *    could store in meta files, like headers
 *
 * Add support for preloading
 *
 * Add .json tests to the Cache Tester button on Settings > Easy ?
 *      maybe send a test json response to make sure the header is getting set correctly
 *      if not, tell user to ask host to configure apache to send application/json for .json files
 *
 * Run `wpsc_rest_save_url_prefix` once during WPSC setup, and then on a cron after that
 *      that's cleaner/faster than loading on every request
 */

/**
 * Load the plugin
 */
function wpsc_rest_bootstrap() {
	global $cache_rest_api;

	// Always register the plugin settings
	add_cacheaction( 'cache_admin_page', 'wpsc_rest_render_plugin_settings' );

	/*
	 * Disable the rest of the plugin if that was explicitly configured.
	 * Otherwise, leave it enabled by default and set a canonical value for `$cache_rest_api`.
	 */
	if ( isset( $cache_rest_api ) && '0' === $cache_rest_api ) {
		return;
	} else {
		$cache_rest_api = '1';
	}

	add_cacheaction( 'add_cacheaction',              'wpsc_rest_bootstrap_phase2'      );
	add_cacheaction( 'supercache_filename',          'wpsc_rest_json_filenames'        );
	add_cacheaction( 'serve_supercache_file',        'wpsc_rest_serve_supercache_file' );
	add_cacheaction( 'before_serve_supercache_file', 'wpsc_send_json_headers'          );
}

/**
 * Register hook callbacks for phase 2
 *
 * We have to wait until phase 2 to register these, since WP isn't fully loaded
 * during phase 1.
 */
function wpsc_rest_bootstrap_phase2() {
	global $cache_enabled;

	add_filter( 'wp_cache_eof_tags',           'wpsc_rest_eof_tags'                 );
	add_filter( 'wp_cache_ob_callback_filter', 'wpsc_rest_remove_comments'          );
	add_filter( 'supercache_filename',         'wpsc_rest_json_filenames'           );
	add_filter( 'supercacherewriterules',      'wpsc_rest_add_rewrite_rules', 10, 4 );

	// Only hook into Core if caching is enabled, to avoid unintended side-effects
	if ( $cache_enabled ) {
		add_action( 'init', 'wpsc_rest_save_url_prefix' );
	}
}

/**
 * Render the REST API section of the Plugins tab
 */
function wpsc_rest_render_plugin_settings() {
	global $cache_rest_api;

	$results = wpsc_process_plugin_settings();

	?>

	<fieldset id="rest-api-section" class="options">
		<h4><?php _e( 'REST API', 'wp-super-cache' ); ?></h4>

		<form name="wp_manager" action="" method="post">
			<?php wp_nonce_field( 'wp-cache' ); ?>

			<label>
				<input type="radio" name="cache_rest_api" value="1" <?php checked( $cache_rest_api, '1' ); ?> />
				<?php _e( 'Enabled', 'wp-super-cache' ); ?>
			</label>

			<label>
				<input type="radio" name="cache_rest_api" value="0" <?php checked( $cache_rest_api, '0' ); ?> />
				<?php _e( 'Disabled', 'wp-super-cache' ); ?>
			</label>

			<p>
				<?php _e( 'Caches endpoints for <a href="http://v2.wp-api.org/">WordPress\' REST API</a>.', 'wp-super-cache' ); ?>
			</p>

			<?php if ( $results['changed'] ) : ?>
				<div class="notice notice-success notice-large">
					<?php printf( __( 'The REST API plugin is now <strong>%s</strong>.', 'wp-super-cache' ), $results['status'] ); ?>
				</div>
			<?php endif; ?>

			<div class="submit">
				<input
					class="button-primary"
					type="submit"
					value="<?php echo esc_attr( __( 'Update', 'wp-super-cache' ) ); ?>"
					<?php echo SUBMITDISABLED; ?>
				/>
			</div>
		</form>
	</fieldset>

	<?php
}

/**
 * Process the form to update the plugin's settings
 *
 * @return array
 */
function wpsc_process_plugin_settings() {
	global $cache_rest_api, $valid_nonce, $wp_cache_config_file;

	if ( isset( $_POST['cache_rest_api'] ) && $valid_nonce ) {
		$changed        = true;
		$cache_rest_api = (int) $_POST['cache_rest_api'];

		wp_cache_replace_line(
			'^ *\$cache_rest_api',
			"\$cache_rest_api = '$cache_rest_api';",
			$wp_cache_config_file
		);
	} else {
		$changed = false;
	}

	$status = $cache_rest_api ? __( 'enabled', 'wp-super-cache' ) : __( 'disabled', 'wp-super-cache' );

	return compact( 'changed', 'status' );
}

/**
 * Determine if the current request is for a REST API endpoint
 *
 * Normally we would check REST_REQUEST for this, but that doesn't get set until `parse_query`
 * runs during phase 2, so instead, we need to pull the API prefix in `wp-cache-config.php`
 * and compare the URI ourselves.
 *
 * @return bool
 */
function wpsc_rest_is_api_request() {
	global $wp_cache_request_uri, $wp_cache_rest_prefix;
	$is_request = false;

	// Set a default value if the prefix hasn't been configured yet
	if ( is_null( $wp_cache_rest_prefix ) ) {
		$wp_cache_rest_prefix = 'wp-json';
	}

	if ( substr( $wp_cache_request_uri, 1, strlen( $wp_cache_rest_prefix ) ) === $wp_cache_rest_prefix ) {
		$is_request = true;
	}

	return $is_request;
}

/*
 * Always try to serve supercache files for REST API requests
 *
 * `$wp_cache_slash_check` is automatically set to `true` by `wp_cache_manager()` when the
 * permalink structure has a trailing slash. When looking for an existing cache file,
 * `wp_cache_serve_cache_file()` assumes files will either have a trailing slash or won't,
 * and doesn't handle the case where both forms are valid.
 *
 * Both forms are valid for REST API endpoints, though, so we need to tell
 * `wp_cache_serve_cache_file()` to look for a supercache file.
 */
function wpsc_rest_serve_supercache_file( $serve_supercache_file ) {
	if ( wpsc_rest_is_api_request() ) {
		$serve_supercache_file = true;
	}

	return $serve_supercache_file;
}

/**
 * Send the correct headers for JSON output
 *
 * By default WPSC sends `Content-Type: text/html`, which could cause the browser to interpret
 * the output as HTML, opening up XSS attack vectors.
 */
function wpsc_send_json_headers() {
	if ( ! wpsc_rest_is_api_request() ) {
		return;
	}

	// The JSON specification says that JSON is always Unicode, and no charset is needed
	header( 'Content-type: application/json' );
}

/**
 * Save the REST API URL prefix in WPSC's config
 *
 * This is necessary for `wpsc_rest_is_api_request()` to work. See the comments there for details.
 */
function wpsc_rest_save_url_prefix() {
	global $wp_cache_rest_prefix, $wp_cache_config_file;

	$canonical_prefix = rest_get_url_prefix();

	if ( $canonical_prefix === $wp_cache_rest_prefix ) {
		return;
	}

	// Update global variable for use later in this request
	$wp_cache_rest_prefix = $canonical_prefix;

	/*
	 * Update stored configuration
	 *
	 * Ignore any errors that `wp_cache_replace_line()` echo's, because we don't want them showing up on the
	 * front-end, or nagging the admin on every screen.
	 */
	ob_start();
	wp_cache_replace_line(
		'^ *\$wp_cache_rest_prefix',
		"\$wp_cache_rest_prefix = '$canonical_prefix';",
		$wp_cache_config_file
	);
	ob_end_clean();
}

/**
 * Register JSON end-of-file markers
 *
 * @todo `null`, "hello", and 21345 are all completely valid (but rare) JSON responses as well
 *
 * `wp_cache_get_ob()` uses end-of-file markers to determine which types of pages to cache.
 * Adding JSON markers causes WPSC to cache files ending with JSON markers.
 *
 * @param string $eof_pattern
 *
 * @return string
 */
function wpsc_rest_eof_tags( $eof_pattern ) {
	$json_object_pattern     = '^[{].*[}]$';
	$json_collection_pattern = '^[\[].*[\]]$';

	$eof_pattern = str_replace(
		'<\?xml',
		sprintf( '<\?xml|%s|%s', $json_object_pattern, $json_collection_pattern ),
		$eof_pattern
	);

	return $eof_pattern;
}

/**
 * Remove WPSC comments from JSON output
 *
 * The JSON spec doesn't support HTML comments -- or any other form, for that matter -- and
 * parsers will fail if they encounter them.
 *
 * `wp_cache_ob_callback_filter` is only being used because it fires at the most appropriate
 * time during script execution; we don't actually want to make any modifications to the
 * buffer.
 *
 * @param string $buffer
 *
 * @return string
 */
function wpsc_rest_remove_comments( $buffer ) {
	global $wp_super_cache_comments;

	if ( wpsc_rest_is_api_request() ) {
		$wp_super_cache_comments = false;
	}

	return $buffer;
}

/**
 * Set a .json extension for REST API cache files
 *
 * @param string $filename
 *
 * @return string
 */
function wpsc_rest_json_filenames( $filename ) {
	if ( wpsc_rest_is_api_request() ) {
		$filename = str_replace( '.html', '.json', $filename );
	}

	return $filename;
}

/**
 * Add extra rewrite rules so that REST API requests are mapped to .json cache files
 *
 * @param string $rules
 *
 * @return string
 */
function wpsc_rest_add_rewrite_rules( $rules, $apache_root, $install_root, $home_root ) {
	global $wp_cache_rest_prefix;

	$condition_rules = wpsc_rest_get_condition_rules();

	/*
	 * Only serving uncompressed files, because Apache sends `Content-Type: text/html` for .json.gz files, which
	 * would be an XSS vector.
	 */
	$json_rules = "
		# JSON / HTTPS / uncompressed
		$condition_rules
		RewriteCond %{REQUEST_URI} ^\/{$wp_cache_rest_prefix}\/.*$
		RewriteCond %{HTTPS} on
		RewriteCond {$apache_root}{$install_root}cache/supercache/%{SERVER_NAME}{$home_root}$1/index-https.json -f
		RewriteRule ^(.*) \"{$install_root}cache/supercache/%{SERVER_NAME}{$home_root}$1/index-https.json\" [L]

		# JSON / HTTP / uncompressed
		$condition_rules
		RewriteCond %{REQUEST_URI} ^\/{$wp_cache_rest_prefix}\/.*$
		RewriteCond %{HTTPS} !on
		RewriteCond {$apache_root}{$install_root}cache/supercache/%{SERVER_NAME}{$home_root}$1/index.json -f
		RewriteRule ^(.*) \"{$install_root}cache/supercache/%{SERVER_NAME}{$home_root}$1/index.json\" [L]
	";

	// Remove tabs because they're just to make it readable for developers, and shouldn't be in the final output
	$json_rules = str_replace( "\t", '', $json_rules );

	return str_replace( '</IfModule>', $json_rules . '</IfModule>', $rules );
}

/**
 * Modify the condition rules for use with JSON rewrite rules
 *
 * If the permalink structure has a trailing slash, then the condition rules generated by
 * `wpsc_get_condition_rules()` will include rules that are designed to prevent URLs without a slash from
 * matching. That is done because Core sends a `301` status and redirects unslashed URLs to the canonical,
 * slashed equivalent.
 *
 * Core doesn't do that for the REST API, though; unslashed URLs are treated identically to slashed URLs, so
 * there's no need to preventing matching. By removing those rules, the unslashed URLs become cacheable.
 *
 * @return string
 */
function wpsc_rest_get_condition_rules() {
	$condition_rules = preg_grep(
		'/RewriteCond %{REQUEST_URI} \!\^\.\*/',
		wpsc_get_condition_rules(),
		PREG_GREP_INVERT
	);

	return implode( "\n", $condition_rules );
}

wpsc_rest_bootstrap();
