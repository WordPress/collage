<?php

declare(strict_types=1);

use PortLibs\Pandoc\EpubMediaBagComparisonHarness;
use PortLibs\Pandoc\EpubPackageReader;
use PortLibs\Pandoc\EpubReader;
use PortLibs\Pandoc\NativeWriter;

$imageBytes = static fn (): array => [
    'images/check.gif' => base64_decode('R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw=='),
    'images/photo' => "\xFF\xD8\xFF\xD9",
    'images/chart.png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='),
];

$opfXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-direct-image-spine</dc:identifier>
    <dc:title>Direct Image Spine EPUB</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="gif" href="images/check.gif" media-type="image/gif"/>
    <item id="jpeg" href="images/photo" media-type="image/jpeg"/>
    <item id="png" href="images/chart.png" media-type="image/png"/>
  </manifest>
  <spine>
    <itemref idref="gif"/>
    <itemref idref="jpeg"/>
    <itemref idref="png"/>
  </spine>
</package>
XML;

$containerXml = <<<'XML'
<?xml version="1.0"?>
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML;

$makeTempDir = static function (string $prefix): string {
    $base = tempnam(sys_get_temp_dir(), $prefix);
    if ($base === false) {
        throw new RuntimeException('Unable to allocate temporary directory');
    }
    @unlink($base);
    if (!mkdir($base, 0777, true) && !is_dir($base)) {
        throw new RuntimeException('Unable to create temporary directory: ' . $base);
    }

    return $base;
};

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $child = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($child)) {
            $removeTree($child);
        } else {
            @unlink($child);
        }
    }
    @rmdir($path);
};

$writeFile = static function (string $root, string $relativePath, string $bytes): void {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create fixture directory: ' . $directory);
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Unable to write fixture file: ' . $path);
    }
};

$writeDirectImagePackage = static function (string $root) use ($writeFile, $containerXml, $opfXml, $imageBytes): void {
    $writeFile($root, 'META-INF/container.xml', $containerXml);
    $writeFile($root, 'OPS/package.opf', $opfXml);
    foreach ($imageBytes() as $path => $bytes) {
        $writeFile($root, 'OPS/' . $path, $bytes);
    }
};

$writeDirectImageEpub = static function (string $path) use ($containerXml, $opfXml, $imageBytes): void {
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create temporary EPUB package');
    }
    $zip->addFromString('META-INF/container.xml', $containerXml);
    $zip->addFromString('OPS/package.opf', $opfXml);
    foreach ($imageBytes() as $imagePath => $bytes) {
        $zip->addFromString('OPS/' . $imagePath, $bytes);
    }
    $zip->close();
};

