# 1. Split wp-cache.php into per-responsibility inc/ files

Date: 2026-06-17

## Status

Accepted

## Context

`wp-cache.php` had grown to ~4,500 lines holding ~120 global functions with
their hook registrations interleaved inline — lifecycle, admin UI, preload,
htaccess generation, cache-file management, settings forms, and Jetpack Boost
banners all in one file, changing at different rates. Navigating or safely
editing it was slow, and there was no PHP unit/integration coverage.

Issue #1061 (executing #1047 item 4) called for splitting the file along its
responsibilities, preceded by a characterization test net, as a **pure
relocation** with zero behaviour change.

## Decision

Move the global-function clusters into ~9 focused `inc/` files (the existing
convention for non-class includes; `src/` stays reserved for classmap-autoloaded
classes), one responsibility per file, each carrying its own
`add_action`/`add_filter` registrations. `wp-cache.php` becomes a thin loader.
The file map is documented in `CONTEXT.md`.

Constraints held: function names, signatures, hook names, and load order are
unchanged; `wp-cache-phase2.php` (the hot-path engine) is untouched; no classes,
shims, or global-state reduction. Each cluster was characterization-tested before
it moved.

A few things are position-dependent and therefore deviate from a literal
byte-for-byte move, while preserving behaviour:

- `register_activation_hook` / `register_deactivation_hook` /
  `register_uninstall_hook` stay in `wp-cache.php` so `__FILE__` resolves to the
  main plugin file; their handlers live in `inc/lifecycle.php`.
- `__DIR__` / `__FILE__` in relocated code resolves to the plugin root via
  `dirname( __DIR__ )` so asset URLs, `WPCACHEHOME`, `advanced-cache.php`
  generation, and partial-template paths stay correct.

## Consequences

- The plugin's behaviour is unchanged: the CI smoke suite (34), the local
  integration suite (37), and the e2e suite (28) all pass. The e2e suite proved
  essential — it caught the `__FILE__`/`__DIR__` path regressions that the
  unit/integration tiers do not exercise.
- Each `inc/` file is small and focused, so future changes touch one
  responsibility at a time.
- The relocated procedural includes carry their pre-existing WPCS debt verbatim
  and are excluded from PHPCS (documented in `.phpcs.xml.dist`). **Follow-up:**
  modernize the relocated `inc/` files (strict comparisons, escaping, spacing)
  and remove the exclusions; a deeper pass can then reduce global state (e.g.
  turn `inc/preload.php` into the #5 preload state machine).
- Because the changed-lines linter (`phpcs-changed`) diffs the cumulative branch
  against trunk, a partially-moved tree can make `git` attribute surviving
  legacy as "added." Only the original and the fully-moved states are
  lint-clean, so this work landed as a single relocation commit rather than one
  commit per cluster.
