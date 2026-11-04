<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\EpubReader;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

return [
    'reads epub metadata and xhtml spine content into shared ast' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', <<<'XML'
<?xml version="1.0"?>
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="OEBPS/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
        $zip->addFromString('OEBPS/package.opf', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-epub-reader</dc:identifier>
    <dc:title>EPUB Reader Demo</dc:title>
    <dc:creator>Port Libs</dc:creator>
    <dc:language>en</dc:language>
    <dc:description>Bounded EPUB reader smoke.</dc:description>
    <meta property="schema:accessMode">textual</meta>
    <meta property="schema:accessibilityFeature">alternativeText</meta>
    <meta property="schema:accessibilityHazard" content="none"/>
    <meta property="schema:accessModeSufficient">textual visual</meta>
    <meta property="schema:accessibilitySummary">Images include alternate text.</meta>
    <meta property="dcterms:conformsTo">EPUB Accessibility 1.1 - WCAG 2.2 Level AA</meta>
    <link id="review-record" rel="record accessibility-summary" href="meta/review.json?profile=a11y#summary" media-type="application/ld+json" properties="accessibility-metadata" title="Accessibility review" hreflang="en"/>
    <link id="remote-onix" rel="alternate" href="https://metadata.example.invalid/onix.xml" media-type="application/xml"/>
  </metadata>
  <manifest>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="cover" href="images/cover.png" media-type="image/png"/>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="review-json" href="meta/review.json" media-type="application/ld+json" properties="metadata"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
  <guide>
    <reference type="text bodymatter" title="Start reading" href="text/chapter.xhtml#main"/>
    <reference type="cover" title="Cover image" href="images/cover.png"/>
    <reference type="glossary" title="Remote glossary" href="https://example.invalid/glossary.xhtml"/>
  </guide>
</package>
XML);
        $zip->addFromString('OEBPS/text/chapter.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body>
    <h1>EPUB Reader Demo</h1>
    <p>A <strong>chapter</strong> with <a href="../images/cover.png">a relative link</a>.</p>
    <p><img src="../images/cover.png" alt="Cover art"/></p>
    <ul><li>One</li><li>Two</li></ul>
  </body>
</html>
HTML);
        $zip->addFromString('OEBPS/nav.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="text/chapter.xhtml">Start</a>
          <ol>
            <li><a href="text/chapter.xhtml#nested">Nested</a></li>
          </ol>
        </li>
      </ol>
    </nav>
    <nav epub:type="landmarks">
      <ol>
        <li><a type="application/xhtml+xml" epub:type="bodymatter" href="text/chapter.xhtml#main">Begin reading</a></li>
        <li><a epub:type="review" href="https://example.invalid/epub/source-record.html">Remote review</a></li>
      </ol>
    </nav>
  </body>
</html>
HTML);
        $zip->addFromString('OEBPS/images/cover.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
        $zip->addFromString('OEBPS/meta/review.json', '{"accessibility":"reviewed"}');
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $blocks = (new WordPressBlockWriter())->write($document);
            $converterBlocks = PandocConverter::convertFile($path, 'epub', 'blocks');
            $meta = $document->attr('meta');
        } finally {
            @unlink($path);
        }

        $t->same('EPUB Reader Demo', $meta['title']);
        $t->same('Port Libs', $meta['author']);
        $t->same('en', $meta['lang']);
        $t->same('OEBPS/package.opf', $meta['epubRootfile']);
        $t->same(2, $meta['epubPackageLinkCount']);
        $t->same(['record' => 1, 'accessibility-summary' => 1, 'alternate' => 1], $meta['epubPackageLinkRelCounts']);
        $t->same(['OEBPS/meta/review.json?profile=a11y#summary', 'https://metadata.example.invalid/onix.xml'], $meta['epubPackageLinkTargets']);
        $t->same([
            [
                'index' => 0,
                'id' => 'review-record',
                'rel' => ['record', 'accessibility-summary'],
                'href' => 'meta/review.json?profile=a11y#summary',
                'target' => 'OEBPS/meta/review.json?profile=a11y#summary',
                'path' => 'OEBPS/meta/review.json',
                'external' => false,
                'mediaType' => 'application/ld+json',
                'properties' => ['accessibility-metadata'],
                'title' => 'Accessibility review',
                'hreflang' => 'en',
                'refines' => null,
                'subjectId' => null,
                'hrefHasQuery' => true,
                'hrefQuery' => 'profile=a11y',
                'hrefHasFragment' => true,
                'hrefFragment' => 'summary',
                'manifestId' => 'review-json',
                'manifestMediaType' => 'application/ld+json',
                'manifestProperties' => ['metadata'],
            ],
            [
                'index' => 1,
                'id' => 'remote-onix',
                'rel' => ['alternate'],
                'href' => 'https://metadata.example.invalid/onix.xml',
                'target' => 'https://metadata.example.invalid/onix.xml',
                'path' => '',
                'external' => true,
                'mediaType' => 'application/xml',
                'properties' => [],
                'title' => null,
                'hreflang' => null,
                'refines' => null,
                'subjectId' => null,
                'hrefHasQuery' => false,
                'hrefQuery' => null,
                'hrefHasFragment' => false,
                'hrefFragment' => null,
                'manifestId' => null,
                'manifestMediaType' => null,
                'manifestProperties' => [],
            ],
        ], $meta['epubPackageLinks']);
        $t->same(true, $meta['epubAccessibilityPresent']);
        $t->same(6, $meta['epubAccessibilityEntryCount']);
        $t->same(1, $meta['epubAccessibilityLinkedRecordCount']);
        $t->same([
            'accessMode' => 1,
            'accessibilityFeature' => 1,
            'accessibilityHazard' => 1,
            'accessModeSufficient' => 1,
            'accessibilitySummary' => 1,
            'conformsTo' => 1,
        ], $meta['epubAccessibilityPropertyCounts']);
        $t->same(['textual'], $meta['epubAccessibility']['accessModes']);
        $t->same(['alternativeText'], $meta['epubAccessibility']['accessibilityFeatures']);
        $t->same(['none'], $meta['epubAccessibility']['accessibilityHazards']);
        $t->same('Images include alternate text.', $meta['epubAccessibility']['accessibilitySummary']);
        $t->same(['EPUB Accessibility 1.1 - WCAG 2.2 Level AA'], $meta['epubAccessibility']['certification']['conformsTo']);
        $t->same([
            [
                'text' => 'textual visual',
                'modes' => ['textual', 'visual'],
                'source' => 'property',
                'id' => null,
            ],
        ], $meta['epubAccessibility']['accessModeSufficient']);
        $t->same([
            [
                'id' => 'review-record',
                'rel' => ['record', 'accessibility-summary'],
                'href' => 'meta/review.json?profile=a11y#summary',
                'target' => 'OEBPS/meta/review.json?profile=a11y#summary',
                'path' => 'OEBPS/meta/review.json',
                'external' => false,
                'mediaType' => 'application/ld+json',
                'properties' => ['accessibility-metadata'],
                'manifestId' => 'review-json',
                'manifestMediaType' => 'application/ld+json',
            ],
        ], $meta['epubAccessibility']['linkedRecords']);
        $t->same(1, $meta['epubSpineItems']);
        $t->same(3, $meta['epubGuideReferenceCount']);
        $t->same(['text', 'bodymatter', 'cover', 'glossary'], $meta['epubGuideReferenceTypes']);
        $t->same(['text' => 1, 'bodymatter' => 1, 'cover' => 1, 'glossary' => 1], $meta['epubGuideReferenceTypeCounts']);
        $t->same([
            [
                'index' => 0,
                'type' => 'text',
                'typeRaw' => 'text bodymatter',
                'types' => ['text', 'bodymatter'],
                'title' => 'Start reading',
                'href' => 'text/chapter.xhtml#main',
                'target' => 'OEBPS/text/chapter.xhtml#main',
                'path' => 'OEBPS/text/chapter.xhtml',
                'fragment' => 'main',
                'hrefHasQuery' => false,
                'hrefQuery' => null,
                'hrefHasFragment' => true,
                'hrefFragment' => 'main',
                'external' => false,
                'manifestId' => 'chapter',
                'manifestMediaType' => 'application/xhtml+xml',
                'manifestProperties' => [],
            ],
            [
                'index' => 1,
                'type' => 'cover',
                'typeRaw' => 'cover',
                'types' => ['cover'],
                'title' => 'Cover image',
                'href' => 'images/cover.png',
                'target' => 'OEBPS/images/cover.png',
                'path' => 'OEBPS/images/cover.png',
                'fragment' => null,
                'hrefHasQuery' => false,
                'hrefQuery' => null,
                'hrefHasFragment' => false,
                'hrefFragment' => null,
                'external' => false,
                'manifestId' => 'cover',
                'manifestMediaType' => 'image/png',
                'manifestProperties' => [],
            ],
            [
                'index' => 2,
                'type' => 'glossary',
                'typeRaw' => 'glossary',
                'types' => ['glossary'],
                'title' => 'Remote glossary',
                'href' => 'https://example.invalid/glossary.xhtml',
                'target' => 'https://example.invalid/glossary.xhtml',
                'path' => '',
                'fragment' => null,
                'hrefHasQuery' => false,
                'hrefQuery' => null,
                'hrefHasFragment' => false,
                'hrefFragment' => null,
                'external' => true,
                'manifestId' => null,
                'manifestMediaType' => null,
                'manifestProperties' => [],
            ],
        ], $meta['epubGuideReferences']);
        $t->same(['OEBPS/text/chapter.xhtml'], $meta['epubReadableResources']);
        $t->same(['OEBPS/images/cover.png'], $meta['epubReferencedResources']);
        $t->same(['OEBPS/images/cover.png'], $meta['epubImageResources']);
        $t->same(['OEBPS/nav.xhtml'], $meta['epubTocResources']);
        $t->same(2, $meta['epubTocEntryCount']);
        $t->same([
            ['text' => 'Start', 'href' => 'OEBPS/text/chapter.xhtml', 'level' => 1],
            ['text' => 'Nested', 'href' => 'OEBPS/text/chapter.xhtml#nested', 'level' => 2],
        ], $meta['epubTocEntries']);
        $t->same(2, $meta['epubLandmarkEntryCount']);
        $t->same([
            ['text' => 'Begin reading', 'href' => 'OEBPS/text/chapter.xhtml#main', 'level' => 1, 'epubTypes' => ['bodymatter']],
            ['text' => 'Remote review', 'href' => 'https://example.invalid/epub/source-record.html', 'level' => 1, 'epubTypes' => ['review']],
        ], $meta['epubLandmarkEntries']);
        $t->contains('<!-- wp:heading {"level":1} -->', $blocks);
        $t->contains('<strong>chapter</strong>', $blocks);
        $t->contains('href="../images/cover.png"', $blocks);
        $t->contains('<!-- wp:image -->', $blocks);
        $t->contains('src="images/cover.png"', $blocks);
        $t->contains('alt="Cover art"', $blocks);
        $t->contains('<!-- wp:list -->', $converterBlocks);
    },
    'rejects epub package entity declarations while stripping inert processing instructions' => static function (TestRunner $t): void {
        $makePackage = static function (string $containerXml): string {
            $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-xml-policy-');
            if ($path === false) {
                throw new RuntimeException('Unable to create temporary EPUB path');
            }

            $zip = new ZipArchive();
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                @unlink($path);
                throw new RuntimeException('Unable to create temporary EPUB package');
            }
            $zip->addFromString('META-INF/container.xml', $containerXml);
            $zip->addFromString('OPS/package.opf', '<?xml version="1.0"?><package xmlns="http://www.idpf.org/2007/opf"><metadata/><manifest/><spine/></package>');
            $zip->close();

            return $path;
        };

        $entityPackage = $makePackage(<<<'XML'
<?xml version="1.0"?>
<!DOCTYPE container [
  <!ENTITY reviewer SYSTEM "file:///etc/passwd">
]>
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles>
</container>
XML);
        $processingInstructionPackage = $makePackage(<<<'XML'
<?xml version="1.0"?>
<?xml-stylesheet href="https://example.invalid/review.xsl"?>
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles>
</container>
XML);

        try {
            $t->throws(InvalidArgumentException::class, static fn (): AstNode => (new EpubReader())->readEpubFile($entityPackage));
            $document = (new EpubReader())->readEpubFile($processingInstructionPackage);
            $t->same('OPS/package.opf', $document->attr('meta')['epubRootfile'] ?? null);
        } finally {
            foreach ([$entityPackage, $processingInstructionPackage] as $path) {
                @unlink($path);
            }
        }
    },
    'maps epub opf core metadata with accumulated identifiers and modified timestamp' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="pub-id">
  <metadata>
    <dc:identifier id="isbn">urn:isbn:9780000000000</dc:identifier>
    <dc:identifier id="pub-id">urn:uuid:12345678-1234-1234-1234-123456789abc</dc:identifier>
    <dc:title>EPUB Metadata Reader</dc:title>
    <dc:creator>First Author</dc:creator>
    <dc:creator>Second Author</dc:creator>
    <dc:language>en-US</dc:language>
    <dc:date>2026-04-05</dc:date>
    <dc:publisher>Example Press</dc:publisher>
    <dc:subject>migration</dc:subject>
    <dc:subject>package metadata</dc:subject>
    <dc:description>Reader metadata parity.</dc:description>
    <dc:rights>Example rights statement.</dc:rights>
    <dc:source>source-record</dc:source>
    <dc:relation>related-record</dc:relation>
    <dc:coverage>global</dc:coverage>
    <dc:type>Text</dc:type>
    <dc:format>application/epub+zip</dc:format>
    <meta property="dcterms:modified">2026-04-06T07:08:09Z</meta>
    <meta property="rendition:layout">pre-paginated</meta>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>EPUB Metadata Reader</h1><p>Body.</p></body></html>');
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
            $native = (new NativeWriter())->write($document);
        } finally {
            @unlink($path);
        }

        $t->same('EPUB Metadata Reader', $meta['title']);
        $t->same('EPUB Metadata Reader', $meta['titleInlines'][0]->attr('text'));
        $t->same(['Second Author', 'First Author'], $meta['author']);
        $t->same('Second Author', $meta['authorInlines'][0][0]->attr('text'));
        $t->same('First Author', $meta['authorInlines'][1][0]->attr('text'));
        $t->contains(
            '( "author" , MetaList [ MetaInlines [ Str "Second" , Space , Str "Author" ] , MetaInlines [ Str "First" , Space , Str "Author" ] ] )',
            $native
        );
        $t->same('en-US', $meta['lang']);
        $t->same('en-US', $meta['language']);
        $t->same('2026-04-05', $meta['date']);
        $t->same('2026-04-05', $meta['dateInlines'][0]->attr('text'));
        $identifier = $meta['identifier'] ?? null;
        $t->same('MetaList', $identifier['type'] ?? null);
        $t->same('urn:uuid:12345678-1234-1234-1234-123456789abc', $identifier['value'][0]['value'][0]->attr('text') ?? null);
        $t->same('urn:isbn:9780000000000', $identifier['value'][1]['value'][0]->attr('text') ?? null);
        $t->same('urn:uuid:12345678-1234-1234-1234-123456789abc', $meta['epubSelectedIdentifier']);
        $t->same('Example Press', $meta['publisher']);
        $subject = $meta['subject'] ?? null;
        $t->same('MetaList', $subject['type'] ?? null);
        $t->same('MetaInlines', $subject['value'][0]['type'] ?? null);
        $t->same('package metadata', $subject['value'][0]['value'][0]->attr('text') ?? null);
        $t->same('MetaInlines', $subject['value'][1]['type'] ?? null);
        $t->same('migration', $subject['value'][1]['value'][0]->attr('text') ?? null);
        $t->same('Reader metadata parity.', $meta['description']);
        $t->same('Example rights statement.', $meta['rights']);
        $t->same('source-record', $meta['source']);
        $t->same('related-record', $meta['relation']);
        $t->same('global', $meta['coverage']);
        $t->same('Text', $meta['type']);
        $t->same('application/epub+zip', $meta['format']);
        $t->same('2026-04-06T07:08:09Z', $meta['modified']);
        $t->same(['2026-04-06T07:08:09Z'], $meta['epubProperties']['dcterms:modified']);
        $t->same(['pre-paginated'], $meta['epubProperties']['rendition:layout']);
    },
    'accumulates repeated upstream dublin core title date language and identifiers' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-dc-accumulation-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="pub-id">
  <metadata>
    <dc:identifier id="isbn">urn:isbn:9780000000002</dc:identifier>
    <dc:identifier id="pub-id">urn:uuid:dc-accumulation</dc:identifier>
    <dc:title>Main Accumulated Title</dc:title>
    <dc:title>Alternate Accumulated Title</dc:title>
    <dc:creator>Accumulation Author</dc:creator>
    <dc:language>en-US</dc:language>
    <dc:language>fr-CA</dc:language>
    <dc:date>2026-07-01</dc:date>
    <dc:date>2026-07-02</dc:date>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Body.</p></body></html>');
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
            $native = (new NativeWriter())->write($document);
        } finally {
            @unlink($path);
        }

        $title = $meta['title'] ?? null;
        $date = $meta['date'] ?? null;
        $language = $meta['language'] ?? null;
        $identifier = $meta['identifier'] ?? null;

        $t->same('MetaList', $title['type'] ?? null);
        $t->same('Alternate Accumulated Title', $title['value'][0]['value'][0]->attr('text') ?? null);
        $t->same('Main Accumulated Title', $title['value'][1]['value'][0]->attr('text') ?? null);
        $t->same('Main Accumulated Title', $meta['titleInlines'][0]->attr('text'));
        $t->same('MetaList', $date['type'] ?? null);
        $t->same('2026-07-02', $date['value'][0]['value'][0]->attr('text') ?? null);
        $t->same('2026-07-01', $date['value'][1]['value'][0]->attr('text') ?? null);
        $t->same('2026-07-01', $meta['dateInlines'][0]->attr('text'));
        $t->same('en-US', $meta['lang']);
        $t->same(['en-US', 'fr-CA'], $meta['languages']);
        $t->same('MetaList', $language['type'] ?? null);
        $t->same('fr-CA', $language['value'][0]['value'][0]->attr('text') ?? null);
        $t->same('en-US', $language['value'][1]['value'][0]->attr('text') ?? null);
        $t->same('MetaList', $identifier['type'] ?? null);
        $t->same('urn:uuid:dc-accumulation', $identifier['value'][0]['value'][0]->attr('text') ?? null);
        $t->same('urn:isbn:9780000000002', $identifier['value'][1]['value'][0]->attr('text') ?? null);
        $t->same('urn:uuid:dc-accumulation', $meta['epubSelectedIdentifier']);
        $t->contains(
            '( "language" , MetaList [ MetaInlines [ Str "fr-CA" ] , MetaInlines [ Str "en-US" ] ] )',
            $native
        );
        $t->contains(
            '( "identifier" , MetaList [ MetaInlines [ Str "urn:uuid:dc-accumulation" ] , MetaInlines [ Str "urn:isbn:9780000000002" ] ] )',
            $native
        );
    },
    'retains upstream dublin core contributor metadata as pandoc meta values' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-contributor-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">urn:uuid:contributor-gap</dc:identifier>
    <dc:title>Contributor Gap</dc:title>
    <dc:creator>Primary Author</dc:creator>
    <dc:contributor>Review Editor</dc:contributor>
    <dc:contributor>Illustration Desk</dc:contributor>
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
        $zip->addFromString('OPS/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Contributor Gap</h1><p>Body.</p></body></html>');
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
            $native = (new NativeWriter())->write($document);
        } finally {
            @unlink($path);
        }

        $contributor = $meta['contributor'] ?? null;
        $t->same('Contributor Gap', $meta['title']);
        $t->same('MetaList', $contributor['type'] ?? null);
        $t->same('MetaInlines', $contributor['value'][0]['type'] ?? null);
        $t->same('Illustration Desk', $contributor['value'][0]['value'][0]->attr('text') ?? null);
        $t->same('MetaInlines', $contributor['value'][1]['type'] ?? null);
        $t->same('Review Editor', $contributor['value'][1]['value'][0]->attr('text') ?? null);
        $t->contains(
            '( "contributor" , MetaList [ MetaInlines [ Str "Illustration" , Space , Str "Desk" ] , MetaInlines [ Str "Review" , Space , Str "Editor" ] ] )',
            $native
        );
    },
    'retains repeated upstream dublin core metadata fields as pandoc meta lists' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-repeated-dc-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0">
  <metadata>
    <dc:title>Repeated DC Metadata</dc:title>
    <dc:publisher>First Publisher</dc:publisher>
    <dc:publisher>Second Publisher</dc:publisher>
    <dc:subject>First Subject</dc:subject>
    <dc:subject>Second Subject</dc:subject>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Body.</p></body></html>');
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
            $native = (new NativeWriter())->write($document);
        } finally {
            @unlink($path);
        }

        $publisher = $meta['publisher'] ?? null;
        $subject = $meta['subject'] ?? null;
        $t->same('MetaList', $publisher['type'] ?? null);
        $t->same('Second Publisher', $publisher['value'][0]['value'][0]->attr('text') ?? null);
        $t->same('First Publisher', $publisher['value'][1]['value'][0]->attr('text') ?? null);
        $t->same('MetaList', $subject['type'] ?? null);
        $t->same('Second Subject', $subject['value'][0]['value'][0]->attr('text') ?? null);
        $t->same('First Subject', $subject['value'][1]['value'][0]->attr('text') ?? null);
        $t->contains(
            '( "publisher" , MetaList [ MetaInlines [ Str "Second" , Space , Str "Publisher" ] , MetaInlines [ Str "First" , Space , Str "Publisher" ] ] )',
            $native
        );
        $t->contains(
            '( "subject" , MetaList [ MetaInlines [ Str "Second" , Space , Str "Subject" ] , MetaInlines [ Str "First" , Space , Str "Subject" ] ] )',
            $native
        );
    },
    'ignores epub metadata outside upstream dc prefixed dublin core elements' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-dc-prefix-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         xmlns:dcx="http://purl.org/dc/elements/1.1/"
         version="3.0">
  <metadata>
    <dc:title>Recognized DC Title</dc:title>
    <dcx:creator>Alt Prefix Author</dcx:creator>
    <description>Bare Description</description>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Body.</p></body></html>');
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
            $native = (new NativeWriter())->write($document);
        } finally {
            @unlink($path);
        }

        $t->same('Recognized DC Title', $meta['title']);
        $t->same(false, array_key_exists('author', $meta));
        $t->same(false, array_key_exists('description', $meta));
        $t->contains('( "title" , MetaInlines [ Str "Recognized" , Space , Str "DC" , Space , Str "Title" ] )', $native);
        $t->same(false, str_contains($native, 'Alt Prefix Author'));
        $t->same(false, str_contains($native, 'Bare Description'));
    },
    'surfaces OPF metadata link vocabulary through epub reader metadata' => static function (TestRunner $t): void {
        $searchFixture = __DIR__ . '/../fixtures/upstream-current-epub-reader/epub/metadata-search-link-semantics.epub';
        $searchMeta = (new EpubReader())->readEpubFile($searchFixture)->attr('meta');
        $searchVocabulary = $searchMeta['epubPackageLinkVocabulary'];
        $searchLink = $searchMeta['epubPackageLinks'][0];

        $t->same(true, $searchVocabulary['present']);
        $t->same(1, $searchVocabulary['linkCount']);
        $t->same(2, $searchVocabulary['relTokenCount']);
        $t->same(0, $searchVocabulary['propertyTokenCount']);
        $t->same(['record' => 1, 'search' => 1], $searchVocabulary['rels']);
        $t->same([], $searchVocabulary['properties']);
        $t->same(0, $searchVocabulary['diagnosticCount']);
        $t->same(['record', 'search'], $searchLink['relVocabulary']['raw']);
        $t->same(2, $searchLink['relVocabulary']['validCount']);
        $t->same('nmtoken', $searchLink['relVocabulary']['items'][1]['kind']);
        $t->same('search', $searchLink['relVocabulary']['items'][1]['value']);
        $t->same([], $searchLink['propertyVocabulary']['items']);

        $accessibilityFixture = __DIR__ . '/../fixtures/upstream-current-epub-reader/epub/accessibility-metadata-package.epub';
        $accessibilityMeta = (new EpubReader())->readEpubFile($accessibilityFixture)->attr('meta');
        $accessibilityVocabulary = $accessibilityMeta['epubPackageLinkVocabulary'];
        $accessibilityLink = $accessibilityMeta['epubPackageLinks'][0];

        $t->same(true, $accessibilityVocabulary['present']);
        $t->same(2, $accessibilityVocabulary['relTokenCount']);
        $t->same(1, $accessibilityVocabulary['propertyTokenCount']);
        $t->same(['accessibility-summary' => 1, 'record' => 1], $accessibilityVocabulary['rels']);
        $t->same(['accessibility-metadata' => 1], $accessibilityVocabulary['properties']);
        $t->same([], $accessibilityMeta['epubPackageLinkVocabularyDiagnostics']);
        $t->same(['record', 'accessibility-summary'], $accessibilityLink['relVocabulary']['raw']);
        $t->same(['accessibility-metadata'], $accessibilityLink['propertyVocabulary']['raw']);
        $t->same(1, $accessibilityLink['propertyVocabulary']['validCount']);
        $t->same('accessibility-metadata', $accessibilityLink['propertyVocabulary']['items'][0]['value']);
    },
    'uses epub2 meta cover content as upstream cover path fallback' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-meta-cover-path-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0">
  <metadata>
    <dc:title>Meta Cover Path</dc:title>
    <meta name="cover" content="images/cover.png"/>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="art" href="images/cover.png" media-type="image/png"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Body.</p></body></html>');
        $coverBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
        $zip->addFromString('OPS/images/cover.png', $coverBytes);
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
            $native = PandocConverter::write($document, 'native');
        } finally {
            @unlink($path);
        }

        $t->same('Meta Cover Path', $meta['title']);
        $t->same('paragraph', $document->children[0]->type);
        $t->same('image', $document->children[0]->children[0]->type);
        $t->same('images/cover.png', $document->children[0]->children[0]->attr('url'));
        $t->same(['OPS/images/cover.png'], $meta['epubMediaBagResources']);
        $t->same(1, $meta['epubMediaResourceCount']);
        $t->same(['epub-media-resource-loaded:OPS/images/cover.png'], $meta['epubMediaResourceDiagnostics']);
        $t->same([
            [
                'path' => 'images/cover.png',
                'zipEntry' => 'OPS/images/cover.png',
                'mimeType' => 'image/png',
                'byteLength' => strlen($coverBytes),
                'sha1' => sha1($coverBytes),
            ],
        ], array_map(static fn (array $entry): array => [
            'path' => (string) $entry['path'],
            'zipEntry' => (string) $entry['zipEntry'],
            'mimeType' => (string) $entry['mimeType'],
            'byteLength' => (int) $entry['byteLength'],
            'sha1' => (string) $entry['sha1'],
        ], $meta['epubMediaResourceDirectory']));
        $t->contains('( "images/cover.png" , "" )', $native);
    },
    'reads epub bytes through the converter input path' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('package.opf', '<?xml version="1.0"?><package xmlns="http://www.idpf.org/2007/opf" xmlns:dc="http://purl.org/dc/elements/1.1/"><metadata><dc:title>Byte EPUB</dc:title></metadata><manifest><item id="c1" href="chapter.xhtml" media-type="application/xhtml+xml"/></manifest><spine><itemref idref="c1"/></spine></package>');
        $zip->addFromString('chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Byte EPUB</h1><p>Body.</p></body></html>');
        $zip->close();

        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new RuntimeException('Unable to read temporary EPUB package');
            }
            $document = PandocConverter::read($bytes, 'epub');
        } finally {
            @unlink($path);
        }

        $t->same('Byte EPUB', $document->attr('meta')['title']);
        $t->same('paragraph', $document->children[0]->type);
        $t->same('span', $document->children[0]->children[0]->type);
        $t->same('chapter.xhtml', $document->children[0]->children[0]->attr('id'));
        $t->same('heading', $document->children[1]->type);
        $t->same('Byte EPUB', $document->children[1]->attr('text'));
    },
    'preserves epub3 navigation landmarks as reader package provenance' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('EPUB/package.opf', <<<'XML'
<?xml version="1.0"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-landmarks</dc:identifier>
    <dc:title>Landmark EPUB</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
        $zip->addFromString('EPUB/text/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1 id="main">Landmark EPUB</h1><p>Body.</p></body></html>');
        $zip->addFromString('EPUB/nav.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol><li><a href="text/chapter.xhtml">Chapter</a></li></ol>
    </nav>
    <nav epub:type="landmarks">
      <ol>
        <li><a type="application/xhtml+xml" epub:type="bodymatter" href="text/chapter.xhtml#main">Begin reading</a></li>
        <li epub:type="cover"><span>Cover image</span></li>
        <li><a epub:type="review" href="https://example.invalid/epub/source-record.html">Remote review</a></li>
      </ol>
    </nav>
  </body>
</html>
HTML);
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
        } finally {
            @unlink($path);
        }

        $t->same('Landmark EPUB', $meta['title']);
        $t->same(['EPUB/nav.xhtml'], $meta['epubTocResources']);
        $t->same(1, $meta['epubTocEntryCount']);
        $t->same(3, $meta['epubLandmarkEntryCount']);
        $t->same([
            ['text' => 'Begin reading', 'href' => 'EPUB/text/chapter.xhtml#main', 'level' => 1, 'epubTypes' => ['bodymatter']],
            ['text' => 'Cover image', 'href' => '', 'level' => 1, 'epubTypes' => ['cover']],
            ['text' => 'Remote review', 'href' => 'https://example.invalid/epub/source-record.html', 'level' => 1, 'epubTypes' => ['review']],
        ], $meta['epubLandmarkEntries']);
        $t->same([], $meta['epubReferencedResources']);
    },
    'surfaces upstream epub page-list and auxiliary navigation sections as reader metadata' => static function (TestRunner $t): void {
        $fixtureDir = __DIR__ . '/../fixtures/upstream-current-epub-reader/epub/';
        $pageListMeta = (new EpubReader())->readEpubFile($fixtureDir . 'page-list-navigation.epub')->attr('meta');

        $t->same(['loi', 'page-list', 'toc'], $pageListMeta['epubNavigationSectionTypes']);
        $t->same(3, $pageListMeta['epubNavigationSectionCount']);
        $t->same(1, $pageListMeta['epubPageListEntryCount']);
        $t->same([
            ['text' => '1', 'href' => 'EPUB/chapter.xhtml#page-1', 'level' => 1],
        ], $pageListMeta['epubPageListEntries']);
        $t->same(1, $pageListMeta['epubAuxiliaryNavigationEntryCount']);
        $t->same([
            ['text' => 'Figure 1', 'href' => 'EPUB/chapter.xhtml', 'level' => 1, 'sectionType' => 'loi'],
        ], $pageListMeta['epubAuxiliaryNavigationEntries']);

        $cases = [
            'audio-navigation' => [
                'types' => ['loa', 'toc'],
                'entry' => ['text' => 'Sample Audio', 'href' => 'EPUB/audio/sample.mp3', 'level' => 1, 'sectionType' => 'loa'],
            ],
            'video-navigation' => [
                'types' => ['lov', 'toc'],
                'entry' => ['text' => 'Sample Video', 'href' => 'EPUB/video/sample.mp4', 'level' => 1, 'sectionType' => 'lov'],
            ],
            'auxiliary-lot-guide-index' => [
                'types' => ['lot', 'toc'],
                'entry' => ['text' => 'Index Table', 'href' => 'EPUB/chapter.xhtml#index', 'level' => 1, 'sectionType' => 'lot'],
            ],
        ];

        foreach ($cases as $fixture => $expected) {
            $meta = (new EpubReader())->readEpubFile($fixtureDir . $fixture . '.epub')->attr('meta');

            $t->same($expected['types'], $meta['epubNavigationSectionTypes']);
            $t->same(2, $meta['epubNavigationSectionCount']);
            $t->same(0, $meta['epubPageListEntryCount']);
            $t->same(1, $meta['epubAuxiliaryNavigationEntryCount']);
            $t->same([$expected['entry']], $meta['epubAuxiliaryNavigationEntries']);
        }
    },
    'uses direct epub nav item labels before nested descendant anchors' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-direct-nav-label-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-direct-nav-label</dc:identifier>
    <dc:title>Direct Nav Label EPUB</dc:title>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/nav.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <h1>Contents</h1>
      <ol>
        <li><span>Part I</span>
          <ol>
            <li><a href="chapter.xhtml">Chapter One</a></li>
          </ol>
        </li>
      </ol>
    </nav>
  </body>
