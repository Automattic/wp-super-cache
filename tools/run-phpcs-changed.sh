#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHPCS_CHANGED="$ROOT_DIR/vendor/bin/phpcs-changed"
PHPCS_WRAPPER="$ROOT_DIR/tools/phpcs-wrapper.sh"
STANDARD="$ROOT_DIR/.phpcs.xml.dist"

dedupe_files() {
	awk 'NF && !seen[$0]++'
}

collect_files() {
	local cmd=("$@")
	"${cmd[@]}" | dedupe_files
}

run_changed() {
	local description="$1"
	shift

	local files=()
	while IFS= read -r file; do
		[ -n "$file" ] && files+=("$file")
	done

	if [ "${#files[@]}" -eq 0 ]; then
		return 0
	fi

	echo "Running PHPCS for $description..."

	local rc=0
	php -d error_reporting='E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED' "$PHPCS_CHANGED" \
		--phpcs-path "$PHPCS_WRAPPER" \
		--standard="$STANDARD" \
		"$@" \
		"${files[@]}" || rc=$?

	return "$rc"
}

status=0
checks_run=0

staged_files="$(collect_files git diff --cached --name-only --diff-filter=ACMR -- '*.php')"
unstaged_files="$(collect_files git diff --name-only --diff-filter=ACMR -- '*.php')"
untracked_files="$(collect_files git ls-files --others --exclude-standard -- '*.php')"

base_ref=""
if git rev-parse --verify origin/trunk >/dev/null 2>&1; then
	base_ref="origin/trunk"
elif git rev-parse --verify trunk >/dev/null 2>&1; then
	base_ref="trunk"
fi

if [ -n "$base_ref" ]; then
	merge_base="$(git merge-base HEAD "$base_ref")"
	branch_files="$(collect_files git diff --name-only --diff-filter=ACMR "$merge_base"...HEAD -- '*.php')"
	if [ -n "$branch_files" ]; then
		checks_run=1
		if ! run_changed "branch PHP changes against $base_ref" --git --git-base "$merge_base" <<<"$branch_files"; then
			status=1
		fi
	fi
fi

if [ -n "$staged_files" ]; then
	checks_run=1
	if ! run_changed "staged PHP changes" --git --git-staged <<<"$staged_files"; then
		status=1
	fi
fi

if [ -n "$unstaged_files" ]; then
	checks_run=1
	if ! run_changed "unstaged PHP changes" --git --git-unstaged <<<"$unstaged_files"; then
		status=1
	fi
fi

if [ -n "$untracked_files" ]; then
	checks_run=1
	echo "Running PHPCS for untracked PHP files..."
	while IFS= read -r file; do
		[ -n "$file" ] || continue
		if ! "$PHPCS_WRAPPER" --standard="$STANDARD" "$file"; then
			status=1
		fi
	done <<<"$untracked_files"
fi

if [ "$checks_run" -eq 0 ]; then
	echo "No changed PHP files found, skipping PHPCS."
fi

exit "$status"
