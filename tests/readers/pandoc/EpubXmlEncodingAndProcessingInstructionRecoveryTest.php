<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\EpubReader;
use PortLibs\Pandoc\ZipPackage;

$utf16 = static function (string $source, string $byteOrder, bool $withBom = true): string {
    $encoding = match ($byteOrder) {
        'le' => 'UTF-16LE',
        'be' => 'UTF-16BE',
        default => throw new RuntimeException('Unsupported UTF-16 byte order: ' . $byteOrder),
    };
    $encoded = iconv('UTF-8', $encoding, $source);
    if (!is_string($encoded)) {
        throw new RuntimeException('Unable to encode UTF-16 EPUB fixture part.');
    }

    if (!$withBom) {
        return $encoded;
    }

    return ($byteOrder === 'le' ? "\xFF\xFE" : "\xFE\xFF") . $encoded;
};

$defaultContainer = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="OEBPS/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML;

$defaultPackage = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf" xmlns:dc="http://purl.org/dc/elements/1.1/" version="3.0" unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">encoding-recovery</dc:identifier>
    <dc:title>Encoding Recovery</dc:title>
    <dc:language>en</dc:language>
  </metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>
  </manifest>
  <spine>
    <itemref idref="chapter"/>
  </spine>
</package>
XML;

$defaultChapter = <<<'XHTML'
<?xml version="1.0" encoding="UTF-8"?>
<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Default EPUB chapter.</p></body></html>
XHTML;

$defaultNav = <<<'XHTML'
<?xml version="1.0" encoding="UTF-8"?>
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body><nav epub:type="toc"><ol><li><a href="chapter.xhtml">Default TOC entry</a></li></ol></nav></body>
</html>
XHTML;

$epubBytes = static function (array $overrides = []) use (
    $defaultContainer,
    $defaultPackage,
    $defaultChapter,
    $defaultNav,
): string {
    $parts = array_replace([
        'META-INF/container.xml' => $defaultContainer,
        'OEBPS/package.opf' => $defaultPackage,
        'OEBPS/chapter.xhtml' => $defaultChapter,
        'OEBPS/nav.xhtml' => $defaultNav,
    ], $overrides);
    $entries = [
        ['name' => 'mimetype', 'data' => 'application/epub+zip', 'compressionMethod' => 0],
    ];
    foreach ($parts as $name => $data) {
        $entries[] = ['name' => $name, 'data' => $data];
    }

    return ZipPackage::fromParts($entries, 'epub xml encoding and PI recovery')->bytes();
};

$documentText = static function (AstNode $node) use (&$documentText): string {
    $text = $node->type === 'text' ? (string) $node->attr('text', '') : '';
    foreach ($node->children as $child) {
        $text .= $documentText($child);
    }

    return $text;
};

$utf16Chapter = static function (string $text): string {
    return '<?xml version="1.0" encoding="UTF-16"?>'
        . '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>' . $text . '</p></body></html>';
};

