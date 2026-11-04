<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdtReader;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'reads odt package metadata and body content into shared ast' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-odt-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary ODT path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary ODT package');
        }
        $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.text');
        $zip->addFromString('meta.xml', <<<'XML'
<?xml version="1.0"?>
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0">
  <office:meta>
    <dc:title>ODT Reader Demo</dc:title>
    <dc:creator>Port Libs</dc:creator>
    <dc:description>Bounded ODT reader smoke.</dc:description>
    <meta:keyword>odt</meta:keyword>
  </office:meta>
</office:document-meta>
XML);
        $zip->addFromString('styles.xml', <<<'XML'
<?xml version="1.0"?>
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
  <office:styles>
    <style:style style:name="Bold" style:family="text"><style:text-properties fo:font-weight="bold"/></style:style>
    <style:style style:name="Italic" style:family="text"><style:text-properties fo:font-style="italic"/></style:style>
    <text:list-style style:name="NumberedAlpha">
      <text:list-level-style-number text:level="1" style:num-format="a" style:num-suffix=")"/>
    </text:list-style>
  </office:styles>
</office:document-styles>
XML);
        $zip->addFromString('content.xml', <<<'XML'
<?xml version="1.0"?>
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:automatic-styles>
    <style:style xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" style:name="InlineStrong" style:family="text">
      <style:text-properties xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0" fo:font-weight="bold"/>
    </style:style>
  </office:automatic-styles>
  <office:body>
    <office:text>
      <text:h text:outline-level="1">ODT Reader Demo</text:h>
      <text:p>A <text:span text:style-name="Bold">bold</text:span> and <text:span text:style-name="Italic">italic</text:span> paragraph with <text:a xlink:href="https://example.test">a link</text:a>.</text:p>
      <text:list>
        <text:list-item><text:p>One</text:p></text:list-item>
        <text:list-item><text:p>Two</text:p></text:list-item>
      </text:list>
      <text:list text:style-name="NumberedAlpha" text:start-value="3">
        <text:list-item><text:p>Alpha three</text:p></text:list-item>
        <text:list-item><text:p>Alpha four</text:p></text:list-item>
      </text:list>
      <table:table>
        <table:table-header-rows>
          <table:table-row>
            <table:table-cell table:number-rows-spanned="2"><text:p>Header row span</text:p></table:table-cell>
            <table:table-cell><text:p>Header two</text:p></table:table-cell>
          </table:table-row>
          <table:table-row>
            <table:covered-table-cell/>
            <table:table-cell><text:p>Header follow-up</text:p></table:table-cell>
          </table:table-row>
        </table:table-header-rows>
        <table:table-row>
          <table:table-cell table:number-columns-spanned="2"><text:p>Cell A</text:p></table:table-cell>
          <table:table-cell><text:p>Cell B</text:p></table:table-cell>
        </table:table-row>
      </table:table>
      <text:p><draw:frame><draw:image xlink:href="Pictures/image.png"/></draw:frame></text:p>
    </office:text>
  </office:body>
</office:document-content>
XML);
        $zip->addFromString('Pictures/image.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
        $zip->close();

        try {
            $document = (new OdtReader())->readOdtFile($path);
            $blocks = (new WordPressBlockWriter())->write($document);
            $converterBlocks = PandocConverter::convertFile($path, 'odt', 'blocks');
            $meta = $document->attr('meta');
        } finally {
            @unlink($path);
        }

        $t->same('ODT Reader Demo', $meta['title']);
        $t->same('Port Libs', $meta['author']);
        $t->same('Bounded ODT reader smoke.', $meta['description']);
        $t->same('odt', $meta['keywords']);
        $t->true(($meta['odtTextStyleCount'] ?? 0) >= 2);
        $t->same(1, $meta['odtListStyleCount']);
        $t->true(($meta['odtPackageEntries'] ?? 0) >= 4);
        $t->same(['Pictures/image.png'], $meta['odtReferencedResources']);
        $t->same(['Pictures/image.png'], $meta['odtImageResources']);
        $t->same('bullet_list', $document->children[2]->type);
        $t->same('ordered_list', $document->children[3]->type);
        $t->same(3, $document->children[3]->attr('start'));
        $t->same('lower_alpha', $document->children[3]->attr('style'));
        $t->same('one_paren', $document->children[3]->attr('delimiter'));
        $table = $document->children[4];
        $t->same('table', $table->type);
        $t->same(2, count($table->children[0]->children));
        $t->same('Header row span', $table->children[0]->children[0]->children[0]->attr('text'));
        $t->same(2, $table->children[0]->children[0]->children[0]->attr('rowspan'));
        $t->same('Header follow-up', $table->children[0]->children[1]->children[0]->attr('text'));
        $t->same(2, $table->children[1]->children[0]->children[0]->attr('colspan'));
        $t->contains('<!-- wp:heading {"level":1} -->', $blocks);
        $t->contains('<strong>bold</strong>', $blocks);
        $t->contains('<em>italic</em>', $blocks);
        $t->contains('<a href="https://example.test">a link</a>', $blocks);
        $t->contains('<!-- wp:list {"ordered":true,"start":3} -->', $blocks);
        $t->contains('<ol start="3" type="a"><li>Alpha three</li><li>Alpha four</li></ol>', $blocks);
        $t->contains('<!-- wp:table -->', $blocks);
        $t->contains('<thead><tr><th rowspan="2"><p>Header row span</p></th><th><p>Header two</p></th></tr><tr><th><p>Header follow-up</p></th></tr></thead>', $blocks);
        $t->contains('<td colspan="2"><p>Cell A</p></td>', $blocks);
        $t->contains('Pictures/image.png', $blocks);
        $t->contains('<!-- wp:list -->', $converterBlocks);
    },
    'preserves odt manifest package metadata through the converter-facing reader' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-odt-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary ODT path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary ODT package');
        }
        $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.text');
        $zip->addFromString('META-INF/manifest.xml', <<<'XML'
<?xml version="1.0"?>
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text" manifest:version="1.3"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml" manifest:size="386"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:size="7"/>
  <manifest:file-entry manifest:full-path="Pictures/secret.png" manifest:media-type="image/png" manifest:size="6">
    <manifest:encryption-data/>
  </manifest:file-entry>
  <manifest:file-entry manifest:full-path="Pictures/missing.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML);
        $zip->addFromString('content.xml', <<<'XML'
<?xml version="1.0"?>
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:body>
    <office:text>
      <text:p>Manifest packet <draw:frame><draw:image xlink:href="Pictures/hero.png"/></draw:frame></text:p>
    </office:text>
  </office:body>
</office:document-content>
XML);
        $zip->addFromString('Pictures/hero.png', 'PNGDATA');
        $zip->addFromString('Pictures/secret.png', 'SECRET');
        $zip->close();

        try {
            $document = (new OdtReader())->readOdtFile($path);
            $meta = $document->attr('meta');
        } finally {
            @unlink($path);
        }

        $manifestEntries = [];
        foreach ($meta['odtManifestEntries'] as $entry) {
            $manifestEntries[$entry['fullPath']] = $entry;
        }

        $t->same('1.3', $meta['odtManifestVersion']);
        $t->same(5, $meta['odtManifestEntryCount']);
        $t->same([
            'application/vnd.oasis.opendocument.text' => 1,
            'image/png' => 3,
            'text/xml' => 1,
        ], $meta['odtManifestMediaTypes']);
        $t->same(['Pictures/missing.png'], $meta['odtManifestMissingEntries']);
        $t->same(['Pictures/secret.png'], $meta['odtManifestEncryptedEntries']);
        $t->same(['Pictures/hero.png', 'Pictures/secret.png', 'Pictures/missing.png'], $meta['odtManifestImageResources']);
        $t->same(['Pictures/hero.png'], $meta['odtReferencedResources']);
        $t->same(['Pictures/hero.png', 'Pictures/secret.png'], $meta['odtImageResources']);
        $t->same(true, $manifestEntries['content.xml']['exists']);
        $t->same(386, $manifestEntries['content.xml']['declaredSize']);
        $t->same(false, $manifestEntries['Pictures/missing.png']['exists']);
        $t->same(true, $manifestEntries['Pictures/secret.png']['encrypted']);
        $t->same('Manifest packet', $document->children[0]->attr('text'));
    },
    'reads odt bytes through the converter input path' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-odt-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary ODT path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary ODT package');
        }
        $zip->addFromString('content.xml', '<?xml version="1.0"?><office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"><office:body><office:text><text:h text:outline-level="1">Byte ODT</text:h><text:p>Body.</text:p></office:text></office:body></office:document-content>');
        $zip->close();

        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new RuntimeException('Unable to read temporary ODT package');
            }
            $document = PandocConverter::read($bytes, 'odt');
        } finally {
            @unlink($path);
        }

        $t->same('heading', $document->children[0]->type);
        $t->same('Byte ODT', $document->children[0]->attr('text'));
        $t->same('paragraph', $document->children[1]->type);
    },
    'preserves odt footnotes through the converter input path' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-odt-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary ODT path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary ODT package');
        }
        $zip->addFromString('content.xml', <<<'XML'
<?xml version="1.0"?>
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Paragraph with source note<text:note text:id="ftn1" text:note-class="footnote"><text:note-citation>1</text:note-citation><text:note-body><text:p>ODT footnote body <text:span>with inline text</text:span>.</text:p></text:note-body></text:note> after.</text:p>
      <text:p>Endnote marker<text:note text:id="edn1" text:note-class="endnote"><text:note-citation>i</text:note-citation><text:note-body><text:p>ODT endnote body.</text:p></text:note-body></text:note>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML);
        $zip->close();

        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new RuntimeException('Unable to read temporary ODT package');
            }
            $document = PandocConverter::read($bytes, 'odt');
        } finally {
            @unlink($path);
        }

        $firstParagraph = $document->children[0];
        $secondParagraph = $document->children[1];
        $footnote = $firstParagraph->children[1];
        $endnote = $secondParagraph->children[1];

        $t->same('Paragraph with source note after.', $firstParagraph->attr('text'));
        $t->same('note', $footnote->type);
        $t->same('odt', $footnote->attr('sourceFormat'));
        $t->same('footnote', $footnote->attr('noteClass'));
        $t->same('ftn1', $footnote->attr('id'));
        $t->same('1', $footnote->attr('citation'));
        $t->same('ODT footnote body with inline text.', $footnote->children[0]->attr('text'));
        $t->same('Endnote marker.', $secondParagraph->attr('text'));
        $t->same('note', $endnote->type);
        $t->same('endnote', $endnote->attr('noteClass'));
        $t->same('edn1', $endnote->attr('id'));
        $t->same('i', $endnote->attr('citation'));
        $t->same('ODT endnote body.', $endnote->children[0]->attr('text'));
        $blocks = (new WordPressBlockWriter())->write($document);
        $t->contains('<sup id="fnref-1"><a href="#fn-1" role="doc-noteref">1</a></sup>', $blocks);
        $t->contains('<div class="wp-block-group footnotes" role="doc-endnotes">', $blocks);
        $t->contains('<!-- wp:list {"ordered":true} -->', $blocks);
        $t->contains('ODT footnote body with inline text.', $blocks);
        $t->contains('ODT endnote body.', $blocks);
    },
    'prefers embedded ODT MathML objects over replacement previews through the converter input path' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-odt-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary ODT path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary ODT package');
        }
        $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.text');
        $zip->addFromString('content.xml', <<<'XML'
<?xml version="1.0"?>
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <office:body>
    <office:text>
      <text:p>Before <draw:frame text:anchor-type="as-char"><draw:object xlink:href="./Object 1"/><draw:image xlink:href="./ObjectReplacements/Object 1"/></draw:frame> after.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML);
        $zip->addFromString('Object 1/content.xml', <<<'XML'
<math xmlns="http://www.w3.org/1998/Math/MathML"><semantics><mrow><mi>k</mi><mo>&gt;</mo><mn>1</mn></mrow><annotation encoding="StarMath 5.0">k &gt; 1</annotation></semantics></math>
XML);
        $zip->addFromString('ObjectReplacements/Object 1', 'MATH-PREVIEW');
        $zip->close();

        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new RuntimeException('Unable to read temporary ODT package');
            }
            $document = PandocConverter::read($bytes, 'odt');
        } finally {
            @unlink($path);
        }

        $paragraph = $document->children[0];
        $math = $paragraph->children[1];
        $blocks = (new WordPressBlockWriter(['writerHTMLMathMethod' => 'mathml']))->write($document);

        $t->same('Before k>1 after.', $paragraph->attr('text'));
        $t->same('math', $math->type);
        $t->same(false, $math->attr('display'));
        $t->same('odt-mathml', $math->attr('sourceFormat'));
        $t->same('Object 1', $math->attr('objectPath'));
        $t->same('Object 1/content.xml', $math->attr('sourcePart'));
        $t->same('k>1', $math->attr('text'));
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML">', $math->attr('mathml'));
        $t->same([], $document->attr('meta')['odtReferencedResources']);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML">', $blocks);
        $t->same(false, str_contains($blocks, 'ObjectReplacements/Object 1'));
        $t->same(0, substr_count($blocks, '<img'));
    },
];
