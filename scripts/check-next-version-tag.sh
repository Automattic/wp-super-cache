#!/usr/bin/env bash
#
# Read-only lint for the 3.1.2 placeholder convention.
#
# Inspects only the lines ADDED in this branch (vs. a base ref) and fails when a
# new @since / @deprecated / _deprecated_*() introduces either:
#   (a) a near-miss of the placeholder token that the release replacer would
#       silently skip (e.g. $next-version$, next-version, $$next_version$$,
#       "@since next"); or
#   (b) a hard-coded version number where the placeholder should be used for
#       not-yet-released code.
#
# It NEVER modifies files. To allow a deliberate hard-coded version (e.g. a doc
# tag that records a real past release), put "next-version-ok" on the line.
#
# Usage: scripts/check-next-version-tag.sh [<base-ref>]   (default: origin/trunk)

set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

BASE="${1:-origin/trunk}"

if git rev-parse --verify --quiet "${BASE}^{commit}" >/dev/null 2>&1; then
	RANGE="$(git merge-base "$BASE" HEAD 2>/dev/null || printf '%s' "$BASE")"
else
	echo "check-next-version-tag: base ref '$BASE' not found; nothing to diff." >&2
	exit 0
fi

exit_code=0
emit() { # file line message
	if [ -n "${CI:-}" ]; then
		printf '::error file=%s,line=%s::%s\n' "$1" "$2" "$3"
	else
		printf '%s:%s: %s\n' "$1" "$2" "$3" >&2
	fi
	exit_code=1
}

check_line() { # file line content
	local f="$1" ln="$2" line="$3"

	# The tooling scripts necessarily contain the token and example tags.
	case "$f" in
		scripts/check-next-version-tag.sh|scripts/replace-next-version-tag.sh) return ;;
	esac

	# Only care about version doc tags / deprecation calls.
	case "$line" in
		*'@since'*|*'@deprecated'*|*_deprecated_*) ;;
		*) return ;;
	esac
	# Deliberate opt-out.
	case "$line" in *next-version-ok*) return ;; esac
	# Correct usage — the exact token.
	case "$line" in *'3.1.2'*) return ;; esac

	# (a) Near-miss placeholder the exact-match release replacer would skip.
	if printf '%s' "$line" | grep -Eiq \
		'@(since|deprecated)([[:space:]]+since)?[[:space:]]+[^[:space:]]*next[-_]version|@(since|deprecated)([[:space:]]+since)?[[:space:]]+next([^[:alnum:]]|$)|_deprecated_[a-z]*\([^)]*next[-_]version'
	then
		emit "$f" "$ln" 'Malformed next-version placeholder — use the exact token 3.1.2 (run scripts/replace-next-version-tag.sh -h).'
		return
	fi

	# (b) Hard-coded version where the placeholder should be used.
	if printf '%s' "$line" | grep -Eiq '@(since|deprecated)([[:space:]]+since)?[[:space:]]+v?[0-9]+\.[0-9]+'
	then
		emit "$f" "$ln" 'Hard-coded version in a new @since/@deprecated — use 3.1.2 for unreleased code, or add "next-version-ok" if this records a real past release.'
	fi
}

file=""
newline=0
while IFS= read -r raw; do
	case "$raw" in
		'+++ '*)
			file="${raw#+++ }"
			file="${file#b/}"
			;;
		'@@ '*)
			hunk="${raw#*+}"
			hunk="${hunk%%[, ]*}"
			newline="$hunk"
			;;
		'+'*)
			check_line "$file" "$newline" "${raw:1}"
			newline=$((newline + 1))
			;;
	esac
done < <(git diff -U0 "$RANGE" HEAD -- . 2>/dev/null)

exit "$exit_code"