</html>
HTML);
        $zip->addFromString('OPS/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Chapter One</h1><p>Body.</p></body></html>');
        $zip->close();

        try {
            $meta = (new EpubReader())->readEpubFile($path)->attr('meta');
        } finally {
            @unlink($path);
        }

        $entries = [
            ['text' => 'Part I', 'href' => '', 'level' => 1],
            ['text' => 'Chapter One', 'href' => 'OPS/chapter.xhtml', 'level' => 2],
        ];

        $t->same('Direct Nav Label EPUB', $meta['title']);
        $t->same(2, $meta['epubTocEntryCount']);
        $t->same($entries, $meta['epubTocEntries']);
        $t->same(1, $meta['epubNavigationSectionCount']);
        $t->same([
            [
                'type' => 'toc',
                'types' => ['toc'],
                'label' => 'Contents',
                'resource' => 'OPS/nav.xhtml',
                'entryCount' => 2,
                'entries' => $entries,
            ],
        ], $meta['epubNavigationSections']);
    },
    'preserves epub manifest and spine metadata without fetching remote targets' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-manifest-spine</dc:identifier>
    <dc:title>Manifest Spine EPUB</dc:title>
  </metadata>
  <manifest>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml" properties="scripted mathml"/>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="cover" href="images/cover.svg" media-type="image/svg+xml" properties="cover-image svg"/>
    <item id="remote" href="https://example.invalid/remote.xhtml" media-type="application/xhtml+xml" properties="remote-resources"/>
  </manifest>
  <spine>
    <itemref id="spine-chapter" idref="chapter" properties="page-spread-right"/>
    <itemref id="spine-remote" idref="remote" linear="no"/>
    <itemref idref="missing"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/text/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Manifest Spine EPUB</h1><p>Local body.</p></body></html>');
        $zip->addFromString('OPS/nav.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><body><nav epub:type="toc"><ol><li><a href="text/chapter.xhtml">Chapter</a></li></ol></nav></body></html>');
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
        } finally {
            @unlink($path);
        }

        $t->same('Manifest Spine EPUB', $meta['title']);
        $t->same(4, $meta['epubManifestItemCount']);
        $t->same([
            [
                'id' => 'chapter',
                'href' => 'text/chapter.xhtml',
                'path' => 'OPS/text/chapter.xhtml',
                'mediaType' => 'application/xhtml+xml',
                'properties' => ['scripted', 'mathml'],
                'external' => false,
                'readable' => true,
                'navigation' => false,
                'ncx' => false,
                'coverImage' => false,
            ],
            [
                'id' => 'nav',
                'href' => 'nav.xhtml',
                'path' => 'OPS/nav.xhtml',
                'mediaType' => 'application/xhtml+xml',
                'properties' => ['nav'],
                'external' => false,
                'readable' => true,
                'navigation' => true,
                'ncx' => false,
                'coverImage' => false,
            ],
            [
                'id' => 'cover',
                'href' => 'images/cover.svg',
                'path' => 'OPS/images/cover.svg',
                'mediaType' => 'image/svg+xml',
                'properties' => ['cover-image', 'svg'],
                'external' => false,
                'readable' => false,
                'navigation' => false,
                'ncx' => false,
                'coverImage' => true,
            ],
            [
                'id' => 'remote',
                'href' => 'https://example.invalid/remote.xhtml',
                'path' => 'https://example.invalid/remote.xhtml',
                'mediaType' => 'application/xhtml+xml',
                'properties' => ['remote-resources'],
                'external' => true,
                'readable' => false,
                'navigation' => false,
                'ncx' => false,
                'coverImage' => false,
            ],
        ], $meta['epubManifestItems']);
        $t->same(3, $meta['epubSpineItems']);
        $t->same([
            [
                'index' => 0,
                'id' => 'spine-chapter',
                'idref' => 'chapter',
                'href' => 'text/chapter.xhtml',
                'path' => 'OPS/text/chapter.xhtml',
                'mediaType' => 'application/xhtml+xml',
                'linear' => true,
                'properties' => ['page-spread-right'],
                'manifestProperties' => ['scripted', 'mathml'],
                'missingManifestItem' => false,
                'external' => false,
                'readable' => true,
            ],
            [
                'index' => 1,
                'id' => 'spine-remote',
                'idref' => 'remote',
                'href' => 'https://example.invalid/remote.xhtml',
                'path' => 'https://example.invalid/remote.xhtml',
                'mediaType' => 'application/xhtml+xml',
                'linear' => false,
                'properties' => [],
                'manifestProperties' => ['remote-resources'],
                'missingManifestItem' => false,
                'external' => true,
                'readable' => false,
            ],
            [
                'index' => 2,
                'id' => null,
                'idref' => 'missing',
                'href' => '',
                'path' => '',
                'mediaType' => '',
                'linear' => true,
                'properties' => [],
                'manifestProperties' => [],
                'missingManifestItem' => true,
                'external' => false,
                'readable' => false,
            ],
        ], $meta['epubSpineItemRefs']);
        $t->same(['OPS/text/chapter.xhtml'], $meta['epubReadableResources']);
        $t->same(['OPS/nav.xhtml'], $meta['epubTocResources']);
    },
    'skips epub spine items whose linear attribute is not exactly yes' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-linear-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-linear-semantics</dc:identifier>
    <dc:title>Linear Semantics EPUB</dc:title>
  </metadata>
  <manifest>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="appendix-uppercase" href="text/appendix-uppercase.xhtml" media-type="application/xhtml+xml"/>
    <item id="appendix-invalid" href="text/appendix-invalid.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
    <itemref idref="appendix-uppercase" linear="YES"/>
    <itemref idref="appendix-invalid" linear="sometimes"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/text/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Main chapter body.</p></body></html>');
        $zip->addFromString('OPS/text/appendix-uppercase.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Uppercase appendix should be skipped.</p></body></html>');
        $zip->addFromString('OPS/text/appendix-invalid.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Invalid appendix should be skipped.</p></body></html>');
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
            $blocks = (new WordPressBlockWriter())->write($document);
            $native = PandocConverter::write($document, 'native');
        } finally {
            @unlink($path);
        }

        $t->same(3, $meta['epubSpineItems']);
        $t->same(true, $meta['epubSpineItemRefs'][0]['linear']);
        $t->same(false, $meta['epubSpineItemRefs'][1]['linear']);
        $t->same(false, $meta['epubSpineItemRefs'][2]['linear']);
        $t->same(['OPS/text/chapter.xhtml'], $meta['epubReadableResources']);
        $t->contains('Main chapter body.', $blocks);
        $t->same(false, str_contains($blocks, 'Uppercase appendix should be skipped.'));
        $t->same(false, str_contains($blocks, 'Invalid appendix should be skipped.'));
        $t->contains('Str "Main"', $native);
        $t->same(false, str_contains($native, 'Str "Uppercase"'));
        $t->same(false, str_contains($native, 'Str "Invalid"'));
    },
    'uses exact upstream spine media type dispatch for parameterized xhtml manifest items' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-parameterized-spine-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-parameterized-spine</dc:identifier>
    <dc:title>Parameterized Spine MIME EPUB</dc:title>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml; charset=utf-8"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Skipped Chapter</h1><p>Body should not be read through parameterized MIME dispatch.</p></body></html>');
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
            $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        } finally {
            @unlink($path);
        }

        $t->same('Parameterized Spine MIME EPUB', $meta['title']);
        $t->same(1, count($document->children));
        $t->same('paragraph', $document->children[0]->type);
        $t->same('span', $document->children[0]->children[0]->type);
        $t->same('chapter.xhtml', $document->children[0]->children[0]->attr('id'));
        $t->same('application/xhtml+xml; charset=utf-8', $meta['epubManifestItems'][0]['mediaType']);
        $t->same(false, $meta['epubManifestItems'][0]['readable']);
        $t->same(false, $meta['epubSpineItemRefs'][0]['readable']);
        $t->same([], $meta['epubReadableResources']);
        $t->contains('Span ( "chapter.xhtml"', $native);
        $t->same(false, str_contains($native, 'Str "Skipped"'));
        $t->same(false, str_contains($native, 'parameterized'));
    },
    'preserves epub basename marker for unreadable linear spine items' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-unreadable-spine-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-unreadable-spine-marker</dc:identifier>
    <dc:title>Unreadable Spine Marker EPUB</dc:title>
  </metadata>
  <manifest>
    <item id="notes" href="text/source-notes.txt" media-type="text/plain"/>
  </manifest>
  <spine>
    <itemref idref="notes"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/text/source-notes.txt', 'Source notes are not XHTML.');
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
            $native = PandocConverter::write($document, 'native');
        } finally {
            @unlink($path);
        }

        $marker = $document->children[0];
        $t->same(1, count($document->children));
        $t->same('paragraph', $marker->type);
        $t->same('span', $marker->children[0]->type);
        $t->same('source-notes.txt', $marker->children[0]->attr('id'));
        $t->same(false, $meta['epubSpineItemRefs'][0]['readable']);
        $t->same([], $meta['epubReadableResources']);
        $t->same(false, str_contains($native, 'No readable EPUB spine content was found.'));
        $t->contains('Span ( "source-notes.txt"', $native);
    },
    'reads all non-linear epub spine as an empty document' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-all-nonlinear-spine-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-all-nonlinear-spine</dc:identifier>
    <dc:title>All Nonlinear Spine</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter" linear="no"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/nav.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="chapter.xhtml">Chapter</a></li>
      </ol>
    </nav>
  </body>
