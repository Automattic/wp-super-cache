# Config Module (write path) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Introduce a single `Automattic\WPSC\Config` module that owns reading/writing the on-disk `wp-cache-config.php` file, route the two public write functions through it, and migrate the in-plugin direct callers to a typed `Config::set()` API — without changing any runtime behaviour or any read site.

**Architecture:** Additive abstraction. `wp_cache_setting()` and `wp_cache_replace_line()` (both in `wp-cache-phase2.php`) stay as public global functions but become thin delegations to `Config`. The dangerous regex file-rewriter moves verbatim into `Config::write_line()`; the per-type value formatting moves into `Config::format_value()`; `Config::set()` reproduces `wp_cache_setting()` exactly (update `$GLOBALS[$field]`, format, write). Globals remain the canonical store and supported read API. In-plugin direct `wp_cache_replace_line()` callers that are simple key/value writes migrate to `Config::set()`; non-key/value callers (line removal, `define` rewrites) and all third-party/`plugins/` callers stay on the raw (now-delegating) function.

**Tech Stack:** PHP 7.x+, WordPress plugin (no runtime autoloader — explicit `require_once`; Composer classmap is dev/test-only), PHPUnit 9.6, wp-env/Docker integration tier.

## Global Constraints

- **No runtime autoloader.** Load the class with explicit `require_once`, mirroring `plugins/jetpack.php` loading `src/device-detection/class-device-detection.php`. Composer `classmap: ["src/"]` covers it under test only.
- **`src/` convention:** namespace `Automattic\WPSC`, filename `class-config.php`, located at `src/config/class-config.php`.
- **Byte-identical write behaviour.** Every Phase B commit must leave the resulting config-file contents and `$GLOBALS[$field]` value byte-for-byte identical to today. The Phase A characterization suite is the gate.
- **Public surface is frozen.** `wp_cache_setting( $field, $value )` and `wp_cache_replace_line( $old, $new, $my_file )` keep identical signatures and return semantics (third-party cache plugins call them).
- **Write path only.** Do NOT touch any read site (`global $foo;`, `$GLOBALS['foo']`, settings-map dynamic reads). No `Config::get()` in this plan.
- **No new locking/concurrency.** Preserve the existing `tempnam` → `rename` → `chmod` → `opcache_invalidate` sequence exactly.
- **Stay-on-raw callers:** all `plugins/*` callers, the 4 `wp-cache-phase2.php` internal (debug-log) callers, and `rest/*` direct `wp_cache_replace_line()` callers are NOT migrated. Third-party + non-key/value.
- **Tiering:** Config write tests run in the **integration tier** (`tests/php/integration/`, `make test-integration`) — the rewriter calls `wp_rand()` and `set_transient()` unconditionally/optionally, which the no-WordPress smoke bootstrap does not provide. CI stays lint + smoke.
- **Verification commands:** `composer lint` (changed-lines PHPCS — check full output, not just the tail), `composer test-php` (smoke), `make test-integration` (local WP+DB), `composer test-e2e` (settings specs — essential for Phase C).
- **No `make pre-build`/`publish`/tag/SVN** steps anywhere.

---

## File Structure

- **Create** `src/config/class-config.php` — the `Automattic\WPSC\Config` class. Sole owner of the config-file format: `set()`, `format_value()`, `write_line()`. ~120 lines.
- **Create** `tests/php/integration/ConfigWritePathTest.php` — Phase A characterization tests against the existing public functions (`wp_cache_setting` / `wp_cache_replace_line`). This is the regression gate for the delegations.
- **Create** `tests/php/integration/ConfigClassTest.php` — Phase B direct unit tests against `Config::set()` / `Config::format_value()` / `Config::write_line()`, mirroring Phase A.
- **Modify** `wp-cache-phase2.php:1445-1565` — replace `wp_cache_setting()` and `wp_cache_replace_line()` bodies with class-guarded delegations.
- **Modify** `wp-cache.php` (after the `inc/` requires, ~line 43) — explicit `require_once` of the Config class for the admin write path.
- **Modify (Phase C)** `inc/lifecycle.php`, `inc/settings-forms.php`, `inc/admin-ui.php`, `inc/preload.php`, `inc/htaccess.php`, `inc/plugins-cookies.php`, `ossdl-cdn.php` — migrate simple key/value writes to `Config::set()`.
- **Modify (Phase D)** `CONTEXT.md`, add `docs/adr/0002-config-module-write-path.md`.

