#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

bash scripts/dependencies.sh

if [ ! -f .local/wordpress/wp-load.php ]; then
    curl -fL https://wordpress.org/wordpress-7.1.4.zip -o .local/wordpress.zip
    unzip -q .local/wordpress.zip -d .local
fi
if [ ! -f .local/wordpress/wp-content/plugins/sqlite-database-integration/db.copy ]; then
    curl -fL https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip -o .local/sqlite.zip
    unzip -q .local/sqlite.zip -d .local/wordpress/wp-content/plugins
fi
if [ ! -f .local/wp-cli.phar ]; then
    curl -fL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o .local/wp-cli.phar
fi
if [ ! -f .local/wordpress/wp-content/db.php ]; then
    cp .local/wordpress/wp-content/plugins/sqlite-database-integration/db.copy .local/wordpress/wp-content/db.php
fi
if [ ! -f .local/wordpress/wp-config.php ]; then
    php .local/wp-cli.phar --allow-root config create --path=.local/wordpress --dbname=pandoc_local --dbuser=local --dbpass=local --skip-check --extra-php='define( "WP_ENVIRONMENT_TYPE", "local" ); define( "WP_DEBUG", true ); define( "WP_DEBUG_LOG", true ); define( "WP_DEBUG_DISPLAY", false ); define( "WP_HTTP_BLOCK_EXTERNAL", true ); define( "DISALLOW_FILE_EDIT", true );'
fi
if ! php .local/wp-cli.phar --allow-root core is-installed --path=.local/wordpress; then
    php .local/wp-cli.phar --allow-root core install --path=.local/wordpress --url=http://127.0.0.1:8890 --title='The Editorial' --admin_user=admin --admin_password=pandoc-local-only --admin_email=admin@example.test --skip-email
    php .local/wp-cli.phar --allow-root option update siteurl http://127.0.0.1:8890 --path=.local/wordpress
    php .local/wp-cli.phar --allow-root option update home http://127.0.0.1:8890 --path=.local/wordpress
    php .local/wp-cli.phar --allow-root option update timezone_string Europe/Warsaw --path=.local/wordpress
fi
# Move only the symlink created by the original local prototype.
old_plugin=.local/wordpress/wp-content/plugins/pandoc-importer
if [ -L "$old_plugin" ] && [ "$(readlink "$old_plugin")" = "$PWD" ]; then
    rm "$old_plugin"
fi
if [ ! -L .local/wordpress/wp-content/plugins/collage ]; then
    ln -s "$PWD" .local/wordpress/wp-content/plugins/collage
fi
php .local/wp-cli.phar --allow-root plugin activate collage --path=.local/wordpress
if [ ! -L .local/wordpress/pandoc-block-check.php ]; then
    ln -s "$PWD/tests/block-validation.php" .local/wordpress/pandoc-block-check.php
fi
echo "Ready. Run bash scripts/serve.sh, then open http://127.0.0.1:8890/wp-admin/tools.php?page=pandoc-importer"
echo "Local-only login: admin / pandoc-local-only"
