# 2. Config module write-path

Date: 2026-06-22

## Status

Accepted

## Context

After splitting `wp-cache.php` into focused `inc/` files (ADR 0001), config-file
writes were still scattered across those files as direct calls to the
`wp_cache_setting()` and `wp_cache_replace_line()` global functions. Neither
function was tested, their on-disk format rules lived only in `wp_cache_setting()`
at line ~1400 of `wp-cache-phase2.php`, and there was no clear owner of the
`wp-cache-config.php` file format. Issue #1062 called for consolidating the write
path into a testable class.

The constraint the config file is operating under is fundamental: `wp-cache-phase1.php`
`@include`s it before WordPress boots — no database, no `wp_options` — so it must
remain a flat, pre-WordPress PHP file that sets global variables.

## Decision

### `Automattic\WPSC\Config` owns the config-file write path

`src/config/class-config.php` (`Automattic\WPSC\Config`) is the single owner of
the on-disk `wp-cache-config.php` format. All config-file writes are expected to
flow through it. Its public surface:

- **`Config::set( $field, $value, $file = null )`** — updates `$GLOBALS[$field]`,
  formats the value, and writes the line. The `$GLOBALS` update is unconditional:
  the runtime state and the file are always kept in sync.
- **`Config::format_value( $value )`** — encodes the PHP literal written to the
  file: numeric → unquoted; bool → `true`/`false`; array/object → whitespace-
  collapsed `var_export`; else → single-quoted string.
- **`Config::write_line( $old, $new, $file )`** — the atomic line rewriter: reads
  the file, replaces the line matching `$old` (a bare regex), writes to a
  `tempnam`-generated temp file, `rename`s it into place, `chmod`s it to match
  the original permissions, and calls `opcache_invalidate`.

### `wp_cache_setting()` and `wp_cache_replace_line()` remain as delegating shims

Both functions stay in `wp-cache-phase2.php` with their original signatures. They
are part of the surface that third-party cache plugins call, so they are not
removed. Each shim lazy-loads the class via:

```php
if ( ! class_exists( 'Automattic\WPSC\Config' ) ) {
    if ( file_exists( WPCACHEHOME . 'src/config/class-config.php' ) ) {
        require_once WPCACHEHOME . 'src/config/class-config.php';
    }
}
```

The `file_exists` guard means the debug-log write path keeps working when only the
engine (`wp-cache-phase2.php`) is loaded and the admin tree is absent.

### Globals remain the canonical read API

`Config::set()` always writes to `$GLOBALS[$field]`. Reads (`global $foo;` /
`$GLOBALS['foo']` / settings-map dynamic lookups) are intentionally not migrated —
that is a separate, larger effort and out of scope here. Any future `Config::get()`
must be a typed read-through over `$GLOBALS` (the single source of truth, populated
from the flat file before WordPress boots), not a value served from a private copy
the class parsed separately. If `Config` maintained its own parsed copy, a
third-party plugin writing a global directly would be invisible to `get()` and the
two would drift — the same symmetry as the write side, which keeps `$GLOBALS`
authoritative.

### No new locking or concurrency

The existing atomic `tempnam`+`rename` sequence is preserved unchanged. File
locking is explicitly out of scope: admin config writes are effectively
single-human, and the rename is already atomic at the OS level.

### No runtime autoloader

The class is loaded by explicit `require_once` — from `wp-cache.php` on the admin
path and lazily from the two phase2 shims. The Composer classmap (`composer.json`
`autoload.classmap`) is dev/test-only and not loaded at runtime.

### Exception catalog: writes that deliberately stay on `wp_cache_replace_line()`

Most direct `wp_cache_replace_line()` callers in the `inc/` files were migrated to
`Config::set()`. The following writes remain on the raw function because their
on-disk format is not reproducible by `Config::format_value()` or they are not
simple `$field = value;` key/value assignments. These are intentional, not misses:

| Exception | Reason |
|-----------|--------|
| `define( ... )` rewrites (`WP_CACHE`, `WPCACHEHOME`, `WPLOCKDOWN`) | Not `$var =` assignments; some target the site `wp-config.php`, not the config file |
| Line removals (`$new = ''`) | Removal is not a value write; `Config::set()` has no delete path |
| `cache_path` | Written via `var_export()`, which escapes backslashes and quotes differently from `format_value`'s string branch |
| `cached_direct_pages` | Written as a hand-built `array( ... )` literal with no integer keys; `var_export` emits `array ( 0 => ... )` |
| `wp_cache_mobile_groups` | Written as an imploded comma-string while the runtime global is kept as an array; the on-disk format diverges from the value type |
| `cache_time_interval` | Always written quoted despite a numeric value; `format_value` would emit it unquoted, changing the on-disk bytes |
| `cache_max_time` | Written unquoted in `inc/settings-forms.php` but quoted in `inc/admin-ui.php` — same field, inconsistent legacy formatting across callers; migrating would resolve the inconsistency but would change the on-disk bytes from at least one path |
| `cache_page_secret` | An `md5()` value written quoted unconditionally; an all-digit md5 would be classified as numeric by `format_value` and emitted unquoted |
| `$wp_cache_pages[ "..." ]` | Array sub-key writes, not a simple `$field = value;` line |
| `sem_id` write in `inc/lifecycle.php` | Uses a bare unanchored regex (`'sem_id'`), not the `'^ *\$sem_id'` anchored form `Config::set()` generates; migrating would change which lines the regex matches |
| Debug-log writes in `wp-cache-phase2.php` | Stay on `wp_cache_setting()` for engine-only load safety (the lazy-load guard); the shim handles this correctly |
| `plugins/*` and `rest/*` direct calls | Third-party-facing callers are out of scope for this refactor |

## Consequences

- Config-file writes have a single testable owner with documented format rules.
  The `format_value` and `write_line` methods can be unit-tested in isolation
  without a WordPress runtime.
- The public shim surface (`wp_cache_setting` / `wp_cache_replace_line`) is
  unchanged, so third-party plugins are unaffected.
- The exception catalog above is the authoritative list of writes that are not
  routed through `Config::set()`. Future contributors should add to it rather than
  silently adding direct `wp_cache_replace_line()` calls without explanation.
- A future `Config::get()` is explicitly pre-constrained: it must read through
  `$GLOBALS`, not a private parsed copy, to preserve symmetry with the write side.
- The config file's flat-PHP format is locked in as a first-class constraint (not
  accidental), because the phase1 drop-in loads it before WordPress boots.
