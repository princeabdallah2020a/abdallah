#!/usr/bin/env bash
# Builds a throw-away local WordPress site (SQLite database, no MySQL needed)
# with the central plugin and theme symlinked, for development and tests.
#
# Usage: tools/dev/setup-local-site.sh <site-dir> <wordpress-src> <sqlite-plugin-src> [port]
#   wordpress-src      : a WordPress core checkout/extract (contains wp-load.php)
#   sqlite-plugin-src  : checkout of github.com/WordPress/sqlite-database-integration
#
# Production uses MySQL/MariaDB; SQLite is for local testing only.
set -euo pipefail

SITE="${1:?site dir}"; WP_SRC="${2:?wordpress source dir}"; SQLITE_SRC="${3:?sqlite plugin source dir}"; PORT="${4:-8088}"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"

rm -rf "$SITE"
mkdir -p "$SITE"
cp -R "$WP_SRC"/. "$SITE"/
rm -rf "$SITE/.git"

PLUGIN_DIR="$SITE/wp-content/plugins/sqlite-database-integration"
mkdir -p "$PLUGIN_DIR"
cp -RL "$SQLITE_SRC/packages/plugin-sqlite-database-integration"/. "$PLUGIN_DIR"/
cp "$PLUGIN_DIR/db.copy" "$SITE/wp-content/db.php"
mkdir -p "$SITE/wp-content/database" "$SITE/wp-content/uploads"

ln -sfn "$ROOT/central/plugins/automotive-marketplace-core" "$SITE/wp-content/plugins/automotive-marketplace-core"
ln -sfn "$ROOT/central/themes/automarket" "$SITE/wp-content/themes/automarket"

cat > "$SITE/wp-config.php" <<PHP
<?php
define( 'DB_NAME', 'local' );
define( 'DB_USER', '' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', '' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
define( 'DB_DIR', __DIR__ . '/wp-content/database/' );
define( 'DB_FILE', 'local.sqlite' );
// Local development keys only. NEVER reuse in production.
foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as \$k ) {
	define( \$k, 'local-dev-only-' . \$k );
}
\$table_prefix = 'wp_';
define( 'WPLANG', 'fr_FR' ); // Local only: in production choose the language in Settings → General.
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_HOME', 'http://127.0.0.1:${PORT}' );
define( 'WP_SITEURL', 'http://127.0.0.1:${PORT}' );
define( 'AUTOMATIC_UPDATER_DISABLED', true );
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
PHP

php "$ROOT/tools/dev/install.php" "$SITE"
echo "Site ready. Start it with:"
echo "  php -S 127.0.0.1:${PORT} -t \"$SITE\" \"$ROOT/tools/dev/router.php\""
