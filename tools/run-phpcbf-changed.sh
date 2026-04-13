#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHPCBF_WRAPPER="$ROOT_DIR/tools/phpcbf-wrapper.sh"
STANDARD="$ROOT_DIR/.phpcs.xml.dist"

dedupe_files() {
	awk 'NF && !seen[$0]++'
}

collect_files() {
	local cmd=("$@")
	"${cmd[@]}" | dedupe_files
}

base_ref=""
if git rev-parse --verify origin/trunk >/dev/null 2>&1; then
	base_ref="origin/trunk"
elif git rev-parse --verify trunk >/dev/null 2>&1; then
	base_ref="trunk"
fi

files="$(
	{
		if [ -n "$base_ref" ]; then
			merge_base="$(git merge-base HEAD "$base_ref")"
			git diff --name-only --diff-filter=ACMR "$merge_base"...HEAD -- '*.php'
		fi
		git diff --cached --name-only --diff-filter=ACMR -- '*.php'
		git diff --name-only --diff-filter=ACMR -- '*.php'
		git ls-files --others --exclude-standard -- '*.php'
	} | dedupe_files
)"

if [ -z "$files" ]; then
	echo "No changed PHP files found, skipping PHPCBF."
	exit 0
fi

mapfile -t file_list <<<"$files"

echo "Running PHPCBF on changed PHP files..."
"$PHPCBF_WRAPPER" --standard="$STANDARD" "${file_list[@]}"
