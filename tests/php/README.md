# PHP tests

WP Super Cache has two tiers of PHP tests, split by what they need to run.

| Tier | Needs | Where it runs | Command |
| --- | --- | --- | --- |
| **Smoke** | Nothing but PHP | CI (PHP 8.2–8.5) + locally | `composer test-php` / `make test` |
| **Integration** | Real WordPress + a database (Docker) | Locally only | `make test-integration` |

CI runs the smoke tier (plus PHP CodeSniffer lint). It does **not** run the
integration tier and does **not** start a database.

The caching engine lives in procedural files (`wp-cache-phase2.php`, …) that are
not autoloaded. Each tier's bootstrap loads them; test files do not need to
`require` them.

---

## Smoke tier (`tests/php/smoke/`)

Fast, no database, no WordPress runtime. Bootstrap:
`tests/php/bootstrap-smoke.php`. It loads the procedural files and provides a
tiny, pure-PHP filter registry (`add_filter()` / `apply_filters()` /
`remove_all_filters()`) standing in for WordPress's hook system — enough to test
WordPress-free functions and to inject filter input.

Use this tier for functions that call only other WPSC functions plus PHP
built-ins/superglobals. The suite is strict: any PHP notice, warning, or
deprecation fails the test, so define every global/superglobal the function
under test reads.

### Add a smoke test

Create `tests/php/smoke/<Name>Test.php`:

```php
<?php
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;

#[CoversFunction( 'my_wpsc_function' )]
class MyWpscFunctionTest extends TestCase {

	protected function setUp(): void {
		// Define every global/superglobal the function reads.
		$_SERVER['REQUEST_URI'] = '/example/';
	}

	public function test_it_does_the_thing(): void {
		$this->assertSame( 'expected', my_wpsc_function() );
	}
}
```

To inject hostile/edge filter input (e.g. the `supercache_filename()` #1050
regression guard), register a filter and assert the sanitised result:

```php
add_filter( 'supercache_filename_str', static fn() => '../../etc/passwd' );
$this->assertSame( 'indexetcpasswd.html', supercache_filename() );
```

> Watch for `static` caches inside the procedural functions (e.g.
> `wpsc_get_accept_header()`, `wp_cache_get_cookies_values()`,
> `wpsc_is_in_cache_directory()`): the first call in the process locks the cached
> value, so keep the relevant inputs constant within a test class.

Run it: `composer test-php`.

---

## Integration tier (`tests/php/integration/`)

Real WordPress (`WP_UnitTestCase`), real hook system, real database. Runs inside
the wp-env Docker environment via `make test-integration`. Bootstrap:
`tests/php/bootstrap-integration.php`, which loads the WordPress test library
(shipped by `wp-phpunit/wp-phpunit`) and the procedural files under a genuine WP
runtime. There is **no** `apply_filters()` stub here — WordPress core defines the
real one.

Use this tier when the behaviour needs WordPress: options, transients, real
filters/actions, or the filesystem against a real install.

### Add an integration test

Create `tests/php/integration/<Name>Test.php`:

```php
<?php
class MyWpscIntegrationTest extends WP_UnitTestCase {

	public function test_option_roundtrip() {
		update_option( 'my_option', 'value' );
		$this->assertSame( 'value', get_option( 'my_option' ) );
	}
}
```

Run it: `make test-integration` (auto-starts wp-env).

The test database connection defaults to the wp-env test database; override with
the `WP_PHPUNIT__DB_HOST`, `WP_PHPUNIT__DB_NAME`, `WP_PHPUNIT__DB_USER`,
`WP_PHPUNIT__DB_PASSWORD`, and `WP_PHPUNIT__ABSPATH` environment variables (see
`tests/php/wp-tests-config.php`).