</html>
HTML);
        $zip->addFromString('OPS/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>All Nonlinear Spine</h1><p>Nonlinear chapter.</p></body></html>');
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
            $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        } finally {
            @unlink($path);
        }

        $t->same([], $document->children);
        $t->same('All Nonlinear Spine', $meta['title']);
        $t->same(1, $meta['epubSpineItems']);
        $t->same(false, $meta['epubSpineItemRefs'][0]['linear']);
        $t->same(true, $meta['epubSpineItemRefs'][0]['readable']);
        $t->same([], $meta['epubReadableResources']);
        $t->same(1, $meta['epubTocEntryCount']);
        $t->same([
            ['text' => 'Chapter', 'href' => 'OPS/chapter.xhtml', 'level' => 1],
        ], $meta['epubTocEntries']);
        $t->same('[]', $native);
    },
    'separates epub media bag image usage from manifest image inventory' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-media-bag-scope-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-media-bag-scope</dc:identifier>
    <dc:title>Media Bag Scope EPUB</dc:title>
  </metadata>
  <manifest>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="used" href="images/used.png" media-type="image/png"/>
    <item id="linked" href="images/linked.gif" media-type="image/gif"/>
    <item id="unused" href="images/unused.jpg" media-type="image/jpeg"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/text/chapter.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body>
    <p><img src="../images/used.png#display" alt="Used image"/></p>
    <p><a href="../images/linked.gif">Linked image only</a></p>
  </body>