return [
    'imports UTF-16LE BOM XHTML spine content without dropping its text' => static function (TestRunner $t) use ($utf16, $utf16Chapter, $epubBytes, $documentText): void {
        $document = (new EpubReader())->read($epubBytes([
            'OEBPS/chapter.xhtml' => $utf16($utf16Chapter('UTF-16 LE chapter: Café.'), 'le'),
        ]));

        $t->contains('UTF-16 LE chapter: Café.', $documentText($document));
    },
    'imports UTF-16BE BOM XHTML spine content without dropping its text' => static function (TestRunner $t) use ($utf16, $utf16Chapter, $epubBytes, $documentText): void {
        $document = (new EpubReader())->read($epubBytes([
            'OEBPS/chapter.xhtml' => $utf16($utf16Chapter('UTF-16 BE chapter: Café.'), 'be'),
        ]));

        $t->contains('UTF-16 BE chapter: Café.', $documentText($document));
    },
    'imports UTF-16LE XHTML inferred from its no-BOM XML byte signature' => static function (TestRunner $t) use ($utf16, $utf16Chapter, $epubBytes, $documentText): void {
        $document = (new EpubReader())->read($epubBytes([
            'OEBPS/chapter.xhtml' => $utf16($utf16Chapter('No BOM UTF-16 LE chapter.'), 'le', false),
        ]));

        $t->contains('No BOM UTF-16 LE chapter.', $documentText($document));
    },
    'imports UTF-16BE XHTML inferred from its no-BOM XML byte signature' => static function (TestRunner $t) use ($utf16, $utf16Chapter, $epubBytes, $documentText): void {
        $document = (new EpubReader())->read($epubBytes([
            'OEBPS/chapter.xhtml' => $utf16($utf16Chapter('No BOM UTF-16 BE chapter.'), 'be', false),
        ]));

        $t->contains('No BOM UTF-16 BE chapter.', $documentText($document));
    },
    'imports a UTF-16LE BOM container XML before opening the package rootfile' => static function (TestRunner $t) use ($utf16, $epubBytes, $documentText): void {
        $container = <<<'XML'
<?xml version="1.0" encoding="UTF-16"?>
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles><rootfile full-path="OEBPS/package.opf" media-type="application/oebps-package+xml"/></rootfiles>
</container>
XML;
        $document = (new EpubReader())->read($epubBytes([
            'META-INF/container.xml' => $utf16($container, 'le'),
        ]));

        $t->same('Encoding Recovery', $document->attr('meta')['title'] ?? null);
        $t->contains('Default EPUB chapter.', $documentText($document));
    },
    'imports a UTF-16BE BOM OPF package after resolving its UTF-8 container' => static function (TestRunner $t) use ($utf16, $epubBytes, $documentText): void {
        $package = <<<'XML'
<?xml version="1.0" encoding="UTF-16"?>
<package xmlns="http://www.idpf.org/2007/opf" xmlns:dc="http://purl.org/dc/elements/1.1/" version="3.0" unique-identifier="book-id">
  <metadata><dc:identifier id="book-id">utf16-opf</dc:identifier><dc:title>UTF-16 OPF metadata</dc:title><dc:language>en</dc:language></metadata>
  <manifest><item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/></manifest>
  <spine><itemref idref="chapter"/></spine>
</package>
XML;
        $document = (new EpubReader())->read($epubBytes([
            'OEBPS/package.opf' => $utf16($package, 'be'),
        ]));

        $t->same('UTF-16 OPF metadata', $document->attr('meta')['title'] ?? null);
        $t->contains('Default EPUB chapter.', $documentText($document));
    },
    'keeps a UTF-16 nav document as EPUB table-of-contents metadata' => static function (TestRunner $t) use ($utf16, $epubBytes): void {
        $nav = <<<'XHTML'
<?xml version="1.0" encoding="UTF-16"?>
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
  <body><nav epub:type="toc"><ol><li><a href="chapter.xhtml">UTF-16 nav entry</a></li></ol></nav></body>
</html>
XHTML;
        $document = (new EpubReader())->read($epubBytes([
            'OEBPS/nav.xhtml' => $utf16($nav, 'le'),
        ]));

        $t->same('UTF-16 nav entry', $document->attr('meta')['epubTocEntries'][0]['text'] ?? null);
    },
    'keeps a UTF-16 NCX document as EPUB table-of-contents metadata' => static function (TestRunner $t) use ($utf16, $epubBytes): void {
        $package = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf" xmlns:dc="http://purl.org/dc/elements/1.1/" version="2.0" unique-identifier="book-id">
  <metadata><dc:identifier id="book-id">utf16-ncx</dc:identifier><dc:title>UTF-16 NCX</dc:title><dc:language>en</dc:language></metadata>
  <manifest>
    <item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>
    <item id="toc" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
  </manifest>
  <spine toc="toc"><itemref idref="chapter"/></spine>
</package>
XML;
        $ncx = <<<'XML'
<?xml version="1.0" encoding="UTF-16"?>
<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
  <navMap><navPoint id="one" playOrder="1"><navLabel><text>UTF-16 NCX entry</text></navLabel><content src="chapter.xhtml"/></navPoint></navMap>
</ncx>
XML;
        $document = (new EpubReader())->read($epubBytes([
            'OEBPS/package.opf' => $package,
            'OEBPS/toc.ncx' => $utf16($ncx, 'be'),
        ]));

        $t->same('UTF-16 NCX entry', $document->attr('meta')['epubTocEntries'][0]['text'] ?? null);
    },
    'imports XHTML with a generic processing instruction before the document element' => static function (TestRunner $t) use ($epubBytes, $documentText): void {
        $chapter = <<<'XHTML'
<?xml version="1.0" encoding="UTF-8"?>
<?epub-export source="supplier"?>
<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Generic processing instruction chapter.</p></body></html>
XHTML;
        $document = (new EpubReader())->read($epubBytes([
            'OEBPS/chapter.xhtml' => $chapter,
        ]));

        $t->contains('Generic processing instruction chapter.', $documentText($document));
    },
    'imports XHTML with an xml-stylesheet processing instruction and HTML entity fallback' => static function (TestRunner $t) use ($epubBytes, $documentText): void {
        // This is the public-book pattern used throughout FearGoidte's
        // A Guide to Modern Cookery EPUB source (pinned in the import
        // investigation): a normal XML stylesheet PI before XHTML content.
        $chapter = <<<'XHTML'
<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet href="css/book.css" type="text/css"?>
<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Stylesheet PI&nbsp;HTML fallback.</p></body></html>
XHTML;
        $document = (new EpubReader())->read($epubBytes([
            'OEBPS/chapter.xhtml' => $chapter,
        ]));

        $t->contains('Stylesheet PI', $documentText($document));
        $t->contains('HTML fallback.', $documentText($document));
    },
    'continues through a processing-instruction spine chapter between valid chapters' => static function (TestRunner $t) use ($epubBytes, $documentText): void {
        $package = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf" xmlns:dc="http://purl.org/dc/elements/1.1/" version="3.0" unique-identifier="book-id">
  <metadata><dc:identifier id="book-id">pi-spine</dc:identifier><dc:title>PI spine</dc:title><dc:language>en</dc:language></metadata>
  <manifest>
    <item id="first" href="first.xhtml" media-type="application/xhtml+xml"/>
    <item id="middle" href="middle.xhtml" media-type="application/xhtml+xml"/>
    <item id="last" href="last.xhtml" media-type="application/xhtml+xml"/>
  </manifest>
  <spine><itemref idref="first"/><itemref idref="middle"/><itemref idref="last"/></spine>
</package>
XML;
        $middle = <<<'XHTML'
<?xml version="1.0" encoding="UTF-8"?>
<?exporter-state checkpoint="middle"?>
<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Middle PI chapter.</p></body></html>
XHTML;
        $document = (new EpubReader())->read($epubBytes([
            'OEBPS/package.opf' => $package,
            'OEBPS/first.xhtml' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>First chapter.</p></body></html>',
            'OEBPS/middle.xhtml' => $middle,
            'OEBPS/last.xhtml' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Last chapter.</p></body></html>',
        ]));

        $text = $documentText($document);
        $t->contains('First chapter.', $text);
        $t->contains('Middle PI chapter.', $text);
        $t->contains('Last chapter.', $text);
    },
];
