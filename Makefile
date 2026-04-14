.DEFAULT_GOAL := help

PLUGIN_NAME := wp-super-cache
WP_ENV := COMPOSE_PROJECT_NAME=$(PLUGIN_NAME) npx --yes @wordpress/env

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
	@echo "This builds build/wp-super-cache.zip from the current working tree."
	@read -r -p "Have you run pre-build and merged the release PR? Press RETURN to continue... " _
	./scripts/build-plugin.sh

## Help
help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-12s\033[0m %s\n", $$1, $$2}'

.PHONY: install up down destroy logs cli wp seed unseed lint lint-all lint-fix pre-build build help