</html>
HTML);
        $usedImageBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
        $linkedImageBytes = base64_decode('R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==');
        $zip->addFromString('OPS/images/used.png', $usedImageBytes);
        $zip->addFromString('OPS/images/linked.gif', $linkedImageBytes);
        $zip->addFromString('OPS/images/unused.jpg', "\xFF\xD8\xFF\xD9");
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
        } finally {
            @unlink($path);
        }

        $t->same('Media Bag Scope EPUB', $meta['title']);
        $t->same(['OPS/text/chapter.xhtml'], $meta['epubReadableResources']);
        $t->same([
            'OPS/images/used.png#display',
            'OPS/images/linked.gif',
        ], $meta['epubReferencedResources']);
        $t->same([
            'OPS/images/used.png',
            'OPS/images/linked.gif',
            'OPS/images/unused.jpg',
        ], $meta['epubImageResources']);
        $t->same(['OPS/images/used.png'], $meta['epubMediaBagResources']);
        $t->same('reader-media-bag-from-emitted-local-media-resources', $meta['epubMediaResourcePolicy']);
        $t->same(1, $meta['epubMediaResourceCount']);
        $t->same(['epub-media-resource-loaded:OPS/images/used.png'], $meta['epubMediaResourceDiagnostics']);
        $t->same([
            [
                'path' => 'images/used.png',
                'zipEntry' => 'OPS/images/used.png',
                'mimeType' => 'image/png',
                'byteLength' => strlen($usedImageBytes),
                'sha1' => sha1($usedImageBytes),
            ],
        ], array_map(static fn (array $entry): array => [
            'path' => (string) $entry['path'],
            'zipEntry' => (string) $entry['zipEntry'],
            'mimeType' => (string) $entry['mimeType'],
            'byteLength' => (int) $entry['byteLength'],
            'sha1' => (string) $entry['sha1'],
        ], $meta['epubMediaResourceDirectory']));
    },
    'does not load path-absolute epub image urls into the local media bag' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-path-absolute-image-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $imageBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
        if (!is_string($imageBytes)) {
            throw new RuntimeException('Unable to decode path-absolute EPUB image bytes');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-path-absolute-media</dc:identifier>
    <dc:title>Path Absolute Media EPUB</dc:title>
  </metadata>
  <manifest>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="root-image" href="images/root.png" media-type="image/png"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/text/chapter.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body><p><img src="/images/root.png" alt="Root absolute image"/></p></body>
</html>
HTML);
        $zip->addFromString('OPS/images/root.png', $imageBytes);
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
        } finally {
            @unlink($path);
        }

        $image = $document->children[1]->children[0] ?? null;

        $t->same('Path Absolute Media EPUB', $meta['title']);
        $t->same('/images/root.png', $image instanceof AstNode ? $image->attr('url') : null);
        $t->same(['OPS/text/chapter.xhtml'], $meta['epubReadableResources']);
        $t->same([], $meta['epubReferencedResources']);
        $t->same(['OPS/images/root.png'], $meta['epubImageResources']);
        $t->same([], $meta['epubMediaBagResources']);
        $t->same(0, $meta['epubMediaResourceCount']);
        $t->same([], $meta['epubMediaResourceDiagnostics']);
        $t->same([], $meta['epubMediaResourceDirectory']);
    },
    'loads raw epub video and text track resources into the local media bag' => static function (TestRunner $t): void {
        $fixture = dirname(__DIR__) . '/fixtures/upstream-current-epub-reader/epub/text-track-captions.epub';
        $fixtureEntryBytes = static function (string $entry) use ($fixture): string {
            $zip = new ZipArchive();
            if ($zip->open($fixture) !== true) {
                throw new RuntimeException('Unable to open text-track captions fixture');
            }
            try {
                $bytes = $zip->getFromName($entry);
                if (!is_string($bytes)) {
                    throw new RuntimeException('Unable to read fixture entry: ' . $entry);
                }

                return $bytes;
            } finally {
                $zip->close();
            }
        };
        $document = (new EpubReader())->readEpubFile($fixture);
        $meta = $document->attr('meta');
        $videoBytes = $fixtureEntryBytes('EPUB/video/demo.mp4');
        $captionsBytes = $fixtureEntryBytes('EPUB/video/captions.vtt');
        $rawHtml = array_values(array_map(
            static fn (AstNode $node): string => (string) $node->attr('html'),
            array_filter(
                $document->children,
                static fn (AstNode $node): bool => $node->type === 'raw_html'
            )
        ));

        $t->same('Text Track Captions', $meta['title']);
        $t->same(['EPUB/chapter.xhtml'], $meta['epubReadableResources']);
        $t->same(['EPUB/video/demo.mp4', 'EPUB/video/captions.vtt'], $meta['epubReferencedResources']);
        $t->same([], $meta['epubImageResources']);
        $t->same(['EPUB/video/demo.mp4', 'EPUB/video/captions.vtt'], $meta['epubMediaBagResources']);
        $t->same('reader-media-bag-from-emitted-local-media-resources', $meta['epubMediaResourcePolicy']);
        $t->same(2, $meta['epubMediaResourceCount']);
        $t->same([
            'epub-media-resource-loaded:EPUB/video/demo.mp4',
            'epub-media-resource-loaded:EPUB/video/captions.vtt',
        ], $meta['epubMediaResourceDiagnostics']);
        $t->same([
            [
                'path' => 'video/captions.vtt',
                'zipEntry' => 'EPUB/video/captions.vtt',
                'mimeType' => 'text/vtt',
                'byteLength' => strlen($captionsBytes),
                'sha1' => sha1($captionsBytes),
            ],
            [
                'path' => 'video/demo.mp4',
                'zipEntry' => 'EPUB/video/demo.mp4',
                'mimeType' => 'video/mp4',
                'byteLength' => strlen($videoBytes),
                'sha1' => sha1($videoBytes),
            ],
        ], array_map(static fn (array $entry): array => [
            'path' => (string) $entry['path'],
            'zipEntry' => (string) $entry['zipEntry'],
            'mimeType' => (string) $entry['mimeType'],
            'byteLength' => (int) $entry['byteLength'],
            'sha1' => (string) $entry['sha1'],
        ], $meta['epubMediaResourceDirectory']));
        $t->same([
            '<video controls="controls" src="video/demo.mp4">',
            '<track kind="captions" src="video/captions.vtt" srclang="en" label="English captions">',
            '</track>',
            '</video>',
        ], $rawHtml);
    },
    'preserves epub picture source wrappers while loading fallback image media' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-picture-source-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $fallbackBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
        if (!is_string($fallbackBytes)) {
            throw new RuntimeException('Unable to decode picture fallback image bytes');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-picture-source</dc:identifier>
    <dc:title>Picture Source EPUB</dc:title>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="fallback" href="images/fallback.png" media-type="image/png"/>
    <item id="responsive" href="images/fallback.webp" media-type="image/webp"/>
    <item id="block-fallback" href="images/block.png" media-type="image/png"/>
    <item id="block-responsive" href="images/block.webp" media-type="image/webp"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/chapter.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body>
    <h1>Picture Source EPUB</h1>
    <p>Before <picture class="responsive"><source srcset="images/fallback.webp 1x" type="image/webp"/><img src="images/fallback.png" alt="Fallback art"/></picture> after.</p>
    <picture class="block-responsive"><source srcset="images/block.webp 1x" type="image/webp"/><img src="images/block.png" alt="Block art"/></picture>
  </body>
</html>
HTML);
        $zip->addFromString('OPS/images/fallback.png', $fallbackBytes);
        $zip->addFromString('OPS/images/fallback.webp', 'WEBP');
        $zip->addFromString('OPS/images/block.png', $fallbackBytes);
        $zip->addFromString('OPS/images/block.webp', 'WEBP-BLOCK');
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
            $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        } finally {
            @unlink($path);
        }

        $paragraph = $document->children[2] ?? new AstNode('missing');
        $pictureOpen = $paragraph->children[1] ?? new AstNode('missing');
        $sourceOpen = $paragraph->children[2] ?? new AstNode('missing');
        $sourceClose = $paragraph->children[3] ?? new AstNode('missing');
        $image = $paragraph->children[4] ?? new AstNode('missing');
        $pictureClose = $paragraph->children[5] ?? new AstNode('missing');

        $t->same('paragraph', $paragraph->type);
        $t->same('Before ', $paragraph->children[0]->attr('text') ?? null);
        $t->same('raw_html_inline', $pictureOpen->type);
        $t->same('<picture class="responsive">', $pictureOpen->attr('html'));
        $t->same('raw_html_inline', $sourceOpen->type);
        $t->same('<source srcset="images/fallback.webp 1x" type="image/webp">', $sourceOpen->attr('html'));
        $t->same('raw_html_inline', $sourceClose->type);
        $t->same('</source>', $sourceClose->attr('html'));
        $t->same('image', $image->type);
        $t->same('images/fallback.png', $image->attr('url'));
        $t->same('Fallback art', $image->attr('alt'));
        $t->same('raw_html_inline', $pictureClose->type);
        $t->same('</picture>', $pictureClose->attr('html'));
        $t->same(' after.', $paragraph->children[6]->attr('text') ?? null);

        $blockPictureOpen = $document->children[3] ?? new AstNode('missing');
        $blockSourceOpen = $document->children[4] ?? new AstNode('missing');
        $blockSourceClose = $document->children[5] ?? new AstNode('missing');
        $blockImagePlain = $document->children[6] ?? new AstNode('missing');
        $blockImage = $blockImagePlain->children[0] ?? new AstNode('missing');
        $blockPictureClose = $blockImagePlain->children[1] ?? new AstNode('missing');

        $t->same('plain', $blockPictureOpen->type);
        $t->same('raw_html_inline', $blockPictureOpen->children[0]->type ?? null);
        $t->same('<picture class="block-responsive">', $blockPictureOpen->children[0]->attr('html') ?? null);
        $t->same('raw_html', $blockSourceOpen->type);
        $t->same('<source srcset="images/block.webp 1x" type="image/webp">', $blockSourceOpen->attr('html'));
        $t->same('raw_html', $blockSourceClose->type);
        $t->same('</source>', $blockSourceClose->attr('html'));
        $t->same('plain', $blockImagePlain->type);
        $t->same('image', $blockImage->type);
        $t->same('images/block.png', $blockImage->attr('url'));
        $t->same('Block art', $blockImage->attr('alt'));
        $t->same('raw_html_inline', $blockPictureClose->type);
        $t->same('</picture>', $blockPictureClose->attr('html'));

        $t->same(['OPS/images/fallback.png', 'OPS/images/block.png'], $meta['epubReferencedResources']);
        $t->same([
            'OPS/images/fallback.png',
            'OPS/images/fallback.webp',
            'OPS/images/block.png',
            'OPS/images/block.webp',
        ], $meta['epubImageResources']);
        $t->same(['OPS/images/fallback.png', 'OPS/images/block.png'], $meta['epubMediaBagResources']);
        $t->same(2, $meta['epubMediaResourceCount']);
        $t->same([
            'epub-media-resource-loaded:OPS/images/fallback.png',
            'epub-media-resource-loaded:OPS/images/block.png',
        ], $meta['epubMediaResourceDiagnostics']);
        $t->same([
            [
                'path' => 'images/block.png',
                'zipEntry' => 'OPS/images/block.png',
                'mimeType' => 'image/png',
                'byteLength' => strlen($fallbackBytes),
                'sha1' => sha1($fallbackBytes),
            ],
            [
                'path' => 'images/fallback.png',
                'zipEntry' => 'OPS/images/fallback.png',
                'mimeType' => 'image/png',
                'byteLength' => strlen($fallbackBytes),
                'sha1' => sha1($fallbackBytes),
            ],
        ], array_map(static fn (array $entry): array => [
            'path' => (string) $entry['path'],
            'zipEntry' => (string) $entry['zipEntry'],
            'mimeType' => (string) $entry['mimeType'],
            'byteLength' => (int) $entry['byteLength'],
            'sha1' => (string) $entry['sha1'],
        ], $meta['epubMediaResourceDirectory']));
        $t->contains('RawInline (Format "html") "<picture class=\\"responsive\\">"', $native);
        $t->contains('RawInline (Format "html") "<source srcset=\\"images/fallback.webp 1x\\" type=\\"image/webp\\">"', $native);
        $t->contains('Image ( "" , [  ] , [  ] ) [ Str "Fallback" , Space , Str "art" ] ( "images/fallback.png" , "" )', $native);
        $t->contains('RawInline (Format "html") "</picture>"', $native);
        $t->contains('Plain [ RawInline (Format "html") "<picture class=\\"block-responsive\\">" ]', $native);
        $t->contains('RawBlock (Format "html") "<source srcset=\\"images/block.webp 1x\\" type=\\"image/webp\\">"', $native);
        $t->contains('Plain [ Image ( "" , [  ] , [  ] ) [ Str "Block" , Space , Str "art" ] ( "images/block.png" , "" )', $native);
    },
    'loads package parts while preserving epub href query and fragment provenance' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-href-suffix-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', <<<'XML'
