<?php
/**
 * Tests for where wp_cache_replace_line() inserts a line that is not in the file yet.
 *
 * @package automattic/wp-super-cache
 */

// wp-cache-phase2.php is loaded by the smoke bootstrap (tests/php/bootstrap-smoke.php).

use PHPUnit\Framework\TestCase;

/**
 * A line added to wp-config.php must not land inside another plugin's marker block.
 *
 * @covers ::wp_cache_replace_line
 */
class WpCacheReplaceLineTest extends TestCase {

	private const WP_CACHE_PATTERN = 'define *\( *\'WP_CACHE\'';
	private const WP_CACHE_LINE    = "define('WP_CACHE', true);";

	/**
	 * Directory holding the config file and the writer's temp files.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * Stand-in for wp-config.php.
	 *
	 * @var string
	 */
	private string $file;

	protected function setUp(): void {
		parent::setUp();

		$this->dir = sys_get_temp_dir() . '/wpsc-replace-line-' . uniqid( '', true );
		mkdir( $this->dir );
		$this->file = $this->dir . '/wp-config.php';

		// wp_cache_replace_line() reads cache_path for tempnam().
		$GLOBALS['cache_path'] = $this->dir . '/';
	}

	protected function tearDown(): void {
		foreach ( (array) glob( $this->dir . '/*' ) as $file ) {
			unlink( $file );
		}
		rmdir( $this->dir );

		unset( $GLOBALS['cache_path'] );

		parent::tearDown();
	}

	/**
	 * Marker blocks as other plugins write them at the top of wp-config.php.
	 *
	 * @return array<string, array{string}>
	 */
	public function provide_marker_blocks() {
		return array(
			'iThemes Security' => array(
				"// BEGIN iThemes Security - Do not modify or remove this line\n" .
				"// iThemes Security Config Details: 2\n" .
				"define( 'DISALLOW_FILE_EDIT', true ); // Disable File Editor - Security > Settings > WordPress Tweaks > File Editor\n" .
				"// END iThemes Security - Do not modify or remove this line\n",
			),
			'Solid Security'   => array(
				"// BEGIN Solid Security - Do not modify or remove this line\n" .
				"// Solid Security Config Details: 2\n" .
				"define( 'DISALLOW_FILE_EDIT', true ); // Disable File Editor - Security > Settings > WordPress Tweaks > File Editor\n" .
				"// END Solid Security - Do not modify or remove this line\n",
			),
			'Kadence Security' => array(
				"// BEGIN Kadence Security - Do not modify or remove this line\n" .
				"// Kadence Security Config Details: 2\n" .
				"define( 'DISALLOW_FILE_EDIT', true ); // Disable File Editor - Security > Settings > WordPress Tweaks > File Editor\n" .
				"// END Kadence Security - Do not modify or remove this line\n",
			),
			'hash markers'     => array(
				"# BEGIN Some Plugin\n" .
				"define( 'SOME_PLUGIN_SETTING', true );\n" .
				"# END Some Plugin\n",
			),
		);
	}

	/**
	 * The new line goes above the block, leaving the block as it was.
	 *
	 * @dataProvider provide_marker_blocks
	 * @param string $block Marker block at the top of the file.
	 */
	public function test_new_line_is_added_above_a_marker_block( $block ) {
		$rest = "\ndefine( 'DB_NAME', 'example' );\nrequire_once ABSPATH . 'wp-settings.php';\n";
		file_put_contents( $this->file, "<?php\n" . $block . $rest );

		$this->assertTrue( wp_cache_replace_line( self::WP_CACHE_PATTERN, self::WP_CACHE_LINE, $this->file ) );

		$this->assertSame(
			"<?php\n" . self::WP_CACHE_LINE . "\n" . $block . $rest,
			file_get_contents( $this->file )
		);
	}

	/**
	 * Without a marker block the line still goes above the first define.
	 */
	public function test_new_line_is_added_above_the_first_define() {
		file_put_contents( $this->file, "<?php\n/** A comment. */\ndefine( 'DB_NAME', 'example' );\n" );

		$this->assertTrue( wp_cache_replace_line( self::WP_CACHE_PATTERN, self::WP_CACHE_LINE, $this->file ) );

		$this->assertSame(
			"<?php\n/** A comment. */\n" . self::WP_CACHE_LINE . "\ndefine( 'DB_NAME', 'example' );\n",
			file_get_contents( $this->file )
		);
	}
}
