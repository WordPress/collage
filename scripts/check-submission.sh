#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

work="$(mktemp -d "$PWD/.local/submission.XXXXXX")"
trap 'rm -rf "$work"' EXIT
unzip -q dist/collage.zip -d "$work"
curl -fsSL https://downloads.wordpress.org/plugin/plugin-check.2.1.0.zip -o "$work/plugin-check.zip"
unzip -oq "$work/plugin-check.zip" -d .local/wordpress/wp-content/plugins
php .local/wp-cli.phar --allow-root plugin activate plugin-check --path=.local/wordpress
# Plugin Check returns zero for warnings, so also reject a non-empty report.
php .local/wp-cli.phar --allow-root --quiet plugin check "$work/collage" --slug=collage \
    --categories=plugin_repo --format=strict-json --path=.local/wordpress > "$work/check.json"
if [ -s "$work/check.json" ]; then
    cat "$work/check.json"
    exit 1
fi
echo "Plugin Check: no directory findings."
