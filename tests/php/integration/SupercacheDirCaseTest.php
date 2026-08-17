<?php
/**
 * Case-normalisation tests for get_current_url_supercache_dir().
 *
 * WordPress stores a non-ASCII post slug with LOWERCASE percent escapes:
 * utf8_uri_encode() builds them with dechex(), and sanitize_title_with_dashes()
 * lowercases the result afterwards. A URL encoder emits UPPERCASE escapes, so a
 * visitor who follows a permalink and one who pastes the unicode URL send two
 * different spellings of the same path. The plugin normalises both to uppercase
 * so they land on one directory, the same way inc/htaccess.php lowercases the
 * home root to keep that segment single-spelled.
 *
 * Only the $post_id = 0 branch ever creates a supercache directory; the
 * $post_id != 0 branch is used exclusively by the deletion paths. So whatever
 * the $post_id = 0 branch produces is the shape the other branch has to match,
 * or cache invalidation on post save looks for a directory that is not there.
 * See PR #1080.
 *
 * Integration tier: needs get_permalink() and the rewrite rules, so these run
 * under the real WordPress runtime.
 *
 * @package automattic/wp-super-cache
 */
class SupercacheDirCaseTest extends WP_UnitTestCase {

	/**
	 * Kazakh title from the original bug report, which sanitises to a slug full
	 * of percent escapes.
	 */
	const NON_ASCII_TITLE = 'Ұlytau oblysy ortasha ajlyқ zhalaқy kөlemi';

	/**
	 * Globals this class overwrites, snapshotted in set_up() and put back in
	 * tear_down() so the values here do not leak into later test classes.
	 *
	 * @var array<string,mixed>
	 */
	private $saved_globals = array();

	public function set_up() {
		parent::set_up();

		$overrides = array(
			'cache_path'           => '/tmp/wpsc-supercache-dir-test/',
			'WPSC_HTTP_HOST'       => 'example.org',
			'wp_cache_home_path'   => '/',
			'wp_cache_request_uri' => '/',
			'cached_direct_pages'  => array(),
		);

		$this->saved_globals = array();
		foreach ( $overrides as $key => $value ) {
			$this->saved_globals[ $key ] = array_key_exists( $key, $GLOBALS ) ? $GLOBALS[ $key ] : null;
			$GLOBALS[ $key ]             = $value;
		}

		$this->set_permalink_structure( '/%postname%/' );
	}

	public function tear_down() {
		foreach ( $this->saved_globals as $key => $value ) {
			if ( null === $value ) {
				unset( $GLOBALS[ $key ] );
			} else {
				$GLOBALS[ $key ] = $value;
			}
		}
		$this->saved_globals = array();

		$this->set_permalink_structure( '' );
		parent::tear_down();
	}

	/**
	 * Assert that nothing earlier in the process has already memoised $post_id = 0.
	 *
	 * The function caches into a static keyed by $post_id and offers no way to
	 * reset it, so the first caller with key 0 wins for the rest of the run.
	 * Without this check, a key poisoned by another test class surfaces here as
	 * an unexplained directory mismatch.
	 */
	private function assertRequestUriBranchNotMemoised() {
		$statics = ( new ReflectionFunction( 'get_current_url_supercache_dir' ) )->getStaticVariables();
		$saved   = isset( $statics['saved_supercache_dir'] ) ? $statics['saved_supercache_dir'] : array();

		$this->assertArrayNotHasKey(
			0,
			$saved,
			'get_current_url_supercache_dir() has already memoised $post_id = 0 earlier in this process, so the request URI branch cannot be exercised here. Only one test per run may use key 0.'
		);
	}

