<?php
/**
 * Case-normalisation tests for the cache deletion paths.
 *
 * #1080 fixed the post's own directory. This covers the archives it appears on:
 * a category, tag or author with a non-ASCII slug. WordPress stores those slugs
 * with LOWERCASE percent escapes, so get_term_link() and get_author_posts_url()
 * hand us a URL that does not match the directory on disk, which has them
 * UPPERCASE. Without normalisation nothing is deleted and the archive keeps
 * serving a stale page after an edit. See #1081.
 *
 * The rule itself is covered in the smoke tier by WpscNormalizeUriCaseTest,
 * which runs in CI. These tests answer a different question: do the deletion
 * call sites actually apply it. So they build their fixture directories with
 * wpsc_normalize_uri_case() and assert separately that the raw URL and the
 * normalised path really do differ, which is what makes the assertion mean
 * something.
 *
 * Integration tier: needs terms, users, permalinks and a real filesystem.
 *
 * @package automattic/wp-super-cache
 */
class DeleteUrlCacheCaseTest extends WP_UnitTestCase {

	/**
	 * Kazakh name from the original bug report. sanitize_title() turns it into a
	 * slug full of lowercase percent escapes.
	 */
	const NON_ASCII_NAME = 'Ұlytau oblysy';

	/** @var string Cache directory this class works in, established once. */
	private static $cache_path;

	/** @var bool Whether setUpBeforeClass() created self::$cache_path itself. */
	private static $created_cache_path = false;

	/**
	 * Globals this class overwrites, snapshotted per test and put back afterwards.
	 *
	 * @var array<string,mixed>
	 */
	private $saved_globals = array();

	/**
	 * Fixture directories created by cache_page(), removed in tear_down().
	 *
	 * @var string[]
	 */
	private $created_dirs = array();

	/**
	 * Establish a cache directory that the deletion functions will accept.
	 *
	 * The function wpsc_is_in_cache_directory() resolves $cache_path once and keeps it in a
	 * static with no way to reset it, so whichever test class calls it first
	 * decides what counts as "inside the cache directory" for the rest of the
	 * process. Rather than guard against that and fail, work inside whatever path
	 * has already been established, and only pick our own when the static is
	 * still empty.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		$statics  = ( new ReflectionFunction( 'wpsc_is_in_cache_directory' ) )->getStaticVariables();
		$memoised = isset( $statics['rp_cache_path'] ) ? $statics['rp_cache_path'] : '';

		self::$cache_path = ( is_string( $memoised ) && '' !== $memoised )
			? trailingslashit( $memoised )
			: sys_get_temp_dir() . '/wpsc-delete-url-cache-test/';

		if ( ! is_dir( self::$cache_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- temp dir for a test fixture.
			mkdir( self::$cache_path, 0777, true );
			self::$created_cache_path = true;
		}
	}

	public static function tear_down_after_class() {
		if ( self::$created_cache_path && is_dir( self::$cache_path ) ) {
			self::rmdir_recursive( self::$cache_path );
		}

		parent::tear_down_after_class();
	}

	public function set_up() {
		parent::set_up();

		$overrides = array(
			'cache_path'          => self::$cache_path,
			'file_prefix'         => 'wp-cache-',
			// Rebuild rather than delete, so the assertion does not depend on the
			// fixture file being more than ten seconds old.
			'cache_rebuild_files' => 1,
		);

		$this->saved_globals = array();
		foreach ( $overrides as $key => $value ) {
			$this->saved_globals[ $key ] = array_key_exists( $key, $GLOBALS ) ? $GLOBALS[ $key ] : null;
			$GLOBALS[ $key ]             = $value;
		}

		$this->set_permalink_structure( '/%postname%/' );

		// WP_Rewrite::init() clears extra_permastructs, and the taxonomy ones are
		// only put back by create_initial_taxonomies(). Without this get_term_link()
		// returns ?cat=N and the tests below have no percent escapes to work with.
		create_initial_taxonomies();
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

		// Only what this class made. set_up_before_class() may have adopted a
		// cache_path it did not create, and in a wp-env run that is the site's real
		// wp-content/cache, so removing 'supercache' wholesale would take the
		// installation's cache with it.
		foreach ( array_reverse( $this->created_dirs ) as $dir ) {
			if ( is_dir( $dir ) ) {
				self::rmdir_recursive( $dir );
			}
		}
		$this->created_dirs = array();

		$this->set_permalink_structure( '' );
		parent::tear_down();
	}

	/**
	 * Remove a directory and everything under it.
	 *
	 * @param string $dir Directory to remove.
	 */
	private static function rmdir_recursive( $dir ) {
		// phpcs:disable WordPress.WP.AlternativeFunctions -- test fixture cleanup.
		foreach ( (array) glob( trailingslashit( $dir ) . '*' ) as $entry ) {
			if ( is_dir( $entry ) ) {
				self::rmdir_recursive( $entry );
			} else {
				unlink( $entry );
			}
		}
		rmdir( $dir );
		// phpcs:enable WordPress.WP.AlternativeFunctions
	}