---

## Phase A — Lock down current write behaviour first

### Task 1: Characterization tests for the existing write path

**Files:**
- Test: `tests/php/integration/ConfigWritePathTest.php` (create)

**Interfaces:**
- Consumes: existing globals `wp_cache_setting( $field, $value )`, `wp_cache_replace_line( $old, $new, $my_file )`; globals `$GLOBALS['wp_cache_config_file']`, `$GLOBALS['cache_path']`.
- Produces: a green characterization suite that later tasks re-run unchanged.

The temp-config helper mirrors the proven pattern in `SettingsFormUpdatersTest.php` (sets both `wp_cache_config_file` and `cache_path`, the latter used by the rewriter's `tempnam`).

- [ ] **Step 1: Write the failing test file**

```php
<?php
/**
 * Characterization tests for the config-file WRITE path as it exists today.
 *
 * Pins the byte-exact on-disk result and the populated $GLOBALS[$field] for
 * wp_cache_setting() (each value type) and for wp_cache_replace_line()'s three
 * branches (replace existing line, unchanged early-return, not-found append).
 * Re-run UNCHANGED after the Config delegations land to prove byte-identical
 * behaviour.
 *
 * Integration tier: the rewriter calls wp_rand() and (optionally) set_transient().
 *
 * @package automattic/wp-super-cache
 */
class ConfigWritePathTest extends WP_UnitTestCase {

	/** @var string[] */
	private $temp_dirs = array();

	public function tear_down() {
		foreach ( $this->temp_dirs as $dir ) {
			$this->rrmdir( $dir );
		}
		$this->temp_dirs = array();
		parent::tear_down();
	}

	/**
	 * Seed a writable temp config file and point the write-path globals at it.
	 *
	 * @param string[] $lines Config lines (without the opening PHP tag).
	 * @return string Absolute path to the config file.
	 */
	private function make_config_file( array $lines ) {
		$dir = trailingslashit( get_temp_dir() ) . 'wpsc-cfg-' . uniqid();
		mkdir( $dir, 0700, true );
		$this->temp_dirs[] = $dir;

		$config = trailingslashit( $dir ) . 'wp-cache-config.php';
		file_put_contents( $config, "<?php\n" . implode( "\n", $lines ) . "\n" );

		$GLOBALS['cache_path']           = trailingslashit( $dir );
		$GLOBALS['wp_cache_config_file'] = $config;

		return $config;
	}

	private function rrmdir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( scandir( $dir ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $dir . '/' . $entry;
			is_dir( $path ) ? $this->rrmdir( $path ) : unlink( $path );
		}
		rmdir( $dir );
	}

	public function test_setting_numeric_writes_unquoted_and_sets_global() {
		$config = $this->make_config_file( array( '$wp_cache_mod_rewrite = 0;' ) );

		$this->assertTrue( wp_cache_setting( 'wp_cache_mod_rewrite', 1 ) );
		$this->assertSame( 1, $GLOBALS['wp_cache_mod_rewrite'] );
		$this->assertStringContainsString( '$wp_cache_mod_rewrite = 1;', file_get_contents( $config ) );
	}

	public function test_setting_boolean_writes_true_false_literal() {
		$config = $this->make_config_file( array( '$wp_supercache_304 = false;' ) );

		wp_cache_setting( 'wp_supercache_304', true );
		$this->assertSame( true, $GLOBALS['wp_supercache_304'] );
		$this->assertStringContainsString( '$wp_supercache_304 = true;', file_get_contents( $config ) );
	}

	public function test_setting_array_writes_collapsed_var_export() {
		$config = $this->make_config_file( array( '$cache_rejected_uri = array();' ) );

		wp_cache_setting( 'cache_rejected_uri', array( 'wp-admin', 'feed' ) );
		$this->assertSame( array( 'wp-admin', 'feed' ), $GLOBALS['cache_rejected_uri'] );
		$this->assertStringContainsString(
			"\$cache_rejected_uri = array ( 0 => 'wp-admin', 1 => 'feed', );",
			file_get_contents( $config )
		);
	}

	public function test_setting_string_writes_single_quoted() {
		$config = $this->make_config_file( array( "\$wp_cache_debug_ip = '';" ) );

		wp_cache_setting( 'wp_cache_debug_ip', '203.0.113.5' );
		$this->assertSame( '203.0.113.5', $GLOBALS['wp_cache_debug_ip'] );
		$this->assertStringContainsString( "\$wp_cache_debug_ip = '203.0.113.5';", file_get_contents( $config ) );
	}

	public function test_replace_line_replaces_matching_line() {
		$config = $this->make_config_file( array( '$wp_cache_front_page_checks = 0;' ) );

		$this->assertTrue(
			wp_cache_replace_line( '^ *\$wp_cache_front_page_checks', '$wp_cache_front_page_checks = 1;', $config )
		);
		$this->assertStringContainsString( '$wp_cache_front_page_checks = 1;', file_get_contents( $config ) );
		$this->assertStringNotContainsString( '$wp_cache_front_page_checks = 0;', file_get_contents( $config ) );
	}

	public function test_replace_line_unchanged_is_noop_early_return() {
		$config = $this->make_config_file( array( '$wp_cache_mobile = 1;' ) );
		$before = file_get_contents( $config );

		$this->assertTrue( wp_cache_replace_line( '^ *\$wp_cache_mobile', '$wp_cache_mobile = 1;', $config ) );
		$this->assertSame( $before, file_get_contents( $config ) );
	}

	public function test_replace_line_not_found_appends_after_assignments() {
		$config = $this->make_config_file( array( '$existing = 1;' ) );

		$this->assertTrue( wp_cache_replace_line( '^ *\$brand_new_key', '$brand_new_key = 5;', $config ) );
		$this->assertStringContainsString( '$brand_new_key = 5;', file_get_contents( $config ) );
		$this->assertStringContainsString( '$existing = 1;', file_get_contents( $config ) );
	}

	public function test_replace_line_missing_file_returns_false() {
		$this->assertFalse( wp_cache_replace_line( '^ *\$x', '$x = 1;', '/no/such/wp-cache-config.php' ) );
	}
}
```

- [ ] **Step 2: Run the suite to verify it PASSES against current code**

Run: `make test-integration -- --filter ConfigWritePathTest` (or the raw `docker exec ... vendor/bin/phpunit -c phpunit-integration.9.xml.dist --filter ConfigWritePathTest` fallback from the handoff).
Expected: all 8 tests PASS. (This task characterizes existing behaviour, so the suite is green from the start — it is the regression net, not a red-then-green TDD cycle.)

- [ ] **Step 3: Confirm it runs under the smoke tier guard (must SKIP/ABSENT, not fatal)**

Run: `composer test-php`
Expected: PASS — `ConfigWritePathTest` is in `tests/php/integration/` and is not collected by the smoke config. Confirm no fatal from `wp_rand`.

- [ ] **Step 4: Commit**

```bash
git add tests/php/integration/ConfigWritePathTest.php
git commit -m "test: characterize config-file write path before Config module (#1062)"
```

---

## Phase B — Introduce the owner (no caller changes yet)

### Task 2: Add the `Config` class + mirrored unit tests

**Files:**
- Create: `src/config/class-config.php`
- Create: `tests/php/integration/ConfigClassTest.php`

**Interfaces:**
- Produces:
  - `Automattic\WPSC\Config::set( string $field, mixed $value, ?string $file = null ): bool` — updates `$GLOBALS[$field]`, formats by type, writes; returns the writer result. `$file` defaults to `$GLOBALS['wp_cache_config_file']`.
  - `Automattic\WPSC\Config::format_value( mixed $value ): string` — returns the PHP literal exactly as `wp_cache_setting()` builds it today (numeric → `(string)`, bool → `true`/`false`, array/object → whitespace-collapsed `var_export`, else → single-quoted).
  - `Automattic\WPSC\Config::write_line( string $old, string $new, string $file ): bool` — the verbatim body of today's `wp_cache_replace_line()`.
- Consumes: WordPress runtime `wp_rand()`, `set_transient()`, `wp_cache_debug()`, `__()`, `is_writeable_ACLSafe()` (all already used by the original function).

- [ ] **Step 1: Write the failing test file**

```php
<?php
/**
 * Direct unit tests for the Config module. Mirror ConfigWritePathTest but call
 * the typed Config API that Phase C callers will use.
 *
 * @package automattic/wp-super-cache
 */
class ConfigClassTest extends WP_UnitTestCase {

	/** @var string[] */
	private $temp_dirs = array();

	public function tear_down() {
		foreach ( $this->temp_dirs as $dir ) {
			$this->rrmdir( $dir );
		}
		$this->temp_dirs = array();
		parent::tear_down();
	}

	private function make_config_file( array $lines ) {
		$dir = trailingslashit( get_temp_dir() ) . 'wpsc-cfg-' . uniqid();
		mkdir( $dir, 0700, true );
		$this->temp_dirs[] = $dir;
		$config = trailingslashit( $dir ) . 'wp-cache-config.php';
		file_put_contents( $config, "<?php\n" . implode( "\n", $lines ) . "\n" );
		$GLOBALS['cache_path']           = trailingslashit( $dir );
		$GLOBALS['wp_cache_config_file'] = $config;
		return $config;
	}

	private function rrmdir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( scandir( $dir ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $dir . '/' . $entry;
			is_dir( $path ) ? $this->rrmdir( $path ) : unlink( $path );
		}
		rmdir( $dir );
	}

	public function test_format_value_numeric() {
		$this->assertSame( '5', \Automattic\WPSC\Config::format_value( 5 ) );
		$this->assertSame( '0', \Automattic\WPSC\Config::format_value( 0 ) );
	}

	public function test_format_value_boolean() {
		$this->assertSame( 'true', \Automattic\WPSC\Config::format_value( true ) );
		$this->assertSame( 'false', \Automattic\WPSC\Config::format_value( false ) );
	}

	public function test_format_value_array_is_whitespace_collapsed() {
		$this->assertSame(
			"array ( 0 => 'a', 1 => 'b', )",
			\Automattic\WPSC\Config::format_value( array( 'a', 'b' ) )
		);
	}

	public function test_format_value_string_single_quoted() {
		$this->assertSame( "'hello'", \Automattic\WPSC\Config::format_value( 'hello' ) );
	}

	public function test_set_updates_global_and_file() {
		$config = $this->make_config_file( array( '$wp_cache_mobile = 0;' ) );
		$this->assertTrue( \Automattic\WPSC\Config::set( 'wp_cache_mobile', 1 ) );
		$this->assertSame( 1, $GLOBALS['wp_cache_mobile'] );
		$this->assertStringContainsString( '$wp_cache_mobile = 1;', file_get_contents( $config ) );
	}

	public function test_set_array_matches_legacy_format() {
		$config = $this->make_config_file( array( '$cache_acceptable_files = array();' ) );
		\Automattic\WPSC\Config::set( 'cache_acceptable_files', array( 'wp-comments-popup.php' ) );
		$this->assertStringContainsString(
			"\$cache_acceptable_files = array ( 0 => 'wp-comments-popup.php', );",
			file_get_contents( $config )
		);
	}

	public function test_write_line_replaces() {
		$config = $this->make_config_file( array( '$x = 0;' ) );
		$this->assertTrue( \Automattic\WPSC\Config::write_line( '^ *\$x', '$x = 9;', $config ) );
		$this->assertStringContainsString( '$x = 9;', file_get_contents( $config ) );
	}

	public function test_write_line_missing_file_returns_false() {
		$this->assertFalse( \Automattic\WPSC\Config::write_line( '^ *\$x', '$x = 1;', '/no/such/file.php' ) );
	}
}
```

- [ ] **Step 2: Run to verify it FAILS**

Run: `make test-integration -- --filter ConfigClassTest`
Expected: FAIL — `Error: Class "Automattic\WPSC\Config" not found`.

- [ ] **Step 3: Write the class**

Create `src/config/class-config.php`. `write_line()` is the verbatim body of `wp_cache_replace_line()` (`wp-cache-phase2.php:1463-1565`) with the parameter renamed `$my_file` → `$file`. `format_value()` and `set()` reproduce `wp_cache_setting()` exactly.

```php
<?php
/**
 * Owner of the on-disk wp-cache-config.php write path.
 *
 * The single place that knows the config file's format. Public write functions
 * (wp_cache_setting / wp_cache_replace_line) delegate here. Globals remain the
 * canonical store and supported read API; set() always updates $GLOBALS[$field].
 *
 * @package automattic/wp-super-cache
 */

namespace Automattic\WPSC;

/**
 * Config file write owner.
 */
class Config {

	/**
	 * Set a config field: update the runtime global, format the value, persist.
	 *
	 * Reproduces wp_cache_setting() exactly.
	 *
	 * @param string      $field Config field name (no leading $).
	 * @param mixed       $value Value to store.
	 * @param string|null $file  Config file path. Defaults to $GLOBALS['wp_cache_config_file'].
	 * @return bool True on success (or unchanged), false on write failure.
	 */
	public static function set( $field, $value, $file = null ) {
		$GLOBALS[ $field ] = $value;

		if ( null === $file ) {
			$file = $GLOBALS['wp_cache_config_file'];
		}

		return self::write_line(
			'^ *\$' . $field,
			"\$$field = " . self::format_value( $value ) . ';',
			$file
		);
	}

	/**
	 * Format a value as the PHP literal written into the config file.
	 *
	 * Reproduces wp_cache_setting()'s per-type formatting byte-for-byte.
	 *
	 * @param mixed $value Value to format.
	 * @return string PHP literal (without trailing semicolon).
	 */
	public static function format_value( $value ) {
		if ( is_numeric( $value ) ) {
			return (string) $value;
		} elseif ( is_bool( $value ) ) {
			return $value === true ? 'true' : 'false';
		} elseif ( is_object( $value ) || is_array( $value ) ) {
			$text = var_export( $value, true );
			return preg_replace( '/[\s]+/', ' ', $text );
		} else {
			return "'$value'";
		}
	}

	/**
	 * Rewrite a single line of the config file via per-line regex.
	 *
	 * Verbatim body of the legacy wp_cache_replace_line(); the atomic
	 * tempnam -> write -> rename -> chmod -> opcache_invalidate sequence is
	 * preserved exactly.
	 *
	 * @param string $old  Regex (without delimiters) matching the line to replace.
	 * @param string $new  Replacement line (no trailing newline).
	 * @param string $file Config file path.
	 * @return bool True on success/unchanged, false on failure.
	 */
	public static function write_line( $old, $new, $file ) {
		// PASTE the verbatim body of wp_cache_replace_line() here, renaming the
		// $my_file parameter to $file throughout. Do not alter any logic.
	}
}
```

When pasting `write_line()`, copy `wp-cache-phase2.php:1464-1564` exactly and rename `$my_file` → `$file`. Do not "improve" it — byte-identical output is the contract.

- [ ] **Step 4: Wire runtime loading from `wp-cache.php`**

In `wp-cache.php`, immediately after the block of `inc/` requires (after line 43, `require_once __DIR__ . '/inc/admin-ui.php';`), add:

```php
require_once __DIR__ . '/src/config/class-config.php';
```

- [ ] **Step 5: Run the class tests to verify they PASS**

Run: `make test-integration -- --filter ConfigClassTest`
Expected: all tests PASS.

- [ ] **Step 6: Run the Phase A suite — still green (class loadable, unused)**

Run: `make test-integration -- --filter ConfigWritePathTest`
Expected: PASS unchanged.

- [ ] **Step 7: Commit**

```bash
git add src/config/class-config.php tests/php/integration/ConfigClassTest.php wp-cache.php
git commit -m "feat: add Automattic\\WPSC\\Config write-path module (#1062)"
```

### Task 3: Delegate `wp_cache_replace_line()` to the module

**Files:**
- Modify: `wp-cache-phase2.php:1463-1565`

**Interfaces:**
- Consumes: `Automattic\WPSC\Config::write_line()`.
- Produces: unchanged `wp_cache_replace_line( $old, $new, $my_file )` signature; all 74 callers route through `Config` transparently.

- [ ] **Step 1: Replace the function body with a class-guarded delegation**

Replace the entire `wp_cache_replace_line()` function (`wp-cache-phase2.php:1463-1565`) with:

```php
function wp_cache_replace_line( $old, $new, $my_file ) {
	if ( ! class_exists( 'Automattic\WPSC\Config' ) ) {
		if ( file_exists( WPCACHEHOME . 'src/config/class-config.php' ) ) {
			require_once WPCACHEHOME . 'src/config/class-config.php';
		}
	}

	return \Automattic\WPSC\Config::write_line( $old, $new, $my_file );
}
```

The lazy `class_exists` guard keeps the phase2/debug-log write path working when only the engine is loaded (e.g. `advanced-cache.php` defines `WPCACHEHOME`).

- [ ] **Step 2: Run both suites — must be byte-identical**

Run: `make test-integration -- --filter ConfigWritePathTest && make test-integration -- --filter ConfigClassTest`
Expected: all PASS, no changes.

- [ ] **Step 3: Lint**

Run: `composer lint`
Expected: pass. (Note: `wp-cache-phase2.php` is NOT in the `inc/` exclusion list, so changed lines are checked. Check full output.)

- [ ] **Step 4: Commit**

```bash
git add wp-cache-phase2.php
git commit -m "refactor: delegate wp_cache_replace_line() to Config::write_line() (#1062)"
```

### Task 4: Delegate `wp_cache_setting()` to the module

**Files:**
- Modify: `wp-cache-phase2.php:1445-1461`

**Interfaces:**
- Consumes: `Automattic\WPSC\Config::set()`.
- Produces: unchanged `wp_cache_setting( $field, $value )` signature; after this commit there is exactly one owner of the file format and all 117+ write sites flow through `Config` via the two shims.

- [ ] **Step 1: Replace the function body with a class-guarded delegation**

Replace the entire `wp_cache_setting()` function (`wp-cache-phase2.php:1445-1461`) with:

```php
function wp_cache_setting( $field, $value ) {
	global $wp_cache_config_file;

	if ( ! class_exists( 'Automattic\WPSC\Config' ) ) {
		if ( file_exists( WPCACHEHOME . 'src/config/class-config.php' ) ) {
			require_once WPCACHEHOME . 'src/config/class-config.php';
		}
	}

	return \Automattic\WPSC\Config::set( $field, $value, $wp_cache_config_file );
}
```

Passing `$wp_cache_config_file` explicitly preserves the original's `global $wp_cache_config_file` source byte-for-byte (rather than relying on `Config::set`'s `$GLOBALS` default).

