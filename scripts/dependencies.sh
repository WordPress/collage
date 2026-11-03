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
mkdir -p vendor/port-libs/lanes/pandoc
cp -R .local/port-libs/lanes/pandoc/src vendor/port-libs/lanes/pandoc/

# Pandoc reads existing PDF text and images without MarkerPDF's OCR pipeline.
# Recreate this generated tree so old installs cannot leave OCR files in the ZIP.
source=.local/port-libs/lanes/markerpdf/src
target=vendor/port-libs/lanes/markerpdf/src
rm -rf "$target"
mkdir -p "$target"
cp "$source"/{BboxGeometry,MarkerAppPreview,PdfActionReviewExtractor,PdfAnnotationExtractor,PdfClassicXrefRebuilder,PdfDocumentFacts,PdfDocumentSecurityStoreExtractor,PdfImageRenderer,PdfMarkupAnnotationExtractor,PdfMetadataExtractor,PdfOutlineExtractor,PdfPageFacts,PdfPagePropertyExtractor,PdfTextExtractor,PdfXrefFreeObjectMap}.php "$target/"
cp -R "$source/Resources" "$target/"

composer dump-autoload --no-dev --optimize