<?xml version="1.0"?>
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="OPS/package.opf?profile=compact#primary" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-href-suffix</dc:identifier>
    <dc:title>Href Suffix EPUB</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="chapter" href="text/chapter.xhtml?source=archive#body" media-type="application/xhtml+xml"/>
    <item id="nav" href="nav.xhtml?profile=toc" media-type="application/xhtml+xml" properties="nav"/>
    <item id="cover" href="images/cover.png?revision=20260703#cover" media-type="image/png" properties="cover-image"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/text/chapter.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body>
    <h1 id="body">Href Suffix EPUB</h1>
    <p><img src="../images/cover.png?display=inline#pixel" alt="Cover suffix"/></p>
    <p><a href="chapter.xhtml?source=archive#body">Self link</a></p>
    <p><a href="chapter.xhtml?source=archive">Self query link</a></p>
  </body>
</html>
HTML);
        $zip->addFromString('OPS/nav.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol><li><a href="text/chapter.xhtml?source=nav#body">Chapter with suffix</a></li></ol>
    </nav>
  </body>
</html>
HTML);
        $coverBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
        $zip->addFromString('OPS/images/cover.png', $coverBytes);
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
            $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
            $blocks = (new WordPressBlockWriter())->write($document);
        } finally {
            @unlink($path);
        }

        $coverImage = $document->children[0]->children[0] ?? new AstNode('missing');

        $t->same('Href Suffix EPUB', $meta['title']);
        $t->same('image', $coverImage->type);
        $t->same('images/cover.png?revision=20260703#cover', $coverImage->attr('url'));
        $t->same('OPS/package.opf', $meta['epubRootfile']);
        $t->same('text/chapter.xhtml?source=archive#body', $meta['epubManifestItems'][0]['href']);
        $t->same('OPS/text/chapter.xhtml?source=archive#body', $meta['epubManifestItems'][0]['path']);
        $t->same(true, $meta['epubManifestItems'][0]['readable']);
        $t->same('nav.xhtml?profile=toc', $meta['epubManifestItems'][1]['href']);
        $t->same('OPS/nav.xhtml?profile=toc', $meta['epubManifestItems'][1]['path']);
        $t->same(true, $meta['epubManifestItems'][1]['navigation']);
        $t->same(true, $meta['epubManifestItems'][1]['readable']);
        $t->same('images/cover.png?revision=20260703#cover', $meta['epubManifestItems'][2]['href']);
        $t->same('OPS/images/cover.png?revision=20260703#cover', $meta['epubManifestItems'][2]['path']);
        $t->same(true, $meta['epubManifestItems'][2]['coverImage']);
        $t->same('text/chapter.xhtml?source=archive#body', $meta['epubSpineItemRefs'][0]['href']);
        $t->same('OPS/text/chapter.xhtml?source=archive#body', $meta['epubSpineItemRefs'][0]['path']);
        $t->same(true, $meta['epubSpineItemRefs'][0]['readable']);
        $t->same(['OPS/text/chapter.xhtml'], $meta['epubReadableResources']);
        $t->same(['OPS/images/cover.png#pixel', 'OPS/text/chapter.xhtml#body', 'OPS/text/chapter.xhtml'], $meta['epubReferencedResources']);
        $t->same(['OPS/images/cover.png'], $meta['epubImageResources']);
        $t->same(['OPS/images/cover.png'], $meta['epubMediaBagResources']);
        $t->same(1, $meta['epubMediaResourceCount']);
        $t->same(['epub-media-resource-loaded:OPS/images/cover.png'], $meta['epubMediaResourceDiagnostics']);
        $t->same([
            [
                'path' => 'images/cover.png',
                'zipEntry' => 'OPS/images/cover.png',
                'mimeType' => 'image/png',
                'byteLength' => strlen($coverBytes),
                'sha1' => sha1($coverBytes),
            ],
        ], array_map(static fn (array $entry): array => [
            'path' => (string) $entry['path'],
            'zipEntry' => (string) $entry['zipEntry'],
            'mimeType' => (string) $entry['mimeType'],
            'byteLength' => (int) $entry['byteLength'],
            'sha1' => (string) $entry['sha1'],
        ], $meta['epubMediaResourceDirectory']));
        $t->same(['OPS/nav.xhtml'], $meta['epubTocResources']);
        $t->same([
            ['text' => 'Chapter with suffix', 'href' => 'OPS/text/chapter.xhtml?source=nav#body', 'level' => 1],
        ], $meta['epubTocEntries']);
        $t->contains('( "images/cover.png?revision=20260703#cover" , "" )', $native);
        $t->contains('( "#chapter.xhtml?source=archive#body" , "" )', $native);
        $t->contains('( "#chapter.xhtml?source=archive" , "" )', $native);
        $t->contains('( "images/cover.png?display=inline#pixel" , "" )', $native);
        $t->contains('href="#chapter.xhtml?source=archive#body"', $blocks);
        $t->contains('href="#chapter.xhtml?source=archive"', $blocks);
        $t->contains('src="images/cover.png?display=inline#pixel"', $blocks);
    },
    'reads epub ncx table of contents metadata' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', '<?xml version="1.0"?><package xmlns="http://www.idpf.org/2007/opf" xmlns:dc="http://purl.org/dc/elements/1.1/"><metadata><dc:title>NCX EPUB</dc:title></metadata><manifest><item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/><item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/></manifest><spine toc="toc"><itemref idref="chapter"/></spine></package>');
        $zip->addFromString('OPS/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>NCX EPUB</h1><p>Body.</p></body></html>');
        $zip->addFromString('OPS/toc.ncx', '<?xml version="1.0"?><ncx xmlns="http://www.daisy.org/z3986/2005/ncx/"><navMap><navPoint id="n1"><navLabel><text>Chapter One</text></navLabel><content src="chapter.xhtml"/><navPoint id="n1-1"><navLabel><text>Section A</text></navLabel><content src="chapter.xhtml#section-a"/></navPoint></navPoint></navMap><pageList><pageTarget id="p1" type="normal" value="1"><navLabel><text>1</text></navLabel><content src="chapter.xhtml#page-1"/></pageTarget><pageTarget id="p2" type="normal" value="2"><navLabel><text>2</text></navLabel><content src="chapter.xhtml#page-2"/></pageTarget></pageList></ncx>');
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
        } finally {
            @unlink($path);
        }

        $t->same(['OPS/toc.ncx'], $meta['epubTocResources']);
        $t->same(2, $meta['epubTocEntryCount']);
        $t->same([
            ['text' => 'Chapter One', 'href' => 'OPS/chapter.xhtml', 'level' => 1],
            ['text' => 'Section A', 'href' => 'OPS/chapter.xhtml#section-a', 'level' => 2],
        ], $meta['epubTocEntries']);
        $t->same(2, $meta['epubPageListEntryCount']);
        $t->same([
            ['text' => '1', 'href' => 'OPS/chapter.xhtml#page-1', 'level' => 1],
            ['text' => '2', 'href' => 'OPS/chapter.xhtml#page-2', 'level' => 1],
        ], $meta['epubPageListEntries']);
    },
    'selects parameterized opf rootfile over earlier non-opf rootfile' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-rootfile-media-type-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', <<<'XML'
