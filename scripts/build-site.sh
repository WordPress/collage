#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

# Assembles the GitHub Pages demo in dist/site. Run scripts/dependencies.sh first.
php scripts/build.php > /dev/null
rm -rf dist/site
cp -R site dist/site
cp samples/* dist/site/examples/
cp dist/collage.zip .wordpress-org/icon.svg LICENSE dist/site/
echo "$PWD/dist/site"
