#!/usr/bin/env bash
# Copies the shared design system into every package that ships it.
# Run from anywhere after editing shared/design-system/design-system.css.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SRC="$ROOT/shared/design-system/design-system.css"
for DEST in \
	"$ROOT/central/plugins/automotive-marketplace-core/assets/css/design-system.css" \
	"$ROOT/central/themes/autobuzy/assets/css/design-system.css" \
	"$ROOT/dealer/plugins/automotive-dealer-crm/assets/css/design-system.css" \
	"$ROOT/dealer/themes/autodealer/assets/css/design-system.css"; do
	mkdir -p "$(dirname "$DEST")"
	cp "$SRC" "$DEST"
	echo "Synced: ${DEST#$ROOT/}"
done