return [
    'reads direct image spine items as pandoc image blocks with media evidence' => static function (TestRunner $t) use ($writeDirectImageEpub, $imageBytes): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-direct-image-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }
        $writeDirectImageEpub($path);

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
            $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        } finally {
            @unlink($path);
        }

        $fullPaths = [
            'OPS/images/check.gif',
            'OPS/images/photo',
            'OPS/images/chart.png',
        ];
        $urls = [
            'images/check.gif',
            'images/photo',
            'images/chart.png',
        ];
        $bytesByUrl = $imageBytes();
        $markerBlocks = [$document->children[0], $document->children[2], $document->children[4]];
        $imageBlocks = [$document->children[1], $document->children[3], $document->children[5]];

        $t->same('Direct Image Spine EPUB', $meta['title']);
        $t->same(['paragraph', 'paragraph', 'paragraph', 'paragraph', 'paragraph', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['span', 'image', 'span', 'image', 'span', 'image'], array_map(static fn ($node): ?string => $node->children[0]->type ?? null, $document->children));
        $t->same(['check.gif', 'photo', 'chart.png'], array_map(static fn ($node): string => (string) $node->children[0]->attr('id'), $markerBlocks));
        $t->same($urls, array_map(static fn ($node): string => (string) $node->children[0]->attr('url'), $imageBlocks));
        $t->same([
            ['', ''],
            ['', ''],
            ['', ''],
        ], array_map(static fn ($node): array => [
            (string) $node->children[0]->attr('alt'),
            (string) $node->children[0]->attr('title'),
        ], $imageBlocks));
        $t->same($fullPaths, $meta['epubReadableResources']);
        $t->same($fullPaths, $meta['epubReferencedResources']);
        $t->same($fullPaths, $meta['epubImageResources']);
        $t->same($fullPaths, $meta['epubMediaBagResources']);
        $t->same('reader-media-bag-from-emitted-local-media-resources', $meta['epubMediaResourcePolicy']);
        $t->same(3, $meta['epubMediaResourceCount']);
        $t->same([
            'epub-media-resource-loaded:OPS/images/check.gif',
            'epub-media-resource-loaded:OPS/images/photo',
            'epub-media-resource-loaded:OPS/images/chart.png',
        ], $meta['epubMediaResourceDiagnostics']);
        $t->same([
            ['path' => 'images/chart.png', 'zipEntry' => 'OPS/images/chart.png', 'mimeType' => 'image/png', 'byteLength' => strlen($bytesByUrl['images/chart.png']), 'sha1' => sha1($bytesByUrl['images/chart.png'])],
            ['path' => 'images/check.gif', 'zipEntry' => 'OPS/images/check.gif', 'mimeType' => 'image/gif', 'byteLength' => strlen($bytesByUrl['images/check.gif']), 'sha1' => sha1($bytesByUrl['images/check.gif'])],
            ['path' => 'images/photo', 'zipEntry' => 'OPS/images/photo', 'mimeType' => 'image/jpeg', 'byteLength' => strlen($bytesByUrl['images/photo']), 'sha1' => sha1($bytesByUrl['images/photo'])],
        ], array_map(static fn (array $entry): array => [
            'path' => (string) $entry['path'],
            'zipEntry' => (string) $entry['zipEntry'],
            'mimeType' => (string) $entry['mimeType'],
            'byteLength' => (int) $entry['byteLength'],
            'sha1' => (string) $entry['sha1'],
        ], $meta['epubMediaResourceDirectory']));
        $t->same([true, true, true], array_map(static fn (array $item): bool => (bool) $item['readable'], $meta['epubManifestItems']));
        $t->same([true, true, true], array_map(static fn (array $item): bool => (bool) $item['readable'], $meta['epubSpineItemRefs']));
        $t->contains('Span ( "check.gif"', $native);
        $t->contains('Span ( "photo"', $native);
        $t->contains('Span ( "chart.png"', $native);
        $t->contains('( "images/check.gif" , "" )', $native);
        $t->contains('( "images/photo" , "" )', $native);
        $t->contains('( "images/chart.png" , "" )', $native);
    },

    'keeps svg spine items marker-only like upstream pandoc' => static function (TestRunner $t) use ($containerXml): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-direct-svg-spine-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', $containerXml);
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-direct-svg-spine</dc:identifier>
    <dc:title>Direct SVG Spine EPUB</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="svg" href="images/cover.svg" media-type="image/svg+xml" properties="svg"/>
  </manifest>
  <spine>
    <itemref idref="svg"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/images/cover.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"><title>Cover</title><rect width="16" height="16"/></svg>');
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
            $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        } finally {
            @unlink($path);
        }

        $t->same('Direct SVG Spine EPUB', $meta['title']);
        $t->same(['paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('span', $document->children[0]->children[0]->type ?? null);
        $t->same('cover.svg', $document->children[0]->children[0]->attr('id'));
        $t->same([], $meta['epubReadableResources']);
        $t->same([], $meta['epubReferencedResources']);
        $t->same(['OPS/images/cover.svg'], $meta['epubImageResources']);
        $t->same([], $meta['epubMediaBagResources']);
        $t->same(0, $meta['epubMediaResourceCount']);
        $t->same(false, $meta['epubManifestItems'][0]['readable'] ?? null);
        $t->same(false, $meta['epubSpineItemRefs'][0]['readable'] ?? null);
        $t->contains('Span ( "cover.svg"', $native);
        $t->true(!str_contains($native, 'Image'), 'SVG spine items should not be emitted as direct image blocks');
    },

    'keeps upstream cover block when cover image is also a direct image spine item' => static function (TestRunner $t) use ($containerXml): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-cover-image-spine-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $coverBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
        if (!is_string($coverBytes)) {
            throw new RuntimeException('Unable to decode cover image fixture bytes');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', $containerXml);
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-cover-image-spine</dc:identifier>
    <dc:title>Cover Image Spine EPUB</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="cover" href="images/cover.png" media-type="image/png" properties="cover-image"/>
  </manifest>
  <spine>
    <itemref idref="cover"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/images/cover.png', $coverBytes);
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
            $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        } finally {
            @unlink($path);
        }

        $t->same('Cover Image Spine EPUB', $meta['title']);
        $t->same(['paragraph', 'paragraph', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['image', 'span', 'image'], array_map(static fn ($node): string => (string) ($node->children[0]->type ?? ''), $document->children));
        $t->same('images/cover.png', $document->children[0]->children[0]->attr('url'));
        $t->same('cover.png', $document->children[1]->children[0]->attr('id'));
        $t->same('images/cover.png', $document->children[2]->children[0]->attr('url'));
        $t->same(['OPS/images/cover.png'], $meta['epubReadableResources']);
        $t->same(['OPS/images/cover.png'], $meta['epubReferencedResources']);
        $t->same(['OPS/images/cover.png'], $meta['epubMediaBagResources']);
        $t->same(1, $meta['epubMediaResourceCount']);
        $t->same(['epub-media-resource-loaded:OPS/images/cover.png'], $meta['epubMediaResourceDiagnostics']);
        $t->same(2, substr_count($native, '( "images/cover.png" , "" )'));
    },

    'preserves direct image spine href suffixes while loading the package part media' => static function (TestRunner $t) use ($containerXml): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-direct-image-suffix-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $imageBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
        if (!is_string($imageBytes)) {
            throw new RuntimeException('Unable to decode direct image suffix fixture bytes');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', $containerXml);
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-direct-image-suffix</dc:identifier>
    <dc:title>Direct Image Suffix EPUB</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="chart" href="images/chart.png?view=print#panel" media-type="image/png"/>
  </manifest>
  <spine>
    <itemref idref="chart"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/images/chart.png', $imageBytes);
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
            $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        } finally {
            @unlink($path);
        }

        $image = $document->children[1]->children[0] ?? new PortLibs\Pandoc\AstNode('missing');

        $t->same('Direct Image Suffix EPUB', $meta['title']);
        $t->same('chart.png', $document->children[0]->children[0]->attr('id'));
        $t->same('image', $image->type);
        $t->same('images/chart.png?view=print#panel', $image->attr('url'));
        $t->same(['OPS/images/chart.png'], $meta['epubReadableResources']);
        $t->same(['OPS/images/chart.png#panel'], $meta['epubReferencedResources']);
        $t->same(['OPS/images/chart.png'], $meta['epubImageResources']);
        $t->same(['OPS/images/chart.png'], $meta['epubMediaBagResources']);
        $t->same(1, $meta['epubMediaResourceCount']);
        $t->same(['epub-media-resource-loaded:OPS/images/chart.png'], $meta['epubMediaResourceDiagnostics']);
        $t->same([
            [
                'path' => 'images/chart.png',
                'zipEntry' => 'OPS/images/chart.png',
                'mimeType' => 'image/png',
                'byteLength' => strlen($imageBytes),
                'sha1' => sha1($imageBytes),
            ],
        ], array_map(static fn (array $entry): array => [
            'path' => (string) $entry['path'],
            'zipEntry' => (string) $entry['zipEntry'],
            'mimeType' => (string) $entry['mimeType'],
            'byteLength' => (int) $entry['byteLength'],
            'sha1' => (string) $entry['sha1'],
        ], $meta['epubMediaResourceDirectory']));
        $t->contains('( "images/chart.png?view=print#panel" , "" )', $native);
    },

    'emits direct image spine blocks even when the package image part is missing' => static function (TestRunner $t) use ($containerXml): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-missing-direct-image-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', $containerXml);
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-missing-direct-image-spine</dc:identifier>
    <dc:title>Missing Direct Image Spine EPUB</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="missing" href="images/missing.png" media-type="image/png"/>
  </manifest>
  <spine>
    <itemref idref="missing"/>
  </spine>