<?xml version="1.0"?>
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="EPUB/preview.xhtml" media-type="application/xhtml+xml"/>
    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml; profile=&quot;primary&quot;"/>
  </rootfiles>
</container>
XML);
        $zip->addFromString('EPUB/preview.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Not an OPF package.</p></body></html>');
        $zip->addFromString('EPUB/package.opf', <<<'XML'
<?xml version="1.0"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-parameterized-rootfile</dc:identifier>
    <dc:title>Parameterized Rootfile EPUB</dc:title>
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
        $zip->addFromString('EPUB/chapter.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Parameterized Rootfile EPUB</h1><p>Body.</p></body></html>');
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
        } finally {
            @unlink($path);
        }

        $t->same('Parameterized Rootfile EPUB', $meta['title']);
        $t->same('EPUB/package.opf', $meta['epubRootfile']);
        $t->same(['EPUB/chapter.xhtml'], $meta['epubReadableResources']);
    },
    'resolves percent encoded manifest and navigation package paths' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0"?>
<package xmlns="http://www.idpf.org/2007/opf" xmlns:dc="http://purl.org/dc/elements/1.1/" version="3.0">
  <metadata>
    <dc:title>Percent Path EPUB</dc:title>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
    <item id="chapter" href="text/chapter%201.xhtml" media-type="application/xhtml+xml"/>
    <item id="cover" href="images/cover%20art.png" media-type="image/png"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/nav.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <nav epub:type="toc">
      <ol>
        <li><a href="text/chapter%201.xhtml#opening">Chapter One</a></li>
      </ol>
    </nav>
  </body>
</html>
HTML);
        $zip->addFromString('OPS/text/chapter 1.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body>
    <h1 id="opening">Percent Path EPUB</h1>
    <p><img src="../images/cover%20art.png" alt="Cover art"/> Body. <a href="chapter%201.xhtml#opening">Self link</a>.</p>
  </body>
</html>
HTML);
        $zip->addFromString('OPS/images/cover art.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $blocks = (new WordPressBlockWriter())->write($document);
            $meta = $document->attr('meta');
        } finally {
            @unlink($path);
        }

        $t->same('Percent Path EPUB', $meta['title']);
        $t->same(['OPS/text/chapter 1.xhtml'], $meta['epubReadableResources']);
        $t->same(['OPS/images/cover art.png', 'OPS/text/chapter 1.xhtml#opening'], $meta['epubReferencedResources']);
        $t->same(['OPS/images/cover art.png'], $meta['epubImageResources']);
        $t->same(['OPS/nav.xhtml'], $meta['epubTocResources']);
        $t->same([
            ['text' => 'Chapter One', 'href' => 'OPS/text/chapter 1.xhtml#opening', 'level' => 1],
        ], $meta['epubTocEntries']);
        $t->same('paragraph', $document->children[0]->type);
        $t->same('chapter%201.xhtml', $document->children[0]->children[0]->attr('id'));
        $t->same('heading', $document->children[1]->type);
        $t->same('chapter%201.xhtml_opening', $document->children[1]->attr('id'));
        $t->same('Percent Path EPUB', $document->children[1]->attr('text'));
        $t->contains('src="images/cover%20art.png"', $blocks);
        $t->contains('id="chapter%201.xhtml_opening"', $blocks);
        $t->contains('href="#chapter%201.xhtml_opening"', $blocks);
        $t->same(false, str_contains($blocks, 'chapter%25201.xhtml'));
    },
    'keeps epub image element ids unprefixed while prefixing upstream attr-bearing references' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-image-id-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0"?>
<package xmlns="http://www.idpf.org/2007/opf" xmlns:dc="http://purl.org/dc/elements/1.1/" version="3.0">
  <metadata>
    <dc:title>Image Id EPUB</dc:title>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="pixel" href="images/pixel.png" media-type="image/png"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/chapter.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body>
    <h1 id="top">Image Id EPUB</h1>
    <p><img id="fig1" class="inline-art" src="images/pixel.png" alt="Pixel"/></p>
  </body>
</html>
HTML);
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
        if (!is_string($pixel)) {
            throw new RuntimeException('Unable to decode image fixture bytes');
        }
        $zip->addFromString('OPS/images/pixel.png', $pixel);
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
            $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        } finally {
            @unlink($path);
        }

        $heading = $document->children[1] ?? new PortLibs\Pandoc\AstNode('missing');
        $image = $document->children[2]->children[0] ?? new PortLibs\Pandoc\AstNode('missing');
        $t->same('heading', $heading->type);
        $t->same('chapter.xhtml_top', $heading->attr('id'));
        $t->same('image', $image->type);
        $t->same('fig1', $image->attr('id'));
        $t->same(['inline-art'], $image->attr('classes'));
        $t->same('images/pixel.png', $image->attr('url'));
        $t->same(['OPS/images/pixel.png'], $meta['epubMediaBagResources']);
        $t->same(['epub-media-resource-loaded:OPS/images/pixel.png'], $meta['epubMediaResourceDiagnostics']);
        $t->contains('Header 1 ( "chapter.xhtml_top"', $native);
        $t->contains('Image ( "fig1" , [ "inline-art" ]', $native);
        $t->same(false, str_contains($native, 'chapter.xhtml_fig1'));
    },
    'preserves ordinary epub body link pandoc attributes' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-body-link-attrs-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">urn:uuid:body-link-role</dc:identifier>
    <dc:title>Body Link Role</dc:title>
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
        $zip->addFromString('OPS/chapter.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body><p>See <a id="bib-link" class="tracked primary" epub:type="biblioref glossary" role="doc-biblioref" target="_blank" rel="noopener" data-kind="ref" href="refs.xhtml#ref-1" title="Reference title">reference</a>.</p></body>
</html>
HTML);
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
            $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        } finally {
            @unlink($path);
        }

        $paragraph = $document->children[1] ?? new PortLibs\Pandoc\AstNode('missing');
        $links = array_values(array_filter(
            $paragraph->children,
            static fn (PortLibs\Pandoc\AstNode $child): bool => $child->type === 'link'
        ));
        $link = $links[0] ?? new PortLibs\Pandoc\AstNode('missing');

        $t->same('link', $link->type);
        $t->same('chapter.xhtml_bib-link', $link->attr('id'));
        $t->same(['tracked', 'primary', 'biblioref', 'glossary'], $link->attr('classes'));
        $t->same('refs.xhtml_ref-1', $link->attr('url'));
        $t->same('Reference title', $link->attr('title'));
        $t->same([
            'role' => 'doc-biblioref',
            'target' => '_blank',
            'rel' => 'noopener',
            'data-kind' => 'ref',
        ], $link->attr('attributes'));
        $t->same(['OPS/refs.xhtml#ref-1'], $meta['epubReferencedResources']);
        $t->contains(
            'Link ( "chapter.xhtml_bib-link" , [ "tracked" , "primary" , "biblioref" , "glossary" ] , [ ( "data-kind" , "ref" ) , ( "rel" , "noopener" ) , ( "role" , "doc-biblioref" ) , ( "target" , "_blank" ) ] ) [ Str "reference" ] ( "refs.xhtml_ref-1" , "Reference title" )',
            $native
        );
    },
    'preserves epub body link attributes after xhtml base href resolution' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-base-link-attrs-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">urn:uuid:base-link-attrs</dc:identifier>
    <dc:title>Base Link Attrs</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/text/chapter.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <head><base href="../refs/"/></head>
  <body><p>See <a id="xref" class="tracked" epub:type="biblioref" role="doc-biblioref" href="notes.xhtml#n1">note</a>.</p></body>
