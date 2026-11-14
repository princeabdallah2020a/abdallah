#!/usr/bin/env bash
# Build installable zips of every AutoBuzy package into dist/.
# Usage: tools/dev/build-release.sh [output-dir]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
OUT="${1:-$ROOT/dist}"
mkdir -p "$OUT"

"$ROOT/tools/dev/sync-design-system.sh" >/dev/null

# The central plugin ships the Anthropic PHP SDK in vendor/.
CORE="$ROOT/central/plugins/automotive-marketplace-core"
if command -v composer >/dev/null 2>&1; then
	(cd "$CORE" && composer install --no-dev --optimize-autoloader --quiet)
elif [ ! -f "$CORE/vendor/autoload.php" ]; then
	echo "warning: composer not found and vendor/ missing: the AI assistant will be disabled in this build" >&2
fi

version() { grep -m1 -E "^\s*\*?\s*Version:" "$1" | sed -E 's/.*Version:\s*//' | tr -d '[:space:]'; }

pack() { # dir name main-file
	local dir="$1" name="$2" main="$3" v
	v="$(version "$dir/$main")"
	rm -f "${OUT:?}/${name:?}-${v:?}.zip"
	(cd "$(dirname "$dir")" && zip -qr "$OUT/$name-$v.zip" "$(basename "$dir")" -x '*/.git*' '*/node_modules/*' '*/tests/*' '*.DS_Store' '*/composer.lock')
	echo "$OUT/$name-$v.zip"
}

pack "$CORE" autobuzy-marketplace-core automotive-marketplace-core.php
pack "$ROOT/central/themes/autobuzy" autobuzy-theme style.css
pack "$ROOT/dealer/plugins/automotive-dealer-crm" autobuzy-dealer-crm automotive-dealer-crm.php
pack "$ROOT/dealer/plugins/automotive-marketplace-connector" autobuzy-marketplace-connector automotive-marketplace-connector.php
pack "$ROOT/dealer/themes/autodealer" autodealer-theme style.css
