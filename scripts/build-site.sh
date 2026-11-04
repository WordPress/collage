#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

# Use the release ZIP when deploying; build from the checkout for local previews.
zip=${1:-dist/collage.zip}
if [ "$#" -eq 0 ]; then
    php scripts/build.php > /dev/null
fi
rm -rf dist/site
cp -R site dist/site
cp samples/* dist/site/examples/
cp "$zip" dist/site/collage.zip
cp .wordpress-org/icon.svg LICENSE dist/site/
echo "$PWD/dist/site"