- [ ] **Step 2: Run both suites — byte-identical**

Run: `make test-integration -- --filter ConfigWritePathTest && make test-integration -- --filter ConfigClassTest`
Expected: all PASS.

- [ ] **Step 3: Run the existing settings-form net (no regression)**

Run: `make test-integration -- --filter SettingsFormUpdatersTest`
Expected: PASS unchanged — these go through `wp_cache_setting` / `wp_cache_replace_line`.

- [ ] **Step 4: Lint + commit**

Run: `composer lint`

```bash
git add wp-cache-phase2.php
git commit -m "refactor: delegate wp_cache_setting() to Config::set() (#1062)"
```

---

## Phase C — Migrate direct callers to the typed API

**Migration rule (applies to every Phase C task).** A call is migratable ONLY if it is a simple key/value write of the exact form:

```php
wp_cache_replace_line( '^ *\$FIELD', "\$FIELD = <formatted value>;", $file );
```

where `<formatted value>` is what `Config::format_value()` produces for the value. Convert it to:

```php
\Automattic\WPSC\Config::set( 'FIELD', $value, $file );
```

**Leave on the raw function (do NOT migrate)** any call that:
- removes a line (replacement is `''`),
- rewrites a `define(...)` or `if ( ! defined ... )` line,
- writes a hand-built literal that `format_value()` would not reproduce (e.g. embedded expressions, multi-line, manual quoting that differs),
- is in `plugins/*`, `rest/*`, or is one of the 4 internal `wp-cache-phase2.php` debug-log calls.

