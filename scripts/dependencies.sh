#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

ref=0e89f7f4e19cefb961e5ee2de61145b4f0c018fc
mkdir -p .local vendor/port-libs/lanes
if [ ! -d .local/port-libs/.git ]; then
    git init .local/port-libs
    git -C .local/port-libs remote add origin https://github.com/adamziel/port-libs.git
    git -C .local/port-libs sparse-checkout init --cone
    git -C .local/port-libs sparse-checkout set lanes/pandoc/src lanes/markerpdf/src
    git -C .local/port-libs fetch --depth=1 --filter=blob:none origin "$ref"
    git -C .local/port-libs checkout --detach FETCH_HEAD
fi
if [ "$(git -C .local/port-libs rev-parse HEAD)" != "$ref" ]; then
    echo "The local library checkout differs from the pinned revision. Keep it unchanged and use a clean project folder." >&2
    exit 1
fi
for lane in pandoc markerpdf; do
    mkdir -p "vendor/port-libs/lanes/$lane"
    cp -R ".local/port-libs/lanes/$lane/src" "vendor/port-libs/lanes/$lane/"
done
composer dump-autoload --no-dev --optimize
