<?php
/**
 * Tests for wp_cache_setting()'s config file writes.
 *
 * @package automattic/wp-super-cache
 */

// wp-cache-phase2.php is loaded by the smoke bootstrap (tests/php/bootstrap-smoke.php).

use PHPUnit\Framework\TestCase;

/**
 * Config file writes must stay data, not become code.
 *
 * The config file is included as PHP before WordPress boots, so any value
 * written into it is code. These tests round-trip a value through the writer and
 * back through include, which is the only assertion that actually proves the
 * file stayed data.
 *
 * @covers ::wp_cache_setting
 */
class WpscCacheSettingTest extends TestCase {

	/**
	 * The field every test writes to. The sink is field-agnostic, so one is enough.
	 *
	 * Deliberately not cache_path: wp_cache_setting() assigns $GLOBALS[ $field ]
	 * before writing, and wp_cache_replace_line() puts its temp file in
	 * $GLOBALS['cache_path'] — writing cache_path here would leave tempnam()
	 * pointing at a directory that does not exist yet, which falls back to the
	 * system temp dir and emits a notice PHPUnit turns into an error.
	 */
	private const FIELD = 'wpsc_example';

	/**
	 * Directory holding the config file and the writer's temp files.
	 *
	 * @var string
	 */
	private string $dir;

	protected function setUp(): void {
		parent::setUp();

		$this->dir = sys_get_temp_dir() . '/wpsc-config-' . uniqid( '', true );
		mkdir( $this->dir );

		// Neither global exists until a test sets it — the smoke bootstrap loads
		// wp-cache-phase2.php without a config file — so tearDown unsets rather
		// than restores. wp_cache_replace_line() reads cache_path for tempnam().
		$GLOBALS['cache_path']           = $this->dir . '/';
		$GLOBALS['wp_cache_config_file'] = $this->dir . '/wp-cache-config.php';

		file_put_contents(
			$GLOBALS['wp_cache_config_file'],
			"<?php\n\$cache_path = '/original/path/';\n\$" . self::FIELD . " = 'original';\n"
		);
	}

	protected function tearDown(): void {
		foreach ( (array) glob( $this->dir . '/*' ) as $file ) {
			unlink( $file );
		}
		rmdir( $this->dir );

		unset(
			$GLOBALS['cache_path'],
			$GLOBALS['wp_cache_config_file'],
			$GLOBALS[ self::FIELD ],
			$GLOBALS['wpsc_config_side_effect']
		);

		parent::tearDown();
	}

	/**
	 * Include the written config in an isolated scope and hand back the value it
	 * defined. A statement smuggled into a value would run here, which is the
	 * point: the marker assertions below detect exactly that.
	 *
	 * @return mixed
	 */
	private function read_back() {
		include $GLOBALS['wp_cache_config_file'];

		// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- Defined by the config file included above.
		return $wpsc_example ?? null;
	}

	/**
	 * Values that used to end the string literal early, so that what followed
	 * became a statement the config file ran on every request.
	 *
	 * Each carries a marker assignment rather than anything real: if it runs, the
	 * value escaped its string.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function breakout_values(): array {
		return array(
			'single quote closes the string early' => array( "/var/www/cache/';\$GLOBALS['wpsc_config_side_effect'] = true;" ),
			'newline starts a new statement'       => array( "/var/www/cache/\n\$GLOBALS['wpsc_config_side_effect'] = true;" ),
		);
	}

	/**
	 * @dataProvider breakout_values
	 *
	 * @param string $value Value whose tail would become a statement.
	 */
	public function test_value_cannot_smuggle_a_statement( string $value ): void {
		wp_cache_setting( self::FIELD, $value );

		$written = $this->read_back();

		$this->assertArrayNotHasKey(
			'wpsc_config_side_effect',
			$GLOBALS,
			'Including the written config ran the smuggled statement.'
		);
		$this->assertSame( $value, $written );
	}

