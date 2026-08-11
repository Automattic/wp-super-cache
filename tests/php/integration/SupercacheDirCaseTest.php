<?php
/**
 * Case-normalisation tests for get_current_url_supercache_dir().
 *
 * WordPress stores a non-ASCII post slug with LOWERCASE percent escapes:
 * utf8_uri_encode() builds them with dechex(), and sanitize_title_with_dashes()
 * lowercases the result afterwards. The supercache directory on disk, however,
 * is written with those escapes UPPERCASED, because browsers send them that way
 * and the mod_rewrite rules do a literal file test on the request path.
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

	public function set_up() {
		parent::set_up();

		$GLOBALS['cache_path']           = '/tmp/wpsc-supercache-dir-test/';
		$GLOBALS['WPSC_HTTP_HOST']       = 'example.org';
		$GLOBALS['wp_cache_home_path']   = '/';
		$GLOBALS['wp_cache_request_uri'] = '/';
		$GLOBALS['cached_direct_pages']  = array();

		$this->set_permalink_structure( '/%postname%/' );
	}

	public function tear_down() {
		$this->set_permalink_structure( '' );
		parent::tear_down();
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
	 * suite. That test is this one.
	 */
	public function test_post_id_and_request_uri_branches_agree_for_non_ascii_slug() {
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
