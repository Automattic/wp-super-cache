# WP Super Cache

A very fast caching engine for WordPress that produces static HTML files.

[![WordPress Plugin](https://img.shields.io/wordpress/plugin/v/wp-super-cache)](https://wordpress.org/plugins/wp-super-cache/)
[![License: GPLv2+](https://img.shields.io/badge/License-GPLv2%2B-blue.svg)](./LICENSE.txt)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-7A86B8.svg)](https://www.php.net/)
[![PHP Tests](https://github.com/Automattic/wp-super-cache/actions/workflows/php-tests.yml/badge.svg)](https://github.com/Automattic/wp-super-cache/actions/workflows/php-tests.yml)

This plugin generates static HTML files from your dynamic WordPress site. After a file is generated, the web server serves that file directly instead of processing WordPress PHP scripts, dramatically reducing load and response times.

For user-facing documentation, see the [WordPress.org plugin page](https://wordpress.org/plugins/wp-super-cache/). For extended documentation, see the [wiki](https://github.com/Automattic/wp-super-cache/wiki).

## Project structure

```
wp-cache.php              Main plugin entry point
wp-cache-phase1.php       Early-loading caching phase (runs before WordPress)
wp-cache-phase2.php       Main caching logic (runs during WordPress init)
advanced-cache.php        Drop-in loaded by WordPress when WP_CACHE is enabled
ossdl-cdn.php             CDN URL rewriting (OSSDL off-linker integration)

inc/                      Core includes (Boost integration, admin UI helpers)
rest/                     REST API endpoint classes for cache management
src/                      Source modules (device detection)
plugins/                  WP Super Cache's own plugin system (loaded early, before WP)
partials/                 Admin settings page tab templates

tests/php/                PHPUnit tests
tests/e2e/                End-to-end tests (Docker + Jest)

changelog/                Individual changelog entries (Jetpack Changelogger format)
.phan/                    Phan static analysis configuration and stubs
```

## Development setup

### Prerequisites

- PHP 7.4+
- [Composer](https://getcomposer.org/)
- Node.js 20+ and Docker (only needed for the Makefile / wp-env workflow)

### Quick start with the Makefile

A `Makefile` is provided to spin up a disposable WordPress site in Docker (via [`@wordpress/env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)) with the plugin pre-mounted. Run `make help` to see every target.

```bash
make install     # composer install + npm install
make up          # start WordPress at http://localhost:8888 (admin / password)
make seed        # create 100 random posts + 100 random pages for cache testing
# ... hack on the plugin; files are live-mounted into the container ...
make unseed      # delete only the content created by `make seed`
make down        # stop containers
make destroy     # stop and wipe the database
```

Other useful targets:

| Target | Purpose |
|--------|---------|
| `make cli` | Open a shell inside the WordPress container |
| `make wp CMD="super-cache status"` | Run an arbitrary `wp-cli` command |
| `make logs` | Tail the WordPress container logs |
| `make lint` / `make lint-fix` | Run / auto-fix PHPCS |

The seed script tags every item it creates with `_wpsc_seed=1` post meta, so `make unseed` only removes content it generated — it will not touch posts or pages you created by hand.

### Manual installation

```bash
composer install
```

### Running tests

```bash
# PHP unit tests
composer test-php

# With coverage
composer test-coverage
```

### Linting

```bash
# PHPCS (WordPress/Jetpack coding standards)
vendor/bin/phpcs
```

### Static analysis

```bash
# Phan
vendor/bin/phan
```

### End-to-end tests

E2E tests use Docker and Jest. See `tests/e2e/` for setup details:

```bash
cd tests/e2e
pnpm install
docker compose up -d
pnpm test
```

## Contributing

1. Branch from `trunk`.
2. Make your changes.
3. Add a changelog entry:
   ```bash
   vendor/bin/changelogger add
   ```
4. Push and open a pull request against `trunk`.

CI will automatically run:
- **PHP tests** across PHP 8.2, 8.3, 8.4, and 8.5
- **PHPCS linting** on changed lines
- **Changelog validation** (warns if no entry is included)

### Translations

Help translate WP Super Cache on the [WordPress.org translation page](https://translate.wordpress.org/projects/wp-plugins/wp-super-cache).

## Security

To report a security vulnerability, visit [automattic.com/security](https://automattic.com/security/) or the [HackerOne bug bounty program](https://hackerone.com/automattic).

## License

WP Super Cache is licensed under the [GNU General Public License v2 (or later)](./LICENSE.txt).