For every migrated call, confirm `Config::format_value($value)` reproduces the original replacement string's value portion. When in doubt, leave it on the raw function and list it in the commit body as an explicit exception.

**Per-task workflow (same for Tasks 5–9):**
1. List the cluster's calls: `grep -n "wp_cache_replace_line(" inc/<file>.php`.
2. For each, classify migratable vs exception per the rule above.
3. Convert the migratable ones to `Config::set()`.
4. Run the relevant integration suite + e2e settings specs.
5. Commit; list exceptions in the commit body.

### Task 5: Lifecycle writes (`inc/lifecycle.php`)

**Files:**
- Modify: `inc/lifecycle.php` (8 `wp_cache_replace_line` + 10 `wp_cache_setting` sites)

The `wp_cache_setting` sites already delegate (Task 4) — leave them unless converting for consistency is trivial; the deliverable is migrating the **direct `wp_cache_replace_line`** calls.

- [ ] **Step 1: List and classify**

Run: `grep -n "wp_cache_replace_line(" inc/lifecycle.php`
For each line, apply the migration rule. Activation/deactivation/enable-disable toggles (e.g. `wp_cache_mod_rewrite`, `wp_cache_enabled`) are typically simple numeric/bool sets → migratable.

- [ ] **Step 2: Convert each migratable call**