	/**
	 * Write a cached index.html for a URL, in the directory the serving side would
	 * have created for it, and assert the fixture is worth having.
	 *
	 * @param string $url Absolute URL of the page being cached.
	 * @return string Path of the cached file.
	 */
	private function cache_page( $url ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );

		$this->assertMatchesRegularExpression(
			'/%[0-9a-f]{2}/',
			$path,
			"Expected lowercase percent escapes in {$url}; without them this test proves nothing."
		);

		$normalised = wpsc_normalize_uri_case( $path );
		$this->assertNotSame(
			$path,
			$normalised,
			'The URL and the directory on disk have to be spelled differently, or the bug cannot reproduce.'
		);

		$dir = trailingslashit( untrailingslashit( get_supercache_dir() ) . $normalised );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test fixture.
		mkdir( $dir, 0777, true );
		$this->created_dirs[] = $dir;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents -- test fixture.
		file_put_contents( $dir . 'index.html', '<html>cached</html>' );

		return $dir . 'index.html';
	}

	/**
	 * Assert a cached file was cleared. With $cache_rebuild_files on, "cleared"
	 * means renamed to .needs-rebuild rather than unlinked.
	 *
	 * @param string $file Path returned by cache_page().
	 */
	private function assertCacheCleared( $file ) {
		$this->assertFileDoesNotExist( $file, 'Cached file was left in place.' );
		$this->assertFileExists( $file . '.needs-rebuild' );
	}

	/**
	 * Saving a post clears the category archive it appears on, even when the
	 * category slug is percent-encoded. This is the reported bug.
	 */
	public function test_category_archive_with_non_ascii_slug_is_cleared() {
		$term_id  = self::factory()->category->create( array( 'name' => self::NON_ASCII_NAME ) );
		$term_url = get_term_link( $term_id, 'category' );
		$this->assertIsString( $term_url );

		$cached  = $this->cache_page( $term_url );
		$post_id = self::factory()->post->create(
			array(
				'post_status'   => 'publish',
				'post_category' => array( $term_id ),
			)
		);

		wpsc_delete_post_archives( $post_id );

		$this->assertCacheCleared( $cached );
	}

	/** The same for a tag, which reaches the deletion path through a different taxonomy. */
	public function test_tag_archive_with_non_ascii_slug_is_cleared() {
		$term_id  = self::factory()->tag->create( array( 'name' => self::NON_ASCII_NAME ) );
		$term_url = get_term_link( $term_id, 'post_tag' );
		$this->assertIsString( $term_url );

		$cached  = $this->cache_page( $term_url );
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		wp_set_post_tags( $post_id, array( $term_id ) );

		wpsc_delete_post_archives( $post_id );

		$this->assertCacheCleared( $cached );
	}

	/**
	 * The REST delete-cache endpoint goes through wpsc_delete_post_cache(), which
	 * hands the post's own permalink to wpsc_delete_url_cache().
	 *
	 * The author archive is not covered here. get_author_posts_url() builds its
	 * URL from user_nicename, and WordPress runs that through
	 * sanitize_user( $nicename, true ), which strips percent escapes, so a
	 * nicename cannot carry them in the first place. Reproducing it would mean
	 * writing the row by hand, which would test a state WordPress does not
	 * produce. The shared helper covers the path regardless.
	 */
	public function test_post_cache_deletion_handles_a_non_ascii_permalink() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => self::NON_ASCII_NAME,
				'post_status' => 'publish',
			)
		);

		$cached = $this->cache_page( get_permalink( $post_id ) );

		$this->assertTrue( wpsc_delete_post_cache( $post_id ) );

		$this->assertCacheCleared( $cached );
	}

	/**
	 * The deprecated wpsc_delete_cats_tags() builds its paths from the term slug
	 * by hand rather than going through wpsc_delete_url_cache(), so it needs the
	 * normalisation of its own. It is still callable, so it is still tested.
	 */
	public function test_deprecated_cats_tags_deletion_handles_non_ascii_slug() {
		$term_id  = self::factory()->category->create( array( 'name' => self::NON_ASCII_NAME ) );
		$term_url = get_term_link( $term_id, 'category' );
		$this->assertIsString( $term_url );

		$cached  = $this->cache_page( $term_url );
		$post_id = self::factory()->post->create(
			array(
				'post_status'   => 'publish',
				'post_category' => array( $term_id ),
			)
		);

		$this->setExpectedDeprecated( 'wpsc_delete_cats_tags' );
		wpsc_delete_cats_tags( $post_id );

		// This path prunes rather than rebuilds, so the file is removed outright.
		$this->assertFileDoesNotExist( $cached );
	}

	/**
	 * The seam wp_cron_preload_cache() uses to pick the directory it deletes.
	 *
	 * That caller deletes and then immediately refetches, so a delete that misses
	 * is served the stale file it was meant to replace, which is why it matters
	 * most and why the computation was pulled out where a test can reach it.
	 */
	public function test_supercache_dir_for_url_normalises_a_term_link() {
		$term_id  = self::factory()->category->create( array( 'name' => self::NON_ASCII_NAME ) );
		$term_url = get_term_link( $term_id, 'category' );
		$this->assertIsString( $term_url );

		$path = (string) wp_parse_url( $term_url, PHP_URL_PATH );
		$this->assertMatchesRegularExpression( '/%[0-9a-f]{2}/', $path, 'Fixture has no lowercase escapes to normalise.' );

		$dir = wpsc_supercache_dir_for_url( $term_url );

		// get_supercache_dir() is already trailingslashed and the path opens with a
		// slash, so the result carries a doubled one. That is what the inline
		// version produced too, and realpath() collapses it.
		$this->assertSame( get_supercache_dir() . wpsc_normalize_uri_case( $path ), $dir );
		$this->assertNotSame(
			get_supercache_dir() . $path,
			$dir,
			'The raw and normalised directories have to differ, or this proves nothing.'
		);
	}

	/**
	 * A URL with no path at all. wp_parse_url() omits the key entirely, which the
	 * inline version this replaced dereferenced anyway.
	 */
	public function test_supercache_dir_for_url_handles_a_url_with_no_path() {
		$this->assertSame(
			get_supercache_dir() . '/',
			wpsc_supercache_dir_for_url( 'https://example.com' )
		);
	}

	/**
	 * A URL with a query string is a no-op, not a nonce failure.
	 *
	 * The admin bar renders the delete button on any URL, so on a site using plain
	 * permalinks every path is '/?p=123' and on a search page it is '/?s=foo'.
	 * Those have never had a supercache directory and the delete has always done
	 * nothing, quietly. What must not happen is the path being emptied before the
	 * nonce check, because then wpsc_admin_bar_delete_cache() dies with "Problem
	 * with nonce" on a request whose nonce was perfectly good.
	 */
	public function test_query_string_path_is_a_no_op_rather_than_a_nonce_failure() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		foreach ( array( '/?p=123', '/?s=foo' ) as $path ) {
			$_POST['path']  = $path;
			$_POST['admin'] = 0;
			$_POST['nonce'] = wp_create_nonce( 'delete-cache-' . $path . '_0' );

			$result = wpsc_delete_cache_directory();

			unset( $_POST['path'], $_POST['admin'], $_POST['nonce'] );

			$this->assertNotFalse(
				$result,
				"Path {$path} was rejected before the nonce check, so the caller would report a nonce failure that did not happen."
			);
		}
	}

	/**
	 * The REST endpoint's 'url' branch, which is the one caller that hands the
	 * helpers a value straight out of JSON.
	 *
	 * Note the path it wants is host-prefixed: it builds from $cache_path
	 * . 'supercache/' rather than get_supercache_dir(), so the host segment has to
	 * come from the caller.
	 */
	public function test_rest_url_deletion_normalises_the_posted_url() {
		require_once __DIR__ . '/../../../rest/class.wp-super-cache-rest-delete-cache.php';

		$term_id  = self::factory()->category->create( array( 'name' => self::NON_ASCII_NAME ) );
		$term_url = get_term_link( $term_id, 'category' );
		$this->assertIsString( $term_url );

		$cached = $this->cache_page( $term_url );
		$host   = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$path   = (string) wp_parse_url( $term_url, PHP_URL_PATH );

		$request = new WP_REST_Request( 'POST', '/wp-super-cache/v1/cache' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'url' => $host . $path ) ) );

		$controller = new WP_Super_Cache_Rest_Delete_Cache();
		$response   = $controller->callback( $request );

		$this->assertNotWPError( $response );
		$this->assertFileDoesNotExist( $cached );
	}

	/**
	 * A 'url' that is not a path is refused rather than being cleaned up into one.
	 * Without the check it would build the site's supercache root, which
	 * wpsc_delete_files() protects, but refusing says so out loud.
	 */
	public function test_rest_url_deletion_refuses_a_url_it_cannot_use() {
		require_once __DIR__ . '/../../../rest/class.wp-super-cache-rest-delete-cache.php';

		$controller = new WP_Super_Cache_Rest_Delete_Cache();

		foreach ( array( '/blog/<script>/', array( 'x' ), "/blog/x\n" ) as $bad_url ) {
			$request = new WP_REST_Request( 'POST', '/wp-super-cache/v1/cache' );
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( array( 'url' => $bad_url ) ) );

			$this->assertWPError( $controller->callback( $request ) );
		}
	}

	/**
	 * The admin bar / REST "delete cache" button takes its path from the browser's
	 * REQUEST_URI, whose case the browser controls.
	 */
	public function test_delete_cache_directory_normalises_the_posted_path() {
		$term_id  = self::factory()->category->create( array( 'name' => self::NON_ASCII_NAME ) );
		$term_url = get_term_link( $term_id, 'category' );
		$this->assertIsString( $term_url );

		$cached = $this->cache_page( $term_url );
		$path   = (string) wp_parse_url( $term_url, PHP_URL_PATH );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_POST['path']  = $path;
		$_POST['admin'] = 0;
		$_POST['nonce'] = wp_create_nonce( 'delete-cache-' . $path . '_0' );

		wpsc_delete_cache_directory();

		unset( $_POST['path'], $_POST['admin'], $_POST['nonce'] );

		$this->assertFileDoesNotExist( $cached );
	}
}
