#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

exec php -d error_reporting='E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED' "$ROOT_DIR/vendor/bin/phpcbf" "$@"