</package>
XML);
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
            $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        } finally {
            @unlink($path);
        }

        $t->same('Missing Direct Image Spine EPUB', $meta['title']);
        $t->same(['paragraph', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['span', 'image'], array_map(static fn ($node): ?string => $node->children[0]->type ?? null, $document->children));
        $t->same('missing.png', $document->children[0]->children[0]->attr('id'));
        $t->same('images/missing.png', $document->children[1]->children[0]->attr('url'));
        $t->same(['OPS/images/missing.png'], $meta['epubReadableResources']);
        $t->same(['OPS/images/missing.png'], $meta['epubReferencedResources']);
        $t->same(['OPS/images/missing.png'], $meta['epubImageResources']);
        $t->same(['OPS/images/missing.png'], $meta['epubMediaBagResources']);
        $t->same(0, $meta['epubMediaResourceCount']);
        $t->same([], $meta['epubMediaResourceDirectory']);
        $t->same(['epub-media-resource-missing:OPS/images/missing.png'], $meta['epubMediaResourceDiagnostics']);
        $t->same(true, $meta['epubManifestItems'][0]['readable'] ?? null);
        $t->same(true, $meta['epubSpineItemRefs'][0]['readable'] ?? null);
        $t->contains('Span ( "missing.png"', $native);
        $t->contains('( "images/missing.png" , "" )', $native);
    },

    'matches upstream-style media bag evidence for direct image spine items' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile, $writeDirectImageEpub, $imageBytes): void {
        $root = $makeTempDir('pandoc-epub-direct-image-bag-');
        try {
            $writeFile($root, 'test/Tests/Readers/EPUB.hs', sprintf(
                <<<'HS'
directImageSpineBag = [("images/check.gif","image/gif",%d)
                      ,("images/photo","image/jpeg",%d)
                      ,("images/chart.png","image/png",%d)]

tests = [ testCase "direct image spine bag"
          (testMediaBag "epub/direct-image-spine.epub" directImageSpineBag) ]
HS,
                strlen($imageBytes()['images/check.gif']),
                strlen($imageBytes()['images/photo']),
                strlen($imageBytes()['images/chart.png'])
            ));
            $writeFile($root, 'src/Text/Pandoc/Readers/EPUB.hs', "module Text.Pandoc.Readers.EPUB where\n");
            $epubPath = $root . '/test/epub/direct-image-spine.epub';
            if (!is_dir(dirname($epubPath)) && !mkdir(dirname($epubPath), 0777, true) && !is_dir(dirname($epubPath))) {
                throw new RuntimeException('Unable to create EPUB fixture directory');
            }
            $writeDirectImageEpub($epubPath);

            $report = (new EpubMediaBagComparisonHarness())->run($root);

            $t->same('completed', $report['status']);
            $t->same(1, $report['comparedCaseCount']);
            $t->same(1, $report['epubParsedCount']);
            $t->same(0, $report['parseFailureCount']);
            $t->same(3, $report['expectedMediaItemCount']);
            $t->same(3, $report['actualMediaItemCount']);
            $t->same(1, $report['mediaBagMatchCount']);
            $t->same(0, $report['mediaBagMismatchCount']);
            $t->same('media-bag-implementation-equivalence-observed', $report['mediaBagParityStatus']);
        } finally {
            $removeTree($root);
        }
    },

    'reads direct image spine items from unpacked epub package directories' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeDirectImagePackage): void {
        $root = $makeTempDir('pandoc-epub-direct-image-package-');
        try {
            $writeDirectImagePackage($root);
            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $markerBlocks = [$document->children[0], $document->children[2], $document->children[4]];
            $imageBlocks = [$document->children[1], $document->children[3], $document->children[5]];

            $t->same(['paragraph', 'paragraph', 'paragraph', 'paragraph', 'paragraph', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
            $t->same(['span', 'image', 'span', 'image', 'span', 'image'], array_map(static fn ($node): ?string => $node->children[0]->type ?? null, $document->children));
            $t->same(['check.gif', 'photo', 'chart.png'], array_map(static fn ($node): string => (string) $node->children[0]->attr('id'), $markerBlocks));
            $t->same([
                'OPS/images/check.gif',
                'OPS/images/photo',
                'OPS/images/chart.png',
            ], array_map(static fn ($node): string => (string) $node->children[0]->attr('url'), $imageBlocks));
            $t->same([true, true, true], array_map(static fn (array $item): bool => (bool) $item['readable'], $epub['spine']));
            $t->same(3, $epub['spineReport']['readableItemCount']);
            $t->same(0, $epub['spineReport']['skippedItemCount']);
        } finally {
            $removeTree($root);
        }
    },
];
