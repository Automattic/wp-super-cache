#!/usr/bin/env bash
#
# Builds build/wp-super-cache/ and build/wp-super-cache.zip from the current
# working tree, excluding files listed in scripts/exclude.lst.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

PLUGIN_SLUG="wp-super-cache"
BUILD_DIR="build"
PLUGIN_DIR="$BUILD_DIR/$PLUGIN_SLUG"
ZIP_FILE="$PLUGIN_SLUG.zip"
EXCLUDE_LIST="scripts/exclude.lst"

rm -rf "$BUILD_DIR"
mkdir -p "$PLUGIN_DIR"

# rsync paths are relative to the source dir, so strip the leading
# wp-super-cache/ prefix that the exclude list carries for zip's benefit.
RSYNC_EXCLUDE="$(mktemp)"
trap 'rm -f "$RSYNC_EXCLUDE"' EXIT
sed "s|^$PLUGIN_SLUG/||" "$EXCLUDE_LIST" > "$RSYNC_EXCLUDE"

rsync -a \
	--exclude "$BUILD_DIR" \
	--exclude-from "$RSYNC_EXCLUDE" \
	./ "$PLUGIN_DIR/"

# Zip from build/ so archive paths start with wp-super-cache/, matching
# the prefixes in exclude.lst.
( cd "$BUILD_DIR" && zip -rq "$ZIP_FILE" "$PLUGIN_SLUG" -x@"../$EXCLUDE_LIST" )

echo "Built $BUILD_DIR/$ZIP_FILE"
