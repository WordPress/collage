<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\EpubPackageReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$fixture = static fn (): string => dirname(__DIR__) . '/fixtures/epub3-package';
$writePackageFile = static function (string $root, string $relativePath, string $bytes): void {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new \RuntimeException('Unable to create EPUB fixture directory: ' . $directory);
    }
    if (file_put_contents($path, $bytes) === false) {
        throw new \RuntimeException('Unable to write EPUB fixture file: ' . $path);
    }
};
$removeDirectory = static function (string $directory): void {
    if (!is_dir($directory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($directory);
};

return [
    'maps epub container opf manifest spine and metadata handoff' => static function (TestRunner $t) use ($fixture): void {
        $document = (new EpubPackageReader())->readDirectory($fixture());
        $meta = $document->attr('meta');
        $epub = $document->attr('epub');
        $manifest = $epub['manifestById'];
        $spine = $epub['spine'];

        $t->same('document', $document->type);
        $t->same('WordPress EPUB Import Packet', $meta['title']);
        $t->same(['Migration Team'], $meta['creators']);
        $t->same('en', $meta['language']);
        $t->same('urn:isbn:9780000000002', $meta['identifier']);
        $t->same('Port Libs Press', $meta['publisher']);
        $t->same('2026-06-09', $meta['date']);
        $t->same('EPUB/package.opf', $epub['containerRootfile']);
        $t->same('3.0', $epub['packageVersion']);
        $t->same('pub-id', $epub['uniqueIdentifierId']);
        $t->same(3, count($epub['metadataProperties']));
        $t->same('dcterms:modified', $epub['metadataProperties'][0]['property']);
        $t->same('2026-06-09T11:50:37Z', $epub['metadataProperties'][0]['value']);
        $t->same('file-as', $epub['metadataProperties'][2]['property']);
        $t->same('#creator', $epub['metadataProperties'][2]['refines']);
        $t->same(2, count($epub['metadataLinks']));
        $t->same('source-record', $epub['metadataLinks'][0]['id']);
        $t->same(['record'], $epub['metadataLinks'][0]['rel']);
        $t->same('chapter1.xhtml#opening-title', $epub['metadataLinks'][0]['href']);
        $t->same('EPUB/chapter1.xhtml', $epub['metadataLinks'][0]['path']);
        $t->same('opening-title', $epub['metadataLinks'][0]['fragment']);
        $t->same('application/xhtml+xml', $epub['metadataLinks'][0]['mediaType']);
        $t->same(['preview'], $epub['metadataLinks'][0]['properties']);
        $t->same('#pub-id', $epub['metadataLinks'][0]['refines']);
        $t->same(false, $epub['metadataLinks'][0]['external']);
        $t->same('remote-a11y', $epub['metadataLinks'][1]['id']);
        $t->same(['record', 'accessibility'], $epub['metadataLinks'][1]['rel']);
        $t->same('https://example.test/a11y/report.json', $epub['metadataLinks'][1]['path']);
        $t->same(true, $epub['metadataLinks'][1]['external']);
        $t->same(5, count($epub['manifest']));
        $t->same('nav.xhtml', $manifest['nav']['href']);
        $t->same('EPUB/nav.xhtml', $manifest['nav']['path']);
        $t->same('application/xhtml+xml', $manifest['nav']['mediaType']);
        $t->same(['nav'], $manifest['nav']['properties']);
        $t->same('EPUB/images/cover.png', $manifest['cover']['path']);
        $t->same(['cover-image'], $manifest['cover']['properties']);
        $t->same(2, count($spine));
        $t->same('chapter1', $spine[0]['idref']);
        $t->same('EPUB/chapter1.xhtml', $spine[0]['path']);
        $t->same(true, $spine[0]['linear']);
        $t->same('chapter2', $spine[1]['idref']);
        $t->same('EPUB/chapter2.xhtml', $spine[1]['path']);
        $t->same(true, $spine[1]['linear']);
    },
    'reports epub container rootfile declaration matrix for compact package review' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-container-rootfiles-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/alternate.opf?profile=print" media-type="application/oebps-package+xml; charset=utf-8"/>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
    <rootfile full-path="../outside.opf" media-type="application/oebps-package+xml"/>
    <rootfile full-path="https://example.invalid/book.opf" media-type="application/oebps-package+xml"/>
    <rootfile full-path="EPUB/missing.opf" media-type="application/oebps-package+xml"/>
    <rootfile full-path="EPUB/preview.xml" media-type="application/xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/alternate.opf', '<package xmlns="http://www.idpf.org/2007/opf" version="3.0"/>');
            $writePackageFile($root, 'EPUB/preview.xml', '<preview/>');
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:container-rootfiles</dc:identifier>
    <dc:title>Container Rootfiles</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml">Chapter</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable container rootfile package.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $meta = $document->attr('meta');
            $epub = $document->attr('epub');
            $report = $epub['containerRootfileReport'];
            $rootfiles = $epub['containerRootfiles'];

            $t->same('Container Rootfiles', $meta['title']);
            $t->same('EPUB/package.opf', $epub['containerRootfile']);
            $t->same(1, $epub['containerSelectedRootfileIndex']);
            $t->same($report, $epub['containerReport']);
            $t->same($report['diagnostics'], $epub['containerDiagnostics']);
            $t->same($report['diagnostics'], $epub['containerRootfileDiagnostics']);
            $t->same('EPUB/package.opf', $report['opfPart']);
            $t->same('EPUB/package.opf', $report['selectedRootfile']);
            $t->same(1, $report['selectedIndex']);
            $t->same(1, $report['selectedRootfileIndex']);
            $t->same('media-type-opf', $report['selectedBy']);
            $t->same(6, $report['rootfileCount']);
            $t->same(5, $report['opfRootfileCount']);
            $t->same(5, $report['alternateRootfileCount']);
            $t->same(1, $report['nonOpfRootfileCount']);
            $t->same(4, $report['localRootfileCount']);
            $t->same(1, $report['externalRootfileCount']);
            $t->same(1, $report['unsafeRootfileCount']);
            $t->same(1, $report['missingRootfileCount']);
            $t->same(1, $report['missingPackagePartCount']);
            $t->same(1, $report['suffixRootfileCount']);
            $t->same(1, $report['mediaTypeParameterRootfileCount']);
            $t->same(4, $report['diagnosticCount']);
            $t->same(false, $report['valid']);
            $t->same([
                'external-rootfile-full-path' => 1,
                'missing-rootfile-package-part' => 1,
                'rootfile-full-path-suffix' => 1,
                'unsafe-rootfile-full-path' => 1,
            ], $report['diagnosticTypes']);
            $t->same([
                'rootfile-full-path-suffix',
                'unsafe-rootfile-full-path',
                'external-rootfile-full-path',
                'missing-rootfile-package-part',
            ], array_column($report['diagnostics'], 'type'));
            $t->same('EPUB/alternate.opf', $rootfiles[0]['path']);
            $t->same('application/oebps-package+xml', $rootfiles[0]['mediaTypeBase']);
            $t->same(true, $rootfiles[0]['mediaTypeHasParameters']);
            $t->same(true, $rootfiles[0]['exists']);
            $t->same(true, $rootfiles[0]['hasQuery']);
            $t->same('profile=print', $rootfiles[0]['query']);
            $t->same(false, $rootfiles[0]['selected']);
            $t->same(true, $rootfiles[1]['selected']);
            $t->same(true, $rootfiles[2]['unsafe']);
            $t->same(true, $rootfiles[3]['external']);
            $t->same(false, $rootfiles[4]['exists']);
            $t->same('EPUB/missing.opf', $rootfiles[4]['path']);
            $t->same(false, $rootfiles[5]['opfPackageCandidate']);
            $t->same('EPUB/preview.xml', $rootfiles[5]['path']);
            $t->same([
                'selectedRootfile' => 'EPUB/package.opf',
                'selectedIndex' => 1,
                'selectedRootfileIndex' => 1,
                'selectedBy' => 'media-type-opf',
                'rootfileCount' => 6,
                'opfRootfileCount' => 5,
                'alternateRootfileCount' => 5,
                'nonOpfRootfileCount' => 1,
                'localRootfileCount' => 4,
                'externalRootfileCount' => 1,
                'unsafeRootfileCount' => 1,
                'missingRootfileCount' => 1,
                'missingPackagePartCount' => 1,
                'suffixRootfileCount' => 1,
                'diagnosticCount' => 4,
                'valid' => false,
            ], $report['summary']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports directory package container rootfile authoring fields for review' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-container-rootfile-authoring-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" xmlns:review="https://example.invalid/epub-review" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/preview.xml" media-type="application/xml" data-review-state="ignored"/>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml; profile=&quot;primary-opf&quot;" xml:lang="en" dir="ltr" review:profile="primary" data-review-state="selected"/>
    <rootfile full-path="EPUB/fixed/package.opf" media-type="application/oebps-package+xml" review:profile="fixed-layout" data-review-state="alternate"/>
    <rootfile full-path="EPUB/missing/package.opf?review=1#rendition" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/preview.xml', '<preview>not an OPF package</preview>');
            $writePackageFile($root, 'EPUB/fixed/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="fixedid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="fixedid">urn:uuid:container-fixed-rendition</dc:identifier>
    <dc:title>Fixed Rendition</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest/>
  <spine/>
</package>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:container-rootfile-report</dc:identifier>
    <dc:title>Container Rootfile Report</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml">Chapter</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable container rootfile report.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $container = $epub['containerReport'];
            $rootfiles = $container['rootfiles'];

            $t->same('EPUB/package.opf', $epub['containerRootfile']);
            $t->same(1, $epub['containerSelectedRootfileIndex']);
            $t->same($container, $epub['containerRootfileReport']);
            $t->same($container['diagnostics'], $epub['containerDiagnostics']);
            $t->same('EPUB/package.opf', $container['opfPart']);
            $t->same('EPUB/package.opf', $container['selectedRootfile']);
            $t->same(1, $container['selectedRootfileIndex']);
            $t->same(4, $container['rootfileCount']);
            $t->same(3, $container['opfRootfileCount']);
            $t->same(3, $container['alternateRootfileCount']);
            $t->same(1, $container['missingRootfileCount']);
            $t->same(2, $container['diagnosticCount']);
            $t->same(['missing-rootfile-package-part', 'rootfile-full-path-suffix'], array_column($container['diagnostics'], 'type'));

            $t->same('EPUB/preview.xml', $rootfiles[0]['path']);
            $t->same('application/xml', $rootfiles[0]['mediaType']);
            $t->same(false, $rootfiles[0]['opfPackageCandidate']);
            $t->same(false, $rootfiles[0]['selected']);
            $t->same(true, $rootfiles[0]['exists']);
            $t->same(['data-review-state' => 'ignored'], $rootfiles[0]['customAttributes']);

            $t->same('EPUB/package.opf', $rootfiles[1]['fullPath']);
            $t->same('EPUB/package.opf', $rootfiles[1]['target']);
            $t->same('application/oebps-package+xml; profile="primary-opf"', $rootfiles[1]['mediaType']);
            $t->same('application/oebps-package+xml', $rootfiles[1]['baseMediaType']);
            $t->same('application/oebps-package+xml; profile=primary-opf', $rootfiles[1]['normalizedMediaType']);
            $t->same(true, $rootfiles[1]['mediaTypeHasParameters']);
            $t->same(['profile' => 'primary-opf'], $rootfiles[1]['mediaTypeParameterMap']);
            $t->same(['profile'], $rootfiles[1]['mediaTypeParameterNames']);
            $t->same(1, $rootfiles[1]['mediaTypeParameterCount']);
            $t->same('en', $rootfiles[1]['language']);
            $t->same('ltr', $rootfiles[1]['direction']);
            $t->same([
                'full-path' => 'EPUB/package.opf',
                'media-type' => 'application/oebps-package+xml; profile="primary-opf"',
                'xml:lang' => 'en',
                'dir' => 'ltr',
                'review:profile' => 'primary',
                'data-review-state' => 'selected',
            ], $rootfiles[1]['attributes']);
            $t->same([
                'xml:lang' => 'en',
                'dir' => 'ltr',
                'review:profile' => 'primary',
                'data-review-state' => 'selected',
            ], $rootfiles[1]['customAttributes']);
            $t->same(true, $rootfiles[1]['selected']);
            $t->same([], $rootfiles[1]['diagnostics']);

            $t->same('EPUB/fixed/package.opf', $rootfiles[2]['path']);
            $t->same(true, $rootfiles[2]['exists']);
            $t->same(false, $rootfiles[2]['selected']);
            $t->same(['review:profile' => 'fixed-layout', 'data-review-state' => 'alternate'], $rootfiles[2]['customAttributes']);

            $t->same('EPUB/missing/package.opf?review=1#rendition', $rootfiles[3]['target']);
            $t->same('EPUB/missing/package.opf', $rootfiles[3]['path']);
            $t->same(false, $rootfiles[3]['exists']);
            $t->same(true, $rootfiles[3]['fullPathHasQuery']);
            $t->same('review=1', $rootfiles[3]['fullPathQuery']);
            $t->same(true, $rootfiles[3]['fullPathHasFragment']);
            $t->same('rendition', $rootfiles[3]['fullPathFragment']);
            $t->same(['missing-rootfile-package-part', 'rootfile-full-path-suffix'], array_column($rootfiles[3]['diagnostics'], 'type'));
            $t->same('EPUB/chapter.xhtml', $epub['manifestById']['chapter']['path']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports epub opf package root authoring provenance for package review' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-package-root-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" xmlns:review="https://example.invalid/epub-review" id="pkg-root" version="3.0" unique-identifier="bookid" xml:lang="ja" dir="rtl" xml:base="sections/" prefix="rendition: http://www.idpf.org/vocab/rendition/# review: https://example.invalid/epub-review# review: https://example.invalid/epub-review-override# broken" data-package="source" review:source="wp-import">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:package-root-report</dc:identifier>
    <dc:title>Package Root Report</dc:title>
    <dc:language>ja</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml">Chapter</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable package root report.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $package = $epub['packageReport'];

            $t->same('pkg-root', $package['id']);
            $t->same('3.0', $package['version']);
            $t->same('bookid', $package['uniqueIdentifierId']);
            $t->same(true, $package['uniqueIdentifierMatched']);
            $t->same('urn:uuid:package-root-report', $package['uniqueIdentifierValue']);
            $t->same('ja', $package['language']);
            $t->same('rtl', $package['direction']);
            $t->same('sections/', $package['base']);
            $t->same('reported-not-applied-to-package-paths', $package['baseResolutionPolicy']);
            $t->same(3, $package['prefixDeclarationCount']);
            $t->same(2, $package['prefixCount']);
            $t->same([
                'rendition' => 'http://www.idpf.org/vocab/rendition/#',
                'review' => 'https://example.invalid/epub-review-override#',
            ], $package['prefixBindings']);
            $t->same(1, $package['duplicatePrefixCount']);
            $t->same(1, $package['invalidPrefixDeclarationCount']);
            $t->same(9, $package['attributeCount']);
            $t->same('sections/', $package['attributes']['xml:base']);
            $t->same('wp-import', $package['attributes']['review:source']);
            $t->same([
                'data-package' => 'source',
                'review:source' => 'wp-import',
            ], $package['customAttributes']);
            $t->same(2, $package['customAttributeCount']);
            $t->same(2, $package['diagnosticCount']);
            $t->same(['duplicate-package-prefix-declaration', 'invalid-package-prefix-declaration'], array_column($package['diagnostics'], 'type'));
            $t->same('review', $package['diagnostics'][0]['prefix']);
            $t->same('broken', $package['diagnostics'][1]['value']);
            $t->same([
                'version' => '3.0',
                'uniqueIdentifierId' => 'bookid',
                'uniqueIdentifierMatched' => true,
                'language' => 'ja',
                'direction' => 'rtl',
                'base' => 'sections/',
                'prefixCount' => 2,
                'prefixDeclarationCount' => 3,
                'prefixBindingCount' => 2,
                'prefixDiagnosticCount' => 2,
                'customAttributeCount' => 2,
                'diagnosticCount' => 2,
                'packageDiagnosticCount' => 2,
                'identifierCount' => 1,
                'selectedIdentifier' => 'urn:uuid:package-root-report',
                'selectedBy' => 'unique-identifier',
                'identifierDiagnosticCount' => 0,
                'valid' => false,
            ], $package['summary']);
            $t->same('EPUB/chapter.xhtml', $epub['manifestById']['chapter']['path']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports epub opf metadata item matrix for package review' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-reader-metadata-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid" scheme="UUID">urn:uuid:reader-metadata-review</dc:identifier>
    <dc:title id="title-main" xml:lang="en">Reader Metadata Review</dc:title>
    <dc:title id="subtitle" xml:lang="es" dir="ltr">Revision bilingue</dc:title>
    <dc:creator id="author" role="aut" file-as="Team, Data Liberation">Data Liberation Team</dc:creator>
    <dc:language id="lang-main" scheme="BCP47">en-US</dc:language>
    <dc:subject id="subject" scheme="BISAC" xml:lang="en" dir="ltr">Computers / Data Migration</dc:subject>
    <dc:description id="summary">Reader metadata package review.</dc:description>
    <dc:rights id="rights" xml:lang="en">CC BY 4.0</dc:rights>
    <meta property="dcterms:modified">2026-06-15T01:57:47Z</meta>
    <meta refines="#subtitle" property="title-type">subtitle</meta>
    <meta refines="#subject" property="authority">BISAC Subject Headings</meta>
    <link id="subject-record" rel="record" refines="#subject" href="meta/subject.json" media-type="application/json" title="Subject authority record" hreflang="en-GB" xml:lang="en" dir="ltr" data-review="metadata-link"/>
    <link id="remote-rights" rel="license" refines="#rights" href="https://example.invalid/license" media-type="text/html" title="Remote license" hreflang="en" lang="en-US" dir="rtl" data-remote="no-fetch"/>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml">Chapter</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable metadata package.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $meta = $document->attr('meta');
            $epub = $document->attr('epub');
            $items = $epub['metadataItems'];
            $report = $epub['metadataReport'];

            $t->same('Reader Metadata Review', $meta['title']);
            $t->same(['Data Liberation Team'], $meta['creators']);
            $t->same('en-US', $meta['language']);
            $t->same('urn:uuid:reader-metadata-review', $meta['identifier']);

            $t->same(true, $report['present']);
            $t->same(8, count($items));
            $t->same(8, $report['itemCount']);
            $t->same(7, $report['kindCount']);
            $t->same([
                'creator' => 1,
                'description' => 1,
                'identifier' => 1,
                'language' => 1,
                'rights' => 1,
                'subject' => 1,
                'title' => 2,
            ], $report['kindCounts']);
            $t->same(2, $report['titleCount']);
            $t->same(1, $report['creatorCount']);
            $t->same(1, $report['languageCount']);
            $t->same(1, $report['subjectCount']);
            $t->same(1, $report['descriptionCount']);
            $t->same(1, $report['rightsCount']);
            $t->same(8, $report['idCount']);
            $t->same(4, $report['languageTaggedCount']);
            $t->same(2, $report['directionTaggedCount']);
            $t->same(3, $report['schemeCount']);
            $t->same(1, $report['roleCount']);
            $t->same(1, $report['fileAsCount']);

            $t->same('identifier', $items[0]['kind']);
            $t->same('http://purl.org/dc/elements/1.1/', $items[0]['namespace']);
            $t->same('dc', $items[0]['prefix']);
            $t->same('UUID', $items[0]['scheme']);
            $t->same('Revision bilingue', $report['itemsById']['subtitle']['value']);
            $t->same('es', $report['itemsById']['subtitle']['language']);
            $t->same('ltr', $report['itemsById']['subtitle']['direction']);
            $t->same('Data Liberation Team', $report['itemsByKind']['creator'][0]['text']);
            $t->same('aut', $report['itemsByKind']['creator'][0]['role']);
            $t->same('Team, Data Liberation', $report['itemsByKind']['creator'][0]['fileAs']);
            $t->same('BISAC', $report['itemsByKind']['subject'][0]['scheme']);
            $t->same('CC BY 4.0', $report['itemsById']['rights']['text']);

            $t->same(3, $report['propertyCount']);
            $t->same(2, $report['refinementPropertyCount']);
            $t->same('title-type', $report['refinementProperties'][0]['property']);
            $t->same('#subtitle', $report['refinementProperties'][0]['refines']);
            $t->same('authority', $report['refinementProperties'][1]['property']);
            $t->same('#subject', $report['refinementProperties'][1]['refines']);
            $t->same(2, $report['linkCount']);
            $t->same(1, $report['localLinkCount']);
            $t->same(1, $report['externalLinkCount']);
            $t->same(2, $report['linkTitleCount']);
            $t->same(2, $report['linkHreflangCount']);
            $t->same(2, $report['linkLanguageTaggedCount']);
            $t->same(2, $report['linkDirectionTaggedCount']);
            $t->same(2, $report['linkCustomAttributeCount']);
            $t->same('EPUB/meta/subject.json', $report['localLinks'][0]['path']);
            $t->same('Subject authority record', $report['localLinks'][0]['title']);
            $t->same('en-GB', $report['localLinks'][0]['hreflang']);
            $t->same('en', $report['localLinks'][0]['language']);
            $t->same('xml:lang', $report['localLinks'][0]['languageSource']);
            $t->same('ltr', $report['localLinks'][0]['direction']);
            $t->same(['data-review' => 'metadata-link'], $report['localLinks'][0]['customAttributes']);
            $t->same('https://example.invalid/license', $report['externalLinks'][0]['path']);
            $t->same('Remote license', $report['externalLinks'][0]['title']);
            $t->same('en', $report['externalLinks'][0]['hreflang']);
            $t->same('en-US', $report['externalLinks'][0]['language']);
            $t->same('lang', $report['externalLinks'][0]['languageSource']);
            $t->same('rtl', $report['externalLinks'][0]['direction']);
            $t->same(['data-remote' => 'no-fetch'], $report['externalLinks'][0]['customAttributes']);
            $t->same([$report['localLinks'][0], $report['externalLinks'][0]], $report['titledLinks']);
            $t->same([$report['localLinks'][0], $report['externalLinks'][0]], $report['hreflangLinks']);
            $t->same([$report['localLinks'][0], $report['externalLinks'][0]], $report['languageTaggedLinks']);
            $t->same([$report['localLinks'][0], $report['externalLinks'][0]], $report['directionTaggedLinks']);
            $t->same($report['kindCounts'], $report['summary']['kindCounts']);
            $t->same(2, $report['summary']['refinementPropertyCount']);
            $t->same(2, $report['summary']['linkTitleCount']);
            $t->same(2, $report['summary']['linkHreflangCount']);
            $t->same(2, $report['summary']['linkLanguageTaggedCount']);
            $t->same(2, $report['summary']['linkDirectionTaggedCount']);
        } finally {
            $removeDirectory($root);
        }
    },
    'ignores non dc-prefixed package metadata items for pandoc parity' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-reader-metadata-dc-prefix-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         xmlns:dcx="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="bookid">
  <metadata>
    <dc:identifier id="bookid">urn:uuid:reader-dc-prefix-parity</dc:identifier>
    <dc:title id="dc-title">DC Prefix Package Title</dc:title>
    <dc:creator>DC Prefix Author</dc:creator>
    <dc:language>en</dc:language>
    <dcx:title id="alternate-title">Alternate Prefix Title</dcx:title>
    <title id="bare-title">Bare OPF Title</title>
    <meta property="dcterms:modified">2026-07-05T00:00:00Z</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml">Chapter</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable DC prefix package.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $meta = $document->attr('meta');
            $epub = $document->attr('epub');
            $report = $epub['metadataReport'];

            $t->same('DC Prefix Package Title', $meta['title']);
            $t->same(['DC Prefix Author'], $meta['creators']);
            $t->same('en', $meta['language']);
            $t->same('urn:uuid:reader-dc-prefix-parity', $meta['identifier']);
            $t->same(4, $report['itemCount']);
            $t->same([
                'creator' => 1,
                'identifier' => 1,
                'language' => 1,
                'title' => 1,
            ], $report['kindCounts']);
            $t->same(false, array_key_exists('alternate-title', $report['itemsById']));
            $t->same(false, array_key_exists('bare-title', $report['itemsById']));
            $t->same(1, $report['propertyCount']);
            $t->same('2026-07-05T00:00:00Z', $epub['metadataProperties'][0]['value']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports epub opf metadata link target policy for direct package review' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-reader-metadata-links-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-metadata-link-review</dc:identifier>
    <dc:title>Metadata Link Review</dc:title>
    <dc:language>en</dc:language>
    <link id="publication-record" rel="record alternate" refines="#bookid" href="records/publication.json?profile=wp#record" media-type="application/json" properties="source review"/>
    <link id="missing-record" rel="review" href="records/missing.json" media-type="application/json"/>
    <link id="remote-record" rel="record" href="https://example.invalid/book.json" media-type="application/json"/>
    <link id="unmanifested-record" rel="preview" href="records/unmanifested.json" media-type="application/json"/>
    <link id="broken-record"/>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="publication-json" href="records/publication.json" media-type="application/json"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml">Chapter</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable metadata link package.</p></body></html>');
            $writePackageFile($root, 'EPUB/records/publication.json', '{"id":"publication"}');
            $writePackageFile($root, 'EPUB/records/unmanifested.json', '{"id":"unmanifested"}');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $report = $epub['metadataReport'];
            $linkReport = $epub['metadataLinkReport'];
            $links = $epub['metadataLinks'];

            $t->same($report['linkReport'], $linkReport);
            $t->same($links, $linkReport['links']);
            $t->same(5, $report['linkCount']);
            $t->same(3, $report['localLinkCount']);
            $t->same(1, $report['externalLinkCount']);
            $t->same(1, $report['missingLinkCount']);
            $t->same(5, $report['linkDiagnosticCount']);
            $t->same(['alternate' => 1, 'preview' => 1, 'record' => 2, 'review' => 1], $report['linkRelCounts']);
            $t->same([
                'EPUB/records/publication.json?profile=wp#record',
                'EPUB/records/missing.json',
                'https://example.invalid/book.json',
                'EPUB/records/unmanifested.json',
            ], $report['linkTargets']);

            $publication = $links[0];
            $t->same('publication-record', $publication['id']);
            $t->same(['record', 'alternate'], $publication['rel']);
            $t->same('EPUB/records/publication.json?profile=wp#record', $publication['target']);
            $t->same('EPUB/records/publication.json', $publication['path']);
            $t->same('record', $publication['fragment']);
            $t->same(true, $publication['hrefHasQuery']);
            $t->same('profile=wp', $publication['hrefQuery']);
            $t->same(true, $publication['hrefHasFragment']);
            $t->same('record', $publication['hrefFragment']);
            $t->same('publication-json', $publication['manifestId']);
            $t->same('application/json', $publication['manifestMediaType']);
            $t->same(true, $publication['exists']);
            $t->same([], $publication['diagnostics']);
            $t->same($publication, $report['linksByRel']['alternate'][0]);

            $t->same('missing-metadata-link-target', $links[1]['diagnostics'][0]['type']);
            $t->same('EPUB/records/missing.json', $linkReport['missingLinks'][0]['path']);
            $t->same('external-metadata-link-target', $links[2]['diagnostics'][0]['type']);
            $t->same('https://example.invalid/book.json', $report['linksByRel']['record'][1]['target']);
            $t->same('unmanifested-metadata-link-target', $links[3]['diagnostics'][0]['type']);
            $t->same('EPUB/records/unmanifested.json', $links[3]['path']);
            $t->same(null, $links[3]['manifestId']);
            $t->same(['missing-metadata-link-rel', 'missing-metadata-link-href'], array_column($links[4]['diagnostics'], 'type'));
            $t->same(
                ['missing-metadata-link-target', 'external-metadata-link-target', 'unmanifested-metadata-link-target', 'missing-metadata-link-rel', 'missing-metadata-link-href'],
                array_column($linkReport['diagnostics'], 'type')
            );
            $t->same(5, $report['summary']['linkCount']);
            $t->same(1, $report['summary']['missingLinkCount']);
            $t->same(5, $report['summary']['linkDiagnosticCount']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports epub metadata link media type parameters for direct reader review' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-reader-metadata-link-media-types-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:reader-metadata-link-media-type-review</dc:identifier>
    <dc:title>Metadata Link Media Type Review</dc:title>
    <dc:language>en</dc:language>
    <link id="review-record" rel="record alternate" refines="#bookid" href="meta/review.json#packet" media-type='application/ld+json; profile="schema;wp"; charset=UTF-8' properties="preview"/>
    <link id="remote-a11y" rel="accessibility" href="https://example.invalid/a11y.html" media-type="text/html; charset=utf-8; charset=windows-1252"/>
    <link id="broken-record" rel="record" href="meta/broken.review" media-type="applicationreview; broken"/>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml">Chapter</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable metadata link package.</p></body></html>');
            $writePackageFile($root, 'EPUB/meta/review.json', '{"review":true}');
            $writePackageFile($root, 'EPUB/meta/broken.review', 'BROKEN');

            $document = (new EpubPackageReader())->readDirectory($root);
            $meta = $document->attr('meta');
            $epub = $document->attr('epub');
            $links = $epub['metadataLinks'];
            $report = $epub['metadataReport'];
            $linkMediaTypes = $report['linkMediaTypes'];

            $t->same('Metadata Link Media Type Review', $meta['title']);
            $t->same(3, count($links));
            $t->same('application/ld+json; profile="schema;wp"; charset=UTF-8', $links[0]['mediaType']);
            $t->same('application/ld+json', $links[0]['baseMediaType']);
            $t->same('application/ld+json', $links[0]['mediaTypeBase']);
            $t->same('application/ld+json; profile=schema;wp; charset=utf-8', $links[0]['normalizedMediaType']);
            $t->same(['profile' => 'schema;wp', 'charset' => 'UTF-8'], $links[0]['mediaTypeParameterMap']);
            $t->same(['profile', 'charset'], $links[0]['mediaTypeParameterNames']);
            $t->same(true, $links[0]['mediaTypeSyntaxValid']);
            $t->same('EPUB/meta/review.json', $links[0]['path']);
            $t->same('packet', $links[0]['fragment']);
            $t->same(true, $links[1]['external']);
            $t->same(['charset' => 'windows-1252'], $links[1]['mediaTypeParameterMap']);
            $t->same(false, $links[1]['mediaTypeSyntaxValid']);
            $t->same('duplicate-metadata-link-media-type-parameter', $links[1]['mediaTypeDiagnostics'][0]['type']);
            $t->same(false, $links[2]['mediaTypeSyntaxValid']);
            $t->same([
                'invalid-metadata-link-media-type',
                'invalid-metadata-link-media-type-parameter',
            ], array_column($links[2]['mediaTypeDiagnostics'], 'type'));

            $t->same(true, $linkMediaTypes['present']);
            $t->same(3, $linkMediaTypes['linkCount']);
            $t->same(3, $linkMediaTypes['itemCount']);
            $t->same(3, $linkMediaTypes['declaredCount']);
            $t->same(2, $linkMediaTypes['parameterLinkCount']);
            $t->same(4, $linkMediaTypes['parameterCount']);
            $t->same(['charset', 'profile'], $linkMediaTypes['parameterNames']);
            $t->same([
                'application/ld+json' => 1,
                'applicationreview' => 1,
                'text/html' => 1,
            ], $linkMediaTypes['baseMediaTypeCounts']);
            $t->same(['review-record', 'remote-a11y'], array_column($linkMediaTypes['parameterItems'], 'id'));
            $t->same(3, $linkMediaTypes['diagnosticCount']);
            $t->same([
                'duplicate-metadata-link-media-type-parameter',
                'invalid-metadata-link-media-type',
                'invalid-metadata-link-media-type-parameter',
            ], array_column($linkMediaTypes['diagnostics'], 'type'));
            $t->same($linkMediaTypes, $report['linkMediaTypes']);
            $t->same($linkMediaTypes['parameterItems'], $report['linkMediaTypeParameterItems']);
            $t->same($linkMediaTypes['diagnostics'], $report['linkMediaTypeDiagnostics']);
            $t->same(4, $report['summary']['linkMediaTypeParameterCount']);
            $t->same(3, $report['summary']['linkMediaTypeDiagnosticCount']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports epub opf metadata refinement targets for direct package review' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-reader-refinement-targets-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" id="pkg-record" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:reader-refinement-targets</dc:identifier>
    <dc:title id="title-main">Reader Refinement Targets</dc:title>
    <dc:creator id="creator">Refinement Desk</dc:creator>
    <dc:language>en</dc:language>
    <meta id="title-kind" refines="#title-main" property="title-type">main</meta>
    <meta refines="#pkg-record" property="schema:name">Package record</meta>
    <meta refines="#chapter" property="schema:encodingFormat">application/xhtml+xml</meta>
    <meta refines="#chapter-entry" property="schema:position">1</meta>
    <meta refines="#series" property="schema:name">Series packet</meta>
    <meta refines="#series-record" property="schema:about">Series link</meta>
    <meta refines="#series-title" property="display-seq">1</meta>
    <meta refines="#creator-record" property="schema:about">Creator record</meta>
    <meta refines="#missing-target" property="schema:reviewStatus">missing</meta>
    <meta refines="https://example.invalid/meta#remote" property="schema:about">Remote refinement</meta>
    <meta refines="meta/authority.json#authority" property="schema:about">Package relative refinement</meta>
    <meta refines="#" property="schema:bad">Broken refinement</meta>
    <link id="creator-record" rel="record" refines="#creator" href="meta/creator.json" media-type="application/json"/>
    <link id="remote-record" rel="record" refines="https://example.invalid/meta#remote" href="https://example.invalid/remote.json" media-type="application/json"/>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="creator-json" href="meta/creator.json" media-type="application/json"/>
    <item id="series-json" href="meta/series.json" media-type="application/json"/>
  </manifest>
  <spine>
    <itemref id="chapter-entry" idref="chapter"/>
  </spine>
  <collection id="series" role="series">
    <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
      <dc:title id="series-title">Series Review</dc:title>
    </metadata>
    <link id="series-record" rel="record" href="meta/series.json" media-type="application/json"/>
  </collection>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml">Chapter</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable refinement package.</p></body></html>');
            $writePackageFile($root, 'EPUB/meta/creator.json', '{"name":"Refinement Desk"}');
            $writePackageFile($root, 'EPUB/meta/series.json', '{"name":"Series Review"}');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $report = $epub['metadataReport'];
            $targets = $epub['metadataRefinementTargets'];
            $find = static function (string $source, string $refines) use ($targets): array {
                foreach ($targets['items'] as $item) {
                    if (($item['source'] ?? null) === $source && ($item['refines'] ?? null) === $refines) {
                        return $item;
                    }
                }

                throw new RuntimeException('Missing refinement item: ' . $source . ' ' . $refines);
            };

            $t->same($targets, $report['refinementTargets']);
            $t->same($targets['diagnostics'], $epub['metadataRefinementTargetDiagnostics']);
            $t->same(true, $targets['present']);
            $t->same(15, $targets['targetIdCount']);
            $t->same(15, $targets['targetCount']);
            $t->same([
                'collection' => 1,
                'collection-link' => 1,
                'collection-metadata-item' => 1,
                'manifest-item' => 4,
                'metadata-item' => 3,
                'metadata-link' => 2,
                'metadata-meta' => 1,
                'package' => 1,
                'spine-itemref' => 1,
            ], $targets['targetKindCounts']);
            $t->same(14, $targets['refinementCount']);
            $t->same(9, $targets['resolvedRefinementCount']);
            $t->same(5, $targets['unresolvedRefinementCount']);
            $t->same(2, $targets['externalRefinementCount']);
            $t->same(1, $targets['packageRelativeRefinementCount']);
            $t->same(2, $targets['diagnosticCount']);
            $t->same(['unresolved-metadata-refinement-target', 'invalid-metadata-refinement-target'], array_column($targets['diagnostics'], 'type'));
            $t->same(14, $report['summary']['refinementTargetCount']);
            $t->same(9, $report['summary']['resolvedRefinementTargetCount']);
            $t->same(5, $report['summary']['unresolvedRefinementTargetCount']);
            $t->same(2, $report['summary']['refinementTargetDiagnosticCount']);

            $t->same(['package'], $find('metadata-meta', '#pkg-record')['targetKinds']);
            $t->same(['metadata-item'], $find('metadata-meta', '#title-main')['targetKinds']);
            $t->same(['manifest-item'], $find('metadata-meta', '#chapter')['targetKinds']);
            $t->same(['spine-itemref'], $find('metadata-meta', '#chapter-entry')['targetKinds']);
            $t->same(['collection'], $find('metadata-meta', '#series')['targetKinds']);
            $t->same(['collection-link'], $find('metadata-meta', '#series-record')['targetKinds']);
            $t->same(['collection-metadata-item'], $find('metadata-meta', '#series-title')['targetKinds']);
            $t->same(['metadata-link'], $find('metadata-meta', '#creator-record')['targetKinds']);
            $t->same(['metadata-item'], $find('metadata-link', '#creator')['targetKinds']);
            $t->same(false, $find('metadata-meta', '#missing-target')['resolved']);
            $t->same('missing-target', $find('metadata-meta', '#missing-target')['subjectId']);
            $t->same(true, $find('metadata-meta', 'https://example.invalid/meta#remote')['targetExternal']);
            $t->same(true, $find('metadata-meta', 'meta/authority.json#authority')['targetPackageRelative']);
            $t->same('invalid-metadata-refinement-target', $find('metadata-meta', '#')['diagnostics'][0]['type']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports epub opf package identity and identifier diagnostics for package review' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-reader-identity-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" id="package-record" version="3.0" unique-identifier="bookid" xml:lang="en" dir="ltr" prefix="schema: https://schema.org/">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="legacy-isbn" scheme="ISBN">9780000000007</dc:identifier>
    <dc:identifier id="bookid" scheme="UUID">urn:uuid:reader-identity-primary</dc:identifier>
    <dc:identifier id="bookid" scheme="UUID">urn:uuid:reader-identity-secondary</dc:identifier>
    <dc:identifier id="duplicate-isbn" scheme="ISBN">9780000000007</dc:identifier>
    <dc:title>Reader Identity Review</dc:title>
    <dc:language>en</dc:language>
    <meta refines="#bookid" property="identifier-type" scheme="onix:codelist5">15</meta>
    <meta refines="#duplicate-isbn" property="identifier-type" scheme="onix:codelist5">15</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml">Chapter</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable identity package.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $meta = $document->attr('meta');
            $epub = $document->attr('epub');
            $packageReport = $epub['packageReport'];
            $uniqueIdentifier = $packageReport['uniqueIdentifier'];
            $identifierDetails = $packageReport['identifierDetails'];
            $identifierSummary = $packageReport['identifierSummary'];

            $t->same('urn:uuid:reader-identity-primary', $meta['identifier']);
            $t->same('bookid', $epub['uniqueIdentifierId']);
            $t->same($packageReport, $epub['identityReport']);
            $t->same($uniqueIdentifier, $epub['uniqueIdentifier']);
            $t->same($identifierDetails, $epub['identifierDetails']);
            $t->same($identifierSummary, $epub['identifierSummary']);
            $t->same('package-record', $packageReport['id']);
            $t->same('3.0', $packageReport['version']);
            $t->same('bookid', $packageReport['uniqueIdentifierId']);
            $t->same('en', $packageReport['language']);
            $t->same('ltr', $packageReport['direction']);
            $t->same('schema: https://schema.org/', $packageReport['prefix']);

            $t->same(true, $uniqueIdentifier['specified']);
            $t->same('bookid', $uniqueIdentifier['id']);
            $t->same(true, $uniqueIdentifier['matched']);
            $t->same('urn:uuid:reader-identity-primary', $uniqueIdentifier['value']);
            $t->same('unique-identifier', $uniqueIdentifier['selectedBy']);
            $t->same(4, $uniqueIdentifier['identifierCount']);
            $t->same(2, $uniqueIdentifier['matchCount']);
            $t->same(1, $uniqueIdentifier['duplicateMatchCount']);
            $t->same(false, $uniqueIdentifier['valid']);
            $t->same('duplicate-unique-identifier-id', $uniqueIdentifier['diagnostics'][0]['type']);
            $t->same(['urn:uuid:reader-identity-primary', 'urn:uuid:reader-identity-secondary'], $uniqueIdentifier['diagnostics'][0]['values']);
            $t->same('15', $uniqueIdentifier['matchedEntries'][0]['identifierType']);
            $t->same('onix:codelist5', $uniqueIdentifier['matchedEntries'][0]['identifierTypeScheme']);

            $t->same(4, count($identifierDetails));
            $t->same(false, $identifierDetails[0]['selectedByUniqueIdentifier']);
            $t->same(true, $identifierDetails[1]['selectedByUniqueIdentifier']);
            $t->same(true, $identifierDetails[2]['selectedByUniqueIdentifier']);
            $t->same('15', $identifierDetails[1]['identifierType']);
            $t->same('onix:codelist5', $identifierDetails[1]['identifierTypeScheme']);
            $t->same(true, $identifierDetails[0]['duplicateValue']);
            $t->same(['legacy-isbn', 'duplicate-isbn'], $identifierDetails[0]['duplicateIds']);
            $t->same([0, 3], $identifierDetails[0]['duplicateIndexes']);

            $t->same(true, $identifierSummary['present']);
            $t->same(4, $identifierSummary['count']);
            $t->same(3, $identifierSummary['typedCount']);
            $t->same(['ISBN', 'UUID'], $identifierSummary['schemes']);
            $t->same(['15'], $identifierSummary['identifierTypes']);
            $t->same('urn:uuid:reader-identity-primary', $identifierSummary['selectedValue']);
            $t->same('bookid', $identifierSummary['selectedId']);
            $t->same(1, $identifierSummary['selectedIndex']);
            $t->same(1, $identifierSummary['duplicateValueCount']);
            $t->same('9780000000007', $identifierSummary['duplicatesByValue'][0]['value']);
            $t->same(['legacy-isbn', 'duplicate-isbn'], $identifierSummary['duplicatesByValue'][0]['ids']);
            $t->same(['duplicate-unique-identifier-id', 'duplicate-metadata-identifier-value'], array_column($packageReport['identifierDiagnostics'], 'type'));
            $t->same(false, $packageReport['valid']);
            $t->same(2, $packageReport['identifierDiagnosticCount']);
            $t->same($packageReport['identifierDiagnostics'], $epub['identifierDiagnostics']);
            $t->same('urn:uuid:reader-identity-primary', $packageReport['summary']['selectedIdentifier']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports epub opf package prefix declarations for reader review' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-reader-prefix-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:base="OPS/" prefix="schema: https://schema.org/ review: https://example.invalid/review# schema: https://schema.example/ broken-token">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:reader-prefix-report</dc:identifier>
    <dc:title>Reader Prefix Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml">Chapter</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable prefix package.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $packageReport = $epub['packageReport'];
            $prefixReport = $packageReport['prefixReport'];

            $t->same('OPS/', $packageReport['xmlBase']);
            $t->same('schema: https://schema.org/ review: https://example.invalid/review# schema: https://schema.example/ broken-token', $packageReport['prefix']);
            $t->same($prefixReport, $epub['packagePrefixReport']);
            $t->same(3, $prefixReport['declarationCount']);
            $t->same(2, $prefixReport['bindingCount']);
            $t->same(false, $prefixReport['valid']);
            $t->same(2, $prefixReport['diagnosticCount']);
            $t->same('schema', $prefixReport['bindings'][0]['prefix']);
            $t->same('https://schema.org/', $prefixReport['bindings'][0]['iri']);
            $t->same('review', $prefixReport['bindings'][1]['prefix']);
            $t->same('https://example.invalid/review#', $prefixReport['bindings'][1]['iri']);
            $t->same('schema', $prefixReport['bindings'][2]['prefix']);
            $t->same('https://schema.example/', $prefixReport['bindings'][2]['iri']);
            $t->same([
                'schema' => 'https://schema.example/',
                'review' => 'https://example.invalid/review#',
            ], $prefixReport['bindingsByPrefix']);
            $t->same($prefixReport['bindingsByPrefix'], $packageReport['prefixBindings']);
            $t->same($prefixReport['bindingsByPrefix'], $epub['packagePrefixBindings']);
            $t->same(['duplicate-package-prefix-declaration', 'invalid-package-prefix-declaration'], array_column($prefixReport['diagnostics'], 'type'));
            $t->same('schema', $prefixReport['diagnostics'][0]['prefix']);
            $t->same('https://schema.org/', $prefixReport['diagnostics'][0]['previousIri']);
            $t->same('https://schema.example/', $prefixReport['diagnostics'][0]['iri']);
            $t->true(str_starts_with($prefixReport['diagnostics'][1]['value'], 'broken-token'), 'Invalid prefix declaration tail is preserved');
            $t->same(2, $packageReport['prefixDiagnosticCount']);
            $t->same(false, $packageReport['prefixValid']);
            $t->same($prefixReport['diagnostics'], $packageReport['prefixDiagnostics']);
            $t->same($prefixReport['diagnostics'], $epub['packagePrefixDiagnostics']);
            $t->same(['duplicate-package-prefix-declaration', 'invalid-package-prefix-declaration'], array_column($packageReport['packageDiagnostics'], 'type'));
            $t->same(2, $packageReport['packageDiagnosticCount']);
            $t->same(3, $packageReport['summary']['prefixDeclarationCount']);
            $t->same(2, $packageReport['summary']['prefixBindingCount']);
            $t->same(2, $packageReport['summary']['prefixDiagnosticCount']);
            $t->same(2, $packageReport['summary']['packageDiagnosticCount']);
            $t->same(false, $packageReport['summary']['valid']);
        } finally {
            $removeDirectory($root);
        }
    },
    'maps epub guide references for compact package review' => static function (TestRunner $t) use ($fixture): void {
        $document = (new EpubPackageReader())->readDirectory($fixture());
        $epub = $document->attr('epub');
        $guide = $epub['guide'];

        $t->same(true, $guide['present']);
        $t->same(4, $guide['itemCount']);
        $t->same(3, $guide['typedItemCount']);
        $t->same(1, $guide['missingTypeCount']);
        $t->same(['cover', 'text', 'glossary', 'appendix'], $guide['types']);
        $t->same(['cover' => 1, 'text' => 1, 'glossary' => 1, 'appendix' => 1], $guide['typeCounts']);
        $t->same(4, count($guide['items']));
        $t->same('cover', $guide['items'][0]['type']);
        $t->same(['cover'], $guide['items'][0]['types']);
        $t->same('Cover image', $guide['items'][0]['title']);
        $t->same('images/cover.png', $guide['items'][0]['href']);
        $t->same('EPUB/images/cover.png', $guide['items'][0]['path']);
        $t->same('', $guide['items'][0]['fragment']);
        $t->same(true, $guide['items'][0]['exists']);
        $t->same('cover', $guide['items'][0]['manifestId']);
        $t->same('image/png', $guide['items'][0]['mediaType']);
        $t->same([], $guide['items'][0]['diagnostics']);
        $t->same('EPUB/chapter1.xhtml', $guide['items'][1]['path']);
        $t->same('opening-title', $guide['items'][1]['fragment']);
        $t->same('Start reading', $guide['itemsByType']['text'][0]['title']);
        $t->same(['glossary', 'appendix'], $guide['items'][2]['types']);
        $t->same('EPUB/glossary.xhtml', $guide['items'][2]['path']);
        $t->same(false, $guide['items'][2]['exists']);
        $t->same('missing-guide-reference', $guide['items'][2]['diagnostics'][0]['type']);
        $t->same('Glossary', $guide['itemsByType']['appendix'][0]['title']);
        $t->same('', $guide['items'][3]['type']);
        $t->same('', $guide['items'][3]['typeRaw']);
        $t->same([], $guide['items'][3]['types']);
        $t->same('EPUB/chapter2.xhtml', $guide['items'][3]['path']);
        $t->same('details', $guide['items'][3]['fragment']);
        $t->same(true, $guide['items'][3]['exists']);
        $t->same('chapter2', $guide['items'][3]['manifestId']);
        $t->same('missing-guide-reference-type', $guide['items'][3]['diagnostics'][0]['type']);
        $t->same(2, $guide['diagnosticCount']);
        $t->same(2, $guide['diagnostics'][0]['index']);
        $t->same(3, $guide['diagnostics'][1]['index']);
    },
    'reports direct reader OPF guide target policy for package review' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-reader-guide-policy-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-guide-target-policy</dc:identifier>
    <dc:title>Guide Target Policy</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
  <guide>
    <reference type="text" title="Start" href="chapter.xhtml?source=guide#start" xml:lang="en" dir="ltr" data-review="primary"/>
    <reference type="glossary" title="Missing glossary" href="missing.xhtml"/>
    <reference type="cover" title="Remote cover" href="https://example.invalid/cover.xhtml"/>
    <reference type="appendix" title="Unmanifested appendix" href="appendix.xhtml#app"/>
    <reference type="toc" title="Untargeted guide entry"/>
  </guide>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml#start">Guide target policy</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p id="start">Guide policy chapter.</p></body></html>');
            $writePackageFile($root, 'EPUB/appendix.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p id="app">Appendix outside manifest.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $guide = $epub['guide'];

            $t->same('Guide Target Policy', $document->attr('meta')['title']);
            $t->same($guide, $epub['guideReferenceReport']);
            $t->same($guide['targets'], $epub['guideReferenceTargets']);
            $t->same($guide['diagnostics'], $epub['guideReferenceDiagnostics']);
            $t->same(5, $guide['itemCount']);
            $t->same(5, $guide['typedItemCount']);
            $t->same(0, $guide['missingTypeCount']);
            $t->same(4, $guide['targetCount']);
            $t->same(2, $guide['localTargetCount']);
            $t->same(1, $guide['externalTargetCount']);
            $t->same(1, $guide['missingTargetCount']);
            $t->same(1, $guide['unmanifestedTargetCount']);
            $t->same(1, $guide['missingHrefCount']);
            $t->same(1, $guide['manifestLinkedTargetCount']);
            $t->same([
                'EPUB/chapter.xhtml?source=guide#start',
                'EPUB/missing.xhtml',
                'https://example.invalid/cover.xhtml',
                'EPUB/appendix.xhtml#app',
            ], $guide['targets']);
            $t->same(['EPUB/chapter.xhtml?source=guide#start', 'EPUB/appendix.xhtml#app'], $guide['localTargets']);
            $t->same(['https://example.invalid/cover.xhtml'], $guide['externalTargets']);
            $t->same(['EPUB/missing.xhtml'], $guide['missingTargets']);
            $t->same(['EPUB/appendix.xhtml#app'], $guide['unmanifestedTargets']);
            $t->same([
                'external-guide-reference-target' => 1,
                'guide-reference-target-not-in-manifest' => 1,
                'missing-guide-reference' => 1,
                'missing-guide-reference-href' => 1,
            ], $guide['diagnosticTypes']);
            $t->same([
                'missing-guide-reference',
                'external-guide-reference-target',
                'guide-reference-target-not-in-manifest',
                'missing-guide-reference-href',
            ], array_column($guide['diagnostics'], 'type'));
            $t->same('EPUB/chapter.xhtml?source=guide#start', $guide['items'][0]['target']);
            $t->same(true, $guide['items'][0]['hrefHasQuery']);
            $t->same('source=guide', $guide['items'][0]['hrefQuery']);
            $t->same('start', $guide['items'][0]['hrefFragment']);
            $t->same('chapter', $guide['items'][0]['manifestId']);
            $t->same('application/xhtml+xml', $guide['items'][0]['mediaTypeBase']);
            $t->same('en', $guide['items'][0]['language']);
            $t->same('ltr', $guide['items'][0]['direction']);
            $t->same(['data-review' => 'primary'], $guide['items'][0]['customAttributes']);
            $t->same('chapter', $guide['manifestLinkedTargets'][0]['manifestId']);
            $t->same('missing-guide-reference', $guide['items'][1]['diagnostics'][0]['type']);
            $t->same(true, $guide['items'][2]['external']);
            $t->same('external-guide-reference-target', $guide['items'][2]['diagnostics'][0]['type']);
            $t->same(true, $guide['items'][3]['exists']);
            $t->same('', $guide['items'][3]['manifestId']);
            $t->same('guide-reference-target-not-in-manifest', $guide['items'][3]['diagnostics'][0]['type']);
            $t->same('', $guide['items'][4]['target']);
            $t->same('missing-guide-reference-href', $guide['items'][4]['diagnostics'][0]['type']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports direct reader OPF collection hierarchy and links for package review' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-reader-collections-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-collection-review</dc:identifier>
    <dc:title>Reader Collection Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="series-record" href="meta/series.json" media-type="application/ld+json"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
  <collection id="series" role="series review" xml:lang="en" dir="ltr">
    <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
      <dc:title>Reader collection packet</dc:title>
    </metadata>
    <link id="series-record-link" rel="record" href="meta/series.json?profile=review#series" media-type="application/ld+json" properties="source"/>
    <link id="missing-review" rel="review" href="meta/missing.json" media-type="application/json"/>
    <link id="remote-review" rel="alternate" href="https://example.invalid/series.json" media-type="application/ld+json"/>
    <collection id="samples" role="preview">
      <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
        <dc:title>Sample picks</dc:title>
      </metadata>
      <link id="chapter-link" rel="first" href="chapter.xhtml#start" media-type="application/xhtml+xml"/>
    </collection>
  </collection>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml">Chapter</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="start">Chapter</h1></body></html>');
            $writePackageFile($root, 'EPUB/meta/series.json', '{"name":"Reader collection packet"}');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $collections = $epub['collections'];
            $report = $epub['collectionReport'];

            $t->same(1, count($collections));
            $series = $collections[0];
            $t->same('series', $series['id']);
            $t->same('series review', $series['role']);
            $t->same(['series', 'review'], $series['roleTokens']);
            $t->same('series', $series['primaryRole']);
            $t->same('en', $series['language']);
            $t->same('ltr', $series['direction']);
            $t->same('Reader collection packet', $series['metadata']['title']);
            $t->same(3, $series['linkCount']);
            $t->same(2, $series['localLinkCount']);
            $t->same(1, $series['externalLinkCount']);
            $t->same(1, $series['missingLinkCount']);
            $t->same(['alternate' => 1, 'record' => 1, 'review' => 1], $series['linkRelCounts']);

            $record = $series['links'][0];
            $t->same('series-record-link', $record['id']);
            $t->same(['record'], $record['rel']);
            $t->same('EPUB/meta/series.json?profile=review#series', $record['target']);
            $t->same('EPUB/meta/series.json', $record['path']);
            $t->same('series-record', $record['manifestId']);
            $t->same('application/ld+json', $record['manifestMediaType']);
            $t->same(true, $record['exists']);
            $t->same(true, $record['hrefHasQuery']);
            $t->same('profile=review', $record['hrefQuery']);
            $t->same(true, $record['hrefHasFragment']);
            $t->same('series', $record['hrefFragment']);
            $t->same(['source'], $record['properties']);
            $t->same([], $record['diagnostics']);

            $t->same('missing-collection-link-target', $series['links'][1]['diagnostics'][0]['type']);
            $t->same('EPUB/meta/missing.json', $series['links'][1]['path']);
            $t->same('external-collection-link-target', $series['links'][2]['diagnostics'][0]['type']);
            $t->same('https://example.invalid/series.json', $series['links'][2]['target']);

            $samples = $series['children'][0];
            $t->same('samples', $samples['id']);
            $t->same(['preview'], $samples['roleTokens']);
            $t->same('Sample picks', $samples['metadata']['title']);
            $t->same('EPUB/chapter.xhtml#start', $samples['links'][0]['target']);
            $t->same('chapter', $samples['links'][0]['manifestId']);

            $t->same($report, $epub['collectionHierarchy']);
            $t->same($report['diagnostics'], $epub['collectionDiagnostics']);
            $t->same($report['linkTargets'], $epub['collectionLinkTargets']);
            $t->same(true, $report['present']);
            $t->same(2, $report['collectionCount']);
            $t->same(1, $report['rootCollectionCount']);
            $t->same(1, $report['leafCollectionCount']);
            $t->same(2, $report['maxDepth']);
            $t->same(['0', '0/0'], $report['pathKeys']);
            $t->same(['preview' => 1, 'review' => 1, 'series' => 1], $report['roleCounts']);
            $t->same(['preview' => 1, 'series' => 1], $report['primaryRoleCounts']);
            $t->same(['alternate' => 1, 'first' => 1, 'record' => 1, 'review' => 1], $report['linkRelCounts']);
            $t->same([1 => 1, 2 => 1], $report['depthCounts']);
            $t->same(3, $report['localLinkCount']);
            $t->same(1, $report['externalLinkCount']);
            $t->same(1, $report['missingLinkCount']);
            $t->same(['Reader collection packet', 'Sample picks'], $report['titles']);
            $t->same([
                'EPUB/meta/series.json?profile=review#series',
                'EPUB/meta/missing.json',
                'https://example.invalid/series.json',
                'EPUB/chapter.xhtml#start',
            ], $report['linkTargets']);
            $t->same(2, $report['diagnosticCount']);
            $t->same(['missing-collection-link-target', 'external-collection-link-target'], array_column($report['diagnostics'], 'type'));
            $t->same([0], $report['diagnostics'][0]['collectionPath']);
            $t->same('series', $report['diagnostics'][0]['collectionId']);
            $t->same(2, $report['itemsByPath']['0']['diagnosticCount']);
            $t->same(0, $report['itemsByPath']['0/0']['diagnosticCount']);
        } finally {
            $removeDirectory($root);
        }
    },
    'maps epub nav document and ncx fallback outlines' => static function (TestRunner $t) use ($fixture): void {
        $document = (new EpubPackageReader())->readDirectory($fixture());
        $epub = $document->attr('epub');
        $toc = array_values(array_filter(
            $epub['toc'],
            static fn (array $entry): bool => $entry['type'] === 'toc'
        ));
        $landmarks = array_values(array_filter(
            $epub['toc'],
            static fn (array $entry): bool => $entry['type'] === 'landmarks'
        ));
        $ncx = $epub['ncx'];

        $t->same(2, count($toc));
        $t->same('Opening Packet', $toc[0]['label']);
        $t->same('chapter1.xhtml', $toc[0]['href']);
        $t->same('EPUB/chapter1.xhtml', $toc[0]['path']);
        $t->same('', $toc[0]['fragment']);
        $t->same(1, count($toc[0]['children']));
        $t->same('Opening Note', $toc[0]['children'][0]['label']);
        $t->same('EPUB/chapter1.xhtml', $toc[0]['children'][0]['path']);
        $t->same('opening-note', $toc[0]['children'][0]['fragment']);
        $t->same('Details', $toc[1]['label']);
        $t->same('EPUB/chapter2.xhtml', $toc[1]['path']);
        $t->same('details', $toc[1]['fragment']);
        $t->same(2, count($landmarks));
        $t->same('Start', $landmarks[0]['label']);
        $t->same('EPUB/chapter1.xhtml', $landmarks[0]['path']);
        $t->same('Table of contents', $landmarks[1]['label']);
        $t->same('EPUB/nav.xhtml', $landmarks[1]['path']);
        $t->same(2, count($ncx));
        $t->same('Opening Packet', $ncx[0]['label']);
        $t->same(1, $ncx[0]['playOrder']);
        $t->same('EPUB/chapter1.xhtml', $ncx[0]['path']);
        $t->same(1, count($ncx[0]['children']));
        $t->same('Opening Note', $ncx[0]['children'][0]['label']);
        $t->same(2, $ncx[0]['children'][0]['playOrder']);
        $t->same('opening-note', $ncx[0]['children'][0]['fragment']);
        $t->same('Details', $ncx[1]['label']);
        $t->same(3, $ncx[1]['playOrder']);
        $t->same('details', $ncx[1]['fragment']);
    },
    'preserves epub nav and ncx label provenance for package review' => static function (TestRunner $t) use ($fixture): void {
        $document = (new EpubPackageReader())->readDirectory($fixture());
        $epub = $document->attr('epub');
        $toc = array_values(array_filter(
            $epub['toc'],
            static fn (array $entry): bool => $entry['type'] === 'toc'
        ));
        $ncx = $epub['ncx'];

        $navLabel = $toc[0]['labelProvenance'];
        $t->same('xhtml-nav', $navLabel['source']);
        $t->same('a', $navLabel['element']);
        $t->same('Opening Packet', $navLabel['text']);
        $t->same('nav-opening-label', $navLabel['attributes']['id']);
        $t->same('source-label', $navLabel['attributes']['class']);
        $t->same('en', $navLabel['language']);
        $t->same('ltr', $navLabel['direction']);
        $t->same(['bodymatter'], $navLabel['epubTypes']);
        $t->same(1, $navLabel['imageLabelCount']);
        $t->same('images/cover.png', $navLabel['imageLabels'][0]['src']);
        $t->same('EPUB/images/cover.png', $navLabel['imageLabels'][0]['path']);
        $t->same('Cover label', $navLabel['imageLabels'][0]['alt']);
        $t->same('Cover thumbnail', $navLabel['imageLabels'][0]['title']);

        $ncxLabel = $ncx[0]['labelProvenance'];
        $t->same('ncx-navLabel', $ncxLabel['source']);
        $t->same('navLabel', $ncxLabel['element']);
        $t->same('Opening Packet', $ncxLabel['text']);
        $t->same('np-1-label', $ncxLabel['attributes']['id']);
        $t->same('source-label', $ncxLabel['attributes']['class']);
        $t->same('en', $ncxLabel['language']);
        $t->same('ltr', $ncxLabel['direction']);
        $t->same('np-1-text', $ncxLabel['textAttributes']['id']);
        $t->same('source-text', $ncxLabel['textAttributes']['class']);
    },
    'reports ncx hierarchy duplicate targets and play order diagnostics' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-reader-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-ncx-review</dc:identifier>
    <dc:title>NCX Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine toc="ncx">
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/toc.ncx', <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
  <head>
    <meta name="dtb:uid" content="urn:reader-ncx-review"/>
  </head>
  <docTitle><text>NCX Review</text></docTitle>
  <navMap>
    <navPoint id="np-start" playOrder="1">
      <navLabel><text>Start</text></navLabel>
      <content src="chapter.xhtml#start"/>
      <navPoint id="np-overview" playOrder="2">
        <navLabel><text>Overview</text></navLabel>
        <content src="chapter.xhtml#overview"/>
      </navPoint>
      <navPoint id="np-overview-duplicate" playOrder="2">
        <navLabel><text>Duplicate Overview</text></navLabel>
        <content src="chapter.xhtml#overview"/>
      </navPoint>
    </navPoint>
    <navPoint id="np-closing" playOrder="1">
      <navLabel><text>Closing</text></navLabel>
      <content src="chapter.xhtml#closing"/>
    </navPoint>
  </navMap>
</ncx>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable NCX packet.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $ncx = $epub['ncx'];
            $report = $epub['ncxReport'];

            $t->same(2, count($ncx));
            $t->same('Start', $ncx[0]['label']);
            $t->same(2, count($ncx[0]['children']));
            $t->same('Duplicate Overview', $ncx[0]['children'][1]['label']);
            $t->same('Duplicate Overview', $ncx[0]['children'][1]['labelProvenance']['text']);
            $t->same('chapter.xhtml#overview', $ncx[0]['children'][1]['href']);
            $t->same('EPUB/chapter.xhtml', $ncx[0]['children'][1]['path']);
            $t->same('overview', $ncx[0]['children'][1]['fragment']);

            $t->same(4, $report['pointCount']);
            $t->same(2, $report['topLevelPointCount']);
            $t->same(2, $report['maxDepth']);
            $t->same(0, $report['missingPlayOrderCount']);
            $t->same(2, $report['nonIncreasingPlayOrderCount']);
            $t->same(1, $report['duplicateTargetCount']);
            $t->same(3, $report['diagnosticCount']);
            $t->same(['non-increasing-ncx-play-order', 'duplicate-ncx-target', 'non-increasing-ncx-play-order'], array_column($report['diagnostics'], 'type'));
            $t->same(2, $report['diagnostics'][0]['index']);
            $t->same('Duplicate Overview', $report['diagnostics'][0]['label']);
            $t->same(2, $report['diagnostics'][0]['playOrder']);
            $t->same(2, $report['diagnostics'][0]['previousPlayOrder']);
            $t->same(2, $report['diagnostics'][1]['index']);
            $t->same(1, $report['diagnostics'][1]['firstIndex']);
            $t->same('EPUB/chapter.xhtml#overview', $report['diagnostics'][1]['target']);
            $t->same('Overview', $report['diagnostics'][1]['firstLabel']);
            $t->same(3, $report['diagnostics'][2]['index']);
            $t->same('Closing', $report['diagnostics'][2]['label']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports epub nav landmarks relations and preserves label and ncx summaries' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-nav-landmarks-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-nav-landmarks-review</dc:identifier>
    <dc:title>Navigation Landmark Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="appendix" href="appendix.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine toc="ncx">
    <itemref idref="chapter"/>
  </spine>
  <guide>
    <reference type="text" title="Start reading" href="chapter.xhtml"/>
    <reference type="appendix" title="Appendix review" href="appendix.xhtml#appendix"/>
  </guide>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="toc" epub:type="toc">
      <h1>Contents</h1>
      <ol>
        <li><a href="chapter.xhtml">Chapter</a></li>
      </ol>
    </nav>
    <nav id="landmark-source" epub:type="landmarks">
      <h2>Landmark routes</h2>
      <ol>
        <li id="body-li"><a id="body-link" epub:type="bodymatter" href="chapter.xhtml">Start</a></li>
        <li id="appendix-li"><a id="appendix-link" href="appendix.xhtml#appendix">Appendix without type</a></li>
        <li id="missing-li" epub:type="glossary"><a id="missing-link" href="missing.xhtml">Missing glossary</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/toc.ncx', <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
  <navMap>
    <navPoint id="np-1" playOrder="1">
      <navLabel id="np-1-label"><text id="np-1-text">Chapter</text></navLabel>
      <content src="chapter.xhtml"/>
      <navPoint id="np-1-1" playOrder="2">
        <navLabel><text>Chapter section</text></navLabel>
        <content src="chapter.xhtml#section"/>
      </navPoint>
    </navPoint>
    <navPoint id="np-2" playOrder="3">
      <navLabel><text>Appendix</text></navLabel>
      <content src="appendix.xhtml#appendix"/>
    </navPoint>
  </navMap>
</ncx>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="section">Chapter</h1></body></html>');
            $writePackageFile($root, 'EPUB/appendix.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="appendix">Appendix</h1></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $landmarks = array_values(array_filter(
                $epub['toc'],
                static fn (array $entry): bool => $entry['type'] === 'landmarks'
            ));
            $navReport = $epub['navReport'];
            $landmarkReport = $navReport['landmarks'];
            $ncxReport = $epub['ncxReport'];

            $t->same($navReport, $epub['tocReport']);
            $t->same(3, count($landmarks));
            $t->same('anchor', $landmarks[0]['labelProvenance']['source']);
            $t->same('body-link', $landmarks[0]['labelProvenance']['labelId']);
            $t->same(['bodymatter'], $landmarks[0]['labelTypes']);
            $t->same('label', $landmarks[0]['typeSource']);

            $t->same(true, $landmarkReport['present']);
            $t->same(3, $landmarkReport['itemCount']);
            $t->same(2, $landmarkReport['typedItemCount']);
            $t->same(1, $landmarkReport['missingTypeCount']);
            $t->same(3, $landmarkReport['targetedItemCount']);
            $t->same(1, $landmarkReport['spineMappedCount']);
            $t->same(2, $landmarkReport['manifestMappedCount']);
            $t->same(2, $landmarkReport['guideRelationCount']);
            $t->same(1, $landmarkReport['outsideSpineTargetCount']);
            $t->same(1, $landmarkReport['missingReferenceCount']);
            $t->same(3, $landmarkReport['diagnosticCount']);
            $t->same(['missing-landmark-nav-type', 'landmark-target-outside-spine', 'missing-landmark-reference'], array_column($landmarkReport['diagnostics'], 'type'));

            $body = $landmarkReport['items'][0];
            $t->same('Start', $body['label']);
            $t->same('bodymatter', $body['semanticType']);
            $t->same('chapter', $body['manifestId']);
            $t->same(0, $body['spineIndex']);
            $t->same('chapter', $body['spineIdref']);
            $t->same(['text'], $body['guideTypes']);
            $t->same([], $body['diagnostics']);

            $appendix = $landmarkReport['items'][1];
            $t->same([], $appendix['semanticTypes']);
            $t->same('appendix', $appendix['manifestId']);
            $t->same(null, $appendix['spineIndex']);
            $t->same(['appendix'], $appendix['guideTypes']);
            $t->same(['missing-landmark-nav-type', 'landmark-target-outside-spine'], array_column($appendix['diagnostics'], 'type'));

            $missing = $landmarkReport['items'][2];
            $t->same(['glossary'], $missing['itemTypes']);
            $t->same('item', $missing['typeSource']);
            $t->same(false, $missing['exists']);
            $t->same('missing-landmark-reference', $missing['diagnostics'][0]['type']);

            $t->same(true, $ncxReport['present']);
            $t->same(3, $ncxReport['itemCount']);
            $t->same(2, $ncxReport['topLevelItemCount']);
            $t->same(3, $ncxReport['playOrderCount']);
            $t->same(0, $ncxReport['diagnosticCount']);
            $t->same(['topLevelItemCount' => 2, 'branchItemCount' => 1, 'leafItemCount' => 2, 'maxDepth' => 1], $ncxReport['hierarchy']);
            $t->same('ncx-navLabel', $ncxReport['items'][0]['labelProvenance']['source']);
            $t->same('np-1-label', $ncxReport['items'][0]['labelProvenance']['labelId']);
            $t->same('np-1-text', $ncxReport['items'][0]['labelProvenance']['textId']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports epub nav document section diagnostics for direct package review' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-nav-document-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-nav-document-review</dc:identifier>
    <dc:title>Navigation Document Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="hidden-toc" epub:type="toc" hidden="hidden">
      <h1>Hidden contents</h1>
      <ol>
        <li><a href="chapter.xhtml#hidden">Hidden start</a></li>
      </ol>
    </nav>
    <nav id="visible-toc" epub:type="toc">
      <h1>Contents</h1>
      <ol>
        <li><a href="chapter.xhtml#start">Start</a></li>
      </ol>
    </nav>
    <nav id="untitled-pages" epub:type="page-list">
      <ol></ol>
    </nav>
    <nav id="glossary-review" epub:type="loi">
      <h2>Figures</h2>
    </nav>
    <nav id="untyped-review">
      <ol>
        <li><a href="chapter.xhtml#note">Untyped note</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body>
    <h1 id="start">Start</h1>
    <p id="hidden">Hidden start.</p>
    <p id="note">Untyped note.</p>
  </body>
</html>
XML);

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $navReport = $epub['navReport'];
            $documentReport = $navReport['document'];
            $sections = $documentReport['sections'];

            $t->same($navReport, $epub['tocReport']);
            $t->same($documentReport, $navReport['document']);
            $t->same($documentReport['diagnostics'], $navReport['documentDiagnostics']);
            $t->same(8, $navReport['documentDiagnosticCount']);
            $t->same(true, $documentReport['present']);
            $t->same('/EPUB/nav.xhtml', $documentReport['part']);
            $t->same('EPUB/nav.xhtml', $documentReport['path']);
            $t->same(5, $documentReport['sectionCount']);
            $t->same(3, $documentReport['primarySectionCount']);
            $t->same(2, $documentReport['tocSectionCount']);
            $t->same(0, $documentReport['landmarksSectionCount']);
            $t->same(1, $documentReport['pageListSectionCount']);
            $t->same(true, $documentReport['requiredTocPresent']);
            $t->same(1, $documentReport['duplicatePrimaryTypeCount']);
            $t->same(1, $documentReport['hiddenPrimarySectionCount']);
            $t->same(1, $documentReport['missingHeadingSectionCount']);
            $t->same(1, $documentReport['missingOrderedListSectionCount']);
            $t->same(2, $documentReport['emptySectionCount']);
            $t->same(1, $documentReport['untypedSectionCount']);
            $t->same(1, $documentReport['unrecognizedSectionCount']);
            $t->same([
                'hidden-primary-nav-section',
                'missing-primary-nav-section-heading',
                'empty-nav-section',
                'unrecognized-nav-section-type',
                'missing-nav-section-ordered-list',
                'empty-nav-section',
                'missing-nav-section-type',
                'duplicate-primary-nav-section',
            ], array_column($documentReport['diagnostics'], 'type'));

            $t->same('hidden-toc', $sections[0]['sectionId']);
            $t->same(['toc'], $sections[0]['sectionTypes']);
            $t->same(true, $sections[0]['hidden']);
            $t->same(1, $sections[0]['itemCount']);
            $t->same('hidden-primary-nav-section', $sections[0]['diagnostics'][0]['type']);
            $t->same('visible-toc', $sections[1]['sectionId']);
            $t->same(['toc'], $sections[1]['sectionTypes']);
            $t->same('untitled-pages', $sections[2]['sectionId']);
            $t->same(['page-list'], $sections[2]['sectionTypes']);
            $t->same(0, $sections[2]['itemCount']);
            $t->same(['missing-primary-nav-section-heading', 'empty-nav-section'], array_column($sections[2]['diagnostics'], 'type'));
            $t->same('glossary-review', $sections[3]['sectionId']);
            $t->same(['loi'], $sections[3]['epubTypes']);
            $t->same(null, $sections[3]['sectionType']);
            $t->same(['unrecognized-nav-section-type', 'missing-nav-section-ordered-list', 'empty-nav-section'], array_column($sections[3]['diagnostics'], 'type'));
            $t->same('untyped-review', $sections[4]['sectionId']);
            $t->same([], $sections[4]['epubTypes']);
            $t->same(['missing-nav-section-type'], array_column($sections[4]['diagnostics'], 'type'));

            $duplicate = $documentReport['diagnostics'][7];
            $t->same('toc', $duplicate['sectionType']);
            $t->same([0, 1], $duplicate['sectionIndexes']);
            $t->same(['hidden-toc', 'visible-toc'], $duplicate['sectionIds']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports epub toc accessibility labels roles and duplicate nav relations' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-toc-a11y-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-toc-a11y-review</dc:identifier>
    <dc:title>Navigation TOC Accessibility Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="appendix" href="appendix.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine toc="ncx">
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="toc" epub:type="toc">
      <h1>Contents</h1>
      <ol>
        <li id="chapter-li"><a id="chapter-link" href="chapter.xhtml">Chapter</a></li>
        <li id="duplicate-li"><a id="duplicate-link" href="chapter.xhtml" aria-label="Chapter duplicate"></a></li>
        <li id="role-li" epub:type="landmarks"><a id="role-link" href="appendix.xhtml">Appendix role leak</a></li>
        <li id="empty-li"><span id="empty-label"></span></li>
      </ol>
    </nav>
    <nav id="landmarks" epub:type="landmarks">
      <ol>
        <li><a epub:type="bodymatter" href="chapter.xhtml">Start</a></li>
      </ol>
    </nav>
    <nav id="pages" epub:type="page-list">
      <ol>
        <li><a href="chapter.xhtml#p1">1</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/toc.ncx', <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
  <navMap>
    <navPoint id="np-1" playOrder="1">
      <navLabel id="np-1-label"><text id="np-1-text">Chapter</text></navLabel>
      <content src="chapter.xhtml"/>
    </navPoint>
  </navMap>
</ncx>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="p1">Chapter</h1></body></html>');
            $writePackageFile($root, 'EPUB/appendix.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Appendix</h1></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $navReport = $epub['navReport'];
            $tocReport = $navReport['toc'];
            $tocItems = $tocReport['items'];

            $t->same($navReport, $epub['tocReport']);
            $t->same(true, $tocReport['present']);
            $t->same(4, $tocReport['itemCount']);
            $t->same(3, $tocReport['targetedItemCount']);
            $t->same(3, $tocReport['accessibilityLabelCount']);
            $t->same(1, $tocReport['missingLabelCount']);
            $t->same(1, $tocReport['missingTargetCount']);
            $t->same(1, $tocReport['roleConflictCount']);
            $t->same(1, $tocReport['duplicateTargetCount']);
            $t->same(2, $tocReport['landmarkRelationCount']);
            $t->same(0, $tocReport['pageListRelationCount']);
            $t->same(4, $tocReport['diagnosticCount']);
            $t->same(['toc-nav-role-conflict', 'missing-toc-target', 'missing-toc-accessibility-label', 'duplicate-toc-target'], array_column($tocReport['diagnostics'], 'type'));

            $t->same('Chapter', $tocItems[0]['accessibilityLabel']);
            $t->same('text', $tocItems[0]['accessibilityLabelSource']);
            $t->same('xhtml-nav', $tocItems[0]['labelProvenance']['source']);
            $t->same(['landmarks'], $tocItems[0]['relationSections']);
            $t->same(2, $tocItems[0]['duplicateTargetCount']);
            $t->same('duplicate-toc-target', $tocItems[0]['diagnostics'][0]['type']);

            $t->same('', $tocItems[1]['label']);
            $t->same('Chapter duplicate', $tocItems[1]['accessibilityLabel']);
            $t->same('aria-label', $tocItems[1]['accessibilityLabelSource']);
            $t->same(2, $tocItems[1]['duplicateTargetCount']);

            $t->same(['landmarks'], $tocItems[2]['itemTypes']);
            $t->same('item', $tocItems[2]['typeSource']);
            $t->same('toc-nav-role-conflict', $tocItems[2]['diagnostics'][0]['type']);

            $t->same('', $tocItems[3]['href']);
            $t->same('', $tocItems[3]['accessibilityLabel']);
            $t->same(null, $tocItems[3]['accessibilityLabelSource']);
            $t->same(['missing-toc-target', 'missing-toc-accessibility-label'], array_column($tocItems[3]['diagnostics'], 'type'));

            $ncxReport = $epub['ncxReport'];
            $t->same(true, $ncxReport['present']);
            $t->same(1, $ncxReport['itemCount']);
            $t->same(0, $ncxReport['diagnosticCount']);
            $t->same('ncx-navLabel', $ncxReport['items'][0]['labelProvenance']['source']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports epub nav fragment target diagnostics across toc landmarks and page list' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-nav-fragments-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-nav-fragment-review</dc:identifier>
    <dc:title>Navigation Fragment Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="toc" epub:type="toc">
      <h1>Contents</h1>
      <ol>
        <li><a id="known-link" href="chapter.xhtml#known">Known</a></li>
        <li><a href="chapter.xhtml#missing">Missing</a></li>
        <li><a href="chapter.xhtml#duplicate">Duplicate</a></li>
        <li><a href="chapter.xhtml">Whole chapter</a></li>
      </ol>
    </nav>
    <nav id="landmarks" epub:type="landmarks">
      <ol>
        <li><a epub:type="bodymatter" href="chapter.xhtml#known">Start</a></li>
      </ol>
    </nav>
    <nav id="pages" epub:type="page-list">
      <ol>
        <li><a href="chapter.xhtml#page-1">1</a></li>
        <li><a href="chapter.xhtml#missing-page">2</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body>
    <h1 id="known">Known section</h1>
    <section id="duplicate"><p>First duplicate anchor.</p></section>
    <aside id="duplicate"><p>Second duplicate anchor.</p></aside>
    <p id="page-1">Printed page one.</p>
  </body>
</html>
XML);

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $navReport = $epub['navReport'];
            $fragmentReport = $navReport['fragmentTargets'];
            $tocReport = $navReport['toc'];
            $landmarkReport = $navReport['landmarks'];
            $items = $fragmentReport['items'];

            $t->same($navReport, $epub['tocReport']);
            $t->same(true, $fragmentReport['present']);
            $t->same(7, $fragmentReport['itemCount']);
            $t->same(7, $fragmentReport['targetedItemCount']);
            $t->same(6, $fragmentReport['fragmentItemCount']);
            $t->same(1, $fragmentReport['fragmentlessTargetCount']);
            $t->same(3, $fragmentReport['resolvedFragmentCount']);
            $t->same(2, $fragmentReport['missingFragmentCount']);
            $t->same(1, $fragmentReport['duplicateFragmentCount']);
            $t->same(0, $fragmentReport['missingDocumentCount']);
            $t->same(['toc' => 4, 'landmarks' => 1, 'page-list' => 2], $fragmentReport['sectionTypeCounts']);
            $t->same(3, $fragmentReport['diagnosticCount']);
            $t->same(['missing-nav-fragment-target', 'duplicate-nav-fragment-target', 'missing-nav-fragment-target'], array_column($fragmentReport['diagnostics'], 'type'));

            $t->same('resolved-fragment', $items[0]['fragmentState']);
            $t->same('known', $items[0]['fragment']);
            $t->same(1, $items[0]['fragmentMatchCount']);
            $t->same(4, $items[0]['targetIdCount']);
            $t->same(3, $items[0]['targetUniqueIdCount']);
            $t->same('xhtml-nav', $items[0]['labelProvenance']['source']);
            $t->same('known-link', $items[0]['labelProvenance']['labelId']);

            $t->same('missing-fragment', $items[1]['fragmentState']);
            $t->same('missing-nav-fragment-target', $items[1]['diagnostics'][0]['type']);
            $t->same(0, $items[1]['fragmentMatchCount']);

            $t->same('duplicate-fragment', $items[2]['fragmentState']);
            $t->same('duplicate-nav-fragment-target', $items[2]['diagnostics'][0]['type']);
            $t->same(2, $items[2]['fragmentMatchCount']);

            $t->same('document', $items[3]['fragmentState']);
            $t->same('', $items[3]['fragment']);
            $t->same([], $items[3]['diagnostics']);

            $t->same('landmarks', $items[4]['sectionType']);
            $t->same('resolved-fragment', $items[4]['fragmentState']);
            $t->same('page-list', $items[5]['sectionType']);
            $t->same('resolved-fragment', $items[5]['fragmentState']);
            $t->same('page-list', $items[6]['sectionType']);
            $t->same('missing-fragment', $items[6]['fragmentState']);

            $t->same(4, $tocReport['itemCount']);
            $t->same(0, $tocReport['diagnosticCount']);
            $t->same('xhtml-nav', $tocReport['items'][0]['labelProvenance']['source']);
            $t->same(1, $tocReport['landmarkRelationCount']);
            $t->same(0, $tocReport['pageListRelationCount']);
            $t->same(1, $landmarkReport['itemCount']);
            $t->same(0, $landmarkReport['diagnosticCount']);
        } finally {
            $removeDirectory($root);
        }
    },
    'maps epub page-list navigation targets for print provenance' => static function (TestRunner $t) use ($fixture): void {
        $document = (new EpubPackageReader())->readDirectory($fixture());
        $epub = $document->attr('epub');
        $pageList = array_values(array_filter(
            $epub['toc'],
            static fn (array $entry): bool => $entry['type'] === 'page-list'
        ));

        $t->same(2, count($pageList));
        $t->same('1', $pageList[0]['label']);
        $t->same('chapter1.xhtml#opening-title', $pageList[0]['href']);
        $t->same('EPUB/chapter1.xhtml', $pageList[0]['path']);
        $t->same('opening-title', $pageList[0]['fragment']);
        $t->same('page-list', $pageList[0]['type']);
        $t->same('2', $pageList[1]['label']);
        $t->same('EPUB/chapter2.xhtml', $pageList[1]['path']);
        $t->same('details', $pageList[1]['fragment']);

        $pageListReport = $epub['tocReport']['pageList'];
        $t->same($epub['navReport'], $epub['tocReport']);
        $t->same(true, $pageListReport['present']);
        $t->same(2, $pageListReport['itemCount']);
        $t->same(2, $pageListReport['pageBreakItemCount']);
        $t->same(2, $pageListReport['targetedItemCount']);
        $t->same(2, $pageListReport['manifestTargetCount']);
        $t->same(2, $pageListReport['spineReadingOrderTargetCount']);
        $t->same(0, $pageListReport['missingManifestTargetCount']);
        $t->same(0, $pageListReport['outsideSpineTargetCount']);
        $t->same(0, $pageListReport['externalTargetCount']);
        $t->same(0, $pageListReport['unresolvedTargetCount']);
        $t->same(1, $pageListReport['collisionTargetCount']);
        $t->same(1, $pageListReport['diagnosticCount']);
        $t->same(['page-list-target-nav-collision'], array_column($pageListReport['diagnostics'], 'type'));
        $t->same('chapter1', $pageListReport['items'][0]['manifestId']);
        $t->same('application/xhtml+xml', $pageListReport['items'][0]['mediaType']);
        $t->same(0, $pageListReport['items'][0]['spineIndex']);
        $t->same('chapter1', $pageListReport['items'][0]['spineIdref']);
        $t->same(true, $pageListReport['items'][0]['inSpineReadingOrder']);
        $t->same('chapter2', $pageListReport['items'][1]['manifestId']);
        $t->same(1, $pageListReport['items'][1]['spineIndex']);
        $t->same(true, $pageListReport['items'][1]['spineLinear']);
    },
    'reports epub page-list CFI fragment targets for package review' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-page-list-cfi-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:page-list-cfi-review</dc:identifier>
    <dc:title>Page List CFI Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml">Page List CFI Review</a></li>
      </ol>
    </nav>
    <nav epub:type="page-list">
      <ol>
        <li><a epub:type="pagebreak" href="chapter.xhtml#epubcfi(/6/2[chapter]!/4/2/2)">1</a></li>
        <li><a epub:type="pagebreak" href="chapter.xhtml#page-2">2</a></li>
      </ol>
    </nav>
  </body>
</html>
HTML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Page List CFI Review</h1><p>Reader page-list CFI audit.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $pageListReport = $document->attr('epub')['pageListReport'];

            $t->same(true, $pageListReport['present']);
            $t->same(2, $pageListReport['itemCount']);
            $t->same(1, $pageListReport['cfiTargetCount']);
            $t->same(1, $pageListReport['epubCfiTargetCount']);
            $t->same('epub-cfi', $pageListReport['items'][0]['fragmentKind']);
            $t->same(true, $pageListReport['items'][0]['epubCfi']);
            $t->same('epubcfi(/6/2[chapter]!/4/2/2)', $pageListReport['items'][0]['cfiFragment']);
            $t->same('id', $pageListReport['items'][1]['fragmentKind']);
            $t->same(false, $pageListReport['items'][1]['epubCfi']);
            $t->same(null, $pageListReport['items'][1]['cfiFragment']);
            $t->same('EPUB/chapter.xhtml#epubcfi(/6/2[chapter]!/4/2/2)', $pageListReport['cfiTargets'][0]['target']);
            $t->same([0], $pageListReport['cfiTargets'][0]['readingSpineIndexes']);
            $t->same('epub-cfi', $pageListReport['readingOrder'][0]['fragmentKind']);
            $t->same(true, $pageListReport['readingOrder'][0]['epubCfi']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports epub page-list manifest and spine reading order diagnostics' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-page-list-spine-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-page-list-spine-review</dc:identifier>
    <dc:title>Page List Spine Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="appendix" href="appendix.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
    <itemref idref="appendix" linear="no"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml">Chapter</a></li>
      </ol>
    </nav>
    <nav epub:type="page-list">
      <ol>
        <li><a epub:type="pagebreak" href="chapter.xhtml#page-1">1</a></li>
        <li><a epub:type="pagebreak" href="stray.xhtml#page-2">2</a></li>
        <li><a epub:type="pagebreak" href="appendix.xhtml#page-a">A</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="page-1">Chapter</h1></body></html>');
            $writePackageFile($root, 'EPUB/appendix.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="page-a">Appendix</h1></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $pageList = array_values(array_filter(
                $epub['toc'],
                static fn (array $entry): bool => $entry['type'] === 'page-list'
            ));
            $pageListReport = $epub['tocReport']['pageList'];
            $items = $pageListReport['items'];

            $t->same(['1', '2', 'A'], array_column($pageList, 'label'));
            $t->same('EPUB/stray.xhtml', $pageList[1]['path']);
            $t->same('EPUB/appendix.xhtml', $pageList[2]['path']);
            $t->same($epub['navReport'], $epub['tocReport']);
            $t->same(4, $epub['tocReport']['itemCount']);
            $t->same(true, $pageListReport['present']);
            $t->same(3, $pageListReport['itemCount']);
            $t->same(3, $pageListReport['pageBreakItemCount']);
            $t->same(3, $pageListReport['targetedItemCount']);
            $t->same(2, $pageListReport['manifestTargetCount']);
            $t->same(1, $pageListReport['spineReadingOrderTargetCount']);
            $t->same(1, $pageListReport['missingManifestTargetCount']);
            $t->same(1, $pageListReport['outsideSpineTargetCount']);
            $t->same(0, $pageListReport['externalTargetCount']);
            $t->same(0, $pageListReport['unresolvedTargetCount']);
            $t->same(3, $pageListReport['diagnosticCount']);
            $t->same([
                'missing-page-list-manifest-item',
                'page-list-target-nonlinear-spine-item',
                'page-list-target-outside-spine-reading-order',
            ], array_column($pageListReport['diagnostics'], 'type'));

            $t->same('chapter', $items[0]['manifestId']);
            $t->same(0, $items[0]['spineIndex']);
            $t->same('chapter', $items[0]['spineIdref']);
            $t->same(true, $items[0]['spineLinear']);
            $t->same(true, $items[0]['inSpineReadingOrder']);
            $t->same([], $items[0]['diagnostics']);

            $t->same(null, $items[1]['manifestId']);
            $t->same(false, $items[1]['inSpineReadingOrder']);
            $t->same(['missing-page-list-manifest-item'], array_column($items[1]['diagnostics'], 'type'));
            $t->same('EPUB/stray.xhtml', $items[1]['diagnostics'][0]['path']);

            $t->same('appendix', $items[2]['manifestId']);
            $t->same(1, $items[2]['spineIndex']);
            $t->same('appendix', $items[2]['spineIdref']);
            $t->same(false, $items[2]['spineLinear']);
            $t->same(false, $items[2]['inSpineReadingOrder']);
            $t->same([
                'page-list-target-nonlinear-spine-item',
                'page-list-target-outside-spine-reading-order',
            ], array_column($items[2]['diagnostics'], 'type'));
            $t->same('nonlinear-spine-item', $items[2]['diagnostics'][1]['reason']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports epub nav external and unsafe href policy by section' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-nav-policy-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-nav-policy-review</dc:identifier>
    <dc:title>Navigation Policy Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml#safe">Safe chapter</a></li>
        <li><a href="http://example.invalid/remote.xhtml#r">HTTP resource</a></li>
        <li><a href="https://example.invalid/secure.xhtml?review=1">HTTPS resource</a></li>
      </ol>
    </nav>
    <nav epub:type="landmarks">
      <ol>
        <li><a href="mailto:desk@example.invalid">Mail contact</a></li>
        <li><a href="data:text/html,&lt;p&gt;inline&lt;/p&gt;">Data payload</a></li>
      </ol>
    </nav>
    <nav epub:type="page-list">
      <ol>
        <li><a epub:type="pagebreak" href="java&#x20;script:alert(1)">Script action</a></li>
        <li><a epub:type="pagebreak" href="../../outside.xhtml#outside">Package escape</a></li>
        <li><a epub:type="pagebreak" href="chapter.xhtml#page-2">Page 2</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="safe">Safe chapter</h1><p id="page-2">Page two.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $policy = $epub['navReport']['hrefPolicy'];
            $entriesByLabel = [];
            $collect = static function (array $entries) use (&$collect, &$entriesByLabel): void {
                foreach ($entries as $entry) {
                    $entriesByLabel[$entry['label']] = $entry;
                    $collect($entry['children']);
                }
            };
            $collect($epub['toc']);

            $t->same($epub['navReport'], $epub['navigationReport']);
            $t->same(true, $policy['present']);
            $t->same(3, $policy['sectionCount']);
            $t->same(['toc' => 1, 'landmarks' => 1, 'page-list' => 1], $policy['sectionTypeCounts']);
            $t->same(8, $policy['itemCount']);
            $t->same(8, $policy['targetedItemCount']);
            $t->same(2, $policy['localTargetCount']);
            $t->same(2, $policy['safeLocalTargetCount']);
            $t->same(6, $policy['externalTargetCount']);
            $t->same(2, $policy['unsafeTargetCount']);
            $t->same(8, $policy['diagnosticCount']);
            $t->same(['toc', 'landmarks', 'page-list'], array_column($policy['sections'], 'type'));
            $t->same([3, 2, 3], array_column($policy['sections'], 'itemCount'));
            $t->same([2, 2, 2], array_column($policy['sections'], 'externalTargetCount'));
            $t->same([0, 1, 1], array_column($policy['sections'], 'unsafeTargetCount'));

            $safe = $entriesByLabel['Safe chapter'];
            $t->same('EPUB/chapter.xhtml', $safe['path']);
            $t->same('safe', $safe['fragment']);
            $t->same('local', $safe['hrefKind']);
            $t->same(false, $safe['external']);
            $t->same(false, $safe['unsafe']);
            $t->same([], $safe['hrefDiagnostics']);
            $page = $entriesByLabel['Page 2'];
            $t->same('EPUB/chapter.xhtml', $page['path']);
            $t->same('page-2', $page['fragment']);
            $t->same([], $page['hrefDiagnostics']);

            $t->same('http', $entriesByLabel['HTTP resource']['hrefScheme']);
            $t->same(true, $entriesByLabel['HTTP resource']['external']);
            $t->same(false, $entriesByLabel['HTTP resource']['unsafe']);
            $t->same(['external-nav-href-target'], array_column($entriesByLabel['HTTP resource']['hrefDiagnostics'], 'type'));
            $t->same('https', $entriesByLabel['HTTPS resource']['hrefScheme']);
            $t->same(['external-nav-href-target'], array_column($entriesByLabel['HTTPS resource']['hrefDiagnostics'], 'type'));
            $t->same('mailto', $entriesByLabel['Mail contact']['hrefScheme']);
            $t->same(true, $entriesByLabel['Mail contact']['external']);
            $t->same(false, $entriesByLabel['Mail contact']['unsafe']);
            $t->same('data', $entriesByLabel['Data payload']['hrefScheme']);
            $t->same(true, $entriesByLabel['Data payload']['unsafe']);
            $t->same(['external-nav-href-target', 'unsafe-nav-href-target'], array_column($entriesByLabel['Data payload']['hrefDiagnostics'], 'type'));
            $t->same('javascript', $entriesByLabel['Script action']['hrefScheme']);
            $t->same('unsafe-uri', $entriesByLabel['Script action']['hrefKind']);
            $t->same(true, $entriesByLabel['Script action']['external']);
            $t->same(true, $entriesByLabel['Script action']['unsafe']);
            $t->same(['external-nav-href-target', 'unsafe-nav-href-target'], array_column($entriesByLabel['Script action']['hrefDiagnostics'], 'type'));
            $t->same('package-relative-external', $entriesByLabel['Package escape']['hrefKind']);
            $t->same('../../outside.xhtml', $entriesByLabel['Package escape']['path']);
            $t->same('outside', $entriesByLabel['Package escape']['fragment']);
            $t->same(true, $entriesByLabel['Package escape']['external']);
            $t->same(false, $entriesByLabel['Package escape']['unsafe']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports epub page-list pagebreak href and label diagnostics' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-nav-pages-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-page-list-review</dc:identifier>
    <dc:title>Page List Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml">Chapter</a></li>
      </ol>
    </nav>
    <nav epub:type="page-list">
      <ol>
        <li><a epub:type="pagebreak" href="chapter.xhtml#p1">1</a></li>
        <li><a epub:type="pagebreak" href="chapter.xhtml#p1">1</a></li>
        <li><a epub:type="pagebreak">3</a></li>
        <li><span epub:type="pagebreak">4</span></li>
        <li><a epub:type="pagebreak" href="chapter.xhtml#p5">   </a></li>
        <li><a href="chapter.xhtml#p6">6</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="p1">Chapter</h1><p>Readable page list package.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $pageList = array_values(array_filter(
                $epub['toc'],
                static fn (array $entry): bool => $entry['type'] === 'page-list'
            ));
            $report = $epub['tocReport'];

            $t->same(7, $report['itemCount']);
            $t->same(['toc' => 1, 'page-list' => 6], $report['typeCounts']);
            $t->same(6, $report['pageListItemCount']);
            $t->same(5, $report['pageBreakItemCount']);
            $t->same(9, $report['diagnosticCount']);
            $t->same([
                'duplicate-page-list-target',
                'duplicate-page-list-target',
                'duplicate-page-list-href',
                'duplicate-page-list-label',
                'missing-page-list-href',
                'missing-page-list-href',
                'missing-page-list-label',
                'missing-pagebreak-type',
                'repeated-page-list-fragment-target',
            ], array_column($report['diagnostics'], 'type'));
            $t->same([0, 1], $report['diagnostics'][0]['indexes']);
            $t->same('EPUB/chapter.xhtml#p1', $report['diagnostics'][0]['target']);
            $t->same(1, $report['diagnostics'][2]['firstIndex']);
            $t->same(2, $report['diagnostics'][2]['index']);
            $t->same('EPUB/chapter.xhtml#p1', $report['diagnostics'][2]['target']);
            $t->same('href', $report['diagnostics'][2]['source']);
            $t->same('1', $report['diagnostics'][3]['label']);
            $t->same('label', $report['diagnostics'][3]['source']);
            $t->same('a@href', $report['diagnostics'][4]['source']);
            $t->same('span@href', $report['diagnostics'][5]['source']);
            $t->same('textContent', $report['diagnostics'][6]['source']);
            $t->same('epub:type', $report['diagnostics'][7]['source']);

            $t->same(6, count($pageList));
            $t->same(true, $pageList[0]['pageBreakProvenance']['present']);
            $t->same(['pagebreak'], $pageList[0]['epubTypes']);
            $t->same('textContent', $pageList[0]['labelProvenance']['source']);
            $t->same([], $pageList[0]['diagnostics']);
            $t->same(['missing-page-list-href'], array_column($pageList[2]['diagnostics'], 'type'));
            $t->same(['missing-page-list-href'], array_column($pageList[3]['diagnostics'], 'type'));
            $t->same(['missing-page-list-label'], array_column($pageList[4]['diagnostics'], 'type'));
            $t->same(['missing-pagebreak-type'], array_column($pageList[5]['diagnostics'], 'type'));
            $t->same(false, $pageList[5]['pageBreakProvenance']['present']);
            $t->same('missing', $pageList[5]['pageBreakProvenance']['source']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports epub nav href normalization diagnostics by section' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-nav-normalization-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-nav-normalization</dc:identifier>
    <dc:title>Navigation Normalization</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter2.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="main-toc" epub:type="toc">
      <h1>Contents</h1>
      <ol>
        <li><a href="text/space%20file.xhtml#encoded-frag">Encoded Local</a></li>
        <li><a href="text/../chapter2.xhtml">Dot Local</a><ol>
          <li><a href="chapter2.xhtml#nested">Nested Local</a></li>
        </ol></li>
        <li><a href="https://example.invalid/book.xhtml#remote">Remote</a></li>
        <li><a href="../../outside.xhtml">Escape</a></li>
        <li><a href="casetarget.xhtml">Case Target</a></li>
        <li><a href="">Empty Target</a></li>
        <li><a href="chapter2.xhtml#details"> </a></li>
      </ol>
    </nav>
    <nav id="landmarks" epub:type="landmarks">
      <ol>
        <li><a href="chapter2.xhtml#details">Details</a></li>
      </ol>
    </nav>
    <nav id="pages" epub:type="page-list">
      <ol>
        <li><a href="text/space%20file.xhtml#page-1">1</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter2.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable chapter.</p></body></html>');
            $writePackageFile($root, 'EPUB/text/space file.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Encoded target.</p></body></html>');
            $writePackageFile($root, 'EPUB/CaseTarget.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Case target.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $toc = $epub['toc'];
            $report = $epub['navReport'];
            $normalization = $report['hrefNormalization'];

            $t->same(1, count($document->children));
            $t->same('Readable chapter.', $document->children[0]->attr('text'));
            $t->same(9, count($toc));
            $t->same(true, $normalization['present']);
            $t->same(3, $normalization['sectionCount']);
            $t->same(10, $normalization['entryCount']);
            $t->same(['toc' => 1, 'landmarks' => 1, 'page-list' => 1], $normalization['typeCounts']);
            $t->same(7, $normalization['localTargetCount']);
            $t->same(1, $normalization['externalTargetCount']);
            $t->same(1, $normalization['missingTargetCount']);
            $t->same(6, $normalization['fragmentTargetCount']);
            $t->same(4, $normalization['normalizedHrefCount']);
            $t->same(2, $normalization['percentDecodedHrefCount']);
            $t->same(1, $normalization['dotSegmentNormalizedHrefCount']);
            $t->same(1, $normalization['packageRootEscapeCount']);
            $t->same(1, $normalization['caseMismatchCount']);
            $t->same(1, $normalization['emptyHrefCount']);
            $t->same(14, $normalization['diagnosticCount']);
            $t->same([
                'nav-href-percent-decoded' => 2,
                'nav-href-fragment-component' => 6,
                'nav-href-dot-segment-normalized' => 1,
                'external-nav-reference' => 1,
                'nav-href-package-root-escape' => 1,
                'case-sensitive-nav-target-mismatch' => 1,
                'missing-nav-item-href' => 1,
                'empty-nav-item-label' => 1,
            ], $normalization['diagnosticTypes']);

            $tocSection = $normalization['sectionsByType']['toc'][0];
            $t->same('main-toc', $tocSection['id']);
            $t->same(8, $tocSection['entryCount']);
            $t->same(3, $tocSection['normalizedHrefCount']);
            $t->same(1, $tocSection['percentDecodedHrefCount']);
            $t->same(1, $tocSection['dotSegmentNormalizedHrefCount']);
            $t->same(1, $tocSection['packageRootEscapeCount']);
            $t->same(1, $tocSection['caseMismatchCount']);
            $t->same(1, $tocSection['emptyHrefCount']);
            $t->same(11, $tocSection['diagnosticCount']);
            $t->same(1, $normalization['sectionsByType']['landmarks'][0]['fragmentTargetCount']);
            $t->same(1, $normalization['sectionsByType']['page-list'][0]['percentDecodedHrefCount']);

            $encoded = $toc[0];
            $t->same('Encoded Local', $encoded['label']);
            $t->same('text/space%20file.xhtml#encoded-frag', $encoded['href']);
            $t->same('EPUB/text/space file.xhtml', $encoded['path']);
            $t->same('EPUB/text/space file.xhtml#encoded-frag', $encoded['target']);
            $t->same('encoded-frag', $encoded['fragment']);
            $t->same(true, $encoded['exists']);
            $t->same(true, $encoded['normalization']['percentDecoded']);
            $t->same(['nav-href-percent-decoded', 'nav-href-fragment-component'], array_column($encoded['diagnostics'], 'type'));

            $dot = $toc[1];
            $t->same('Dot Local', $dot['label']);
            $t->same('EPUB/chapter2.xhtml', $dot['path']);
            $t->same(true, $dot['exists']);
            $t->same(true, $dot['normalization']['dotSegmentNormalized']);
            $t->same(['nav-href-dot-segment-normalized'], array_column($dot['diagnostics'], 'type'));
            $t->same(1, count($dot['children']));
            $t->same('Nested Local', $dot['children'][0]['label']);
            $t->same(1, $dot['children'][0]['depth']);
            $t->same('nested', $dot['children'][0]['fragment']);

            $remote = $toc[2];
            $t->same(true, $remote['external']);
            $t->same('https://example.invalid/book.xhtml', $remote['path']);
            $t->same('https://example.invalid/book.xhtml#remote', $remote['target']);
            $t->same('remote', $remote['fragment']);
            $t->same(['external-nav-reference', 'nav-href-fragment-component'], array_column($remote['diagnostics'], 'type'));

            $escape = $toc[3];
            $t->same(true, $escape['external']);
            $t->same(false, $escape['unsafe']);
            $t->same('../../outside.xhtml', $escape['path']);
            $t->same('../../outside.xhtml', $escape['target']);
            $t->same(true, $escape['normalization']['packageRootEscape']);
            $t->same(['nav-href-package-root-escape'], array_column($escape['diagnostics'], 'type'));

            $caseMismatch = $toc[4];
            $t->same('EPUB/casetarget.xhtml', $caseMismatch['path']);
            $t->same(false, $caseMismatch['exists']);
            $t->same('EPUB/CaseTarget.xhtml', $caseMismatch['caseMatchedPath']);
            $t->same(true, $caseMismatch['normalization']['caseMismatch']);
            $t->same(['case-sensitive-nav-target-mismatch'], array_column($caseMismatch['diagnostics'], 'type'));

            $empty = $toc[5];
            $t->same('Empty Target', $empty['label']);
            $t->same('', $empty['href']);
            $t->same('', $empty['path']);
            $t->same(['missing-nav-item-href'], array_column($empty['diagnostics'], 'type'));

            $emptyLabel = $toc[6];
            $t->same('', $emptyLabel['label']);
            $t->same('details', $emptyLabel['fragment']);
            $t->same(['nav-href-fragment-component', 'empty-nav-item-label'], array_column($emptyLabel['diagnostics'], 'type'));

            $landmark = $toc[7];
            $t->same('landmarks', $landmark['type']);
            $t->same('EPUB/chapter2.xhtml', $landmark['path']);
            $t->same('details', $landmark['fragment']);

            $page = $toc[8];
            $t->same('page-list', $page['type']);
            $t->same('EPUB/text/space file.xhtml', $page['path']);
            $t->same('page-1', $page['fragment']);
            $t->same(true, $page['normalization']['percentDecoded']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports epub page-list nav collisions and duplicate package references' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-page-list-collisions-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-page-list-collisions</dc:identifier>
    <dc:title>Page List Collision Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="appendix" href="appendix.xhtml" media-type="application/xhtml+xml"/>
    <item id="glossary" href="glossary.xhtml" media-type="application/xhtml+xml"/>
    <item id="review-copy" href="review-copy-a.xhtml" media-type="application/xhtml+xml"/>
    <item id="review-copy" href="review-copy-b.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
    <itemref idref="appendix" linear="no"/>
    <itemref idref="chapter"/>
    <itemref idref="glossary"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml#page-1">Chapter start</a></li>
        <li><a href="appendix.xhtml#appendix-note">Appendix note</a></li>
      </ol>
    </nav>
    <nav epub:type="landmarks">
      <ol>
        <li><a epub:type="bodymatter" href="chapter.xhtml#page-1">Begin reading</a></li>
      </ol>
    </nav>
    <nav epub:type="page-list">
      <ol>
        <li><a epub:type="pagebreak" href="chapter.xhtml#page-1">1</a></li>
        <li><a epub:type="pagebreak" href="appendix.xhtml#appendix-note">A-1</a></li>
        <li><a epub:type="pagebreak" href="chapter.xhtml#page-1">1 duplicate</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Chapter text.</p></body></html>');
            $writePackageFile($root, 'EPUB/appendix.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Appendix text.</p></body></html>');
            $writePackageFile($root, 'EPUB/glossary.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Glossary text.</p></body></html>');
            $writePackageFile($root, 'EPUB/review-copy-a.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Review copy A.</p></body></html>');
            $writePackageFile($root, 'EPUB/review-copy-b.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Review copy B.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $manifestReport = $epub['manifestReport'];
            $spineReport = $epub['spineReport'];
            $navigationReport = $epub['navigationReport'];
            $pageListReport = $epub['pageListReport'];

            $t->same([], array_values(array_diff(
                ['label', 'href', 'path', 'fragment', 'type', 'children'],
                array_keys($epub['toc'][0])
            )));
            $t->same(1, $manifestReport['duplicateManifestIdCount']);
            $t->same(2, $manifestReport['duplicateManifestItemCount']);
            $t->same(['review-copy'], $manifestReport['duplicateManifestIds']);
            $t->same([4, 5], $manifestReport['duplicateManifestIdItems'][0]['indexes']);
            $t->same(['duplicate-manifest-id', 'duplicate-manifest-id'], array_column($manifestReport['duplicateManifestIdDiagnostics'], 'type'));
            $t->same(1, $spineReport['duplicateIdrefCount']);
            $t->same(2, $spineReport['duplicateIdrefItemCount']);
            $t->same(['chapter'], $spineReport['duplicateIdrefs']);
            $t->same([0, 2], $spineReport['duplicateIdrefItems'][0]['indexes']);
            $t->same(['duplicate-spine-idref', 'duplicate-spine-idref'], array_column($spineReport['duplicateIdrefDiagnostics'], 'type'));
            $t->same(6, $navigationReport['entryCount']);
            $t->same(2, $navigationReport['tocEntryCount']);
            $t->same(1, $navigationReport['landmarksEntryCount']);
            $t->same(3, $navigationReport['pageListEntryCount']);
            $t->same(true, $pageListReport['present']);
            $t->same(3, $pageListReport['itemCount']);
            $t->same(2, $pageListReport['linearTargetCount']);
            $t->same(1, $pageListReport['nonlinearTargetCount']);
            $t->same(1, $pageListReport['outsideSpineTargetCount']);
            $t->same(2, $pageListReport['collisionTargetCount']);
            $t->same(1, $pageListReport['repeatedFragmentTargetCount']);
            $diagnosticTypes = array_column($pageListReport['diagnostics'], 'type');
            foreach ([
                'page-list-target-nav-collision',
                'page-list-target-nonlinear-spine-item',
                'page-list-target-outside-spine-reading-order',
                'duplicate-page-list-href',
                'page-list-reading-order-regression',
                'repeated-page-list-fragment-target',
            ] as $diagnosticType) {
                $t->same(true, in_array($diagnosticType, $diagnosticTypes, true));
            }
            $t->same(['toc', 'landmarks'], array_column($pageListReport['items'][0]['collisions'], 'type'));
            $t->same([0, 1, 0], array_column($pageListReport['readingOrder'], 'spineIndex'));
            $t->same([true, false, true], array_column($pageListReport['readingOrder'], 'linear'));
            $t->same([
                'EPUB/chapter.xhtml#page-1',
                'EPUB/appendix.xhtml#appendix-note',
                'EPUB/chapter.xhtml#page-1',
            ], array_column($pageListReport['readingOrder'], 'target'));
            $t->same([0, 2], $pageListReport['repeatedFragmentTargets'][0]['indexes']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports epub page-list duplicate spine target issue ordering' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-page-list-duplicate-spine-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-page-list-duplicate-spine</dc:identifier>
    <dc:title>Page List Duplicate Spine Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="appendix" href="appendix.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
    <itemref idref="appendix" linear="no"/>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml#p1">Chapter</a></li>
      </ol>
    </nav>
    <nav epub:type="page-list">
      <ol>
        <li><a epub:type="pagebreak" href="chapter.xhtml#p1">1</a></li>
        <li><a epub:type="pagebreak" href="appendix.xhtml#pa">A</a></li>
        <li><a epub:type="pagebreak" href="chapter.xhtml#p1">1 again</a></li>
        <li><a epub:type="pagebreak" href="missing.xhtml#pm">M</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p id="p1">Page one.</p></body></html>');
            $writePackageFile($root, 'EPUB/appendix.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p id="pa">Appendix page.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $spineReport = $epub['spineReport'];
            $pageListReport = $epub['tocReport']['pageList'];
            $items = $pageListReport['items'];

            $t->same($epub['navReport'], $epub['tocReport']);
            $t->same([], array_values(array_diff(
                ['label', 'href', 'path', 'fragment', 'type', 'children'],
                array_keys($epub['toc'][0])
            )));
            $t->same(1, $spineReport['duplicateIdrefCount']);
            $t->same(2, $spineReport['duplicateIdrefItemCount']);
            $t->same(['chapter'], $spineReport['duplicateIdrefs']);
            $t->same([0, 2], $spineReport['duplicateIdrefItems'][0]['indexes']);
            $t->same(['duplicate-spine-idref', 'duplicate-spine-idref'], array_column($spineReport['duplicateIdrefDiagnostics'], 'type'));
            $t->same(true, $pageListReport['present']);
            $t->same(4, $pageListReport['itemCount']);
            $t->same(4, $pageListReport['targetedItemCount']);
            $t->same(3, $pageListReport['manifestTargetCount']);
            $t->same(2, $pageListReport['spineReadingOrderTargetCount']);
            $t->same(2, $pageListReport['linearTargetCount']);
            $t->same(1, $pageListReport['nonlinearTargetCount']);
            $t->same(1, $pageListReport['missingManifestTargetCount']);
            $t->same(1, $pageListReport['outsideSpineTargetCount']);
            $t->same(1, $pageListReport['collisionTargetCount']);
            $t->same(1, $pageListReport['repeatedFragmentTargetCount']);
            $t->same(1, $pageListReport['duplicatePageTargetCount']);
            $t->same(2, $pageListReport['duplicatePageTargetItemCount']);
            $t->same(1, $pageListReport['duplicateSpineTargetCount']);
            $t->same(2, $pageListReport['duplicateSpineTargetItemCount']);
            $t->same('EPUB/chapter.xhtml#p1', $pageListReport['duplicatePageTargets'][0]['target']);
            $t->same([0, 2], $pageListReport['duplicatePageTargets'][0]['indexes']);
            $t->same('EPUB/chapter.xhtml', $pageListReport['duplicateSpineTargets'][0]['path']);
            $t->same([0, 2], $pageListReport['duplicateSpineTargets'][0]['spineIndexes']);
            $t->same(12, $pageListReport['diagnosticCount']);
            $t->same([
                'page-list-target-nav-collision',
                'duplicate-page-list-target',
                'page-list-target-duplicate-spine-itemref',
                'page-list-target-nonlinear-spine-item',
                'page-list-target-outside-spine-reading-order',
                'page-list-target-nav-collision',
                'duplicate-page-list-target',
                'page-list-target-duplicate-spine-itemref',
                'page-list-reading-order-regression',
                'duplicate-page-list-href',
                'missing-page-list-manifest-item',
                'repeated-page-list-fragment-target',
            ], array_column($pageListReport['diagnostics'], 'type'));
            $t->same([0, 1, 0, null], array_column($pageListReport['readingOrder'], 'spineIndex'));
            $t->same([true, false, true, null], array_column($pageListReport['readingOrder'], 'linear'));
            $t->same([
                'EPUB/chapter.xhtml#p1',
                'EPUB/appendix.xhtml#pa',
                'EPUB/chapter.xhtml#p1',
                'EPUB/missing.xhtml#pm',
            ], array_column($pageListReport['readingOrder'], 'target'));
            $t->same([
                'EPUB/chapter.xhtml#p1',
                'EPUB/appendix.xhtml#pa',
                'EPUB/missing.xhtml#pm',
            ], array_keys($pageListReport['readingOrderByTarget']));
            $t->same([0, 2], array_column($pageListReport['readingOrderByTarget']['EPUB/chapter.xhtml#p1'], 'index'));
            $t->same([1], array_column($pageListReport['readingOrderByTarget']['EPUB/appendix.xhtml#pa'], 'index'));
            $t->same([3], array_column($pageListReport['readingOrderByTarget']['EPUB/missing.xhtml#pm'], 'index'));
            $t->same([0, 2], array_column($pageListReport['readingOrderBySpineIndex'][0], 'index'));
            $t->same([1], array_column($pageListReport['readingOrderBySpineIndex'][1], 'index'));
            $t->same([0, 2], array_column($pageListReport['readingOrderBySpineIndex'][2], 'index'));
            $t->same(['chapter', 'chapter'], $pageListReport['readingOrder'][0]['spineIdrefs']);
            $t->same([], $pageListReport['readingOrder'][0]['nonlinearSpineIndexes']);
            $t->same(['appendix'], $pageListReport['readingOrder'][1]['spineIdrefs']);
            $t->same([1], $pageListReport['readingOrder'][1]['nonlinearSpineIndexes']);
            $t->same(['toc'], array_column($items[0]['collisions'], 'type'));
            $t->same([0, 2], $items[0]['spineIndexes']);
            $t->same([0, 2], $items[0]['readingSpineIndexes']);
            $t->same([], $items[0]['nonlinearSpineIndexes']);
            $t->same(['chapter', 'chapter'], $items[0]['spineIdrefs']);
            $t->same(true, $items[0]['duplicatePageTarget']);
            $t->same(true, $items[0]['duplicateSpineTarget']);
            $t->same([
                'page-list-target-nav-collision',
                'duplicate-page-list-target',
                'page-list-target-duplicate-spine-itemref',
                'repeated-page-list-fragment-target',
            ], array_column($items[0]['diagnostics'], 'type'));
            $t->same([
                'page-list-target-nonlinear-spine-item',
                'page-list-target-outside-spine-reading-order',
            ], array_column($items[1]['diagnostics'], 'type'));
            $t->same('nonlinear-spine-item', $items[1]['diagnostics'][1]['reason']);
            $t->same([1], $items[1]['spineIndexes']);
            $t->same([], $items[1]['readingSpineIndexes']);
            $t->same([1], $items[1]['nonlinearSpineIndexes']);
            $t->same(['appendix'], $items[1]['spineIdrefs']);
            $t->same([
                'page-list-target-nav-collision',
                'duplicate-page-list-target',
                'page-list-target-duplicate-spine-itemref',
                'page-list-reading-order-regression',
                'duplicate-page-list-href',
                'repeated-page-list-fragment-target',
            ], array_column($items[2]['diagnostics'], 'type'));
            $t->same([0, 2], $items[2]['spineIndexes']);
            $t->same([0, 2], $items[2]['readingSpineIndexes']);
            $t->same(0, $items[2]['diagnostics'][3]['spineIndex']);
            $t->same(0, $items[2]['diagnostics'][4]['firstPageListIndex']);
            $t->same(null, $items[3]['manifestId']);
            $t->same(['missing-page-list-manifest-item'], array_column($items[3]['diagnostics'], 'type'));
            $t->same([0, 2], $pageListReport['repeatedFragmentTargets'][0]['indexes']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports page-list reading-order metadata inside normalized nav collisions' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-page-list-collision-reading-order-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-page-list-collision-review</dc:identifier>
    <dc:title>Page List Collision Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="appendix" href="appendix.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
    <itemref idref="chapter"/>
    <itemref idref="appendix" linear="no"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="toc-review" epub:type="toc">
      <ol>
        <li><a href="text/chapter.xhtml#page-1">Chapter page</a></li>
      </ol>
    </nav>
    <nav id="landmarks-review" epub:type="landmarks">
      <ol>
        <li><a href="./text/chapter.xhtml#page-1">Start page</a></li>
      </ol>
    </nav>
    <nav id="pages-review" epub:type="page-list">
      <ol>
        <li><a epub:type="pagebreak" href="text/chapter.xhtml#Page-1">1</a></li>
        <li><a epub:type="pagebreak" href="text/chapter.xhtml#%50age-1">1 encoded</a></li>
        <li><a epub:type="pagebreak" href="text/chapter.xhtml#dupe">2</a></li>
        <li><a epub:type="pagebreak" href="text/chapter.xhtml#dupe">2 duplicate</a></li>
        <li><a epub:type="pagebreak" href="appendix.xhtml#back">A</a></li>
        <li><a epub:type="pagebreak" href="https://example.invalid/page#remote">remote</a></li>
        <li><a epub:type="pagebreak" href="javascript:alert(1)#bad">script</a></li>
        <li><a epub:type="pagebreak" href="#local-note">local note</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/text/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable page.</p></body></html>');
            $writePackageFile($root, 'EPUB/appendix.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Back matter.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $pageList = array_values(array_filter(
                $epub['toc'],
                static fn (array $entry): bool => $entry['type'] === 'page-list'
            ));
            $navReport = $epub['navReport'];
            $pageListReport = $epub['tocReport']['pageList'];

            $t->same(8, count($pageList));
            $t->same([], array_values(array_diff(
                ['label', 'href', 'path', 'fragment', 'type', 'children'],
                array_keys($pageList[0])
            )));
            $t->same('EPUB/text/chapter.xhtml', $pageList[0]['path']);
            $t->same('Page-1', $pageList[0]['fragment']);

            $t->same(1, $pageListReport['duplicatePageTargetCount']);
            $t->same('EPUB/text/chapter.xhtml#dupe', $pageListReport['duplicatePageTargets'][0]['target']);
            $t->same([2, 3], $pageListReport['duplicatePageTargets'][0]['indexes']);
            $t->same(1, $pageListReport['duplicateSpineTargetCount']);
            $t->same('EPUB/text/chapter.xhtml', $pageListReport['duplicateSpineTargets'][0]['path']);
            $t->same([0, 1], $pageListReport['duplicateSpineTargets'][0]['spineIndexes']);
            $t->same([
                'duplicate-page-list-target',
                'page-list-target-duplicate-spine-itemref',
                'repeated-page-list-fragment-target',
            ], array_column($pageListReport['items'][2]['diagnostics'], 'type'));
            $t->same('page-list-target-outside-spine-reading-order', $pageListReport['items'][4]['diagnostics'][1]['type']);
            $t->same('external-page-list-reference', $pageListReport['items'][5]['diagnostics'][1]['type']);

            $t->same(1, $navReport['normalizedCollisionGroupCount']);
            $pageListCollision = $navReport['normalizedCollisionDiagnostics'][0];
            $t->same('page-list', $pageListCollision['sectionType']);
            $t->same('epub/text/chapter.xhtml#page-1', $pageListCollision['normalizedTarget']);
            $t->same([0, 1], $pageListCollision['itemIndexes']);
            $t->same([2, 3], $pageListCollision['sourceIndexes']);
            $t->same(2, $pageListCollision['pageListTargetCount']);
            $t->same([0, 1], $pageListCollision['pageListItemIndexes']);
            $t->same([0, 1], $pageListCollision['pageListSpineIndexes']);
            $t->same([0, 1], $pageListCollision['pageListReadingSpineIndexes']);
            $t->same(0, $pageListCollision['pageListDuplicatePageTargetCount']);
            $t->same(2, $pageListCollision['pageListDuplicateSpineTargetCount']);
            $t->same([0, 1], $pageListCollision['pageListTargets'][0]['readingSpineIndexes']);
            $t->same([], $pageListCollision['pageListTargets'][0]['nonlinearSpineIndexes']);
            $t->same(['chapter', 'chapter'], $pageListCollision['pageListTargets'][0]['spineIdrefs']);
            $t->same(true, $pageListCollision['pageListTargets'][0]['duplicateSpineTarget']);

            $t->same(1, $navReport['crossSectionCollisionGroupCount']);
            $t->same(4, $navReport['crossSectionCollisionItemCount']);
            $crossSectionCollision = $navReport['crossSectionCollisionDiagnostics'][0];
            $t->same('epub/text/chapter.xhtml#page-1', $crossSectionCollision['normalizedTarget']);
            $t->same(['toc', 'landmarks', 'page-list', 'page-list'], array_column($crossSectionCollision['itemRefs'], 'sectionType'));
            $t->same(2, $crossSectionCollision['pageListTargetCount']);
            $t->same([0, 1], $crossSectionCollision['pageListItemIndexes']);
            $t->same([0, 1], $crossSectionCollision['pageListReadingSpineIndexes']);
            $t->same([0, 1], $crossSectionCollision['itemRefs'][2]['pageListTarget']['readingSpineIndexes']);

            $policyDiagnosticsByHref = [];
            foreach ($navReport['hrefPolicy']['diagnostics'] as $diagnostic) {
                $href = is_string($diagnostic['href'] ?? null) ? $diagnostic['href'] : '';
                $policyDiagnosticsByHref[$href][] = $diagnostic['type'];
            }
            $normalizationDiagnosticsByHref = [];
            foreach ($navReport['hrefNormalization']['diagnostics'] as $diagnostic) {
                $href = is_string($diagnostic['href'] ?? null) ? $diagnostic['href'] : '';
                $normalizationDiagnosticsByHref[$href][] = $diagnostic['type'];
            }
            $t->same(['external-nav-href-target'], $policyDiagnosticsByHref['https://example.invalid/page#remote']);
            $t->same(['external-nav-href-target', 'unsafe-nav-href-target'], $policyDiagnosticsByHref['javascript:alert(1)#bad']);
            $t->same(['nav-href-fragment-component'], $normalizationDiagnosticsByHref['#local-note']);
        } finally {
            $removeDirectory($root);
        }
    },
    'keeps unsafe page-list hrefs out of collision reading order summaries' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-page-list-collision-precedence-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-page-list-collision-precedence</dc:identifier>
    <dc:title>Page List Collision Precedence</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="pages-review" epub:type="page-list">
      <ol>
        <li><a epub:type="pagebreak" href="text/chapter.xhtml#Page-1">1</a></li>
        <li><a epub:type="pagebreak" href="text/chapter.xhtml#%50age-1">1 encoded</a></li>
        <li><a epub:type="pagebreak" href="https://example.invalid/text/chapter.xhtml#page-1">remote</a></li>
        <li><a epub:type="pagebreak" href="javascript:alert(1)#page-1">script</a></li>
        <li><a epub:type="pagebreak" href="#local-note">local note</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/text/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p id="Page-1">Readable page.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $navReport = $epub['navReport'];
            $pageListReport = $epub['tocReport']['pageList'];
            $items = $pageListReport['items'];

            $t->same(5, $pageListReport['itemCount']);
            $t->same(2, $navReport['externalTargetCount']);
            $t->same(1, $navReport['unsafeTargetCount']);
            $t->same(1, $navReport['normalizedCollisionGroupCount']);
            $t->same(2, $navReport['normalizedCollisionItemCount']);
            $t->same(0, $navReport['crossSectionCollisionGroupCount']);
            $t->same(0, $pageListReport['collisionTargetCount']);
            $t->same(0, $pageListReport['duplicatePageTargetCount']);

            $collision = $navReport['normalizedCollisionDiagnostics'][0];
            $t->same('page-list', $collision['sectionType']);
            $t->same('epub/text/chapter.xhtml#page-1', $collision['normalizedTarget']);
            $t->same(['text/chapter.xhtml#Page-1', 'text/chapter.xhtml#%50age-1'], $collision['hrefs']);
            $t->same([0, 1], $collision['pageListItemIndexes']);
            $t->same([0], $collision['pageListSpineIndexes']);
            $t->same([0], $collision['pageListReadingSpineIndexes']);
            $t->same(2, $collision['pageListTargetCount']);
            $t->same(['fragment', 'percent-encoding'], $collision['collisionKinds']);

            $t->same([0, 0, null, null, null], array_column($pageListReport['readingOrder'], 'spineIndex'));
            $t->same([true, true, null, null, null], array_column($pageListReport['readingOrder'], 'linear'));
            $t->same(['external-nav-href-target', 'unsafe-nav-href-target', 'external-page-list-reference'], array_column($items[3]['diagnostics'], 'type'));
            $t->same(['missing-page-list-manifest-item'], array_column($items[4]['diagnostics'], 'type'));
        } finally {
            $removeDirectory($root);
        }
    },
    'reports normalized epub nav target collisions without changing toc entries' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-nav-collisions-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-nav-collision-review</dc:identifier>
    <dc:title>Nav Collision Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav id="pages-review" epub:type="page-list">
      <ol>
        <li><a href="TEXT/chapter.xhtml">Print page one</a></li>
        <li><a href="text/chapter.xhtml">Print page one copy</a></li>
      </ol>
    </nav>
    <nav id="toc-review" epub:type="toc">
      <ol>
        <li><a href="Text/Chapter.xhtml">Case target</a></li>
        <li><a href="text/%43hapter.xhtml">Encoded target</a></li>
        <li><a href="./text/../text/chapter.xhtml">Dot segment target</a></li>
        <li><a href="text/chapter.xhtml#Intro">Fragment target</a></li>
        <li><a href="text/chapter.xhtml#%49ntro">Encoded fragment target</a></li>
        <li><a href="text/chapter.xhtml#intro">Lower fragment target</a></li>
        <li><a href="#Appendix">Fragment-only target</a></li>
        <li><a href="nav.xhtml#appendix">Nav file fragment target</a></li>
        <li><a href="https://example.invalid/chapter.xhtml#intro">Remote target</a></li>
        <li><a href="../../outside.xhtml#bad">Escaping target</a></li>
        <li><a href="text/%ZZ.xhtml">Malformed escape target</a></li>
      </ol>
    </nav>
    <nav id="landmark-review" epub:type="landmarks">
      <ol>
        <li><a href="text/chapter.xhtml">Start</a></li>
        <li><a href="./text/chapter.xhtml">Start dot copy</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/text/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable chapter.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $toc = array_values(array_filter(
                $epub['toc'],
                static fn (array $entry): bool => $entry['type'] === 'toc'
            ));
            $landmarks = array_values(array_filter(
                $epub['toc'],
                static fn (array $entry): bool => $entry['type'] === 'landmarks'
            ));
            $pageList = array_values(array_filter(
                $epub['toc'],
                static fn (array $entry): bool => $entry['type'] === 'page-list'
            ));
            $report = $epub['navReport'];

            $tocEntryKeys = ['label', 'href', 'path', 'fragment', 'type', 'sectionType', 'sectionIndex', 'diagnostics', 'children'];
            $t->same($tocEntryKeys, array_values(array_intersect($tocEntryKeys, array_keys($toc[0]))));
            $t->same(11, count($toc));
            $t->same(2, count($landmarks));
            $t->same(2, count($pageList));
            $t->same('Print page one', $pageList[0]['label']);
            $t->same([], array_values(array_diff(
                ['label', 'href', 'path', 'fragment', 'type', 'children'],
                array_keys($pageList[0])
            )));
            $t->same('', $toc[6]['path']);
            $t->same('Appendix', $toc[6]['fragment']);
            $t->same('../../outside.xhtml', $toc[9]['path']);
            $t->same('bad', $toc[9]['fragment']);
            $t->same('package-relative-external', $toc[9]['hrefKind']);
            $t->same(true, $toc[9]['normalization']['packageRootEscape']);
            $t->same(true, $toc[10]['unsafe']);
            $t->same('invalid-percent-escape', $toc[10]['hrefKind']);

            $t->same(3, $report['sectionCount']);
            $t->same(15, $report['itemCount']);
            $t->same(15, $report['targetedItemCount']);
            $t->same(13, $report['localTargetCount']);
            $t->same(2, $report['externalTargetCount']);
            $t->same(1, $report['unsafeTargetCount']);
            $t->same(2, $report['hrefPolicy']['externalTargetCount']);
            $t->same(1, $report['hrefPolicy']['unsafeTargetCount']);
            $t->same(1, $report['hrefNormalization']['packageRootEscapeCount']);
            $t->same(5, $report['normalizedCollisionGroupCount']);
            $t->same(12, $report['normalizedCollisionItemCount']);
            $t->same(5, count($report['normalizedCollisionDiagnostics']));
            $t->same(1, $report['crossSectionCollisionGroupCount']);
            $t->same(7, $report['crossSectionCollisionItemCount']);

            $collisionSections = $report['normalizedCollisionSections'];
            $t->same(['page-list', 'toc', 'landmarks'], array_column($collisionSections, 'type'));
            $t->same(1, $collisionSections[0]['normalizedCollisionGroupCount']);
            $t->same(2, $collisionSections[0]['normalizedCollisionItemCount']);
            $t->same(3, $collisionSections[1]['normalizedCollisionGroupCount']);
            $t->same(8, $collisionSections[1]['normalizedCollisionItemCount']);
            $t->same(2, $collisionSections[1]['externalTargetCount']);
            $t->same(1, $collisionSections[1]['unsafeTargetCount']);
            $t->same(1, $collisionSections[1]['packageRootEscapeTargetCount']);
            $t->same(1, $collisionSections[2]['normalizedCollisionGroupCount']);
            $t->same(2, $collisionSections[2]['normalizedCollisionItemCount']);
            $t->same(['toc', 'toc', 'toc', 'landmarks', 'page-list'], array_column($report['normalizedCollisionDiagnostics'], 'sectionType'));

            $pathCollision = $report['normalizedCollisionDiagnostics'][0];
            $t->same(1, $pathCollision['sectionIndex']);
            $t->same('epub/text/chapter.xhtml', $pathCollision['normalizedTarget']);
            $t->same([0, 1, 2], $pathCollision['itemIndexes']);
            $t->same(['Text/Chapter.xhtml', 'text/%43hapter.xhtml', './text/../text/chapter.xhtml'], $pathCollision['hrefs']);
            $t->same(['percent-encoding', 'dot-segment', 'case'], $pathCollision['collisionKinds']);

            $fragmentCollision = $report['normalizedCollisionDiagnostics'][1];
            $t->same('epub/text/chapter.xhtml#intro', $fragmentCollision['normalizedTarget']);
            $t->same([3, 4, 5], $fragmentCollision['itemIndexes']);
            $t->same(['fragment', 'percent-encoding', 'case'], $fragmentCollision['collisionKinds']);

            $fragmentOnlyCollision = $report['normalizedCollisionDiagnostics'][2];
            $t->same('epub/nav.xhtml#appendix', $fragmentOnlyCollision['normalizedTarget']);
            $t->same([6, 7], $fragmentOnlyCollision['itemIndexes']);
            $t->same(['#Appendix', 'nav.xhtml#appendix'], $fragmentOnlyCollision['hrefs']);
            $t->same(['fragment', 'case'], $fragmentOnlyCollision['collisionKinds']);

            $landmarkCollision = $report['normalizedCollisionDiagnostics'][3];
            $t->same('landmarks', $landmarkCollision['sectionType']);
            $t->same('epub/text/chapter.xhtml', $landmarkCollision['normalizedTarget']);
            $t->same(['dot-segment'], $landmarkCollision['collisionKinds']);

            $pageListCollision = $report['normalizedCollisionDiagnostics'][4];
            $t->same('page-list', $pageListCollision['sectionType']);
            $t->same(0, $pageListCollision['sectionIndex']);
            $t->same('epub/text/chapter.xhtml', $pageListCollision['normalizedTarget']);
            $t->same(['case'], $pageListCollision['collisionKinds']);

            $crossSectionCollision = $report['crossSectionCollisionDiagnostics'][0];
            $t->same('cross-section-normalized-nav-target-collision', $crossSectionCollision['type']);
            $t->same('epub/text/chapter.xhtml', $crossSectionCollision['normalizedTarget']);
            $t->same(3, $crossSectionCollision['sectionCount']);
            $t->same(['toc', 'landmarks', 'page-list'], $crossSectionCollision['sectionTypes']);
            $t->same([1, 2, 0], $crossSectionCollision['sectionIndexes']);
            $t->same(['toc-review', 'landmark-review', 'pages-review'], $crossSectionCollision['sectionIds']);
            $t->same(7, $crossSectionCollision['itemCount']);
            $t->same(6, $crossSectionCollision['rawHrefCount']);
            $t->same(['toc', 'toc', 'toc', 'landmarks', 'landmarks', 'page-list', 'page-list'], array_column($crossSectionCollision['itemRefs'], 'sectionType'));
            $t->same(['Text/Chapter.xhtml', 'text/%43hapter.xhtml', './text/../text/chapter.xhtml', 'text/chapter.xhtml', './text/chapter.xhtml', 'TEXT/chapter.xhtml'], $crossSectionCollision['hrefs']);
            $t->same(['percent-encoding', 'dot-segment', 'case'], $crossSectionCollision['collisionKinds']);

            $targetDiagnosticsByHref = [];
            foreach ($report['hrefPolicy']['diagnostics'] as $diagnostic) {
                if (!is_array($diagnostic) || !is_string($diagnostic['href'] ?? null)) {
                    continue;
                }
                $targetDiagnosticsByHref[$diagnostic['href']][] = $diagnostic['type'] ?? '';
            }
            $t->same(['external-nav-href-target'], $targetDiagnosticsByHref['https://example.invalid/chapter.xhtml#intro']);
            $t->same(['external-nav-href-target'], $targetDiagnosticsByHref['../../outside.xhtml#bad']);
            $t->same(['unsafe-nav-href-target'], $targetDiagnosticsByHref['text/%ZZ.xhtml']);
        } finally {
            $removeDirectory($root);
        }
    },
    'maps epub spine xhtml assets into shared ast and wordpress blocks' => static function (TestRunner $t) use ($fixture): void {
        $document = (new EpubPackageReader())->readDirectory($fixture());
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(7, count($document->children));
        $t->same(['heading', 'paragraph', 'blockquote', 'heading', 'paragraph', 'bullet_list', 'definition_list'], array_map(
            static fn (AstNode $node): string => $node->type,
            $document->children
        ));

        $heading = $document->children[0];
        $intro = $document->children[1];
        $quote = $document->children[2];
        $details = $document->children[4];
        $list = $document->children[5];
        $definitions = $document->children[6];

        $t->same(1, $heading->attr('level'));
        $t->same('opening-title', $heading->attr('id'));
        $t->same('Opening Packet', $heading->attr('text'));
        $t->same('paragraph', $intro->type);
        $t->same('Intro EPUB packet with details and Cover image.', $intro->attr('text'));
        $t->same(['text', 'strong', 'text', 'link', 'text', 'image', 'text'], array_map(
            static fn (AstNode $node): string => $node->type,
            $intro->children
        ));
        $t->same('EPUB', $intro->children[1]->children[0]->attr('text'));
        $t->same('EPUB/chapter2.xhtml#details', $intro->children[3]->attr('url'));
        $t->same('Details', $intro->children[3]->attr('title'));
        $t->same('details', $intro->children[3]->children[0]->attr('text'));
        $t->same('EPUB/images/cover.png', $intro->children[5]->attr('url'));
        $t->same('Cover image', $intro->children[5]->attr('alt'));
        $t->same('Cover', $intro->children[5]->attr('title'));
        $t->same('blockquote', $quote->type);
        $t->same('Reviewer note with wp_insert_post.', $quote->children[0]->attr('text'));
        $t->same('code', $quote->children[0]->children[1]->type);
        $t->same('wp_insert_post', $quote->children[0]->children[1]->attr('text'));
        $t->same(['text', 'emph', 'linebreak', 'text'], array_map(
            static fn (AstNode $node): string => $node->type,
            $details->children
        ));
        $t->same('reading order', $details->children[1]->children[0]->attr('text'));
        $t->same(2, count($list->children));
        $t->same('First migration check', $list->children[0]->children[0]->attr('text'));
        $t->same('Second check with source', $list->children[1]->children[0]->attr('text'));
        $t->same('https://example.test/source', $list->children[1]->children[0]->children[1]->attr('url'));
        $t->same('Review status Resource note', $definitions->attr('text'));
        $t->same('review-glossary', $definitions->attr('htmlAttributes')['id']);
        $t->same('migration-terms', $definitions->attr('htmlAttributes')['class']);
        $t->same(2, count($definitions->children));
        $t->same('Review status', $definitions->children[0]->attr('term'));
        $t->same('Ready for direct XHTML handoff.', $definitions->children[0]->children[1]->children[0]->attr('text'));
        $t->same('strong', $definitions->children[0]->children[1]->children[0]->children[1]->type);
        $t->same('Resource note', $definitions->children[1]->attr('term'));
        $t->same(true, $definitions->children[1]->children[1]->attr('loose'));
        $t->same('EPUB/chapter1.xhtml#opening-note', $definitions->children[1]->children[1]->children[0]->children[1]->attr('url'));
        $t->same('bullet_list', $definitions->children[1]->children[1]->children[1]->type);
        $t->contains('<h1 id="opening-title">Opening Packet</h1>', $blocks);
        $t->contains('<strong>EPUB</strong>', $blocks);
        $t->contains('<a href="EPUB/chapter2.xhtml#details" title="Details">details</a>', $blocks);
        $t->contains('<img src="EPUB/images/cover.png" alt="Cover image" title="Cover"/>', $blocks);
        $t->contains('<blockquote class="wp-block-quote"><p>Reviewer note with <code>wp_insert_post</code>.</p></blockquote>', $blocks);
        $t->contains('<em>reading order</em><br/>and a hard break.', $blocks);
        $t->contains('<li>First migration check</li><li>Second check with <a href="https://example.test/source">source</a></li>', $blocks);
        $t->contains('<div id="review-glossary" class="wp-block-group pandoc-definition-list migration-terms">', $blocks);
        $t->contains('<p class="pandoc-definition-term"><strong>Review status</strong></p>', $blocks);
        $t->contains('<ul class="pandoc-definition-values"><li>Ready for <strong>direct XHTML</strong> handoff.</li></ul>', $blocks);
        $t->contains('<p class="pandoc-definition-term"><strong>Resource note</strong></p>', $blocks);
        $t->contains('<ul class="pandoc-definition-values"><li><p>Keep package-local links like <a href="EPUB/chapter1.xhtml#opening-note">opening note</a> reviewable.</p><ul><li>Preserve nested checks.</li></ul></li></ul>', $blocks);
    },
    'maps epub xhtml mathml into native math nodes' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-xhtml-mathml-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-mathml-review</dc:identifier>
    <dc:title>MathML Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body>
    <p>Inline <math xmlns="http://www.w3.org/1998/Math/MathML"><semantics><mrow><msup><mi>x</mi><mn>2</mn></msup></mrow><annotation encoding="application/x-tex">x^2</annotation></semantics></math> and fallback <math xmlns="http://www.w3.org/1998/Math/MathML" id="mathml-only" data-source="mathml-only"><mrow><mi>y</mi><mo>+</mo><mn>1</mn></mrow></math>.</p>
    <p><math xmlns="http://www.w3.org/1998/Math/MathML" display="block"><semantics><mrow><mi>E</mi><mo>=</mo><mi>m</mi><msup><mi>c</mi><mn>2</mn></msup></mrow><annotation encoding="application/x-tex">E=mc^2</annotation></semantics></math></p>
    <p><math xmlns="http://www.w3.org/1998/Math/MathML" display="block"><mtext>∫ − ∞ ∞ e − x 2 d x = π</mtext></math></p>
  </body>
</html>
XML);

            $document = (new EpubPackageReader())->readDirectory($root);
            $blocks = (new WordPressBlockWriter())->write($document);
            $inline = $document->children[0];
            $display = $document->children[1]->children[0] ?? new AstNode('missing');
            $known = $document->children[2]->children[0] ?? new AstNode('missing');
            $fallback = $inline->children[3] ?? new AstNode('missing');

            $t->same(['paragraph', 'paragraph', 'paragraph'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
            $t->same('Inline x^2 and fallback y + 1.', $inline->attr('text'));
            $t->same(['text', 'math', 'text', 'math', 'text'], array_map(static fn (AstNode $node): string => $node->type, $inline->children));
            $t->same(false, $inline->children[1]->attr('display'));
            $t->same('x^2', $inline->children[1]->attr('text'));
            $t->same('math', $fallback->type);
            $t->same(false, $fallback->attr('display'));
            $t->same('y + 1', $fallback->attr('text'));
            $t->same('math', $display->type);
            $t->same(true, $display->attr('display'));
            $t->same('E=mc^2', $display->attr('text'));
            $t->same('math', $known->type);
            $t->same('\int_{- \infty}^{\infty}e^{- x^{2}}\, dx = \sqrt{\pi}', $known->attr('text'));
            $t->contains('<span class="math inline">\(x^2\)</span>', $blocks);
            $t->contains('<span class="math inline">\(y + 1\)</span>', $blocks);
            $t->contains('<span class="math display">\[E=mc^2\]</span>', $blocks);
            $t->contains('<span class="math display">\[\int_{- \infty}^{\infty}e^{- x^{2}}\, dx = \sqrt{\pi}\]</span>', $blocks);
        } finally {
            $removeDirectory($root);
        }
    },
    'maps epub xhtml definition lists into shared ast and wordpress blocks' => static function (TestRunner $t) use ($fixture): void {
        $document = (new EpubPackageReader())->readDirectory($fixture());
        $blocks = (new WordPressBlockWriter())->write($document);
        $definitions = $document->children[6];
        $firstItem = $definitions->children[0];
        $secondItem = $definitions->children[1];

        $t->same('definition_list', $definitions->type);
        $t->same('review-glossary', $definitions->attr('htmlAttributes')['id']);
        $t->same('migration-terms', $definitions->attr('htmlAttributes')['class']);
        $t->same('Review status', $firstItem->attr('term'));
        $t->same('term', $firstItem->children[0]->type);
        $t->same('Ready for direct XHTML handoff.', $firstItem->children[1]->children[0]->attr('text'));
        $t->same('direct XHTML', $firstItem->children[1]->children[0]->children[1]->children[0]->attr('text'));
        $t->same('Resource note', $secondItem->attr('term'));
        $t->same(true, $secondItem->children[1]->attr('loose'));
        $t->same('EPUB/chapter1.xhtml#opening-note', $secondItem->children[1]->children[0]->children[1]->attr('url'));
        $t->same('Preserve nested checks.', $secondItem->children[1]->children[1]->children[0]->children[0]->attr('text'));
        $t->contains('<div id="review-glossary" class="wp-block-group pandoc-definition-list migration-terms">', $blocks);
        $t->contains('<p class="pandoc-definition-term"><strong>Review status</strong></p>', $blocks);
        $t->contains('<ul class="pandoc-definition-values"><li>Ready for <strong>direct XHTML</strong> handoff.</li></ul>', $blocks);
        $t->contains('<p class="pandoc-definition-term"><strong>Resource note</strong></p>', $blocks);
        $t->contains('<ul class="pandoc-definition-values"><li><p>Keep package-local links like <a href="EPUB/chapter1.xhtml#opening-note">opening note</a> reviewable.</p><ul><li>Preserve nested checks.</li></ul></li></ul>', $blocks);
    },
    'preserves epub xhtml figure caption inline provenance for writer handoff' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-xhtml-figure-caption-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-figure-caption-review</dc:identifier>
    <dc:title>Figure Caption Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="cover" href="images/cover.png" media-type="image/png"/>
    <item id="audit" href="audit.html" media-type="text/html"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body>
    <figure id="cover-figure" class="reviewed" data-review="figure">
      <img src="images/cover.png" title="Cover source"/>
      <figcaption id="cover-caption" class="source-caption">Reviewed <em>cover</em> <a href="audit.html#cover" title="Audit record">audit</a> <code>sha256</code></figcaption>
    </figure>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/images/cover.png', 'PNG');
            $writePackageFile($root, 'EPUB/audit.html', '<html><body>audit</body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $wordpress = (new WordPressBlockWriter())->write($document);
            $figure = $document->children[0];
            $image = $figure->children[0];
            $captionInlines = $figure->attr('captionInlines');

            $t->same(1, count($document->children));
            $t->same('figure', $figure->type);
            $t->same('Reviewed cover audit sha256', $figure->attr('caption'));
            $t->same('cover-figure', $figure->attr('htmlAttributes')['id']);
            $t->same('reviewed', $figure->attr('htmlAttributes')['class']);
            $t->same('epub-xhtml-figcaption', $figure->attr('captionSource')['source']);
            $t->same('cover-caption', $figure->attr('captionSource')['sourceAttributes']['htmlAttributes']['id']);
            $t->same(['source-caption'], $figure->attr('captionSource')['sourceAttributes']['classes']);
            $t->same(['text', 'emph', 'text', 'link', 'text', 'code'], array_map(static fn (AstNode $node): string => $node->type, $captionInlines));
            $t->same('cover', $captionInlines[1]->children[0]->attr('text'));
            $t->same('EPUB/audit.html#cover', $captionInlines[3]->attr('url'));
            $t->same('Audit record', $captionInlines[3]->attr('title'));
            $t->same('sha256', $captionInlines[5]->attr('text'));
            $t->same('EPUB/images/cover.png', $image->attr('url'));
            $t->same('Cover source', $image->attr('title'));
            $t->contains('<figcaption>Reviewed <em>cover</em> <a href="EPUB/audit.html#cover" title="Audit record">audit</a> <code>sha256</code></figcaption>', $wordpress);
        } finally {
            $removeDirectory($root);
        }
    },
    'maps epub xhtml tables into shared ast and wordpress blocks' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-xhtml-table-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-table-review</dc:identifier>
    <dc:title>Table Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body>
    <table id="migration-matrix" class="review" border="1" data-review="epub-table">
      <caption id="matrix-caption">Migration <strong>matrix</strong></caption>
      <thead>
        <tr><th scope="col">Source</th><th scope="col">Status</th><th scope="col">Notes</th></tr>
      </thead>
      <tbody id="matrix-body">
        <tr>
          <th scope="row">Posts</th>
          <td align="center">Ready</td>
          <td rowspan="2"><p>Uses <code>wp_insert_post</code>.</p><ul><li>Check media.</li></ul></td>
        </tr>
        <tr><th scope="row">Pages</th><td>Queued</td></tr>
        <tr><td colspan="3">Footer preflight</td></tr>
      </tbody>
      <tfoot><tr><td colspan="2">Total</td><td>2 items</td></tr></tfoot>
    </table>
  </body>
</html>
XML);

            $document = (new EpubPackageReader())->readDirectory($root);
            $blocks = (new WordPressBlockWriter())->write($document);
            $table = $document->children[0];
            $head = $table->children[0];
            $body = $table->children[1];
            $foot = $table->children[2];
            $headerRow = $head->children[0];
            $postsRow = $body->children[0];
            $notesCell = $postsRow->children[2];
            $footerRow = $foot->children[0];

            $t->same(1, count($document->children));
            $t->same('table', $table->type);
            $t->same('Migration matrix', $table->attr('caption'));
            $t->same('migration-matrix', $table->attr('htmlAttributes')['id']);
            $t->same('review', $table->attr('htmlAttributes')['class']);
            $t->same('epub-xhtml', $table->attr('sourceFormat'));
            $t->same(['text', 'strong'], array_map(static fn (AstNode $node): string => $node->type, $table->attr('captionInlines')));
            $t->same('matrix-caption', $table->attr('captionSource')['sourceAttributes']['htmlAttributes']['id']);
            $t->same(['table_head', 'table_body', 'table_foot'], array_map(static fn (AstNode $node): string => $node->type, $table->children));
            $t->same(3, $table->attr('tableGeometry')['columnCount']);
            $t->same('Migration matrix', $table->attr('tableGeometry')['caption']);

            $t->same(1, count($head->children));
            $t->same(3, count($headerRow->children));
            $t->same(true, $headerRow->children[0]->attr('header'));
            $t->same('Source', $headerRow->children[0]->attr('text'));
            $t->same('col', $headerRow->children[0]->attr('htmlAttributes')['scope']);
            $t->same('matrix-body', $body->attr('htmlAttributes')['id']);
            $t->same(3, count($body->children));
            $t->same(true, $postsRow->children[0]->attr('header'));
            $t->same('row', $postsRow->children[0]->attr('htmlAttributes')['scope']);
            $t->same('center', $postsRow->children[1]->attr('align'));
            $t->same(2, $notesCell->attr('rowspan'));
            $t->same(['paragraph', 'bullet_list'], array_map(static fn (AstNode $node): string => $node->type, $notesCell->children));
            $t->same('wp_insert_post', $notesCell->children[0]->children[1]->attr('text'));
            $t->same(3, $body->children[2]->children[0]->attr('colspan'));
            $t->same(2, $footerRow->children[0]->attr('colspan'));

            $t->contains('<figcaption id="matrix-caption" class="wp-element-caption">Migration <strong>matrix</strong></figcaption>', $blocks);
            $t->contains('<table id="migration-matrix" class="review"', $blocks);
            $t->contains('data-review="epub-table"', $blocks);
            $t->contains('border="1"', $blocks);
            $t->contains('<th scope="col">Source</th>', $blocks);
            $t->contains('<th scope="row">Posts</th>', $blocks);
            $t->contains('<td style="text-align:center">Ready</td>', $blocks);
            $t->contains('<td rowspan="2"><p>Uses <code>wp_insert_post</code>.</p><ul><li>Check media.</li></ul></td>', $blocks);
            $t->contains('<tr><td colspan="3">Footer preflight</td></tr>', $blocks);
            $t->contains('<tfoot><tr><td colspan="2">Total</td><td>2 items</td></tr></tfoot>', $blocks);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports direct package manifest suffixes and skipped spine entries' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-reader-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-manifest-review</dc:identifier>
    <dc:title>Manifest Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="appendix" href="appendix.xhtml?draft=review#appendix-start" media-type="application/xhtml+xml"/>
    <item id="remote" href="https://cdn.example.invalid/remote.xhtml?edition=review#remote" media-type="application/xhtml+xml"/>
    <item id="missing" href="missing.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
    <itemref idref="appendix" linear="no"/>
    <itemref idref="remote"/>
    <itemref idref="missing"/>
    <itemref idref="ghost"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable chapter.</p></body></html>');
            $writePackageFile($root, 'EPUB/appendix.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Nonlinear appendix.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $manifest = $epub['manifestById'];
            $manifestReport = $epub['manifestReport'];
            $spine = $epub['spine'];
            $spineReport = $epub['spineReport'];

            $t->same(1, count($document->children));
            $t->same('Readable chapter.', $document->children[0]->attr('text'));
            $t->same('EPUB/appendix.xhtml?draft=review#appendix-start', $manifest['appendix']['target']);
            $t->same('EPUB/appendix.xhtml', $manifest['appendix']['path']);
            $t->same(true, $manifest['appendix']['exists']);
            $t->same(true, $manifest['appendix']['hrefHasQuery']);
            $t->same('draft=review', $manifest['appendix']['hrefQuery']);
            $t->same(true, $manifest['appendix']['hrefHasFragment']);
            $t->same('appendix-start', $manifest['appendix']['hrefFragment']);
            $t->same(['manifest-href-query-component', 'manifest-href-fragment-component'], array_column($manifest['appendix']['diagnostics'], 'type'));

            $t->same(true, $manifest['remote']['external']);
            $t->same('https://cdn.example.invalid/remote.xhtml?edition=review#remote', $manifest['remote']['target']);
            $t->same('https://cdn.example.invalid/remote.xhtml', $manifest['remote']['path']);
            $t->same(false, $manifest['remote']['exists']);
            $t->same(['external-manifest-href-target', 'manifest-href-query-component', 'manifest-href-fragment-component'], array_column($manifest['remote']['diagnostics'], 'type'));
            $t->same(false, $manifest['missing']['exists']);
            $t->same(['missing-manifest-href-target'], array_column($manifest['missing']['diagnostics'], 'type'));

            $t->same(4, $manifestReport['itemCount']);
            $t->same(1, $manifestReport['externalItemCount']);
            $t->same(1, $manifestReport['missingItemCount']);
            $t->same(2, $manifestReport['hrefSuffixCount']);
            $t->same(6, $manifestReport['diagnosticCount']);
            $t->same(['appendix', 'remote'], array_column($manifestReport['hrefSuffixItems'], 'id'));
            $t->same(['manifest-href-query-component', 'manifest-href-fragment-component', 'external-manifest-href-target', 'manifest-href-query-component', 'manifest-href-fragment-component', 'missing-manifest-href-target'], array_column($manifestReport['diagnostics'], 'type'));

            $t->same(5, $spineReport['itemCount']);
            $t->same(4, $spineReport['linearItemCount']);
            $t->same(1, $spineReport['nonlinearItemCount']);
            $t->same(1, $spineReport['readableItemCount']);
            $t->same(4, $spineReport['skippedItemCount']);
            $t->same(1, $spineReport['externalItemCount']);
            $t->same(1, $spineReport['missingPackagePartItemCount']);
            $t->same(1, $spineReport['missingManifestItemCount']);
            $t->same(3, $spineReport['diagnosticCount']);
            $t->same('EPUB/appendix.xhtml?draft=review#appendix-start', $spine[1]['target']);
            $t->same(false, $spine[1]['readable']);
            $t->same('external-spine-item', $spine[2]['diagnostics'][0]['type']);
            $t->same('missing-spine-item-package-part', $spine[3]['diagnostics'][0]['type']);
            $t->same('missing-spine-manifest-item', $spine[4]['diagnostics'][0]['type']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports direct package malformed manifest item attributes without dropping readable spine' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-reader-malformed-manifest-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-malformed-manifest-review</dc:identifier>
    <dc:title>Malformed Manifest Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item href="assets/no-id.bin" media-type="application/octet-stream" data-review="missing-id"/>
    <item id="missing-href" media-type="text/css" data-review="missing-href"/>
    <item id="missing-type" href="assets/no-type.bin" data-review="missing-type"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/assets/no-id.bin', 'NO-ID');
            $writePackageFile($root, 'EPUB/assets/no-type.bin', 'NO-TYPE');
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable chapter survives malformed manifest rows.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $manifest = $epub['manifestById'];
            $report = $epub['manifestReport'];

            $t->same(1, count($document->children));
            $t->same('Readable chapter survives malformed manifest rows.', $document->children[0]->attr('text'));
            $t->same(3, count($epub['manifest']));
            $t->same(false, isset($manifest['']));

            $t->same('', $manifest['missing-href']['href']);
            $t->same('', $manifest['missing-href']['path']);
            $t->same(false, $manifest['missing-href']['exists']);
            $t->same(false, $manifest['missing-href']['requiredAttributesPresent']);
            $t->same(['href'], $manifest['missing-href']['missingRequiredAttributes']);
            $t->same(['missing-manifest-item-href'], array_column($manifest['missing-href']['diagnostics'], 'type'));

            $t->same('assets/no-type.bin', $manifest['missing-type']['href']);
            $t->same('EPUB/assets/no-type.bin', $manifest['missing-type']['path']);
            $t->same(true, $manifest['missing-type']['exists']);
            $t->same(false, $manifest['missing-type']['requiredAttributesPresent']);
            $t->same(['media-type'], $manifest['missing-type']['missingRequiredAttributes']);
            $t->same(['invalid-manifest-media-type', 'missing-manifest-item-media-type'], array_column($manifest['missing-type']['diagnostics'], 'type'));

            $t->same(3, $report['itemCount']);
            $t->same(0, $report['missingItemCount']);
            $t->same(3, $report['malformedItemCount']);
            $t->same(3, $report['missingRequiredAttributeItemCount']);
            $t->same(3, $report['missingRequiredAttributeCount']);
            $t->same(['id', 'href', 'media-type'], $report['missingRequiredAttributeNames']);
            $t->same([0, 1, 2], array_column($report['missingRequiredAttributeItems'], 'index'));
            $t->same([['id'], ['href'], ['media-type']], array_column($report['missingRequiredAttributeItems'], 'missingAttributes'));
            $t->same('', $report['malformedItems'][0]['id']);
            $t->same('assets/no-id.bin', $report['malformedItems'][0]['href']);
            $t->same('EPUB/assets/no-id.bin', $report['malformedItems'][0]['path']);
            $t->same('missing-href', $report['malformedItems'][1]['id']);
            $t->same('missing-type', $report['malformedItems'][2]['id']);
            $t->same(1, $report['invalidMediaTypeCount']);
            $t->same(1, $report['mediaTypeDiagnosticCount']);
            $t->same(4, $report['diagnosticCount']);
            $t->same([
                'missing-manifest-item-id',
                'missing-manifest-item-href',
                'invalid-manifest-media-type',
                'missing-manifest-item-media-type',
            ], array_column($report['diagnostics'], 'type'));
            $t->same([0, 1, 2, 2], array_column($report['diagnostics'], 'index'));
        } finally {
            $removeDirectory($root);
        }
    },
    'normalizes direct package manifest media types while preserving parameter diagnostics' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-reader-media-type-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-manifest-media-type-review</dc:identifier>
    <dc:title>Manifest Media Type Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml; charset=UTF-8" properties="nav"/>
    <item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml; profile=&quot;legacy;review&quot;"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml; charset=UTF-8; charset=windows-1252"/>
    <item id="style" href="style.css" media-type="text/css; charset=UTF-8"/>
    <item id="overlay" href="overlay.smil" media-type="application/smil+xml; charset=UTF-8"/>
    <item id="widget" href="widgets/review.bin" media-type="application/x-review-widget" fallback-style="style" media-overlay="overlay"/>
    <item id="broken" href="broken.review" media-type="review-packet; flag"/>
  </manifest>
  <spine toc="ncx">
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml">Chapter</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/toc.ncx', <<<'XML'
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
  <navMap>
    <navPoint id="np-1" playOrder="1">
      <navLabel><text>Chapter</text></navLabel>
      <content src="chapter.xhtml"/>
    </navPoint>
  </navMap>
</ncx>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Parameterized XHTML remains readable.</p></body></html>');
            $writePackageFile($root, 'EPUB/style.css', 'body { color: #222; }');
            $writePackageFile($root, 'EPUB/overlay.smil', '<smil xmlns="http://www.w3.org/ns/SMIL"><body/></smil>');
            $writePackageFile($root, 'EPUB/widgets/review.bin', 'REVIEW');
            $writePackageFile($root, 'EPUB/broken.review', 'BROKEN');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $manifest = $epub['manifestById'];
            $manifestReport = $epub['manifestReport'];
            $spineReport = $epub['spineReport'];

            $t->same(1, count($document->children));
            $t->same('Parameterized XHTML remains readable.', $document->children[0]->attr('text'));
            $t->same(true, $epub['ncxReport']['present']);
            $t->same(1, $epub['ncxReport']['itemCount']);
            $t->same('application/xhtml+xml; charset=UTF-8; charset=windows-1252', $manifest['chapter']['rawMediaType']);
            $t->same('application/xhtml+xml', $manifest['chapter']['mediaType']);
            $t->same('application/xhtml+xml; charset=windows-1252', $manifest['chapter']['normalizedMediaType']);
            $t->same(2, $manifest['chapter']['mediaTypeParameterCount']);
            $t->same(['charset' => 'windows-1252'], $manifest['chapter']['mediaTypeParameterMap']);
            $t->same('duplicate-manifest-media-type-parameter', $manifest['chapter']['mediaTypeDiagnostics'][0]['type']);
            $t->same('application/x-dtbncx+xml', $manifest['ncx']['mediaType']);
            $t->same(['profile' => 'legacy;review'], $manifest['ncx']['mediaTypeParameterMap']);
            $t->same('text/css', $manifest['style']['mediaType']);
            $t->same('application/smil+xml', $manifest['overlay']['mediaType']);

            $t->same(1, $spineReport['readableItemCount']);
            $t->same(0, $manifestReport['missingItemCount']);
            $t->same(7, $manifestReport['itemCount']);
            $t->same(6, $manifestReport['mediaTypeParameterCount']);
            $t->same(5, $manifestReport['mediaTypeParameterizedItemCount']);
            $t->same(['charset', 'profile'], $manifestReport['mediaTypeParameterNames']);
            $t->same(['nav', 'ncx', 'chapter', 'style', 'overlay'], array_column($manifestReport['mediaTypeParameterItems'], 'id'));
            $t->same(2, $manifestReport['invalidMediaTypeCount']);
            $t->same(['chapter', 'broken'], array_column($manifestReport['invalidMediaTypeItems'], 'id'));
            $t->same(3, $manifestReport['mediaTypeDiagnosticCount']);
            $t->same([
                'duplicate-manifest-media-type-parameter',
                'invalid-manifest-media-type',
                'invalid-manifest-media-type-parameter',
            ], array_column($manifestReport['mediaTypeDiagnostics'], 'type'));
            $t->same(0, $manifestReport['manifestItemReferenceDiagnosticCount']);
            $t->same('text/css; charset=UTF-8', $manifestReport['fallbackStyleReferences'][0]['targetMediaType']);
            $t->same('text/css', $manifestReport['fallbackStyleReferences'][0]['targetMediaTypeBase']);
            $t->same('application/smil+xml; charset=UTF-8', $manifestReport['mediaOverlayReferences'][0]['targetMediaType']);
            $t->same('application/smil+xml', $manifestReport['mediaOverlayReferences'][0]['targetMediaTypeBase']);
            $t->same(3, $manifestReport['diagnosticCount']);
            $t->same($manifestReport['mediaTypeDiagnostics'], $manifestReport['diagnostics']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports direct package manifest item reference readiness matrix' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-manifest-reference-readiness-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-manifest-reference-readiness</dc:identifier>
    <dc:title>Manifest Reference Readiness</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="style" href="style.css" media-type="text/css"/>
    <item id="overlay" href="overlay.smil" media-type="application/smil+xml"/>
    <item id="poster" href="poster.png" media-type="image/png"/>
    <item id="remote-poster" href="https://cdn.example.invalid/poster.png" media-type="image/png"/>
    <item id="missing-poster" href="missing-poster.png" media-type="image/png"/>
    <item id="widget-ok" href="widgets/ok.bin" media-type="application/x-review-widget" fallback="poster" fallback-style="style" media-overlay="overlay"/>
    <item id="widget-missing-fallback" href="widgets/missing-fallback.bin" media-type="application/x-review-widget" fallback="missing-fallback"/>
    <item id="widget-external-fallback" href="widgets/external-fallback.bin" media-type="application/x-review-widget" fallback="remote-poster"/>
    <item id="widget-missing-part-fallback" href="widgets/missing-part-fallback.bin" media-type="application/x-review-widget" fallback="missing-poster"/>
    <item id="widget-missing-style" href="widgets/missing-style.bin" media-type="application/x-review-widget" fallback-style="missing-style"/>
    <item id="widget-non-css-style" href="widgets/non-css-style.bin" media-type="application/x-review-widget" fallback-style="poster"/>
    <item id="chapter-missing-overlay" href="chapter-missing-overlay.xhtml" media-type="application/xhtml+xml" media-overlay="missing-overlay"/>
    <item id="chapter-non-smil-overlay" href="chapter-non-smil-overlay.xhtml" media-type="application/xhtml+xml" media-overlay="style"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            foreach ([
                'chapter.xhtml' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable chapter.</p></body></html>',
                'chapter-missing-overlay.xhtml' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Missing overlay.</p></body></html>',
                'chapter-non-smil-overlay.xhtml' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Wrong overlay.</p></body></html>',
                'style.css' => 'body { color: #333; }',
                'overlay.smil' => '<smil xmlns="http://www.w3.org/ns/SMIL"><body/></smil>',
                'poster.png' => 'PNGDATA',
                'widgets/ok.bin' => 'OK',
                'widgets/missing-fallback.bin' => 'MISSING-FALLBACK',
                'widgets/external-fallback.bin' => 'EXTERNAL-FALLBACK',
                'widgets/missing-part-fallback.bin' => 'MISSING-PART-FALLBACK',
                'widgets/missing-style.bin' => 'MISSING-STYLE',
                'widgets/non-css-style.bin' => 'NON-CSS-STYLE',
            ] as $path => $bytes) {
                $writePackageFile($root, 'EPUB/' . $path, $bytes);
            }

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $manifest = $epub['manifestById'];
            $manifestReport = $epub['manifestReport'];

            $t->same('poster', $manifest['widget-ok']['fallback']);
            $t->same('style', $manifest['widget-ok']['fallbackStyle']);
            $t->same('overlay', $manifest['widget-ok']['mediaOverlay']);
            $t->same('missing-fallback', $manifest['widget-missing-fallback']['fallback']);
            $t->same('remote-poster', $manifest['widget-external-fallback']['fallback']);
            $t->same('missing-poster', $manifest['widget-missing-part-fallback']['fallback']);
            $t->same('missing-style', $manifest['widget-missing-style']['fallbackStyle']);
            $t->same('poster', $manifest['widget-non-css-style']['fallbackStyle']);
            $t->same('missing-overlay', $manifest['chapter-missing-overlay']['mediaOverlay']);
            $t->same('style', $manifest['chapter-non-smil-overlay']['mediaOverlay']);

            $t->same(14, $manifestReport['itemCount']);
            $t->same(1, $manifestReport['externalItemCount']);
            $t->same(1, $manifestReport['missingItemCount']);
            $t->same(10, $manifestReport['manifestItemReferenceCount']);
            $t->same(7, $manifestReport['manifestItemReferenceDiagnosticCount']);
            $t->same(9, $manifestReport['diagnosticCount']);
            $t->same([
                'external-manifest-href-target',
                'missing-manifest-href-target',
                'missing-manifest-fallback-item',
                'external-manifest-fallback-target',
                'missing-manifest-fallback-part',
                'missing-manifest-fallback-style-item',
                'non-css-manifest-fallback-style',
                'missing-manifest-media-overlay-item',
                'unexpected-manifest-media-overlay-type',
            ], array_column($manifestReport['diagnostics'], 'type'));

            $t->same(4, $manifestReport['fallbackReferenceCount']);
            $t->same(3, $manifestReport['fallbackReferenceDiagnosticCount']);
            $t->same(['widget-ok', 'widget-missing-fallback', 'widget-external-fallback', 'widget-missing-part-fallback'], array_column($manifestReport['fallbackReferences'], 'sourceId'));
            $t->same('EPUB/poster.png', $manifestReport['fallbackReferences'][0]['target']);
            $t->same(true, $manifestReport['fallbackReferences'][0]['targetExists']);
            $t->same([], $manifestReport['fallbackReferences'][0]['diagnostics']);
            $t->same([
                'missing-manifest-fallback-item',
                'external-manifest-fallback-target',
                'missing-manifest-fallback-part',
            ], array_column($manifestReport['fallbackReferenceDiagnostics'], 'type'));
            $t->same(['missing-fallback', 'remote-poster', 'missing-poster'], array_column($manifestReport['fallbackReferenceDiagnostics'], 'targetId'));

            $t->same(3, $manifestReport['fallbackStyleReferenceCount']);
            $t->same(2, $manifestReport['fallbackStyleReferenceDiagnosticCount']);
            $t->same('text/css', $manifestReport['fallbackStyleReferences'][0]['targetMediaType']);
            $t->same([
                'missing-manifest-fallback-style-item',
                'non-css-manifest-fallback-style',
            ], array_column($manifestReport['fallbackStyleReferenceDiagnostics'], 'type'));
            $t->same('image/png', $manifestReport['fallbackStyleReferences'][2]['targetMediaType']);

            $t->same(3, $manifestReport['mediaOverlayReferenceCount']);
            $t->same(2, $manifestReport['mediaOverlayReferenceDiagnosticCount']);
            $t->same('application/smil+xml', $manifestReport['mediaOverlayReferences'][0]['targetMediaType']);
            $t->same([
                'missing-manifest-media-overlay-item',
                'unexpected-manifest-media-overlay-type',
            ], array_column($manifestReport['mediaOverlayReferenceDiagnostics'], 'type'));
            $t->same('text/css', $manifestReport['mediaOverlayReferences'][2]['targetMediaType']);
        } finally {
            $removeDirectory($root);
        }
    },
    'preserves direct reader OPF authoring attributes for package review' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-reader-authoring-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:review="https://example.invalid/epub-review" id="reader-authoring" version="3.0" unique-identifier="bookid" xml:lang="fr" dir="rtl" xml:base="review-base/" prefix="schema: https://schema.org/ review: https://example.invalid/vocab#" review:packet="source" data-package="handoff">
  <metadata>
    <dc:identifier id="bookid">urn:reader-authoring-review</dc:identifier>
    <dc:title>Reader Authoring Review</dc:title>
    <dc:language>fr</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav" data-nav="toc"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml" xml:base="alternate/" xml:lang="fr" dir="rtl" data-track="spine" review:role="chapter"/>
  </manifest>
  <spine page-progression-direction="rtl">
    <itemref id="spine-chapter" idref="chapter" linear="yes" properties="page-spread-left" xml:lang="fr" dir="rtl" data-spine="primary" review:note="ordered"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml">Chapter</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable chapter.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $packageAuthoring = $epub['packageAuthoring'];
            $manifestAuthoring = $epub['manifestAuthoring'];
            $spineAuthoring = $epub['spineAuthoring'];
            $manifest = $epub['manifestById'];
            $spine = $epub['spine'];

            $t->same(true, $packageAuthoring['present']);
            $t->same('reader-authoring', $packageAuthoring['id']);
            $t->same('3.0', $packageAuthoring['version']);
            $t->same('bookid', $packageAuthoring['uniqueIdentifierId']);
            $t->same('fr', $packageAuthoring['language']);
            $t->same('rtl', $packageAuthoring['direction']);
            $t->same('review-base/', $packageAuthoring['xmlBase']);
            $t->same('schema: https://schema.org/ review: https://example.invalid/vocab#', $packageAuthoring['prefix']);
            $t->same(9, $packageAuthoring['attributeCount']);
            $t->same('source', $packageAuthoring['customAttributes']['review:packet']);
            $t->same('handoff', $packageAuthoring['customAttributes']['data-package']);
            $t->same(2, $packageAuthoring['customAttributeCount']);
            $t->same(true, $packageAuthoring['hasCustomAttributes']);

            $t->same('fr', $manifest['chapter']['language']);
            $t->same('rtl', $manifest['chapter']['direction']);
            $t->same('alternate/', $manifest['chapter']['base']);
            $t->same('reported-not-applied-to-manifest-hrefs', $manifest['chapter']['baseResolutionPolicy']);
            $t->same(false, $manifest['chapter']['baseResolution']['appliesToManifestHrefs']);
            $t->same('EPUB/chapter.xhtml', $manifest['chapter']['target']);
            $t->same('spine', $manifest['chapter']['customAttributes']['data-track']);
            $t->same('chapter', $manifest['chapter']['customAttributes']['review:role']);
            $t->same(true, $manifestAuthoring['present']);
            $t->same(2, $manifestAuthoring['itemCount']);
            $t->same(1, $manifestAuthoring['languageItemCount']);
            $t->same(1, $manifestAuthoring['directionItemCount']);
            $t->same(1, $manifestAuthoring['baseItemCount']);
            $t->same(2, $manifestAuthoring['customAttributeItemCount']);
            $t->same('toc', $manifestAuthoring['itemsById']['nav']['customAttributes']['data-nav']);
            $t->same('alternate/', $manifestAuthoring['itemsById']['chapter']['base']);
            $t->same('reported-not-applied-to-manifest-hrefs', $manifestAuthoring['itemsById']['chapter']['baseResolutionPolicy']);
            $t->same(['chapter'], array_column($manifestAuthoring['baseItems'], 'id'));
            $t->same(['nav', 'chapter'], array_column($manifestAuthoring['customAttributeItems'], 'id'));
            $t->same($manifest['chapter']['attributes'], $manifestAuthoring['itemsById']['chapter']['attributes']);
            $t->same($manifest['chapter']['customAttributes'], $manifestAuthoring['itemsById']['chapter']['customAttributes']);

            $t->same('spine-chapter', $spine[0]['id']);
            $t->same('yes', $spine[0]['linearRaw']);
            $t->same('fr', $spine[0]['language']);
            $t->same('rtl', $spine[0]['direction']);
            $t->same('primary', $spine[0]['customAttributes']['data-spine']);
            $t->same('ordered', $spine[0]['customAttributes']['review:note']);
            $t->same(true, $spineAuthoring['present']);
            $t->same(1, $spineAuthoring['itemCount']);
            $t->same(1, $spineAuthoring['languageItemCount']);
            $t->same(1, $spineAuthoring['directionItemCount']);
            $t->same(1, $spineAuthoring['customAttributeItemCount']);
            $t->same($spine[0]['attributes'], $spineAuthoring['items'][0]['attributes']);
            $t->same($spine[0]['customAttributes'], $spineAuthoring['items'][0]['customAttributes']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports direct manifest and spine authoring review details' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-reader-authoring-details-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-authoring-details</dc:identifier>
    <dc:title>Reader Authoring Details</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav scripted" xml:lang="en" dir="ltr" data-review="nav"/>
    <item id="chapter" href="chapter.xhtml?rev=1#intro" media-type="application/xhtml+xml; charset=utf-8" properties="scripted remote-resources" fallback="fallback-html" fallback-style="style" media-overlay="mo" data-stage="chapter"/>
    <item id="fallback-html" href="fallback.xhtml" media-type="application/xhtml+xml"/>
    <item id="style" href="style.css" media-type="text/css"/>
    <item id="mo" href="overlay.smil" media-type="application/smil+xml"/>
    <item id="remote" href="https://example.invalid/remote.xhtml" media-type="application/xhtml+xml" properties="remote-resources"/>
  </manifest>
  <spine page-progression-direction="rtl">
    <itemref id="intro-ref" idref="chapter" linear="yes" properties="page-spread-right rendition:layout-pre-paginated" xml:lang="en" dir="rtl" data-spine="intro"/>
    <itemref idref="missing" linear="no" properties="page-spread-left"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml">Chapter</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable chapter.</p></body></html>');
            $writePackageFile($root, 'EPUB/fallback.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Fallback chapter.</p></body></html>');
            $writePackageFile($root, 'EPUB/style.css', 'body { color: #111; }');
            $writePackageFile($root, 'EPUB/overlay.smil', '<smil xmlns="http://www.w3.org/ns/SMIL"><body/></smil>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $manifestAuthoring = $epub['manifestAuthoring'];
            $spineAuthoring = $epub['spineAuthoring'];

            $chapterAuthoring = $manifestAuthoring['itemsById']['chapter'];
            $t->same(6, $manifestAuthoring['itemCount']);
            $t->same(3, $manifestAuthoring['propertyItemCount']);
            $t->same(['scripted', 'remote-resources'], $manifestAuthoring['propertiesByItemId']['chapter']);
            $t->same(1, $manifestAuthoring['fallbackItemCount']);
            $t->same('chapter', $manifestAuthoring['fallbackItems'][0]['id']);
            $t->same('fallback-html', $chapterAuthoring['fallback']);
            $t->same(1, $manifestAuthoring['fallbackStyleItemCount']);
            $t->same('style', $chapterAuthoring['fallbackStyle']);
            $t->same(1, $manifestAuthoring['mediaOverlayItemCount']);
            $t->same('mo', $manifestAuthoring['mediaOverlayItems'][0]['mediaOverlay']);
            $t->same(1, $manifestAuthoring['hrefSuffixItemCount']);
            $t->same(true, $chapterAuthoring['hrefHasQuery']);
            $t->same('rev=1', $chapterAuthoring['hrefQuery']);
            $t->same(true, $chapterAuthoring['hrefHasFragment']);
            $t->same('intro', $chapterAuthoring['hrefFragment']);
            $t->same(1, $manifestAuthoring['mediaTypeParameterItemCount']);
            $t->same(true, $chapterAuthoring['mediaTypeHasParameters']);
            $t->same(1, $chapterAuthoring['mediaTypeParameterCount']);
            $t->same(['charset'], $chapterAuthoring['mediaTypeParameterNames']);
            $t->same(['manifest-href-query-component', 'manifest-href-fragment-component'], array_column($chapterAuthoring['diagnostics'], 'type'));
            $t->same(2, $manifestAuthoring['diagnosticItemCount']);
            $t->same(3, $manifestAuthoring['diagnosticCount']);
            $t->same(['chapter', 'remote'], array_column($manifestAuthoring['diagnosticItems'], 'id'));
            $t->same(['manifest-href-query-component', 'manifest-href-fragment-component', 'external-manifest-href-target'], array_column($manifestAuthoring['diagnostics'], 'type'));

            $t->same(2, $spineAuthoring['itemCount']);
            $t->same(2, $spineAuthoring['propertyItemCount']);
            $t->same(['page-spread-right', 'rendition:layout-pre-paginated'], $spineAuthoring['propertiesByIndex'][0]);
            $t->same(2, $spineAuthoring['explicitLinearItemCount']);
            $t->same(['yes', 'no'], array_column($spineAuthoring['explicitLinearItems'], 'linearRaw'));
            $t->same(1, $spineAuthoring['nonLinearItemCount']);
            $t->same('missing', $spineAuthoring['nonLinearItems'][0]['idref']);
            $t->same(1, $spineAuthoring['diagnosticItemCount']);
            $t->same(1, $spineAuthoring['diagnosticCount']);
            $t->same('missing-spine-manifest-item', $spineAuthoring['items'][1]['diagnostics'][0]['type']);
            $t->same('missing-spine-manifest-item', $spineAuthoring['diagnostics'][0]['type']);
            $t->same('intro', $spineAuthoring['itemsByIndex'][0]['customAttributes']['data-spine']);
        } finally {
            $removeDirectory($root);
        }
    },
    'preserves direct manifest media type parameters while resolving readable package content' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-reader-media-types-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-media-type-parameter-review</dc:identifier>
    <dc:title>Manifest Media Type Parameter Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml; charset=UTF-8" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type='application/xhtml+xml; profile="chapter;review"' media-overlay="overlay"/>
    <item id="style" href="style.css" media-type='text/css; charset="UTF-8"'/>
    <item id="overlay" href="overlay.smil" media-type="application/smil+xml; codec=smil"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml">Parameterized chapter</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable parameterized XHTML.</p></body></html>');
            $writePackageFile($root, 'EPUB/style.css', 'body { color: #111; }');
            $writePackageFile($root, 'EPUB/overlay.smil', '<smil xmlns="http://www.w3.org/ns/SMIL"><body/></smil>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $manifest = $epub['manifestById'];
            $manifestReport = $epub['manifestReport'];
            $spine = $epub['spine'];

            $t->same('application/xhtml+xml; charset=UTF-8', $manifest['nav']['rawMediaType']);
            $t->same('application/xhtml+xml', $manifest['nav']['mediaType']);
            $t->same('application/xhtml+xml', $manifest['nav']['mediaTypeBase']);
            $t->same('application/xhtml+xml; charset=utf-8', $manifest['nav']['normalizedMediaType']);
            $t->same(true, $manifest['nav']['mediaTypeHasParameters']);
            $t->same(['charset' => 'UTF-8'], $manifest['nav']['mediaTypeParameterMap']);
            $t->same(['charset'], $manifest['nav']['mediaTypeParameterNames']);
            $t->same([], $manifest['nav']['mediaTypeDiagnostics']);

            $t->same('application/xhtml+xml', $manifest['chapter']['mediaTypeBase']);
            $t->same(['profile' => 'chapter;review'], $manifest['chapter']['mediaTypeParameterMap']);
            $t->same('chapter;review', $manifest['chapter']['mediaTypeParameters'][0]['value']);
            $t->same('profile="chapter;review"', $manifest['chapter']['mediaTypeParameters'][0]['raw']);
            $t->same('application/smil+xml', $manifest['overlay']['mediaTypeBase']);
            $t->same(['codec' => 'smil'], $manifest['overlay']['mediaTypeParameterMap']);

            $t->same([
                'application/smil+xml' => 1,
                'application/xhtml+xml' => 2,
                'text/css' => 1,
            ], $manifestReport['mediaTypeBaseCounts']);
            $t->same(4, $manifestReport['mediaTypeParameterItemCount']);
            $t->same(4, $manifestReport['mediaTypeParameterCount']);
            $t->same(['charset', 'codec', 'profile'], $manifestReport['mediaTypeParameterNames']);
            $t->same(['nav', 'chapter', 'style', 'overlay'], array_column($manifestReport['mediaTypeParameterItems'], 'id'));
            $t->same([], $manifestReport['mediaTypeDiagnostics']);
            $t->same(0, $manifestReport['mediaTypeDiagnosticCount']);

            $t->same(1, $manifestReport['mediaOverlayReferenceCount']);
            $t->same('chapter', $manifestReport['mediaOverlayReferences'][0]['sourceId']);
            $t->same('overlay', $manifestReport['mediaOverlayReferences'][0]['targetId']);
            $t->same('application/smil+xml; codec=smil', $manifestReport['mediaOverlayReferences'][0]['targetMediaType']);
            $t->same('application/smil+xml', $manifestReport['mediaOverlayReferences'][0]['targetMediaTypeBase']);
            $t->same(0, $manifestReport['mediaOverlayReferenceDiagnosticCount']);

            $t->same('application/xhtml+xml; profile="chapter;review"', $spine[0]['mediaType']);
            $t->same('application/xhtml+xml', $spine[0]['mediaTypeBase']);
            $t->same(true, $spine[0]['readable']);
            $t->same(1, $epub['spineReport']['readableItemCount']);
            $t->same('Parameterized chapter', $epub['toc'][0]['label']);
            $t->same(1, count($document->children));
        } finally {
            $removeDirectory($root);
        }
    },
    'reports direct reader manifest item xml base authoring policy' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-manifest-base-authoring-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" xmlns:dc="http://purl.org/dc/elements/1.1/" version="3.0" unique-identifier="bookid">
  <metadata>
    <dc:identifier id="bookid">urn:manifest-base-authoring</dc:identifier>
    <dc:title>Manifest Base Authoring Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml" xml:base="ignored-base/" xml:lang="en"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable chapter.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $manifest = $epub['manifestById'];
            $authoring = $epub['manifestAuthoring'];
            $chapter = $manifest['chapter'];

            $t->same('ignored-base/', $chapter['base']);
            $t->same('reported-not-applied-to-manifest-hrefs', $chapter['baseResolutionPolicy']);
            $t->same(false, $chapter['baseResolution']['appliesToManifestHrefs']);
            $t->same(true, $chapter['baseResolution']['metadataOnly']);
            $t->same('EPUB/chapter.xhtml', $chapter['target']);
            $t->same('EPUB/chapter.xhtml', $chapter['path']);
            $t->same(false, array_key_exists('xml:base', $chapter['customAttributes']));
            $t->same(1, $authoring['baseItemCount']);
            $t->same(['chapter'], array_column($authoring['baseItems'], 'id'));
            $t->same('ignored-base/', $authoring['itemsById']['chapter']['base']);
            $t->same('reported-not-applied-to-manifest-hrefs', $authoring['itemsById']['chapter']['baseResolutionPolicy']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports direct package spine page progression metadata' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-spine-progression-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-spine-progression-review</dc:identifier>
    <dc:title>Spine Progression Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine toc="ghost-ncx" page-progression-direction="sideways">
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable chapter.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $spineMetadata = $epub['spineMetadata'];
            $spineReport = $epub['spineReport'];

            $t->same(true, $spineMetadata['present']);
            $t->same('ghost-ncx', $spineMetadata['toc']);
            $t->same('default', $spineMetadata['pageProgressionDirection']);
            $t->same('sideways', $spineMetadata['pageProgressionDirectionRaw']);
            $t->same(true, $spineMetadata['pageProgressionDirectionSpecified']);
            $t->same(false, $spineMetadata['pageProgressionDirectionValid']);
            $t->same(false, $spineMetadata['rightToLeft']);
            $t->same(1, $spineMetadata['diagnosticCount']);
            $t->same('invalid-spine-page-progression-direction', $spineMetadata['diagnostics'][0]['type']);
            $t->same('sideways', $spineMetadata['diagnostics'][0]['value']);

            $t->same($spineMetadata, $spineReport['spineMetadata']);
            $t->same('default', $spineReport['pageProgressionDirection']);
            $t->same('sideways', $spineReport['pageProgressionDirectionRaw']);
            $t->same(true, $spineReport['pageProgressionDirectionSpecified']);
            $t->same(false, $spineReport['pageProgressionDirectionValid']);
            $t->same(false, $spineReport['rightToLeft']);
            $t->same(1, $spineReport['spineMetadataDiagnosticCount']);
            $t->same(['invalid-spine-page-progression-direction'], array_column($spineReport['spineMetadataDiagnostics'], 'type'));
            $t->same(['invalid-spine-page-progression-direction'], array_column($spineReport['diagnostics'], 'type'));
        } finally {
            $removeDirectory($root);
        }
    },
    'reports direct package spine linear value diagnostics for reader handoff' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-reader-spine-linear-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-spine-linear-review</dc:identifier>
    <dc:title>Spine Linear Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="appendix" href="appendix.xhtml" media-type="application/xhtml+xml"/>
    <item id="colophon" href="colophon.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref id="chapter-spine" idref="chapter" linear="sometimes"/>
    <itemref id="appendix-spine" idref="appendix" linear="NO"/>
    <itemref id="colophon-spine" idref="colophon"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', <<<'XML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml">Chapter</a></li>
        <li><a href="appendix.xhtml">Appendix</a></li>
        <li><a href="colophon.xhtml">Colophon</a></li>
      </ol>
    </nav>
  </body>
</html>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Chapter body.</p></body></html>');
            $writePackageFile($root, 'EPUB/appendix.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Appendix body.</p></body></html>');
            $writePackageFile($root, 'EPUB/colophon.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Colophon body.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $spine = $epub['spine'];
            $spineReport = $epub['spineReport'];
            $spineAuthoring = $epub['spineAuthoring'];

            $t->same(3, $spineReport['itemCount']);
            $t->same(2, $spineReport['linearItemCount']);
            $t->same(1, $spineReport['nonlinearItemCount']);
            $t->same(2, $spineReport['readableItemCount']);
            $t->same(1, $spineReport['invalidLinearItemCount']);
            $t->same(1, $spineReport['linearDiagnosticCount']);
            $t->same(1, $spineReport['diagnosticCount']);
            $t->same(['invalid-spine-linear-value'], array_column($spineReport['diagnostics'], 'type'));
            $t->same('chapter', $spineReport['linearDiagnostics'][0]['idref']);
            $t->same('sometimes', $spineReport['invalidLinearItems'][0]['linearRaw']);

            $t->same('chapter', $spine[0]['idref']);
            $t->same('sometimes', $spine[0]['linearRaw']);
            $t->same(true, $spine[0]['linearSpecified']);
            $t->same('sometimes', $spine[0]['linearValue']);
            $t->same(false, $spine[0]['linearValid']);
            $t->same(true, $spine[0]['linear']);
            $t->same(true, $spine[0]['readable']);
            $t->same('invalid-spine-linear-value', $spine[0]['linearDiagnostics'][0]['type']);
            $t->same('sometimes', $spine[0]['linearDiagnostics'][0]['value']);

            $t->same('appendix', $spine[1]['idref']);
            $t->same('NO', $spine[1]['linearRaw']);
            $t->same(true, $spine[1]['linearSpecified']);
            $t->same('no', $spine[1]['linearValue']);
            $t->same(true, $spine[1]['linearValid']);
            $t->same(false, $spine[1]['linear']);
            $t->same(false, $spine[1]['readable']);
            $t->same([], $spine[1]['linearDiagnostics']);

            $t->same('colophon', $spine[2]['idref']);
            $t->same(null, $spine[2]['linearRaw']);
            $t->same(false, $spine[2]['linearSpecified']);
            $t->same(null, $spine[2]['linearValue']);
            $t->same(true, $spine[2]['linearValid']);
            $t->same(true, $spine[2]['linear']);
            $t->same(true, $spine[2]['readable']);

            $t->same('sometimes', $spineAuthoring['items'][0]['linearRaw']);
            $t->same(false, $spineAuthoring['items'][0]['linearValid']);
            $t->same('sometimes', $spineAuthoring['items'][0]['linearValue']);
            $t->same('NO', $spineAuthoring['items'][1]['linearRaw']);
            $t->same(false, $spineAuthoring['items'][1]['linear']);
            $t->same(null, $spineAuthoring['items'][2]['linearRaw']);
            $t->same(false, $spineAuthoring['items'][2]['linearSpecified']);

            $t->same(2, count($document->children));
            $t->contains('Chapter body.', $document->children[0]->attr('text'));
            $t->contains('Colophon body.', $document->children[1]->attr('text'));
        } finally {
            $removeDirectory($root);
        }
    },
    'reports direct package OPF bindings and readable handler handoff' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-direct-bindings-' . str_replace('.', '', uniqid('', true));
        $handlerXhtml = '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Bound widget fallback stays readable.</p></body></html>';
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-binding-review</dc:identifier>
    <dc:title>Binding Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="widget" href="widgets/review-widget.bin" media-type="application/x-review-widget"/>
    <item id="widget-handler" href="text/widget-handler.xhtml" media-type="application/xhtml+xml" properties="scripted"/>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="widget"/>
    <itemref idref="chapter"/>
  </spine>
  <bindings>
    <mediaType id="widget-binding" media-type="application/x-review-widget; profile=&quot;interactive&quot;" handler="widget-handler" data-source="package-review"/>
    <mediaType media-type="application/x-missing-widget" handler="missing-handler"/>
  </bindings>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><body><nav epub:type="toc"><ol><li><a href="text/chapter.xhtml">Chapter</a></li></ol></nav></body></html>');
            $writePackageFile($root, 'EPUB/widgets/review-widget.bin', 'CUSTOM-WIDGET-BYTES');
            $writePackageFile($root, 'EPUB/text/widget-handler.xhtml', $handlerXhtml);
            $writePackageFile($root, 'EPUB/text/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Regular chapter remains readable.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $bindings = $epub['bindings'];
            $binding = $bindings['items'][0];
            $missing = $bindings['items'][1];
            $spine = $epub['spine'];

            $t->same(true, $bindings['present']);
            $t->same(2, $bindings['itemCount']);
            $t->same(2, $bindings['handlerCount']);
            $t->same(1, $bindings['resolvedHandlerCount']);
            $t->same(1, $bindings['readableHandlerCount']);
            $t->same(1, $bindings['missingHandlerCount']);
            $t->same(['application/x-review-widget', 'application/x-missing-widget'], $bindings['boundMediaTypes']);
            $t->same('widget-binding', $binding['id']);
            $t->same('application/x-review-widget; profile="interactive"', $binding['rawMediaType']);
            $t->same('application/x-review-widget', $binding['mediaType']);
            $t->same('application/x-review-widget; profile=interactive', $binding['normalizedMediaType']);
            $t->same(['profile' => 'interactive'], $binding['mediaTypeParameterMap']);
            $t->same('widget-handler', $binding['handlerId']);
            $t->same('text/widget-handler.xhtml', $binding['handlerHref']);
            $t->same('EPUB/text/widget-handler.xhtml', $binding['handlerPath']);
            $t->same('application/xhtml+xml', $binding['handlerMediaType']);
            $t->same(['scripted'], $binding['handlerProperties']);
            $t->same(true, $binding['handlerExists']);
            $t->same(true, $binding['handlerReadable']);
            $t->same(strlen($handlerXhtml), $binding['handlerByteLength']);
            $t->same(hash('sha256', $handlerXhtml), $binding['handlerByteSha256']);
            $t->same('package-review', $binding['customAttributes']['data-source']);
            $t->same([], $binding['diagnostics']);
            $t->same('application/x-missing-widget', $missing['mediaType']);
            $t->same('missing-handler', $missing['handlerId']);
            $t->same(false, $missing['handlerExists']);
            $t->same('missing-binding-handler-manifest-item', $missing['diagnostics'][0]['type']);
            $t->same(1, $bindings['diagnosticCount']);
            $t->same('missing-binding-handler-manifest-item', $bindings['diagnostics'][0]['type']);
            $t->same(1, $bindings['diagnostics'][0]['index']);
            $t->same($binding, $bindings['itemsByMediaType']['application/x-review-widget']);
            $t->same($bindings, $epub['bindingReport']);
            $t->same($bindings['diagnostics'], $epub['bindingDiagnostics']);

            $t->same('widget', $spine[0]['idref']);
            $t->same('application/x-review-widget', $spine[0]['mediaType']);
            $t->same('EPUB/widgets/review-widget.bin', $spine[0]['path']);
            $t->same($binding, $spine[0]['binding']);
            $t->same(true, $spine[0]['bindingHandlerReadable']);
            $t->same(true, $spine[0]['readable']);
            $t->same('widget-handler', $spine[0]['contentId']);
            $t->same('EPUB/text/widget-handler.xhtml', $spine[0]['contentPath']);
            $t->same('application/xhtml+xml', $spine[0]['contentMediaType']);
            $t->same(true, $spine[0]['contentIsFallback']);
            $t->same('binding-handler', $spine[0]['fallbackChain'][0]['source']);
            $t->same('application/x-review-widget', $spine[0]['fallbackChain'][0]['bindingMediaType']);
            $t->same([], $spine[0]['fallbackDiagnostics']);
            $t->same('EPUB/text/chapter.xhtml', $spine[1]['contentPath']);
            $t->same(false, $spine[1]['contentIsFallback']);
            $t->same(2, count($document->children));
            $t->contains('Bound widget fallback stays readable.', $document->children[0]->attr('text'));
            $t->contains('Regular chapter remains readable.', $document->children[1]->attr('text'));
        } finally {
            $removeDirectory($root);
        }
    },
    'reports direct package spine page-spread itemref properties' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-spine-page-spread-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-spine-page-spread-review</dc:identifier>
    <dc:title>Spine Page Spread Review</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="appendix" href="appendix.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref id="chapter-spine" idref="chapter" properties="rendition:page-spread-left page-spread-left"/>
    <itemref id="appendix-spine" idref="appendix" linear="no" properties="page-spread-right rendition:page-spread-center"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable chapter.</p></body></html>');
            $writePackageFile($root, 'EPUB/appendix.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Nonlinear appendix.</p></body></html>');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $spine = $epub['spine'];
            $spineReport = $epub['spineReport'];

            $t->same('left', $spine[0]['pageSpread']);
            $t->same(['rendition:page-spread-left', 'page-spread-left'], $spine[0]['pageSpreadProperties']);
            $t->same(false, $spine[0]['spineItemProperties']['pageSpread']['conflicting']);
            $t->same([], $spine[0]['spineItemDiagnostics']);
            $t->same([], $spine[0]['diagnostics']);

            $t->same('right', $spine[1]['pageSpread']);
            $t->same(['page-spread-right', 'rendition:page-spread-center'], $spine[1]['pageSpreadProperties']);
            $t->same(true, $spine[1]['spineItemProperties']['pageSpread']['conflicting']);
            $t->same(['right', 'center'], $spine[1]['spineItemProperties']['pageSpread']['placements']);
            $t->same(['conflicting-spine-page-spread-properties'], array_column($spine[1]['spineItemDiagnostics'], 'type'));
            $t->same(['conflicting-spine-page-spread-properties'], array_column($spine[1]['diagnostics'], 'type'));

            $t->same(2, $spineReport['pageSpreadCount']);
            $t->same(1, $spineReport['pageSpreadLeftCount']);
            $t->same(1, $spineReport['pageSpreadRightCount']);
            $t->same(0, $spineReport['pageSpreadCenterCount']);
            $t->same('chapter-spine', $spineReport['pageSpreadItems'][0]['id']);
            $t->same('left', $spineReport['pageSpreadItems'][0]['placement']);
            $t->same(false, $spineReport['pageSpreadItems'][0]['conflicting']);
            $t->same('appendix-spine', $spineReport['pageSpreadItems'][1]['id']);
            $t->same('right', $spineReport['pageSpreadItems'][1]['placement']);
            $t->same(true, $spineReport['pageSpreadItems'][1]['conflicting']);
            $t->same(1, $spineReport['itemDiagnosticCount']);
            $t->same('conflicting-spine-page-spread-properties', $spineReport['itemDiagnostics'][0]['type']);
            $t->same('appendix', $spineReport['itemDiagnostics'][0]['idref']);
            $t->same(1, $spineReport['diagnosticCount']);
            $t->same(['conflicting-spine-page-spread-properties'], array_column($spineReport['diagnostics'], 'type'));
            $t->same($spineReport['pageSpreadItems'], $epub['spinePageSpreadItems']);
            $t->same($spineReport['itemDiagnostics'], $epub['spineItemDiagnostics']);
        } finally {
            $removeDirectory($root);
        }
    },
    'reports direct package spine binding fallback summary' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-direct-spine-binding-fallbacks-' . str_replace('.', '', uniqid('', true));
        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:reader-spine-binding-fallback-summary</dc:identifier>
    <dc:title>Spine Binding Fallback Summary</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="widget" href="widgets/review-widget.bin" media-type="application/x-review-widget"/>
    <item id="widget-handler" href="text/widget-handler.xhtml" media-type="application/xhtml+xml"/>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="bad-widget" href="widgets/bad-widget.bin" media-type="application/x-bad-widget"/>
    <item id="style-handler" href="styles/handler.css" media-type="text/css"/>
  </manifest>
  <spine>
    <itemref id="bound-widget" idref="widget"/>
    <itemref idref="chapter"/>
    <itemref idref="bad-widget"/>
  </spine>
  <bindings>
    <mediaType media-type="application/x-review-widget" handler="widget-handler"/>
    <mediaType media-type="application/x-bad-widget" handler="style-handler"/>
  </bindings>
