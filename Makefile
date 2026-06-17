.DEFAULT_GOAL := help
SHELL := /bin/bash

PLUGIN_NAME := wp-super-cache
# Use the locally-installed @wordpress/env (a devDependency) rather than fetching
# it via `npx --yes` on every call, which needs registry access on each run.
# Run `make install` (npm install) once to populate it.
WP_ENV := COMPOSE_PROJECT_NAME=$(PLUGIN_NAME) node_modules/.bin/wp-env

## Development environment
install: ## Install PHP (Composer) and JS (npm) dependencies
	composer install
	npm install

up: ## Start WordPress in Docker (http://localhost:8888, admin/password)
	$(WP_ENV) start

down: ## Stop the WordPress containers
	$(WP_ENV) stop

destroy: ## Remove the WordPress containers and database
	$(WP_ENV) destroy

logs: ## Tail the WordPress container logs
	$(WP_ENV) logs

cli: ## Open a shell inside the cli container
	$(WP_ENV) run cli bash

wp: ## Run an arbitrary wp-cli command, e.g. `make wp CMD="plugin list"`
	$(WP_ENV) run cli wp $(CMD)

## Test content
seed: ## Create 100 randomly named posts and 100 pages for cache testing
	$(WP_ENV) run cli wp eval-file wp-content/plugins/wp-super-cache/tests/dev/seed.php

unseed: ## Delete content created by `make seed`
	$(WP_ENV) run cli wp eval-file wp-content/plugins/wp-super-cache/tests/dev/unseed.php

## Test
PLUGIN_DIR_IN_CONTAINER := /var/www/html/wp-content/plugins/wp-super-cache
WPSC_TESTS_CONFIG := $(PLUGIN_DIR_IN_CONTAINER)/tests/php/wp-tests-config.php

test: ## Run the fast PHP smoke suite (no database, no Docker)
	composer test-php

test-integration: ## Run the full WordPress integration suite in Docker (auto-starts wp-env)
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
