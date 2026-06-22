# WP Super Cache — Context

WP Super Cache is a procedural WordPress caching plugin. Most code is global
functions (no namespaces); `src/` is reserved for classmap-autoloaded classes,
and `inc/` holds the global-function include files.

## Entry points

- **`wp-cache.php`** — the plugin main file, reduced to a thin loader: plugin
  header, constants, the `require_once` include list, `wpsc_init()` and its init
  wiring, the frontend `wp_die`/`set_home` hooks, and the
  activation/deactivation/uninstall registrations (which must stay here so
  `__FILE__` resolves to the main plugin file).
- **`wp-cache-phase2.php`** — the drop-in caching engine on the request hot path
  (served via `advanced-cache.php`). Not part of the admin/lifecycle split.

## `inc/` file map (admin & lifecycle clusters)

Each file owns one responsibility plus its own `add_action`/`add_filter`
registrations. Admin-only function clusters live in dedicated `inc/` files.

| File | Responsibility |
|------|----------------|
| `inc/boost.php` | Jetpack Boost migration notice/AJAX and the install banner |
| `inc/plugins-cookies.php` | WPSC plugin-list & extra-cookie management; feed GC |
| `inc/cache-files.php` | Admin cache-file listing, stats, sizing, cleaning; per-post invalidation |
| `inc/htaccess.php` | `.htaccess` / mod_rewrite rule generation and management |
| `inc/settings-forms.php` | Settings-page form handlers and validators |
| `inc/preload.php` | The preload subsystem (status, cron worker, scheduling, UI, AJAX) |
| `inc/lifecycle.php` | Activation/deactivation/uninstall, enable/disable, advanced-cache + config/cache-dir management, GC defaults |
| `inc/admin-notices.php` | Admin notices, plugin-row UI, the front-page site check |
| `inc/admin-ui.php` | The settings screen: menus, enqueues, the renderer (incl. inline JS), tabs, render helpers |
| `inc/delete-cache-button.php`, `inc/preload-notification.php` | Pre-existing standalone admin features |

The `inc/` files are loaded by the `require_once` block at the top of
`wp-cache.php`, which runs before `wpsc_init()`, so relocated functions are
defined in time for their hooks.

### Relocated-code conventions

- Functions in the relocated clusters keep their original global names and
  signatures — themes/plugins calling them see no change.
- `__DIR__` / `__FILE__` inside relocated code resolves to the plugin root via
  `dirname( __DIR__ )` (the `inc/` files sit one level below root).
- The relocated procedural includes carry pre-existing WPCS debt verbatim and
  are excluded from PHPCS in `.phpcs.xml.dist`; modernizing them is a tracked
  follow-up, separate from the relocation. Newly authored `inc/` files remain
  fully linted.

## Config write path

`src/config/class-config.php` (`Automattic\WPSC\Config`) is the single owner of
the on-disk `wp-cache-config.php` format. All config-file writes flow through
`Config::set()` / `Config::write_line()`. The global functions `wp_cache_setting()`
and `wp_cache_replace_line()` in `wp-cache-phase2.php` are delegating shims kept
for third-party compatibility; they lazy-load the class so engine-only loads
remain safe. `$GLOBALS` variables remain the canonical, supported read API —
reads are not migrated. See `docs/adr/0002-config-module-write-path.md` for the
full decision, constraints, and the catalog of writes that deliberately bypass
`Config::set()`.

## Tests

Two tiers (see `tests/php/README.md`):

- **CI smoke** — `composer test-php`, PHPUnit 9.6, no database; covers
  WordPress-free helpers in `wp-cache-phase2.php`.
- **Local integration** — `make test-integration`, `WP_UnitTestCase` + a real
  database in the wp-env Docker environment; the bootstrap loads `wp-cache.php`
  so the relocated procedural functions run under a real WP runtime.
- **e2e** — `composer test-e2e` (Docker/Jest); covers activation and the
  settings UI, including behaviour that depends on plugin-root path resolution.

See `docs/adr/` for the decisions behind this layout.
