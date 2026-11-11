#!/usr/bin/env bash
#
# Assembles build/wp-super-cache/ and build/wp-super-cache.zip from the tracked
# files at HEAD (via `git archive`), shipping ONLY the plugin's runtime files.
#
# This is an allow-list: `git archive` is restricted to the ship_paths below, so
# a newly-added dev file — docs, tooling, config, agent instructions, a new
# top-level directory — can never leak into the release unless it is explicitly
# added here. (Using `git archive` also means untracked/ignored files never
# ship.) Pure, non-interactive: runs locally (`make build`) and in CI.
#
# When you add a new runtime file or directory to the plugin, add it here.

set -euo pipefail

command -v git >/dev/null || { >&2 echo "git is required"; exit 1; }
command -v zip >/dev/null || { >&2 echo "zip is required"; exit 1; }

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

PLUGIN_SLUG="wp-super-cache"
BUILD_DIR="build"
PLUGIN_DIR="$BUILD_DIR/$PLUGIN_SLUG"

# The only paths that ship in the plugin.
ship_paths=(
	wp-cache.php
	wp-cache-base.php
	wp-cache-config-sample.php
	wp-cache-phase1.php
	wp-cache-phase2.php
	advanced-cache.php
	ossdl-cdn.php
	readme.txt
	LICENSE.txt
	SECURITY.md
	assets
	inc
	js
	languages
	partials
	plugins
	rest
	src
	styling
)

rm -rf "$BUILD_DIR"
mkdir -p "$PLUGIN_DIR"

# Export only the allow-listed tracked paths at HEAD.
git archive HEAD -- "${ship_paths[@]}" | tar -x -C "$PLUGIN_DIR"

( cd "$BUILD_DIR" && zip -rq "$PLUGIN_SLUG.zip" "$PLUGIN_SLUG" )

echo "Built $BUILD_DIR/$PLUGIN_SLUG.zip"