	/**
	 * Values that must survive the write unchanged.
	 *
	 * The escape-sequence case is the one input where the escaping could plausibly
	 * eat itself — a string already containing the literal sequence the newline
	 * flattening introduces. It cannot, because var_export() escapes the quotes and
	 * backslash before strtr() runs and strtr() only matches real control bytes,
	 * but nothing else in the suite pins that ordering.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function round_trip_values(): array {
		return array(
			'double quote'        => array( '/var/www/ca"che/' ),
			'trailing backslash'  => array( 'C:\\inetpub\\cache\\' ),
			'the escape sequence' => array( '\' . "\n" . \'' ),
		);
	}

	/**
	 * @dataProvider round_trip_values
	 *
	 * @param string $value Value that must come back byte-exact.
	 */
	public function test_value_round_trips( string $value ): void {
		wp_cache_setting( self::FIELD, $value );

		$this->assertSame( $value, $this->read_back() );
	}

	/**
	 * The entry must occupy exactly one physical line whatever the value holds.
	 *
	 * The writer matches '^ *\$field' one line at a time, so a value written across
	 * two lines loses its tail on the next write of the same field.
	 */
	public function test_value_with_newlines_is_written_on_one_line(): void {
		$before = count( file( $GLOBALS['wp_cache_config_file'] ) );

		wp_cache_setting( self::FIELD, "one\ntwo\r\nthree" );

		$this->assertCount(
			$before,
			file( $GLOBALS['wp_cache_config_file'] ),
			'The value was written across more than one physical line.'
		);
		$this->assertSame( "one\ntwo\r\nthree", $this->read_back() );
	}

	/**
	 * Rewriting a field whose stored value contained a newline must leave the file
	 * parseable. This is the failure the one-line rule exists to prevent: the first
	 * write is valid PHP either way, the second write is where a two-line entry
	 * orphans its tail and the config stops parsing.
	 */
	public function test_overwriting_a_newline_value_leaves_the_file_parseable(): void {
		wp_cache_setting( self::FIELD, "/var/www/cache/\n\$GLOBALS['wpsc_config_side_effect'] = true;" );
		wp_cache_setting( self::FIELD, 'plain' );

		$this->assertSame( 'plain', $this->read_back() );
		$this->assertArrayNotHasKey( 'wpsc_config_side_effect', $GLOBALS );
	}

	/**
	 * Null reaches the string branch — is_numeric(), is_bool() and is_array() all
	 * reject it. The (string) cast keeps it writing as '', which is what the old
	 * "'$value'" interpolation produced; without the cast var_export() would emit
	 * the bare token NULL and the config would yield null instead.
	 */
	public function test_null_is_written_as_an_empty_string(): void {
		wp_cache_setting( self::FIELD, null );

		$this->assertStringContainsString( '$' . self::FIELD . " = '';", file_get_contents( $GLOBALS['wp_cache_config_file'] ) );
		$this->assertSame( '', $this->read_back() );
	}

	/**
	 * The escaping must not change what an ordinary value looks like on disk.
	 * Every legitimate setting is written exactly as before.
	 */
	public function test_ordinary_value_is_written_unchanged(): void {
		wp_cache_setting( self::FIELD, '/var/www/html/wp-content/cache/' );

		$this->assertStringContainsString(
			'$' . self::FIELD . " = '/var/www/html/wp-content/cache/';",
			file_get_contents( $GLOBALS['wp_cache_config_file'] )
		);
	}

	/** Numeric and boolean settings keep their unquoted form. */
	public function test_numeric_and_boolean_values_are_unquoted(): void {
		wp_cache_setting( self::FIELD, 2 );
		$this->assertStringContainsString( '$' . self::FIELD . ' = 2;', file_get_contents( $GLOBALS['wp_cache_config_file'] ) );

		wp_cache_setting( self::FIELD, true );
		$this->assertStringContainsString( '$' . self::FIELD . ' = true;', file_get_contents( $GLOBALS['wp_cache_config_file'] ) );
	}
}
