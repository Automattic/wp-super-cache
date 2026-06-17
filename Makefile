.DEFAULT_GOAL := help
SHELL := /bin/bash

PLUGIN_NAME := wp-super-cache
# Use the locally-installed @wordpress/env (a devDependency) rather than fetching
# it via `npx --yes` on every call, which needs registry access on each run.
# Run `make install` (npm install) once to populate it.
WP_ENV_BIN := node_modules/.bin/wp-env
WP_ENV := COMPOSE_PROJECT_NAME=$(PLUGIN_NAME) $(WP_ENV_BIN)

# Guard: any wp-env target depends on this. If the binary is missing the recipe
# below runs and exits with a helpful message; if it exists, it's up to date and
# the recipe is skipped.
$(WP_ENV_BIN):
	@echo "Error: wp-env is not installed. Run 'make install' first." >&2
	@exit 1

## Development environment
install: ## Install PHP (Composer) and JS (npm) dependencies
	composer install
	npm install

up: $(WP_ENV_BIN) ## Start WordPress in Docker (http://localhost:8888, admin/password)
	$(WP_ENV) start

down: $(WP_ENV_BIN) ## Stop the WordPress containers
	$(WP_ENV) stop

destroy: $(WP_ENV_BIN) ## Remove the WordPress containers and database
	$(WP_ENV) destroy

logs: $(WP_ENV_BIN) ## Tail the WordPress container logs
	$(WP_ENV) logs

cli: $(WP_ENV_BIN) ## Open a shell inside the cli container
	$(WP_ENV) run cli bash

wp: $(WP_ENV_BIN) ## Run an arbitrary wp-cli command, e.g. `make wp CMD="plugin list"`
	$(WP_ENV) run cli wp $(CMD)

## Test content
seed: $(WP_ENV_BIN) ## Create 100 randomly named posts and 100 pages for cache testing
	$(WP_ENV) run cli wp eval-file wp-content/plugins/wp-super-cache/tests/dev/seed.php

unseed: $(WP_ENV_BIN) ## Delete content created by `make seed`
	$(WP_ENV) run cli wp eval-file wp-content/plugins/wp-super-cache/tests/dev/unseed.php

## Test
PLUGIN_DIR_IN_CONTAINER := /var/www/html/wp-content/plugins/wp-super-cache
WPSC_TESTS_CONFIG := $(PLUGIN_DIR_IN_CONTAINER)/tests/php/wp-tests-config.php

test: ## Run the fast PHP smoke suite (no database, no Docker)
	composer test-php

test-integration: $(WP_ENV_BIN) ## Run the full WordPress integration suite in Docker (auto-starts wp-env)
	$(WP_ENV) start
	$(WP_ENV) run tests-cli --env-cwd=wp-content/plugins/wp-super-cache \
		env WP_PHPUNIT__TESTS_CONFIG=$(WPSC_TESTS_CONFIG) \
		vendor/bin/phpunit -c phpunit-integration.9.xml.dist --colors=always

## Lint
lint: ## Run PHP CodeSniffer
	composer lint

lint-all: ## Run PHP CodeSniffer on all files
	composer phpcs

lint-fix: ## Auto-fix PHP CodeSniffer issues
	composer lint-fix

## Release
pre-build: ## Prepare a release PR. Usage: make pre-build VERSION=x.y.z
	@test -n "$(VERSION)" || { echo "Usage: make pre-build VERSION=x.y.z"; exit 1; }
	./scripts/pre-build.sh $(VERSION)

build: ## Build build/wp-super-cache.zip (run pre-build and merge the PR first)
	./scripts/build-plugin.sh

publish: ## Create a GitHub release from readme.txt + build/wp-super-cache.zip
	./scripts/publish.sh

## Help
help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-12s\033[0m %s\n", $$1, $$2}'

.PHONY: install up down destroy logs cli wp seed unseed test test-integration lint lint-all lint-fix pre-build build publish help
