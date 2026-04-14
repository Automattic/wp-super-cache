#!/usr/bin/env bash
#
# Creates a GitHub release for the version currently set in readme.txt
# (attaching build/wp-super-cache.zip and using the matching changelog
# section as the release notes), then optionally publishes the plugin
# files to the WordPress.org SVN repository.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

PLUGIN_SLUG="wp-super-cache"
BUILD_DIR="build/$PLUGIN_SLUG"
ZIP_FILE="build/$PLUGIN_SLUG.zip"
SVN_DIR="build/svn"
SVN_URL="https://plugins.svn.wordpress.org/$PLUGIN_SLUG/"

if [[ ! -f "$ZIP_FILE" ]] || [[ ! -d "$BUILD_DIR" ]]; then
	echo "$ZIP_FILE or $BUILD_DIR missing. Run \`make build\` first." >&2
	exit 1
fi

VERSION="$(sed -nE 's/^Stable tag: ([0-9A-Za-z.+-]+).*/\1/p' readme.txt | head -1)"
if [[ -z "$VERSION" ]]; then
	echo "Could not read Stable tag from readme.txt." >&2
	exit 1
fi

TAG="v$VERSION"

if gh release view "$TAG" >/dev/null 2>&1; then
	echo "Release $TAG already exists on GitHub." >&2
	exit 1
fi

# Extract the changelog block for this version: from `### <version>` up to
# the next `### ` heading or a `--------` divider, whichever comes first.
NOTES="$(awk -v ver="$VERSION" '
	$0 ~ "^### " ver "( |$|-)" { inblock = 1; print; next }
	inblock && /^### / { exit }
	inblock && /^--------/ { exit }
	inblock { print }
' readme.txt)"

if [[ -z "$NOTES" ]]; then
	echo "No changelog section found for $VERSION in readme.txt." >&2
	exit 1
fi

cat <<EOF

Tag:     $TAG
Zip:     $ZIP_FILE
Notes:
----------------------------------------
$NOTES
----------------------------------------
EOF

read -r -p "Create GitHub release $TAG? [y/N] " confirm
if [[ "$confirm" != "y" && "$confirm" != "Y" ]]; then
	echo "Aborted."
	exit 1
fi

gh release create "$TAG" "$ZIP_FILE" \
	--title "$VERSION" \
	--notes "$NOTES"

# ---- SVN publish to WordPress.org ----

read -r -p "Also publish $VERSION to WordPress.org SVN? [y/N] " svn_confirm
if [[ "$svn_confirm" != "y" && "$svn_confirm" != "Y" ]]; then
	echo "Skipped SVN publish."
	exit 0
fi

if ! command -v svn >/dev/null 2>&1; then
	echo "svn is not installed." >&2
	exit 1
fi

rm -rf "$SVN_DIR"
mkdir -p "$SVN_DIR"

echo "Shallow-checking out $SVN_URL"
svn checkout "$SVN_URL" "$SVN_DIR" --depth=empty
( cd "$SVN_DIR" && svn up trunk && svn up tags --depth=immediates )

if [[ -e "$SVN_DIR/tags/$VERSION" ]]; then
	echo "Tag $VERSION already exists in SVN. Aborting." >&2
	exit 1
fi

echo "Syncing $BUILD_DIR/ into $SVN_DIR/trunk/"
rsync -a --delete --exclude='.svn/' "$BUILD_DIR/" "$SVN_DIR/trunk/"

# Stage SVN adds/removes based on `svn status`.
(
	cd "$SVN_DIR"
	while IFS= read -r line; do
		flag="${line:0:1}"
		# svn status prints flags in cols 1-7 and a space, then the path at col 9.
		path="${line:8}"
		# Append @ to avoid svn interpreting @ in filenames as peg revisions.
		case "$flag" in
			'?') svn add "${path}@" ;;
			'!') svn rm "${path}@" ;;
		esac
	done < <(svn status)
)

echo
echo "----- svn status -----"
( cd "$SVN_DIR" && svn status )
echo "----------------------"
read -r -p "Commit trunk and create tags/$VERSION on WordPress.org SVN? [y/N] " commit_confirm
if [[ "$commit_confirm" != "y" && "$commit_confirm" != "Y" ]]; then
	echo "Aborted before SVN commit. $SVN_DIR is left in place for inspection."
	exit 1
fi

(
	cd "$SVN_DIR"
	svn commit -m "Update to version $VERSION"
	svn cp "^/$PLUGIN_SLUG/trunk" "^/$PLUGIN_SLUG/tags/$VERSION" -m "Tagging version $VERSION"
)

echo "Published $PLUGIN_SLUG $VERSION to WordPress.org SVN."