</html>
HTML);
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
            $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        } finally {
            @unlink($path);
        }

        $paragraph = $document->children[1] ?? new PortLibs\Pandoc\AstNode('missing');
        $links = array_values(array_filter(
            $paragraph->children,
            static fn (PortLibs\Pandoc\AstNode $child): bool => $child->type === 'link'
        ));
        $link = $links[0] ?? new PortLibs\Pandoc\AstNode('missing');

        $t->same('link', $link->type);
        $t->same('chapter.xhtml_xref', $link->attr('id'));
        $t->same(['tracked', 'biblioref'], $link->attr('classes'));
        $t->same('notes.xhtml_n1', $link->attr('url'));
        $t->same(['role' => 'doc-biblioref'], $link->attr('attributes'));
        $t->same(['OPS/refs/notes.xhtml#n1'], $meta['epubReferencedResources']);
        $t->contains(
            'Link ( "chapter.xhtml_xref" , [ "tracked" , "biblioref" ] , [ ( "role" , "doc-biblioref" ) ] ) [ Str "note" ] ( "notes.xhtml_n1" , "" )',
            $native
        );
    },
    'resolves document scoped epub xhtml xml base before native reference rewriting' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-xml-base-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $imageBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
        if (!is_string($imageBytes)) {
            throw new RuntimeException('Unable to decode xml base image fixture bytes');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">urn:uuid:xhtml-xml-base</dc:identifier>
    <dc:title>XHTML XML Base</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="pixel" href="assets/pixel.png" media-type="image/png"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/text/chapter.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" xml:base="../assets/">
  <body>
    <p>See <a id="xref" class="tracked" epub:type="biblioref" role="doc-biblioref" href="refs.xhtml#r1">reference</a> and <img id="pixel" class="inline-art" src="pixel.png" alt="Pixel"/>.</p>
  </body>
</html>
HTML);
        $zip->addFromString('OPS/assets/pixel.png', $imageBytes);
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
            $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        } finally {
            @unlink($path);
        }

        $paragraph = $document->children[1] ?? new AstNode('missing');
        $links = array_values(array_filter(
            $paragraph->children,
            static fn (AstNode $child): bool => $child->type === 'link'
        ));
        $images = array_values(array_filter(
            $paragraph->children,
            static fn (AstNode $child): bool => $child->type === 'image'
        ));
        $link = $links[0] ?? new AstNode('missing');
        $image = $images[0] ?? new AstNode('missing');

        $t->same('link', $link->type);
        $t->same('chapter.xhtml_xref', $link->attr('id'));
        $t->same(['tracked', 'biblioref'], $link->attr('classes'));
        $t->same('refs.xhtml_r1', $link->attr('url'));
        $t->same(['role' => 'doc-biblioref'], $link->attr('attributes'));

        $t->same('image', $image->type);
        $t->same('pixel', $image->attr('id'));
        $t->same(['inline-art'], $image->attr('classes'));
        $t->same('assets/pixel.png', $image->attr('url'));
        $t->same('Pixel', $image->attr('alt'));

        $t->same(['OPS/assets/refs.xhtml#r1', 'OPS/assets/pixel.png'], $meta['epubReferencedResources']);
        $t->same(['OPS/assets/pixel.png'], $meta['epubMediaBagResources']);
        $t->same(1, $meta['epubMediaResourceCount']);
        $t->same(['epub-media-resource-loaded:OPS/assets/pixel.png'], $meta['epubMediaResourceDiagnostics']);
        $t->same([
            [
                'path' => 'assets/pixel.png',
                'zipEntry' => 'OPS/assets/pixel.png',
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
        $t->contains(
            'Link ( "chapter.xhtml_xref" , [ "tracked" , "biblioref" ] , [ ( "role" , "doc-biblioref" ) ] ) [ Str "reference" ] ( "refs.xhtml_r1" , "" )',
            $native
        );
        $t->contains('Image ( "pixel" , [ "inline-art" ] , [  ] ) [ Str "Pixel" ] ( "assets/pixel.png" , "" )', $native);
    },
    'resolves scoped epub xhtml xml base before native reference rewriting' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-scoped-xml-base-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $imageBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
        if (!is_string($imageBytes)) {
            throw new RuntimeException('Unable to decode scoped xml base image fixture bytes');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">urn:uuid:scoped-xhtml-xml-base</dc:identifier>
    <dc:title>Scoped XHTML XML Base</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="icon" href="assets/icons/pixel.png" media-type="image/png"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/text/chapter.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <p xml:base="../refs/">See <a id="xref" class="tracked" epub:type="biblioref" role="doc-biblioref" href="notes.xhtml#n1">reference</a>.</p>
    <p xml:base="../assets/">Icon <span xml:base="icons/"><img id="icon" class="inline-art" src="pixel.png" alt="Icon"/></span>.</p>
  </body>
</html>
HTML);
        $zip->addFromString('OPS/assets/icons/pixel.png', $imageBytes);
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
            $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        } finally {
            @unlink($path);
        }

        $collectNodes = null;
        $collectNodes = static function (AstNode $node, string $type) use (&$collectNodes): array {
            $matches = $node->type === $type ? [$node] : [];
            foreach ($node->children as $child) {
                array_push($matches, ...$collectNodes($child, $type));
            }

            return $matches;
        };

        $link = $collectNodes($document, 'link')[0] ?? new AstNode('missing');
        $image = $collectNodes($document, 'image')[0] ?? new AstNode('missing');

        $t->same('link', $link->type);
        $t->same('chapter.xhtml_xref', $link->attr('id'));
        $t->same(['tracked', 'biblioref'], $link->attr('classes'));
        $t->same('notes.xhtml_n1', $link->attr('url'));
        $t->same(['role' => 'doc-biblioref'], $link->attr('attributes'));

        $t->same('image', $image->type);
        $t->same('icon', $image->attr('id'));
        $t->same(['inline-art'], $image->attr('classes'));
        $t->same('assets/icons/pixel.png', $image->attr('url'));
        $t->same('Icon', $image->attr('alt'));

        $t->same(['OPS/refs/notes.xhtml#n1', 'OPS/assets/icons/pixel.png'], $meta['epubReferencedResources']);
        $t->same(['OPS/assets/icons/pixel.png'], $meta['epubMediaBagResources']);
        $t->same(1, $meta['epubMediaResourceCount']);
        $t->same(['epub-media-resource-loaded:OPS/assets/icons/pixel.png'], $meta['epubMediaResourceDiagnostics']);
        $t->same([
            [
                'path' => 'assets/icons/pixel.png',
                'zipEntry' => 'OPS/assets/icons/pixel.png',
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
        $t->contains(
            'Link ( "chapter.xhtml_xref" , [ "tracked" , "biblioref" ] , [ ( "role" , "doc-biblioref" ) ] ) [ Str "reference" ] ( "notes.xhtml_n1" , "" )',
            $native
        );
        $t->contains('Image ( "icon" , [ "inline-art" ] , [  ] ) [ Str "Icon" ] ( "assets/icons/pixel.png" , "" )', $native);
    },
    'preserves epub footnote definition link attributes without body href overlay collision' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-footnote-link-attrs-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">urn:uuid:footnote-link-attrs</dc:identifier>
    <dc:title>Footnote Link Attrs</dc:title>
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
        $zip->addFromString('OPS/chapter.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <p>Body text <a epub:type="noteref" href="#fn1">1</a> and <a id="ordinary" class="body-link" role="doc-example" data-kind="ordinary" href="#fnref1">ordinary</a>.</p>
    <aside epub:type="footnote" id="fn1">
      <p><a id="back-link" class="tracked secondary" epub:type="backlink review" role="doc-backlink" target="_self" rel="prev" data-kind="return" href="#fnref1">back</a></p>
    </aside>
  </body>
</html>
HTML);
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        } finally {
            @unlink($path);
        }

        $paragraph = $document->children[1] ?? new PortLibs\Pandoc\AstNode('missing');
        $notes = array_values(array_filter(
            $paragraph->children,
            static fn (PortLibs\Pandoc\AstNode $child): bool => $child->type === 'note'
        ));
        $ordinaryLinks = array_values(array_filter(
            $paragraph->children,
            static fn (PortLibs\Pandoc\AstNode $child): bool => $child->type === 'link'
        ));
        $note = $notes[0] ?? new PortLibs\Pandoc\AstNode('missing');
        $backlinkParagraph = $note->children[0] ?? new PortLibs\Pandoc\AstNode('missing');
        $backlink = $backlinkParagraph->children[0] ?? new PortLibs\Pandoc\AstNode('missing');
        $ordinaryLink = $ordinaryLinks[0] ?? new PortLibs\Pandoc\AstNode('missing');

        $t->same('link', $backlink->type);
        $t->same('chapter.xhtml_back-link', $backlink->attr('id'));
        $t->same(['tracked', 'secondary', 'backlink', 'review'], $backlink->attr('classes'));
        $t->same('#chapter.xhtml_fnref1', $backlink->attr('url'));
        $t->same([
            'role' => 'doc-backlink',
            'target' => '_self',
            'rel' => 'prev',
            'data-kind' => 'return',
        ], $backlink->attr('attributes'));

        $t->same('link', $ordinaryLink->type);
        $t->same('chapter.xhtml_ordinary', $ordinaryLink->attr('id'));
        $t->same(['body-link'], $ordinaryLink->attr('classes'));
        $t->same('#chapter.xhtml_fnref1', $ordinaryLink->attr('url'));
        $t->same([
            'role' => 'doc-example',
            'data-kind' => 'ordinary',
        ], $ordinaryLink->attr('attributes'));
        $t->contains(
            'Link ( "chapter.xhtml_back-link" , [ "tracked" , "secondary" , "backlink" , "review" ] , [ ( "data-kind" , "return" ) , ( "rel" , "prev" ) , ( "role" , "doc-backlink" ) , ( "target" , "_self" ) ] ) [ Str "back" ] ( "#chapter.xhtml_fnref1" , "" )',
            $native
        );
        $t->contains(
            'Link ( "chapter.xhtml_ordinary" , [ "body-link" ] , [ ( "data-kind" , "ordinary" ) , ( "role" , "doc-example" ) ] ) [ Str "ordinary" ] ( "#chapter.xhtml_fnref1" , "" )',
            $native
        );
    },
    'preserves external epub noteref link class when note body is outside linear spine' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-external-noteref-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-external-noteref</dc:identifier>
    <dc:title>External Noteref EPUB</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="notes" href="notes.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
    <itemref idref="notes" linear="no"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/chapter.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body><p>Main note <a epub:type="noteref" href="notes.xhtml#fn1">1</a>.</p></body>
</html>
HTML);
        $zip->addFromString('OPS/notes.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body><aside epub:type="footnote" id="fn1"><p>Separate note body.</p></aside></body>
</html>
HTML);
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
            $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        } finally {
            @unlink($path);
        }

        $paragraph = $document->children[1];
        $links = array_values(array_filter(
            $paragraph->children,
            static fn (PortLibs\Pandoc\AstNode $child): bool => $child->type === 'link'
        ));
        $link = $links[0] ?? new PortLibs\Pandoc\AstNode('missing');
        $t->same('link', $link->type);
        $t->same(['noteref'], $link->attr('classes'));
        $t->same('notes.xhtml_fn1', $link->attr('url'));
        $t->same(['OPS/chapter.xhtml'], $meta['epubReadableResources']);
        $t->same(['OPS/notes.xhtml#fn1'], $meta['epubReferencedResources']);
        $t->contains('Link ( "" , [ "noteref" ] , [  ] ) [ Str "1" ] ( "notes.xhtml_fn1" , "" )', $native);
        $t->same(false, str_contains($native, 'Note ['));
        $t->same(false, str_contains($native, 'Separate note body'));
    },
    'fills html5 recovered standalone epub footnote note bodies before media bag extraction' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-standalone-footnote-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $noteImage = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
        if (!is_string($noteImage)) {
            throw new RuntimeException('Unable to decode note image fixture bytes');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-standalone-footnote</dc:identifier>
    <dc:title>Standalone Footnote EPUB</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="note-image" href="images/note.png" media-type="image/png"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/chapter.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <p>Body text <a epub:type="noteref" href="#fn1">1</a>.</p>
    <aside epub:type="footnote" id="fn1">
      <p>Footnote body <img src="images/note.png" alt="Note art">.</p>
      <p><a role="doc-backlink" href="#fnref1">back</a></p>
    </aside>
  </body>
</html>
HTML);
        $zip->addFromString('OPS/images/note.png', $noteImage);
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $meta = $document->attr('meta');
            $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        } finally {
            @unlink($path);
        }

        $paragraph = $document->children[1] ?? new PortLibs\Pandoc\AstNode('missing');
        $note = $paragraph->children[1] ?? new PortLibs\Pandoc\AstNode('missing');
        $noteParagraph = $note->children[0] ?? new PortLibs\Pandoc\AstNode('missing');
        $noteImageNode = $noteParagraph->children[1] ?? new PortLibs\Pandoc\AstNode('missing');
        $backlinkParagraph = $note->children[1] ?? new PortLibs\Pandoc\AstNode('missing');
        $backlink = $backlinkParagraph->children[0] ?? new PortLibs\Pandoc\AstNode('missing');

        $t->same('paragraph', $paragraph->type);
        $t->same('note', $note->type);
        $t->same(['paragraph', 'paragraph'], array_map(static fn (PortLibs\Pandoc\AstNode $node): string => $node->type, $note->children));
        $t->same('Footnote body Note art.', $noteParagraph->attr('text'));
        $t->same('image', $noteImageNode->type);
        $t->same('images/note.png', $noteImageNode->attr('url'));
        $t->same('Note art', $noteImageNode->attr('alt'));
        $t->same('paragraph', $backlinkParagraph->type);
        $t->same('link', $backlink->type);
        $t->same('back', $backlink->children[0]->attr('text') ?? null);
        $t->same('#chapter.xhtml_fnref1', $backlink->attr('url'));
        $t->same(['role' => 'doc-backlink'], $backlink->attr('attributes'));
        $t->same(['OPS/chapter.xhtml'], $meta['epubReadableResources']);
        $t->same(['OPS/images/note.png'], $meta['epubReferencedResources']);
        $t->same(['OPS/images/note.png'], $meta['epubImageResources']);
        $t->same(['OPS/images/note.png'], $meta['epubMediaBagResources']);
        $t->same(1, $meta['epubMediaResourceCount']);
        $t->same(['epub-media-resource-loaded:OPS/images/note.png'], $meta['epubMediaResourceDiagnostics']);
        $t->same([
            [
                'path' => 'images/note.png',
                'zipEntry' => 'OPS/images/note.png',
                'mimeType' => 'image/png',
                'byteLength' => strlen($noteImage),
                'sha1' => sha1($noteImage),
            ],
        ], array_map(static fn (array $entry): array => [
            'path' => (string) $entry['path'],
            'zipEntry' => (string) $entry['zipEntry'],
            'mimeType' => (string) $entry['mimeType'],
            'byteLength' => (int) $entry['byteLength'],
            'sha1' => (string) $entry['sha1'],
        ], $meta['epubMediaResourceDirectory']));
        $t->contains('Note [ Para [ Str "Footnote" , Space , Str "body" , Image', $native);
        $t->contains('( "images/note.png" , "" )', $native);
        $t->contains('Link ( "" , [  ] , [ ( "role" , "doc-backlink" ) ] ) [ Str "back" ] ( "#chapter.xhtml_fnref1" , "" )', $native);
    },
    'preserves epub xhtml wbr raw inline close in native output' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-wbr-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-wbr</dc:identifier>
    <dc:title>WBR EPUB</dc:title>
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
        $zip->addFromString('OPS/chapter.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body>
    <p>Soft<wbr/>break remains readable.</p>
  </body>
</html>
HTML);
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        } finally {
            @unlink($path);
        }

        $paragraph = $document->children[1] ?? new AstNode('missing');

        $t->same('paragraph', $paragraph->type);
        $t->contains('RawInline (Format "html") "<wbr>"', $native);
        $t->contains('RawInline (Format "html") "</wbr>"', $native);
        $t->contains('Str "Soft" , RawInline (Format "html") "<wbr>" , RawInline (Format "html") "</wbr>" , Str "break"', $native);
    },
    'unwraps epub chapter sectioning content but preserves ordinary chapter class sections' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-chapter-section-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-chapter-section</dc:identifier>
    <dc:title>Chapter Section EPUB</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="semantic" href="semantic.xhtml" media-type="application/xhtml+xml"/>
    <item id="classed" href="classed.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine>
    <itemref idref="semantic"/>
    <itemref idref="classed"/>
  </spine>
