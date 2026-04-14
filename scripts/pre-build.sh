#!/usr/bin/env bash
#
# Prepares a release: creates a release/<version> branch, bumps the version
# in readme.txt and wp-cache.php, generates a commit-log summary of the last
# six months, opens readme.txt in vim so the changelog section can be
# updated by hand, then pushes the branch and opens a PR.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

VERSION="${1:-}"
if [[ -z "$VERSION" ]]; then
	echo "Usage: $0 VERSION (e.g. 4.0.0)" >&2
	exit 1
fi
if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[a-z0-9.-]+)?$ ]]; then
	echo "Version must look like x.y.z or x.y.z-suffix, got: $VERSION" >&2
	exit 1
fi

CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
if [[ "$CURRENT_BRANCH" != "trunk" ]]; then
	echo "pre-build must be run from trunk (currently on: $CURRENT_BRANCH)." >&2
	exit 1
fi

echo "Fetching origin..."
git fetch origin trunk
if ! git merge-base --is-ancestor origin/trunk HEAD; then
	echo "Local trunk is behind origin/trunk. Pull before running pre-build." >&2
	exit 1
fi

if ! git diff-index --quiet HEAD --; then
	echo "Working tree has uncommitted changes. Commit or stash first." >&2
	exit 1
fi

BRANCH="release/$VERSION"
if git rev-parse --verify "$BRANCH" >/dev/null 2>&1; then
	echo "Branch $BRANCH already exists." >&2
	exit 1
fi

echo "Creating branch $BRANCH"
git checkout -b "$BRANCH"

echo "Bumping version to $VERSION"
sed -i '' -E "s/^(Stable tag: ).*/\1$VERSION/" readme.txt
sed -i '' -E "s/^( \* Version: ).*/\1$VERSION/" wp-cache.php

LOG_FILE="$(mktemp -t wpsc-changelog.XXXXXX)"
if date -v-6m +%Y-%m-%d >/dev/null 2>&1; then
	SINCE_DATE="$(date -v-6m +%Y-%m-%d)"
else
	SINCE_DATE="$(date -d '6 months ago' +%Y-%m-%d)"
fi
gh pr list \
	--state merged \
	--base trunk \
	--limit 500 \
	--search "merged:>=$SINCE_DATE" \
	--json number,title,author,mergedAt \
	--template '{{range .}}- {{.title}} (#{{.number}}) — @{{.author.login}}{{"\n"}}{{end}}' \
	> "$LOG_FILE"

cat <<EOF

PRs merged since $SINCE_DATE have been written to:
  $LOG_FILE

Update the "== Changelog ==" section of readme.txt using those PRs as
reference. vim will open both files now.
EOF
read -r -p "Press RETURN when ready to update readme.txt (Ctrl-C to abort)... " _

vim -o readme.txt "$LOG_FILE"

echo
echo "----- git diff -----"
git --no-pager diff
echo "--------------------"

read -r -p "Commit, push, and open PR for $VERSION? [y/N] " confirm
if [[ "$confirm" != "y" && "$confirm" != "Y" ]]; then
	echo "Aborted. Branch $BRANCH left in place with your changes."
	exit 1
fi

git add readme.txt wp-cache.php
git commit -m "Release $VERSION"
git push -u origin "$BRANCH"

gh pr create \
	--title "Release $VERSION" \
	--body "Prepares WP Super Cache $VERSION for release. Bumps the plugin version and updates the changelog."

rm -f "$LOG_FILE"