For each migratable site, replace
`wp_cache_replace_line( '^ *\$FIELD', "\$FIELD = $value;", $file )`
with
`\Automattic\WPSC\Config::set( 'FIELD', $value, $file )`.
Preserve the surrounding control flow and the `$file`/config-file variable used at that site.

- [ ] **Step 3: Run integration + e2e**

Run: `make test-integration` then `composer test-e2e`
Expected: PASS. e2e exercises activation/lifecycle — essential here.

- [ ] **Step 4: Lint + commit**

Run: `composer lint`
(`inc/lifecycle.php` is in the `.phpcs.xml.dist` exclusion list per #1066, so full-file PHPCS is excluded; `composer lint` still checks changed lines via the branch-diff sub-check — read full output.)

```bash
git add inc/lifecycle.php
git commit -m "refactor: migrate lifecycle config writes to Config::set() (#1062)"
```

### Task 6: Settings-form writes (`inc/settings-forms.php`)

**Files:**
- Modify: `inc/settings-forms.php` (20 `wp_cache_replace_line` sites)

- [ ] **Step 1: List and classify**

Run: `grep -n "wp_cache_replace_line(" inc/settings-forms.php`
Covers the rejected/accepted-list, tracking-parameter, and debug-settings updaters. Watch for any non-key/value rewrites (leave on raw).

- [ ] **Step 2: Convert migratable calls** (per the migration rule).

- [ ] **Step 3: Run the strongest net for this cluster**

Run: `make test-integration -- --filter SettingsFormUpdatersTest` then `make test-integration` then `composer test-e2e`
Expected: PASS. `SettingsFormUpdatersTest` directly pins this cluster's parse→persist contract.

- [ ] **Step 4: Lint + commit**

```bash
git add inc/settings-forms.php
git commit -m "refactor: migrate settings-form config writes to Config::set() (#1062)"
```

### Task 7: Admin-UI / manager writes (`inc/admin-ui.php`)

**Files:**
- Modify: `inc/admin-ui.php` (28 `wp_cache_replace_line` sites — the largest cluster, the `wp_cache_manager_updates` save path)

This cluster is the highest-risk: most sites, most likely to contain subtle non-key/value rewrites. Classify each carefully; leave anything non-trivial on the raw function and list it.

- [ ] **Step 1: List and classify** — `grep -n "wp_cache_replace_line(" inc/admin-ui.php`

- [ ] **Step 2: Convert migratable calls** (per the migration rule). Leave exceptions on raw.

- [ ] **Step 3: Run integration + e2e**

Run: `make test-integration` then `composer test-e2e`
Expected: PASS. The settings save path is exercised by the e2e settings specs.

- [ ] **Step 4: Lint + commit** (list any exceptions in the body)

```bash
git add inc/admin-ui.php
git commit -m "refactor: migrate admin-UI config writes to Config::set() (#1062)"
```

### Task 8: Preload writes (`inc/preload.php`)

**Files:**
- Modify: `inc/preload.php` (8 `wp_cache_setting` sites; 0 direct `wp_cache_replace_line`)

Since `inc/preload.php` has no direct `wp_cache_replace_line` calls, this task is the optional consistency conversion of its `wp_cache_setting` calls to `Config::set()`. If the maintainer skips the cosmetic Task 10, **skip this task too** — the preload writes already delegate.

- [ ] **Step 1: List** — `grep -n "wp_cache_setting(" inc/preload.php`

- [ ] **Step 2: (Optional) Convert** `wp_cache_setting( 'FIELD', $value )` → `\Automattic\WPSC\Config::set( 'FIELD', $value )`.

- [ ] **Step 3: Run** — `make test-integration -- --filter PreloadStatusTest` then `composer test-e2e`

- [ ] **Step 4: Lint + commit**

```bash
git add inc/preload.php
git commit -m "refactor: route preload config writes through Config::set() (#1062)"
```

### Task 9: Remaining scattered writes

**Files:**
- Modify: `inc/htaccess.php` (1 `wp_cache_replace_line` + 2 `wp_cache_setting`), `inc/plugins-cookies.php` (5 `wp_cache_setting`), `ossdl-cdn.php` (1 `wp_cache_setting`)

htaccess/mod-rewrite flags are the likeliest to contain `define` rewrites — classify carefully.

- [ ] **Step 1: List per file**

Run: `grep -n "wp_cache_replace_line(\|wp_cache_setting(" inc/htaccess.php inc/plugins-cookies.php ossdl-cdn.php`

- [ ] **Step 2: Convert migratable calls**; leave `define`/conditional rewrites on raw.

- [ ] **Step 3: Run integration + e2e**

Run: `make test-integration -- --filter HtaccessRulesTest` then `make test-integration` then `composer test-e2e`

- [ ] **Step 4: Lint + commit** (list exceptions)

```bash
git add inc/htaccess.php inc/plugins-cookies.php ossdl-cdn.php
git commit -m "refactor: migrate remaining scattered config writes to Config::set() (#1062)"
```

### Task 10 (optional, cosmetic): Retire internal `wp_cache_setting()` usage

Convert the remaining internal `wp_cache_setting()` calls to `Config::set()` for consistency. `wp_cache_setting()` stays as a public back-compat alias. Drop this task if churn outweighs value — the maintainer's call.

- [ ] **Step 1:** `grep -rn "wp_cache_setting(" inc/ rest/ ossdl-cdn.php` (exclude the definition).
- [ ] **Step 2:** Convert each to `\Automattic\WPSC\Config::set(...)`.
- [ ] **Step 3:** `make test-integration && composer test-e2e`.
- [ ] **Step 4:** `composer lint` + commit `refactor: use Config::set() for internal config writes (#1062)`.

---

## Phase D — Document

### Task 11: Document the module and the contract

**Files:**
- Modify: `CONTEXT.md`
- Create: `docs/adr/0002-config-module-write-path.md`

- [ ] **Step 1: Add an ADR**

Create `docs/adr/0002-config-module-write-path.md` (follow the format of `docs/adr/0001-split-wp-cache-into-inc-files.md`) stating:
- All config-file writes go through `Automattic\WPSC\Config`.
- `wp_cache_setting()` / `wp_cache_replace_line()` remain as public delegating shims (third-party surface).
- The globals / `$GLOBALS` remain a permanent, supported read API; reads are intentionally NOT migrated.
- The config file stays a flat pre-WordPress PHP file (drop-in `@include`s it before WP boots; cannot move to `wp_options`).
- No locking added; the existing atomic `tempnam`+`rename` is preserved (single-human admin writes).
- Constraint recorded for a future `Config::get()`: it must be a typed read-through over `$GLOBALS` (single source of truth), not a private parsed copy.

- [ ] **Step 2: Update `CONTEXT.md`**

Add a short "Config write path" entry pointing at `src/config/class-config.php` as the owner and linking the ADR.

- [ ] **Step 3: Commit**

```bash
git add CONTEXT.md docs/adr/0002-config-module-write-path.md
git commit -m "docs: record Config write-path module and contract (#1062)"
```

---

## Self-Review

- **Spec coverage:** Phase A (commit 1) → Task 1. Phase B (commits 2–4) → Tasks 2–4. Phase C (commits 5–10) → Tasks 5–10. Phase D (commit 11) → Task 11. Decision Document points (globals permanent, flat file, no locking, shims stay, no autoloader) → Global Constraints + Task 11 ADR. Out-of-scope items (read migration, `Config::get()`, locking) → excluded and recorded.
- **Type consistency:** `Config::set($field, $value, $file=null)`, `Config::format_value($value)`, `Config::write_line($old, $new, $file)` used identically in Tasks 2, 3, 4, and the Phase C migration rule.
- **Drift noted:** issue says 64 direct `wp_cache_replace_line` callers; actual is 74 (28 admin-ui + 20 settings-forms + 8 lifecycle + 1 htaccess in-plugin migratable; 11 plugins/phase2/rest stay on raw). Clusters unchanged.
- **Placeholders:** the only deliberate "fill at execution" point is the verbatim paste of the rewriter body into `write_line()` (Task 2 Step 3) — sourced exactly from `wp-cache-phase2.php:1464-1564` — and the per-site classification in Phase C, which is inherent to the work (the issue itself flags that subtle non-key/value cases hide in admin-ui/settings-forms and must be inspected, not blind-converted).