</package>
XML);
            $writePackageFile($root, 'EPUB/widgets/review-widget.bin', 'CUSTOM-WIDGET-BYTES');
            $writePackageFile($root, 'EPUB/widgets/bad-widget.bin', 'BAD-WIDGET-BYTES');
            $writePackageFile($root, 'EPUB/text/widget-handler.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable fallback.</p></body></html>');
            $writePackageFile($root, 'EPUB/text/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Direct chapter.</p></body></html>');
            $writePackageFile($root, 'EPUB/styles/handler.css', 'body { color: #111; }');

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $spineReport = $epub['spineReport'];
            $fallback = $spineReport['fallbackContentItems'][0];
            $diagnostic = $spineReport['fallbackDiagnostics'][0];

            $t->same(3, $spineReport['itemCount']);
            $t->same(2, $spineReport['readableItemCount']);
            $t->same(1, $spineReport['skippedItemCount']);
            $t->same(1, $spineReport['fallbackContentCount']);
            $t->same(1, $spineReport['bindingFallbackContentCount']);
            $t->same($fallback, $spineReport['bindingFallbackContentItems'][0]);
            $t->same(0, $fallback['index']);
            $t->same('widget', $fallback['idref']);
            $t->same('bound-widget', $fallback['spineItemId']);
            $t->same('EPUB/widgets/review-widget.bin', $fallback['spinePath']);
            $t->same('application/x-review-widget', $fallback['spineMediaType']);
            $t->same('widget-handler', $fallback['contentId']);
            $t->same('EPUB/text/widget-handler.xhtml', $fallback['contentPath']);
            $t->same('application/xhtml+xml', $fallback['contentMediaType']);
            $t->same('binding-handler', $fallback['source']);
            $t->same('application/x-review-widget', $fallback['bindingMediaType']);
            $t->same('binding-handler', $fallback['fallbackChain'][0]['source']);
            $t->same(1, $spineReport['fallbackDiagnosticCount']);
            $t->same(1, $spineReport['diagnosticCount']);
            $t->same($diagnostic, $spineReport['diagnostics'][0]);
            $t->same(2, $diagnostic['index']);
            $t->same('bad-widget', $diagnostic['idref']);
            $t->same('non-xhtml-binding-handler', $diagnostic['type']);
            $t->same('style-handler', $diagnostic['handlerId']);
            $t->same('text/css', $diagnostic['handlerMediaType']);
            $t->same(2, count($document->children));
        } finally {
            $removeDirectory($root);
        }
    },
    'reports direct package OCF sidecar files for package review' => static function (TestRunner $t) use ($writePackageFile, $removeDirectory): void {
        $root = sys_get_temp_dir() . '/port-libs-epub-reader-ocf-sidecars-' . str_replace('.', '', uniqid('', true));
        $chapterXhtml = '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Readable sidecar package.</p></body></html>';
        $ocfManifestXml = sprintf(<<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.0">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/epub+zip"/>
  <manifest:file-entry manifest:full-path="EPUB/chapter.xhtml" manifest:media-type="application/xhtml+xml" manifest:size="%d"/>
  <manifest:file-entry manifest:full-path="EPUB/missing.xhtml" manifest:media-type="application/xhtml+xml" manifest:size="9"/>
  <manifest:file-entry manifest:full-path="https://example.invalid/remote.bin" manifest:media-type="application/octet-stream"/>
  <manifest:file-entry manifest:media-type="text/plain"/>
</manifest:manifest>
XML, strlen($chapterXhtml));
        $rightsXml = '<rights xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><license>Review License</license></rights>';
        $signaturesXml = '<signaturez xmlns="urn:oasis:names:tc:opendocument:xmlns:container"/>';

        mkdir($root, 0777, true);
        try {
            $writePackageFile($root, 'META-INF/container.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
            $writePackageFile($root, 'META-INF/manifest.xml', $ocfManifestXml);
            $writePackageFile($root, 'META-INF/rights.xml', $rightsXml);
            $writePackageFile($root, 'META-INF/signatures.xml', $signaturesXml);
            $writePackageFile($root, 'EPUB/package.opf', <<<'XML'
<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="bookid">urn:uuid:reader-ocf-sidecars</dc:identifier>
    <dc:title>OCF Sidecar Package</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
            $writePackageFile($root, 'EPUB/nav.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><body><nav epub:type="toc"><ol><li><a href="chapter.xhtml">Chapter</a></li></ol></nav></body></html>');
            $writePackageFile($root, 'EPUB/chapter.xhtml', $chapterXhtml);

            $document = (new EpubPackageReader())->readDirectory($root);
            $epub = $document->attr('epub');
            $sidecars = $epub['ocfSidecars'];
            $manifest = $sidecars['itemsByKind']['manifest'];
            $rights = $sidecars['itemsByKind']['rights'];
            $signatures = $sidecars['itemsByKind']['signatures'];
            $manifestItemsByPart = $manifest['itemsByPart'];
            $chapter = $manifestItemsByPart['EPUB/chapter.xhtml'];
            $missing = $manifestItemsByPart['EPUB/missing.xhtml'];
            $external = $manifest['items'][3];
            $missingFullPath = $manifest['items'][4];

            $t->same($sidecars, $epub['ocfSidecars']);
            $t->same($sidecars['items'], $epub['ocfSidecarItems']);
            $t->same($sidecars['diagnostics'], $epub['ocfSidecarDiagnostics']);
            $t->same(true, $sidecars['present']);
            $t->same(3, $sidecars['sidecarCount']);
            $t->same(['manifest', 'rights', 'signatures'], $sidecars['kinds']);
            $t->same(false, $sidecars['metadataPresent']);
            $t->same(true, $sidecars['manifestPresent']);
            $t->same(true, $sidecars['rightsPresent']);
            $t->same(true, $sidecars['signaturesPresent']);
            $t->same(4, $sidecars['referenceCount']);
            $t->same(2, $sidecars['localReferenceCount']);
            $t->same(1, $sidecars['externalReferenceCount']);
            $t->same(1, $sidecars['missingReferenceCount']);
            $t->same(4, $sidecars['diagnosticCount']);
            $t->same([
                'ocf-manifest-missing-reference',
                'ocf-manifest-external-reference',
                'missing-ocf-manifest-full-path',
                'unexpected-ocf-sidecar-root',
            ], array_column($sidecars['diagnostics'], 'type'));

            $t->same('META-INF/manifest.xml', $manifest['partName']);
            $t->same('manifest', $manifest['rootName']);
            $t->same('urn:oasis:names:tc:opendocument:xmlns:manifest:1.0', $manifest['rootNamespace']);
            $t->same(true, $manifest['rootValid']);
            $t->same('odf-manifest', $manifest['format']);
            $t->same(true, $manifest['odfCompatible']);
            $t->same('1.0', $manifest['version']);
            $t->same(strlen($ocfManifestXml), $manifest['byteLength']);
            $t->same(hash('sha256', $ocfManifestXml), $manifest['byteSha256']);
            $t->same(5, $manifest['itemCount']);
            $t->same(4, $manifest['declaredPartCount']);
            $t->same(3, $manifest['missingItemCount']);
            $t->same(0, $manifest['sizeMismatchCount']);
            $t->same(4, $manifest['referenceCount']);
            $t->same(2, $manifest['localReferenceCount']);
            $t->same(1, $manifest['externalReferenceCount']);
            $t->same(1, $manifest['missingReferenceCount']);
            $t->same(3, count($manifest['diagnostics']));
            $t->same([
                'ocf-manifest-missing-reference',
                'ocf-manifest-external-reference',
                'missing-ocf-manifest-full-path',
            ], array_column($manifest['diagnostics'], 'type'));

            $t->same('EPUB/chapter.xhtml', $chapter['part']);
            $t->same(true, $chapter['exists']);
            $t->same(strlen($chapterXhtml), $chapter['byteLength']);
            $t->same(hash('sha256', $chapterXhtml), $chapter['byteSha256']);
            $t->same(true, $chapter['canExposeBytes']);
            $t->same([], $chapter['diagnostics']);
            $t->same(false, $missing['exists']);
            $t->same('ocf-manifest-missing-reference', $missing['diagnostics'][0]['type']);
            $t->same(true, $external['reference']['external']);
            $t->same('https://example.invalid/remote.bin', $external['target']);
            $t->same('ocf-manifest-external-reference', $external['diagnostics'][0]['type']);
            $t->same(null, $missingFullPath['fullPath']);
            $t->same('missing-ocf-manifest-full-path', $missingFullPath['diagnostics'][0]['type']);

            $t->same('rights', $rights['rootName']);
            $t->same(true, $rights['rootValid']);
            $t->same([], $rights['diagnostics']);
            $t->same(strlen($rightsXml), $rights['byteLength']);
            $t->same(hash('sha256', $rightsXml), $rights['byteSha256']);
            $t->same('signaturez', $signatures['rootName']);
            $t->same(false, $signatures['rootValid']);
            $t->same('unexpected-ocf-sidecar-root', $signatures['diagnostics'][0]['type']);
            $t->same('signatures', $signatures['diagnostics'][0]['expectedRootName']);
        } finally {
            $removeDirectory($root);
        }
    },
    'rejects missing epub package directories before parsing' => static function (TestRunner $t): void {
        $t->throws(\RuntimeException::class, static function (): void {
            (new EpubPackageReader())->readDirectory(dirname(__DIR__) . '/fixtures/missing-epub-package');
        });
    },
];