	/**
	 * Publish a post and return its ID plus the path part of its permalink.
	 *
	 * @param string $title Post title.
	 * @return array{0:int,1:string} Post ID and permalink path.
	 */
	private function publish( $title ) {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => $title,
				'post_status' => 'publish',
			)
		);

		return array( $post_id, (string) wp_parse_url( get_permalink( $post_id ), PHP_URL_PATH ) );
	}

	/**
	 * The deletion branch has to land on the same directory the serving branch
	 * creates. This is the regression from the bug report.
	 *
	 * Note: get_current_url_supercache_dir() memoises into a static keyed by
	 * $post_id and never resets it, so key 0 may only be used by ONE test in the
	 * suite. That test is this one, and the precondition below says so out loud
	 * if that ever stops being true.
	 */
	public function test_post_id_and_request_uri_branches_agree_for_non_ascii_slug() {
		$this->assertRequestUriBranchNotMemoised();

		list( $post_id, $path ) = $this->publish( self::NON_ASCII_TITLE );

		$this->assertStringContainsString( '%', $path, 'Expected the slug to be percent-encoded.' );

		$from_post_id = get_current_url_supercache_dir( $post_id );

		$GLOBALS['wp_cache_request_uri'] = $path;
		$from_request_uri                = get_current_url_supercache_dir( 0 );

		$this->assertSame(
			$from_request_uri,
			$from_post_id,
			'The deletion branch must resolve to the directory the serving branch creates.'
		);
	}

	/**
	 * Every percent escape in the directory name is uppercased, whichever branch
	 * produced it.
	 */
	public function test_percent_escapes_are_uppercased_on_the_post_id_branch() {
		list( $post_id ) = $this->publish( self::NON_ASCII_TITLE );

		$dir = get_current_url_supercache_dir( $post_id );

		preg_match_all( '/%[0-9a-fA-F]{2}/', $dir, $matches );
		$this->assertNotEmpty( $matches[0], "Expected percent escapes in {$dir}." );
		$this->assertSame( array_map( 'strtoupper', $matches[0] ), $matches[0] );
	}

	/**
	 * The host segment is lowercased when it comes from the home option.
	 *
	 * $WPSC_HTTP_HOST is empty under WP-CLI and cron, and the fallback reads the
	 * host out of the home option. wp-cache-base.php lowercases the HTTP_HOST it
	 * normally uses, so an install whose home option carries uppercase had those
	 * requests name a different directory than its web requests did.
	 *
	 * Nothing here memoises: a home option that site_url() does not appear in
	 * sends the function down its DONOTREMEMBER branch, so key 0 stays free for
	 * the test above.
	 */
	public function test_host_from_the_home_option_is_lowercased() {
		$GLOBALS['WPSC_HTTP_HOST'] = '';

		// Filtered rather than saved with update_option(): WP_HOME is defined in the
		// test config, and core's _config_wp_home() puts that back on option_home at
		// priority 10, so a stored value never survives to be read.
		$uppercase_home = static function () {
			return 'https://Example.ORG';
		};
		add_filter( 'option_home', $uppercase_home, 20 );

		try {
			list( $post_id ) = $this->publish( 'Hello There' );

			$dir = get_current_url_supercache_dir( $post_id );
		} finally {
			remove_filter( 'option_home', $uppercase_home, 20 );
		}

		$this->assertStringContainsString( 'supercache/example.org/', $dir );
		$this->assertStringNotContainsString( 'Example.ORG', $dir );
	}

	/**
	 * The rest of the path is lowercased on the post ID branch too. An install in
	 * a subdirectory with a capital in it used to produce /Blog/... here while the
	 * directory on disk was /blog/..., because inc/htaccess.php lowercases the
	 * home root for the same convention.
	 */
	public function test_path_is_lowercased_on_the_post_id_branch() {
		$GLOBALS['wp_cache_home_path'] = '/Blog/';

		list( $post_id ) = $this->publish( 'Hello There' );

		$dir = get_current_url_supercache_dir( $post_id );

		$this->assertStringContainsString( '/blog/hello-there/', $dir );
		$this->assertStringNotContainsString( '/Blog/', $dir );
	}
}