</package>
XML);
        $zip->addFromString('OPS/semantic.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body>
    <section id="semantic-chapter" epub:type="bodymatter chapter">
      <h1>Semantic Chapter</h1>
      <p>Unwrapped chapter section.</p>
    </section>
  </body>
</html>
HTML);
        $zip->addFromString('OPS/classed.xhtml', <<<'HTML'
<html xmlns="http://www.w3.org/1999/xhtml">
  <body>
    <section id="classed-chapter" class="chapter">
      <h1>Classed Chapter</h1>
      <p>Still wrapped section.</p>
    </section>
  </body>
</html>
HTML);
        $zip->close();

        try {
            $document = (new EpubReader())->readEpubFile($path);
            $native = (new NativeWriter(['blocksOnly' => true]))->write($document);
        } finally {
            @unlink($path);
        }

        $semanticHeading = $document->children[1] ?? new AstNode('missing');
        $semanticParagraph = $document->children[2] ?? new AstNode('missing');
        $classedDiv = $document->children[4] ?? new AstNode('missing');
        $classedHeading = $classedDiv->children[0] ?? new AstNode('missing');
        $classedParagraph = $classedDiv->children[1] ?? new AstNode('missing');

        $t->same([
            'paragraph',
            'heading',
            'paragraph',
            'paragraph',
            'div',
        ], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same('Semantic Chapter', $semanticHeading->attr('text'));
        $t->same('Unwrapped chapter section.', $semanticParagraph->attr('text'));
        $t->same('div', $classedDiv->type);
        $t->same('classed.xhtml_classed-chapter', $classedDiv->attr('id'));
        $t->same(['section', 'chapter'], $classedDiv->attr('classes'));
        $t->same('Classed Chapter', $classedHeading->attr('text'));
        $t->same('Still wrapped section.', $classedParagraph->attr('text'));
        $t->contains('Header 1 ( "" , [  ] , [  ] ) [ Str "Semantic" , Space , Str "Chapter" ]', $native);
        $t->contains('Div ( "classed.xhtml_classed-chapter" , [ "section" , "chapter" ] , [  ] )', $native);
        $t->true(!str_contains($native, 'Div ( "semantic.xhtml_semantic-chapter"'));
        $t->true(!str_contains($native, '_epubSemanticTypes'));
    },
    'preserves checked-in manifest fallback and media overlay relationships' => static function (TestRunner $t): void {
        $fixtureRoot = __DIR__ . '/../fixtures/upstream-current-epub-reader/epub';
        $reader = new EpubReader();
        $itemsById = static function (array $items): array {
            $indexed = [];
            foreach ($items as $item) {
                if (is_array($item) && is_string($item['id'] ?? null)) {
                    $indexed[$item['id']] = $item;
                }
            }

            return $indexed;
        };

        $fallbackDocument = $reader->readEpubFile($fixtureRoot . '/spine-fallback-resource.epub');
        $fallbackMeta = $fallbackDocument->attr('meta');
        $fallbackItems = $itemsById($fallbackMeta['epubManifestItems']);
        $fallbackSource = $fallbackItems['source'];
        $fallbackSpine = $fallbackMeta['epubSpineItemRefs'][0];
        $fallbackMarker = $fallbackDocument->children[0]->children[0] ?? new AstNode('missing');

        $t->same('source.fbk', $fallbackMarker->attr('id'));
        $t->same(false, $fallbackSource['readable']);
        $t->same('fallback', $fallbackSource['fallbackId']);
        $t->same(false, $fallbackSource['fallbackMissing']);
        $t->same('fallback.xhtml', $fallbackSource['fallbackHref']);
        $t->same('EPUB/fallback.xhtml', $fallbackSource['fallbackPath']);
        $t->same('application/xhtml+xml', $fallbackSource['fallbackMediaType']);
        $t->same([], $fallbackSource['fallbackProperties']);
        $t->same(false, $fallbackSource['fallbackExternal']);
        $t->same(true, $fallbackSource['fallbackReadable']);
        $t->same('source', $fallbackSpine['idref']);
        $t->same(false, $fallbackSpine['readable']);
        $t->same('fallback', $fallbackSpine['fallbackId']);
        $t->same('EPUB/fallback.xhtml', $fallbackSpine['fallbackPath']);
        $t->same(true, $fallbackSpine['fallbackReadable']);

        $fallbackStyleDocument = $reader->readEpubFile($fixtureRoot . '/manifest-fallback-style.epub');
        $fallbackStyleItems = $itemsById($fallbackStyleDocument->attr('meta')['epubManifestItems']);
        $widget = $fallbackStyleItems['widget'];

        $t->same('fallback', $widget['fallbackId']);
        $t->same('EPUB/fallback.xhtml', $widget['fallbackPath']);
        $t->same(true, $widget['fallbackReadable']);
        $t->same('style', $widget['fallbackStyleId']);
        $t->same(false, $widget['fallbackStyleMissing']);
        $t->same('styles/fallback.css', $widget['fallbackStyleHref']);
        $t->same('EPUB/styles/fallback.css', $widget['fallbackStylePath']);
        $t->same('text/css', $widget['fallbackStyleMediaType']);
        $t->same(false, $widget['fallbackStyleExternal']);
        $t->same(false, $widget['fallbackStyleReadable']);

        $overlayDocument = $reader->readEpubFile($fixtureRoot . '/media-overlay-package.epub');
        $overlayMeta = $overlayDocument->attr('meta');
        $overlayItems = $itemsById($overlayMeta['epubManifestItems']);
        $overlayChapter = $overlayItems['chapter'];
        $overlaySpine = $overlayMeta['epubSpineItemRefs'][0];

        $t->same('mo', $overlayChapter['mediaOverlayId']);
        $t->same(false, $overlayChapter['mediaOverlayMissing']);
        $t->same('overlays/chapter.smil', $overlayChapter['mediaOverlayHref']);
        $t->same('EPUB/overlays/chapter.smil', $overlayChapter['mediaOverlayPath']);
        $t->same('application/smil+xml', $overlayChapter['mediaOverlayMediaType']);
        $t->same([], $overlayChapter['mediaOverlayProperties']);
        $t->same(false, $overlayChapter['mediaOverlayExternal']);
        $t->same(false, $overlayChapter['mediaOverlayReadable']);
        $t->same('chapter', $overlaySpine['idref']);
        $t->same(true, $overlaySpine['readable']);
        $t->same('mo', $overlaySpine['mediaOverlayId']);
        $t->same('EPUB/overlays/chapter.smil', $overlaySpine['mediaOverlayPath']);
    },
    'preserves epub details and summary raw block wrappers from html document dom' => static function (TestRunner $t): void {
        $fixture = __DIR__ . '/../fixtures/upstream-current-epub-reader/epub/xhtml-details-summary-spine.epub';

        $document = (new EpubReader())->readEpubFile($fixture);
        $native = (new NativeWriter(['blocksOnly' => true]))->write($document);

        $t->same([
            'paragraph',
            'heading',
            'raw_html',
            'raw_html',
            'paragraph',
            'raw_html',
            'paragraph',
            'bullet_list',
            'raw_html',
            'paragraph',
        ], array_map(static fn (PortLibs\Pandoc\AstNode $node): string => $node->type, $document->children));
        $t->same('<details open="open">', $document->children[2]->attr('html'));
        $t->same('<summary>', $document->children[3]->attr('html'));
        $t->same('</summary>', $document->children[5]->attr('html'));
        $t->same('</details>', $document->children[8]->attr('html'));
        $t->same('Review summary', $document->children[4]->attr('text'));
        $t->same('After disclosure.', $document->children[9]->attr('text'));
        $t->contains('RawBlock (Format "html") "<details open=\\"open\\">"', $native);
        $t->contains('RawBlock (Format "html") "<summary>"', $native);
        $t->contains('RawBlock (Format "html") "</summary>"', $native);
        $t->contains('BulletList [ [ Plain [ Str "Nested" , Space , Str "disclosure" , Space , Str "item." ]', $native);
        $t->contains('RawBlock (Format "html") "</details>"', $native);
    },
    'preserves CSS style attributes on EPUB nodes that Pandoc represents with Attr values' => static function (TestRunner $t): void {
        $bytes = ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => '<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>'],
            ['name' => 'EPUB/package.opf', 'data' => '<package xmlns="http://www.idpf.org/2007/opf" xmlns:dc="http://purl.org/dc/elements/1.1/"><metadata><dc:identifier id="book">style-attrs</dc:identifier><dc:title>Style attributes</dc:title></metadata><manifest><item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/></manifest><spine><itemref idref="chapter"/></spine></package>'],
            ['name' => 'EPUB/chapter.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><div style="text-align: center"><span style="font-size: large">Title</span></div><p><span>Separator</span></p><table style="width:70%; text-align: center"><tr><td>Cell</td></tr></table><p>Release<br/>' . "\n" . '  Updated</p></body></html>'],
        ], 'epub style attribute regression')->bytes();
        $document = (new EpubReader())->read($bytes);
        $findStyled = static function (AstNode $node, string $type, string $style) use (&$findStyled): ?AstNode {
            if ($node->type === $type && ($node->attr('attributes')['style'] ?? null) === $style) {
                return $node;
            }
            foreach ($node->children as $child) {
                $found = $findStyled($child, $type, $style);
                if ($found instanceof AstNode) {
                    return $found;
                }
            }

            return null;
        };

        $t->true($findStyled($document, 'div', 'text-align: center') instanceof AstNode);
        $t->true($findStyled($document, 'span', 'font-size: large') instanceof AstNode);
        $t->true($findStyled($document, 'table', 'width:70%; text-align: center') instanceof AstNode);

        $plainSpan = null;
        $findPlainSpan = static function (AstNode $node) use (&$plainSpan, &$findPlainSpan): void {
            if ($plainSpan instanceof AstNode) {
                return;
            }
            if (
                $node->type === 'span'
                && $node->attrs === []
                && (($node->children[0] ?? null)?->attr('text') === 'Separator')
            ) {
                $plainSpan = $node;
                return;
            }
            foreach ($node->children as $child) {
                $findPlainSpan($child);
            }
        };
        $findPlainSpan($document);
        $t->true($plainSpan instanceof AstNode);

        $breakParagraph = null;
        foreach ($document->children as $node) {
            if ($node->type === 'paragraph' && array_map(static fn (AstNode $child): string => $child->type, $node->children) === ['text', 'linebreak', 'text']) {
                $breakParagraph = $node;
                break;
            }
        }
        $t->same('Release', $breakParagraph?->children[0]->attr('text'));
        $t->same('Updated', $breakParagraph?->children[2]->attr('text'));
    },
    'keeps valid xhtml self-closing non-void elements empty before the html bridge' => static function (TestRunner $t): void {
        $chapter_xhtml = <<<'XHTML'
<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.1//EN' 'http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd'>
<html xmlns="http://www.w3.org/1999/xhtml"><body><div/><div class="figure"/><h1>Heading</h1><p>After.</p></body></html>
XHTML;
        $bytes = ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => '<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/></rootfiles></container>'],
            ['name' => 'EPUB/package.opf', 'data' => '<package xmlns="http://www.idpf.org/2007/opf" xmlns:dc="http://purl.org/dc/elements/1.1/"><metadata><dc:identifier id="book">self-closing-xhtml</dc:identifier><dc:title>Self-closing XHTML</dc:title></metadata><manifest><item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/></manifest><spine><itemref idref="chapter"/></spine></package>'],
            ['name' => 'EPUB/chapter.xhtml', 'data' => $chapter_xhtml],
        ], 'epub self-closing xhtml regression')->bytes();

        $document = (new EpubReader())->read($bytes);

        $t->same(['paragraph', 'div', 'div', 'heading', 'paragraph'], array_map(
            static fn (AstNode $node): string => $node->type,
            $document->children,
        ));
        $t->same([], $document->children[1]->children);
        $t->same([], $document->children[2]->children);
        $t->same(['figure'], $document->children[2]->attr('classes'));
        $t->same('Heading', $document->children[3]->attr('text'));
        $t->same('After.', $document->children[4]->attr('text'));
    },
    'imports declared epub bytes that are actually xhtml as fallback content' => static function (TestRunner $t): void {
        $xhtml = <<<'HTML'
<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Download page</h1><p>The EPUB download is being prepared.</p></body></html>
HTML;

        $document = (new EpubReader())->read($xhtml);
        $meta = $document->attr('meta');
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('epub', $document->attr('sourceFormat'));
        $t->same('invalid-epub-package', $meta['epubFallback']['reason']);
        $t->same(strlen($xhtml), $meta['epubFallback']['sourceBytes']);
        $t->contains('end-of-central-directory', $meta['epubFallback']['error']);
        $t->contains('<h1 id="download-page">Download page</h1>', $blocks);
        $t->contains('<p>The EPUB download is being prepared.</p>', $blocks);
    },
];
