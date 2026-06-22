<?php
/**
 * Characterization tests for the htaccess / mod_rewrite rule generation in
 * wp-cache.php.
 *
 * These pin the CURRENT output of wpsc_get_htaccess_info() (the highest-value
 * net: the rules are string-generated and easy to break silently) before the
 * htaccess cluster is relocated to inc/htaccess.php (issue #1061). Path-derived
 * fields (document_root, inst_root, ...) are environment-specific, so the
 * assertions target the behaviour-defining parts: the rewrite condition list,
 * the mobile and charset toggles, and the gzip / header / expires blocks.
 *
 * The file-writing entry points (update_mod_rewrite_rules and its add/remove
 * wrappers) read and write the real .htaccess and fetch the live URL, so they
 * are not characterized here; their rule content is produced by the generator
 * covered below and their file plumbing stays with e2e coverage.
 *
 * Integration tier: the cluster lives in wp-cache.php and the generator calls
 * WordPress functions (get_home_path, get_bloginfo, get_option, filters).
 *
 * @package automattic/wp-super-cache
 */
class HtaccessRulesTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		// get_home_path() and extract_from_markers() live in the admin includes.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';

		$_SERVER['DOCUMENT_ROOT'] = '/var/www/html';
		unset( $_SERVER['PHP_DOCUMENT_ROOT'] );

		// Cluster globals, defaulted so the generator is deterministic.
		$GLOBALS['wp_cache_mobile_enabled']  = 0;
		$GLOBALS['wp_cache_mobile_browsers'] = '';
		$GLOBALS['wp_cache_mobile_prefixes'] = '';
		$GLOBALS['wp_cache_disable_utf8']    = 0;
		unset( $GLOBALS['htaccess_path'] );

		// Trailing-slash permalink: adds the REQUEST_URI condition rules.
		update_option( 'permalink_structure', '/%postname%/' );
	}

	/**
	 * Characterizes wpsc_get_logged_in_cookie(): defaults to the standard
	 * WordPress logged-in cookie prefix.
	 */
	public function test_logged_in_cookie_default() {
		$this->assertSame( 'wordpress_logged_in', wpsc_get_logged_in_cookie() );
	}

	/**
	 * Characterizes the default rewrite condition list: POST and query-string
	 * exclusions, the trailing-slash URI conditions, the logged-in/comment/
	 * postpass cookie guard, and no mobile user-agent rule when mobile is off.
	 */
	public function test_condition_rules_default() {
		$cond = wpsc_get_htaccess_info()['condition_rules'];

		$this->assertContains( 'RewriteCond %{REQUEST_METHOD} !POST', $cond );
		$this->assertContains( 'RewriteCond %{QUERY_STRING} ^$', $cond );
		$this->assertContains( 'RewriteCond %{REQUEST_URI} !^.*[^/]$', $cond );
		$this->assertContains( 'RewriteCond %{REQUEST_URI} !^.*//.*$', $cond );

		$joined = implode( "\n", $cond );
		$this->assertStringContainsString( 'comment_author_', $joined );
		$this->assertStringContainsString( 'wordpress_logged_in', $joined );
		$this->assertStringContainsString( 'wp-postpass_', $joined );

		foreach ( $cond as $rule ) {
			$this->assertStringNotContainsString( 'HTTP_USER_AGENT', $rule, 'no mobile rule when mobile disabled' );
		}
	}

	/**
	 * Characterizes the rewrite rule block: charset directive present when UTF-8
	 * is enabled, plus the rewrite engine and the gzip accept-encoding condition.
	 */
	public function test_rules_block_includes_charset_when_utf8_enabled() {
		$rules = wpsc_get_htaccess_info()['rules'];

		$this->assertStringContainsString( 'AddDefaultCharset', $rules );
		$this->assertStringContainsString( 'RewriteEngine On', $rules );
		$this->assertStringContainsString( 'RewriteCond %{HTTP:Accept-Encoding} gzip', $rules );
		$this->assertStringContainsString( '<IfModule mod_rewrite.c>', $rules );
	}

	/**
	 * Characterizes the charset toggle: AddDefaultCharset is omitted when UTF-8
	 * handling is disabled.
	 */
	public function test_rules_block_omits_charset_when_utf8_disabled() {
		$GLOBALS['wp_cache_disable_utf8'] = 1;

		$this->assertStringNotContainsString( 'AddDefaultCharset', wpsc_get_htaccess_info()['rules'] );
	}

	/**
	 * Characterizes the mobile toggle: when mobile is enabled, the browser and
	 * prefix user-agent conditions are added (comma-space lists become
	 * pipe-delimited alternations).
	 */
	public function test_mobile_conditions_added_when_enabled() {
		$GLOBALS['wp_cache_mobile_enabled']  = 1;
		$GLOBALS['wp_cache_mobile_browsers'] = 'Android, iPhone';
		$GLOBALS['wp_cache_mobile_prefixes'] = 'w3c, acs';

		$joined = implode( "\n", wpsc_get_htaccess_info()['condition_rules'] );

		$this->assertStringContainsString( 'RewriteCond %{HTTP_USER_AGENT} !^.*(Android|iPhone).* [NC]', $joined );
		$this->assertStringContainsString( 'RewriteCond %{HTTP_USER_AGENT} !^(w3c|acs).* [NC]', $joined );
	}

	/**
	 * Characterizes the permalink toggle: a non-trailing-slash permalink
	 * structure omits the REQUEST_URI condition rules.
	 */
	public function test_plain_permalink_omits_uri_conditions() {
		update_option( 'permalink_structure', '' );

		$cond = wpsc_get_htaccess_info()['condition_rules'];

		$this->assertNotContains( 'RewriteCond %{REQUEST_URI} !^.*[^/]$', $cond );
		$this->assertNotContains( 'RewriteCond %{REQUEST_URI} !^.*//.*$', $cond );
	}

	/**
	 * Characterizes the gzip rule block: mod_mime/mod_deflate handling, the
	 * default Vary and Cache-Control headers, the mod_expires rule, and the
	 * directory-listing lockdown.
	 */
	public function test_gziprules_default_headers_and_expires() {
		$gzip = wpsc_get_htaccess_info()['gziprules'];

		$this->assertStringContainsString( '<IfModule mod_mime.c>', $gzip );
		$this->assertStringContainsString( "Header set Vary 'Accept-Encoding, Cookie'", $gzip );
		$this->assertStringContainsString( "Header set Cache-Control 'max-age=3, must-revalidate'", $gzip );
		$this->assertStringContainsString( 'ExpiresByType text/html A3', $gzip );
		$this->assertStringContainsString( 'Options -Indexes', $gzip );
	}
}
