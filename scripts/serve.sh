#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
if [ ! -f .local/wordpress/wp-config.php ]; then
    echo "Run bash scripts/setup.sh first." >&2
    exit 1
fi
echo "Collage: http://127.0.0.1:8890/wp-admin/tools.php?page=pandoc-importer"
exec php -d opcache.enable=0 -d upload_max_filesize=8M -d post_max_size=10M -d memory_limit=256M -d max_execution_time=45 -S 127.0.0.1:8890 -t .local/wordpress
