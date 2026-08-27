<?php
/**
 * Regression tests for deleting legacy wp-cache variants by URL path.
 *
 * @package automattic/wp-super-cache
 */

use PHPUnit\Framework\TestCase;

/**
 * @covers ::wpsc_delete_legacy_cache_files
 */
class WpscDeleteLegacyCacheFilesTest extends TestCase {

	/** @var string */
	private $root;

	/** @var array<string,mixed> */
	private $saved_globals = array();

	protected function setUp(): void {
		parent::setUp();

		$this->root = sys_get_temp_dir() . '/wpsc-delete-legacy-' . uniqid( '', true ) . '/';
		foreach ( array( 'cache/blogs/1/meta', 'cache/blogs/2/meta', 'outside/meta' ) as $dir ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test fixture.
			mkdir( $this->root . $dir, 0777, true );
		}

		$overrides = array(
			'cache_path'     => $this->root . 'cache/',
			'blog_cache_dir' => $this->root . 'cache/blogs/1/',
			'file_prefix'    => 'wp-cache-',
		);

		foreach ( $overrides as $key => $value ) {
			$this->saved_globals[ $key ] = array_key_exists( $key, $GLOBALS ) ? $GLOBALS[ $key ] : null;
			$GLOBALS[ $key ]             = $value;
		}
	}

	protected function tearDown(): void {
		foreach ( $this->saved_globals as $key => $value ) {
			if ( null === $value ) {
				unset( $GLOBALS[ $key ] );
			} else {
				$GLOBALS[ $key ] = $value;
			}
		}

		$this->remove_dir( $this->root );
		parent::tearDown();
	}

	/**
	 * Delete a test directory recursively.
	 *
	 * @param string $dir Directory path.
	 */
	private function remove_dir( $dir ): void {
		// phpcs:disable WordPress.WP.AlternativeFunctions -- test fixture cleanup.
		foreach ( (array) glob( $dir . '*' ) as $entry ) {
			is_dir( $entry ) ? $this->remove_dir( $entry . '/' ) : unlink( $entry );
		}
		rmdir( $dir );
		// phpcs:enable WordPress.WP.AlternativeFunctions
	}

	/**
	 * Write one current-format legacy cache payload and metadata pair.
	 *
	 * @param string $dir  Blog cache directory.
	 * @param string $name Unique fixture name.
	 * @param string $uri  URI stored in metadata.
	 * @return string Payload path.
	 */
	private function write_pair( $dir, $name, $uri ) {
		$file = 'wp-cache-' . $name . '.php';
		// phpcs:disable WordPress.WP.AlternativeFunctions -- test fixture.
		file_put_contents( $dir . $file, '<?php die(); ?>cached' );
		file_put_contents( $dir . 'meta/' . $file, '<?php die(); ?>' . json_encode( array( 'uri' => $uri ) ) );
		// phpcs:enable WordPress.WP.AlternativeFunctions

		return $dir . $file;
	}

	/**
	 * Write one pre-2015 .html payload and serialized .meta pair.
	 *
	 * @param string $dir  Blog cache directory.
	 * @param string $name Unique fixture name.
	 * @param string $uri  URI stored in metadata.
	 * @return string Payload path.
	 */
	private function write_old_pair( $dir, $name, $uri ) {
		$payload = 'wp-cache-' . $name . '.html';
		$meta    = 'wp-cache-' . $name . '.meta';
		// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- test fixture for the historical on-disk format.
		file_put_contents( $dir . $payload, 'cached' );
		file_put_contents( $dir . 'meta/' . $meta, serialize( array( 'uri' => $uri ) ) );
		// phpcs:enable

		return $dir . $payload;
	}

	/** Same path variants are deleted; other paths and blogs remain. */
	public function test_deletes_only_same_path_variants_from_current_blog(): void {
		$blog_dir  = $GLOBALS['blog_cache_dir'];
		$other_dir = $this->root . 'cache/blogs/2/';
		$delete    = array(
			$this->write_pair( $blog_dir, 'bare', 'example.test/page/' ),
			$this->write_pair( $blog_dir, 'utm', 'example.test/page/?utm_source=newsletter' ),
			$this->write_pair( $blog_dir, 'click-ids', 'example.test/page/?gclid=1&fbclid=2' ),
			$this->write_pair( $blog_dir, 'functional', 'example.test/page/?preview=true' ),
			$this->write_pair( $blog_dir, 'gzip', 'example.test/page/?utm_source=newsletter' ),
			$this->write_old_pair( $blog_dir, 'old-html', 'example.test/page/?utm_source=old' ),
		);
		$keep      = array(
			$this->write_pair( $blog_dir, 'child', 'example.test/page/child/?utm_source=x' ),
			$this->write_pair( $blog_dir, 'sibling', 'example.test/page-two/?utm_source=x' ),
			$this->write_pair( $other_dir, 'other-blog', 'example.test/page/?utm_source=x' ),
		);

		$malformed = $this->write_pair( $blog_dir, 'malformed', 'example.test/page/' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_put_contents -- malformed test fixture.
		file_put_contents( $blog_dir . 'meta/' . basename( $malformed ), 'not-json' );
		$keep[] = $malformed;

		$this->assertSame( 6, wpsc_delete_legacy_cache_files( '/page/' ) );

		foreach ( $delete as $file ) {
			$this->assertFileDoesNotExist( $file );
			$meta = str_ends_with( $file, '.html' ) ? str_replace( '.html', '.meta', basename( $file ) ) : basename( $file );
			$this->assertFileDoesNotExist( dirname( $file ) . '/meta/' . $meta );
		}
		foreach ( $keep as $file ) {
			$this->assertFileExists( $file );
			$this->assertFileExists( dirname( $file ) . '/meta/' . basename( $file ) );
		}

		$outside                   = $this->write_pair( $this->root . 'outside/', 'outside', 'example.test/page/' );
		$GLOBALS['blog_cache_dir'] = $this->root . 'outside/';
		$this->assertSame( 0, wpsc_delete_legacy_cache_files( '/page/' ) );
		$this->assertFileExists( $outside );
	}
}
