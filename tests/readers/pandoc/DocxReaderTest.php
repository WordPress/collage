<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxReader;
use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$buildDocxReaderPackageBytes = static function (string $documentXml): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-docx-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary DOCX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary DOCX package');
    }
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $zip->addFromString('word/document.xml', $documentXml);
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary DOCX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

/**
 * @param array<string, string> $parts
 */
$buildDocxReaderPackagePartsBytes = static function (array $parts): string {
    $path = tempnam(sys_get_temp_dir(), 'pandoc-docx-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temporary DOCX path');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        throw new RuntimeException('Unable to create temporary DOCX package');
    }

    foreach ($parts as $name => $contents) {
        $zip->addFromString($name, $contents);
    }
    $zip->close();

    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read temporary DOCX package');
        }

        return $bytes;
    } finally {
        @unlink($path);
    }
};

/**
 * @param array<string, string> $parts
 * @param array<string, int> $compressionMethodsByName
 */
$buildDocxReaderNativeZipPackageBytes = static function (array $parts, array $compressionMethodsByName = []): string {
    $body = '';
    $central = '';
    $entryCount = 0;

    foreach ($parts as $name => $contents) {
        $method = $compressionMethodsByName[$name] ?? 8;
        $compressed = match ($method) {
            0 => $contents,
            8 => gzdeflate($contents),
            default => $contents,
        };
        if (!is_string($compressed)) {
            throw new RuntimeException("Unable to deflate DOCX fixture entry {$name}");
        }

        $crc32 = (int) sprintf('%u', crc32($contents));
        $offset = strlen($body);
        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            0x0800,
            $method,
            0,
            0,
            $crc32,
            strlen($compressed),
            strlen($contents),
            strlen($name),
            0,
        );
        $body .= $name . $compressed;

        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            0x0800,
            $method,
            0,
            0,
            $crc32,
            strlen($compressed),
            strlen($contents),
            strlen($name),
            0,
            0,
            0,
            0,
            0,
            $offset,
        );
        $central .= $name;
        ++$entryCount;
    }

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, $entryCount, $entryCount, strlen($central), strlen($body), 0);
};

return [
    'returns a diagnostic document for corrupt docx package bytes' => static function (TestRunner $t): void {
        $bytes = "not a zip package\nplain text payload";

        $document = (new DocxReader())->read($bytes);
        $meta = $document->attr('meta');

        $t->same('docx', $document->attr('sourceFormat'));
        $t->same('invalid-docx-package', $meta['docxFallback']['reason']);
        $t->same(strlen($bytes), $meta['docxFallback']['sourceBytes']);
        $t->same(hash('sha256', $bytes), $meta['docxFallback']['sourceSha256']);
        $t->same('code_block', $document->children[0]->type);
        $t->same($bytes, $document->children[0]->attr('text'));
        $t->same(['docx-source'], $document->children[0]->attr('classes'));
    },

    'maps leading docx metadata styles without consuming after-normal body paragraphs' => static function (TestRunner $t) use ($buildDocxReaderPackagePartsBytes): void {
        $bytes = $buildDocxReaderPackagePartsBytes([
            '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>',
            'word/styles.xml' => '<?xml version="1.0"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/></w:style><w:style w:type="paragraph" w:styleId="Author"><w:name w:val="Author"/></w:style><w:style w:type="paragraph" w:styleId="Date"><w:name w:val="Date"/></w:style><w:style w:type="paragraph" w:styleId="Abstract"><w:name w:val="Abstract"/></w:style></w:styles>',
            'word/document.xml' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:pPr><w:pStyle w:val="Title"/></w:pPr><w:r><w:t>Leading Title</w:t></w:r></w:p><w:p><w:pPr><w:pStyle w:val="Author"/></w:pPr><w:r><w:t>Mary Ann Evans</w:t></w:r></w:p><w:p><w:pPr><w:pStyle w:val="Author"/></w:pPr><w:r><w:t>Aurore Dupin</w:t></w:r></w:p><w:p><w:pPr><w:pStyle w:val="Date"/></w:pPr><w:r><w:t>July 28, 2014</w:t></w:r></w:p><w:p><w:pPr><w:pStyle w:val="Abstract"/></w:pPr><w:r><w:t>Leading abstract text.</w:t></w:r></w:p><w:p><w:r><w:t>Normal body.</w:t></w:r></w:p><w:p><w:pPr><w:pStyle w:val="Title"/></w:pPr><w:r><w:t>Visible After Title</w:t></w:r></w:p><w:p><w:pPr><w:pStyle w:val="Author"/></w:pPr><w:r><w:t>Visible After Author</w:t></w:r></w:p><w:p><w:pPr><w:pStyle w:val="Date"/></w:pPr><w:r><w:t>Visible After Date</w:t></w:r></w:p><w:p><w:pPr><w:pStyle w:val="Abstract"/></w:pPr><w:r><w:t>Visible after abstract.</w:t></w:r></w:p></w:body></w:document>',
        ]);

        $document = (new DocxReader(['preserveRunStyles' => true]))->read($bytes);
        $meta = $document->attr('meta');

        $t->same('Leading Title', $meta['title']);
        $t->same(['Mary Ann Evans', 'Aurore Dupin'], $meta['author']);
        $t->same('July 28, 2014', $meta['date']);
        $t->same('MetaInlines', $meta['abstract']['type']);
        $t->same('Leading abstract text.', $meta['abstract']['value'][0]->attr('text'));
        $t->same([
            'Normal body.',
            'Visible After Title',
            'Visible After Author',
            'Visible After Date',
            'Visible after abstract.',
        ], array_map(static fn ($block): string => (string) $block->attr('text', ''), $document->children));
    },
    'attaches ranged docx comments to range starts and preserves scrubbed revision metadata' => static function (TestRunner $t) use ($buildDocxReaderPackagePartsBytes): void {
        $bytes = $buildDocxReaderPackagePartsBytes([
            '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/></Types>',
            'word/comments.xml' => '<?xml version="1.0"?><w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:comment w:id="3" w:author="Author"><w:p><w:r><w:t>With a comment!</w:t></w:r></w:p></w:comment></w:comments>',
            'word/document.xml' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t xml:space="preserve">Here is a </w:t></w:r><w:del w:id="1" w:author="Author"><w:r><w:delText>dummy</w:delText></w:r></w:del><w:ins w:id="2" w:author="Author"><w:r><w:t>test</w:t></w:r></w:ins><w:r><w:t xml:space="preserve"> </w:t></w:r><w:commentRangeStart w:id="3"/><w:r><w:t>document</w:t></w:r><w:commentRangeEnd w:id="3"/><w:r><w:commentReference w:id="3"/></w:r><w:r><w:t>.</w:t></w:r></w:p></w:body></w:document>',
        ]);

        $paragraph = (new DocxReader())->read($bytes)->children[0];
        $children = $paragraph->children;
        $types = array_map(static fn ($node): string => $node->type, $children);

        $t->same(['text', 'span', 'span', 'text', 'span', 'text', 'span', 'text'], $types);
        $t->same(['deletion'], $children[1]->attr('classes'));
        $t->same(['author' => 'Author'], $children[1]->attr('attributes'));
        $t->same(['insertion'], $children[2]->attr('classes'));
        $t->same(['author' => 'Author'], $children[2]->attr('attributes'));
        $t->same(['comment-start'], $children[4]->attr('classes'));
        $t->same(['id' => '3', 'author' => 'Author'], $children[4]->attr('attributes'));
        $t->same('With a comment!', $children[4]->children[0]->attr('text'));
        $t->same(['comment-end'], $children[6]->attr('classes'));
        $t->same(['id' => '3'], $children[6]->attr('attributes'));
        $t->same([], array_values(array_filter($children, static fn ($node): bool => $node->type === 'note')));

        $noCommentsParagraph = (new DocxReader(['commentsMode' => 'accept']))->read($bytes)->children[0];
        $noCommentsChildren = $noCommentsParagraph->children;
        $noCommentsClasses = [];
        foreach ($noCommentsChildren as $child) {
            $classes = $child->attr('classes', []);
            if (is_array($classes)) {
                array_push($noCommentsClasses, ...$classes);
            }
        }

        $t->same('Here is a dummytest document.', $noCommentsParagraph->attr('text'));
        $t->same(['text', 'span', 'span', 'text'], array_map(static fn ($node): string => $node->type, $noCommentsChildren));
        $t->same(['deletion'], $noCommentsChildren[1]->attr('classes'));
        $t->same(['insertion'], $noCommentsChildren[2]->attr('classes'));
        $t->same([], array_values(array_filter($noCommentsChildren, static fn ($node): bool => $node->type === 'note')));
        $t->true(!in_array('comment-start', $noCommentsClasses, true), 'accepted comments should not expose comment-start spans');
        $t->true(!in_array('comment-end', $noCommentsClasses, true), 'accepted comments should not expose comment-end spans');
        $t->throws(InvalidArgumentException::class, static fn (): DocxReader => new DocxReader(['commentsMode' => 'merge']));
    },
    'nests adjacent docx comment range ends to match native inline granularity' => static function (TestRunner $t) use ($buildDocxReaderPackagePartsBytes): void {
        $bytes = $buildDocxReaderPackagePartsBytes([
            '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/></Types>',
            'word/comments.xml' => '<?xml version="1.0"?><w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:comment w:id="7" w:author="Outer"><w:p><w:r><w:t>Outer note.</w:t></w:r></w:p></w:comment><w:comment w:id="8" w:author="Inner"><w:p><w:r><w:t>Inner note.</w:t></w:r></w:p></w:comment></w:comments>',
            'word/document.xml' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>One </w:t></w:r><w:commentRangeStart w:id="7"/><w:r><w:t>outer </w:t></w:r><w:commentRangeStart w:id="8"/><w:r><w:t>inner</w:t></w:r><w:commentRangeEnd w:id="7"/><w:commentRangeEnd w:id="8"/><w:r><w:t>.</w:t></w:r></w:p></w:body></w:document>',
        ]);

        $paragraph = (new DocxReader())->read($bytes)->children[0];
        $outerEnd = $paragraph->children[5];

        $t->same(['text', 'span', 'text', 'span', 'text', 'span', 'text'], array_map(static fn ($node): string => $node->type, $paragraph->children));
        $t->same('inner', $paragraph->children[4]->attr('text'));
        $t->same(['comment-end'], $outerEnd->attr('classes'));
        $t->same(['id' => '7'], $outerEnd->attr('attributes'));
        $t->same(1, count($outerEnd->children));
        $t->same(['comment-end'], $outerEnd->children[0]->attr('classes'));
        $t->same(['id' => '8'], $outerEnd->children[0]->attr('attributes'));
    },
    'records bounded docx comment formatting warnings only for preserved comments' => static function (TestRunner $t) use ($buildDocxReaderPackagePartsBytes): void {
        $bytes = $buildDocxReaderPackagePartsBytes([
            '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/></Types>',
            'word/comments.xml' => '<?xml version="1.0"?><w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:comment w:id="9" w:author="Reviewer"><w:p><w:r><w:t>Table follows:</w:t></w:r></w:p><w:tbl><w:tr><w:tc><w:p><w:r><w:t>A</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>B</w:t></w:r></w:p></w:tc></w:tr></w:tbl></w:comment></w:comments>',
            'word/document.xml' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t xml:space="preserve">A </w:t></w:r><w:commentRangeStart w:id="9"/><w:r><w:t>comment</w:t></w:r><w:commentRangeEnd w:id="9"/><w:r><w:commentReference w:id="9"/></w:r><w:r><w:t>.</w:t></w:r></w:p></w:body></w:document>',
        ]);

        $preserveMeta = (new DocxReader(['revisionMode' => 'preserve']))->read($bytes)->attr('meta');
        $rejectMeta = (new DocxReader(['revisionMode' => 'reject']))->read($bytes)->attr('meta');

        $t->same(['Docx comment 9 will not retain formatting'], $preserveMeta['docxWarnings']);
        $t->same(1, $preserveMeta['docxWarningCount']);
        $t->same([], $rejectMeta['docxWarnings']);
        $t->same(0, $rejectMeta['docxWarningCount']);
    },
    'resolves docx tracked revisions by configured revision mode' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t xml:space="preserve">Base </w:t></w:r><w:ins w:author="Insert Reviewer" w:date="2026-06-26T12:00:00Z"><w:r><w:t xml:space="preserve">inserted </w:t></w:r></w:ins><w:del w:author="Delete Reviewer" w:date="2026-06-26T12:01:00Z"><w:r><w:delText xml:space="preserve">deleted </w:delText></w:r></w:del><w:moveFrom w:id="9" w:author="Move Reviewer" w:date="2026-06-26T12:02:00Z"><w:r><w:delText xml:space="preserve">moved-from </w:delText></w:r></w:moveFrom><w:moveTo w:id="9" w:author="Move Reviewer" w:date="2026-06-26T12:03:00Z"><w:r><w:t xml:space="preserve">moved-to </w:t></w:r></w:moveTo><w:r><w:t>tail</w:t></w:r></w:p></w:body></w:document>');

        $preserveDocument = (new DocxReader(['revisionMode' => 'preserve']))->read($bytes);
        $preserve = $preserveDocument->children[0];
        $preserveBlocks = (new WordPressBlockWriter())->write($preserveDocument);
        $t->same('Base inserted deleted moved-from moved-to tail', $preserve->attr('text'));
        $t->same(['insertion'], $preserve->children[1]->attr('classes'));
        $t->same(['deletion'], $preserve->children[2]->attr('classes'));
        $t->same(['deletion', 'move-from'], $preserve->children[3]->attr('classes'));
        $t->same(['insertion', 'move-to'], $preserve->children[4]->attr('classes'));
        $t->contains('<ins class="insertion" data-pandoc-change-author="Insert Reviewer"', $preserveBlocks);
        $t->contains('<del class="deletion" data-pandoc-change-author="Delete Reviewer"', $preserveBlocks);
        $t->contains('<del class="deletion move-from" data-pandoc-change-author="Move Reviewer"', $preserveBlocks);
        $t->contains('<ins class="insertion move-to" data-pandoc-change-author="Move Reviewer"', $preserveBlocks);

        $acceptDocument = (new DocxReader(['revisionMode' => 'accept']))->read($bytes);
        $accept = $acceptDocument->children[0];
        $acceptBlocks = (new WordPressBlockWriter())->write($acceptDocument);
        $t->same('Base inserted moved-to tail', $accept->attr('text'));
        $t->same(1, count($accept->children));
        $t->same('text', $accept->children[0]->type);
        $t->true(!str_contains($acceptBlocks, '<ins'), 'accepted revisions should not preserve insertion spans');
        $t->true(!str_contains($acceptBlocks, '<del'), 'accepted revisions should not preserve deletion spans');
        $t->true(!str_contains($acceptBlocks, 'deleted'), 'accepted revisions should drop deleted text');
        $t->true(!str_contains($acceptBlocks, 'moved-from'), 'accepted revisions should drop moveFrom text');

        $rejectDocument = PandocConverter::read($bytes, 'docx', ['revisionMode' => 'reject']);
        $reject = $rejectDocument->children[0];
        $rejectBlocks = (new WordPressBlockWriter())->write($rejectDocument);
        $t->same('Base deleted moved-from tail', $reject->attr('text'));
        $t->same(1, count($reject->children));
        $t->same('text', $reject->children[0]->type);
        $t->true(!str_contains($rejectBlocks, '<ins'), 'rejected revisions should not preserve insertion spans');
        $t->true(!str_contains($rejectBlocks, '<del'), 'rejected revisions should not preserve deletion spans');
        $t->true(!str_contains($rejectBlocks, 'inserted'), 'rejected revisions should drop inserted text');
        $t->true(!str_contains($rejectBlocks, 'moved-to'), 'rejected revisions should drop moveTo text');

        $t->throws(InvalidArgumentException::class, static fn (): DocxReader => new DocxReader(['revisionMode' => 'merge']));
    },
    'reads docx soft and non-breaking hyphen run markers' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Soft hyphen: [</w:t><w:softHyphen/><w:t>]</w:t></w:r></w:p><w:p><w:r><w:t>Non-breaking hyphen: [</w:t><w:noBreakHyphen/><w:t>]</w:t></w:r></w:p></w:body></w:document>');

        $document = (new DocxReader(['preserveRunStyles' => true]))->read($bytes);
        $softHyphen = "\u{00AD}";
        $nonBreakingHyphen = "\u{2011}";

        $t->same("Soft hyphen: [{$softHyphen}]", $document->children[0]->attr('text'));
        $t->same("Soft hyphen: [{$softHyphen}]", $document->children[0]->children[0]->attr('text'));
        $t->same("Non-breaking hyphen: [{$nonBreakingHyphen}]", $document->children[1]->attr('text'));
        $t->same("Non-breaking hyphen: [{$nonBreakingHyphen}]", $document->children[1]->children[0]->attr('text'));
    },
    'decodes docx symbol font run characters' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:sym w:font="Symbol" w:char="00DA"/></w:r><w:r><w:sym w:font="Symbol" w:char="F0DA"/></w:r><w:r><w:rPr><w:rFonts w:ascii="Symbol" w:hAnsi="Symbol"/></w:rPr><w:t></w:t></w:r><w:r><w:t> </w:t></w:r></w:p></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);

        $t->same("∨∨( ", $document->children[0]->attr('text'));
        $t->same("∨∨( ", $document->children[0]->children[0]->attr('text'));
    },
    'reads docx verbatim character style as inline code' => static function (TestRunner $t) use ($buildDocxReaderPackagePartsBytes): void {
        $bytes = $buildDocxReaderPackagePartsBytes([
            '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>',
            'word/styles.xml' => '<?xml version="1.0"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:style w:type="character" w:styleId="VerbatimChar"><w:name w:val="Verbatim Char"/></w:style></w:styles>',
            'word/document.xml' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t xml:space="preserve">Use </w:t></w:r><w:r><w:rPr><w:rStyle w:val="VerbatimChar"/></w:rPr><w:t xml:space="preserve">inline   code</w:t></w:r><w:r><w:t xml:space="preserve"> here.</w:t></w:r></w:p></w:body></w:document>',
        ]);

        $paragraph = (new DocxReader())->read($bytes)->children[0];

        $t->same('Use inline code here.', $paragraph->attr('text'));
        $t->same(['text', 'code', 'text'], array_map(static fn ($node): string => $node->type, $paragraph->children));
        $t->same('inline   code', $paragraph->children[1]->attr('text'));
    },
    'preserves docx run colors highlights and font sizes as wordpress inline styles' => static function (TestRunner $t) use ($buildDocxReaderPackagePartsBytes): void {
        $bytes = $buildDocxReaderPackagePartsBytes([
            '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>',
            'word/styles.xml' => '<?xml version="1.0"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:style w:type="character" w:styleId="AlertRun"><w:name w:val="Alert Run"/><w:rPr><w:color w:val="C2410C"/><w:highlight w:val="cyan"/><w:sz w:val="20"/></w:rPr></w:style></w:styles>',
            'word/document.xml' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t xml:space="preserve">Before </w:t></w:r><w:r><w:rPr><w:color w:val="1F2937"/><w:highlight w:val="yellow"/><w:sz w:val="28"/></w:rPr><w:t>Direct</w:t></w:r><w:r><w:t xml:space="preserve"> and </w:t></w:r><w:r><w:rPr><w:rStyle w:val="AlertRun"/></w:rPr><w:t>Inherited</w:t></w:r><w:r><w:t>.</w:t></w:r></w:p></w:body></w:document>',
        ]);

        $document = (new DocxReader(['preserveRunStyles' => true]))->read($bytes);
        $paragraph = $document->children[0];
        $wordpress = (new WordPressBlockWriter())->write($document);

        $t->same('Before Direct and Inherited.', $paragraph->attr('text'));
        $t->contains('<span style="color:#1f2937; font-size:14pt"><mark style="background-color:#ffff00">Direct</mark></span>', $wordpress);
        $t->contains('<span style="color:#c2410c; font-size:10pt"><mark style="background-color:#00ffff">Inherited</mark></span>', $wordpress);
        $t->true(!str_contains($wordpress, 'w:highlight'), 'WordprocessingML styling should not leak into WordPress markup.');
    },
    'preserves docx paragraph indentation spacing and page breaks for wordpress imports' => static function (TestRunner $t) use ($buildDocxReaderPackagePartsBytes): void {
        $bytes = $buildDocxReaderPackagePartsBytes([
            '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>',
            'word/styles.xml' => '<?xml version="1.0"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:style w:type="paragraph" w:styleId="IndentedBody"><w:name w:val="Indented Body"/><w:pPr><w:ind w:left="720" w:right="360" w:firstLine="240"/><w:spacing w:before="120" w:after="240" w:line="360" w:lineRule="auto"/></w:pPr></w:style></w:styles>',
            'word/document.xml' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:pPr><w:pStyle w:val="IndentedBody"/></w:pPr><w:r><w:t>Styled layout.</w:t></w:r></w:p><w:p><w:pPr><w:pStyle w:val="IndentedBody"/><w:ind w:left="1440" w:hanging="360"/><w:spacing w:after="0" w:line="480" w:lineRule="exact"/><w:pageBreakBefore/></w:pPr><w:r><w:t>Direct layout.</w:t></w:r></w:p></w:body></w:document>',
        ]);

        $document = (new DocxReader(['preserveRunStyles' => true]))->read($bytes);
        $wordpress = (new WordPressBlockWriter())->write($document);

        $t->same(['style' => 'margin-left:36pt; margin-right:18pt; text-indent:12pt; margin-top:6pt; margin-bottom:12pt; line-height:1.5'], $document->children[0]->attr('htmlAttributes'));
        $t->same(['style' => 'margin-left:72pt; margin-right:18pt; text-indent:-18pt; margin-top:6pt; margin-bottom:0pt; line-height:24pt; break-before:page; page-break-before:always'], $document->children[1]->attr('htmlAttributes'));
        $t->contains('<p style="margin-left:36pt; margin-right:18pt; margin-top:6pt; margin-bottom:12pt; text-indent:12pt; line-height:1.5">Styled layout.</p>', $wordpress);
        $t->contains('<p style="margin-left:72pt; margin-right:18pt; margin-top:6pt; margin-bottom:0pt; text-indent:-18pt; line-height:24pt; break-before:page; page-break-before:always">Direct layout.</p>', $wordpress);
    },
    'preserves docx table width alignment fill and borders for wordpress imports' => static function (TestRunner $t) use ($buildDocxReaderPackagePartsBytes): void {
        $bytes = $buildDocxReaderPackagePartsBytes([
            '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>',
            'word/styles.xml' => '<?xml version="1.0"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:style w:type="table" w:styleId="DataTable"><w:name w:val="Data Table"/><w:tblPr><w:shd w:fill="E2F0D9"/><w:jc w:val="center"/></w:tblPr></w:style></w:styles>',
            'word/document.xml' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl><w:tblPr><w:tblStyle w:val="DataTable"/><w:tblW w:w="5000" w:type="pct"/><w:jc w:val="right"/><w:shd w:fill="FFF2CC"/><w:tblBorders><w:top w:val="single" w:sz="16" w:color="4472C4"/></w:tblBorders></w:tblPr><w:tblGrid><w:gridCol w:w="2400"/><w:gridCol w:w="2400"/></w:tblGrid><w:tr><w:tc><w:p><w:r><w:t>A</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>B</w:t></w:r></w:p></w:tc></w:tr></w:tbl></w:body></w:document>',
        ]);

        $document = (new DocxReader(['preserveRunStyles' => true]))->read($bytes);
        $table = $document->children[0];
        $wordpress = (new WordPressBlockWriter())->write($document);

        $t->same('table', $table->type);
        $t->same('right', $table->attr('htmlAttributes')['align']);
        $t->same('100%', $table->attr('htmlAttributes')['width']);
        $t->same('background-color:#FFF2CC; border-style:solid; border-width:2pt; border-color:#4472C4', $table->attr('htmlAttributes')['style']);
        $t->contains('<table align="right" width="100%" style="background-color:#fff2cc; border-color:#4472c4; border-style:solid; border-width:2pt"', $wordpress);
        $t->contains('data-docx-table-style="DataTable"', $wordpress);
    },
    'preserves docx paragraph and heading alignment for wordpress native attrs' => static function (TestRunner $t) use ($buildDocxReaderPackagePartsBytes): void {
        $bytes = $buildDocxReaderPackagePartsBytes([
            '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>',
            'word/styles.xml' => '<?xml version="1.0"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:style w:type="paragraph" w:styleId="CenteredBody"><w:name w:val="Centered Body"/><w:pPr><w:jc w:val="center"/><w:shd w:fill="D9EAF7"/></w:pPr></w:style><w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:pPr><w:jc w:val="right"/><w:shd w:fill="EEECE1"/></w:pPr></w:style></w:styles>',
            'word/document.xml' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:pPr><w:pStyle w:val="CenteredBody"/></w:pPr><w:r><w:t>Centered by style.</w:t></w:r></w:p><w:p><w:pPr><w:pStyle w:val="CenteredBody"/><w:jc w:val="both"/><w:shd w:fill="FFF2CC"/></w:pPr><w:r><w:t>Justified directly.</w:t></w:r></w:p><w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Right heading</w:t></w:r></w:p></w:body></w:document>',
        ]);

        $document = (new DocxReader())->read($bytes);
        $blocks = $document->children;
        $wordpress = (new WordPressBlockWriter())->write($document);

        $t->same('paragraph', $blocks[0]->type);
        $t->same('center', $blocks[0]->attr('align'));
        $t->same('#D9EAF7', $blocks[0]->attr('backgroundColor'));
        $t->same('justify', $blocks[1]->attr('align'));
        $t->same('#FFF2CC', $blocks[1]->attr('backgroundColor'));
        $t->same('heading', $blocks[2]->type);
        $t->same('right', $blocks[2]->attr('align'));
        $t->same('#EEECE1', $blocks[2]->attr('backgroundColor'));
        $t->contains('<!-- wp:paragraph {"align":"center","style":{"color":{"background":"#D9EAF7"}}} -->', $wordpress);
        $t->contains('<!-- wp:paragraph {"align":"justify","style":{"color":{"background":"#FFF2CC"}}} -->', $wordpress);
        $t->contains('<!-- wp:heading {"level":1,"textAlign":"right","style":{"color":{"background":"#EEECE1"}}} -->', $wordpress);
        $t->contains('<p class="has-text-align-center" style="background-color:#D9EAF7">Centered by style.</p>', $wordpress);
        $t->contains('<h1 id="right-heading" class="has-text-align-right" style="background-color:#EEECE1">Right heading</h1>', $wordpress);
    },
    'nests docx direct underline inside strong and emphasis wrappers' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:rPr><w:i/><w:u w:val="single"/></w:rPr><w:t>italic underlined</w:t></w:r></w:p><w:p><w:r><w:rPr><w:b/><w:u w:val="single"/></w:rPr><w:t>bold underlined</w:t></w:r></w:p><w:p><w:r><w:rPr><w:b/><w:i/><w:u w:val="single"/></w:rPr><w:t>bold italic underlined</w:t></w:r></w:p></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);
        $italicUnderline = $document->children[0]->children[0];
        $boldUnderline = $document->children[1]->children[0];
        $boldItalicUnderline = $document->children[2]->children[0];

        $t->same('emph', $italicUnderline->type);
        $t->same('underline', $italicUnderline->children[0]->type);
        $t->same('italic underlined', $italicUnderline->children[0]->children[0]->attr('text'));
        $t->same('strong', $boldUnderline->type);
        $t->same('underline', $boldUnderline->children[0]->type);
        $t->same('bold underlined', $boldUnderline->children[0]->children[0]->attr('text'));
        $t->same('emph', $boldItalicUnderline->type);
        $t->same('strong', $boldItalicUnderline->children[0]->type);
        $t->same('underline', $boldItalicUnderline->children[0]->children[0]->type);
        $t->same('bold italic underlined', $boldItalicUnderline->children[0]->children[0]->children[0]->attr('text'));
    },
    'wraps docx verbatim superscript and subscript around inline code' => static function (TestRunner $t) use ($buildDocxReaderPackagePartsBytes): void {
        $bytes = $buildDocxReaderPackagePartsBytes([
            '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>',
            'word/styles.xml' => '<?xml version="1.0"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:style w:type="character" w:styleId="VerbatimChar"><w:name w:val="Verbatim Char"/></w:style></w:styles>',
            'word/document.xml' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>m</w:t></w:r><w:r><w:rPr><w:rStyle w:val="VerbatimChar"/><w:vertAlign w:val="superscript"/></w:rPr><w:t>2</w:t></w:r></w:p><w:p><w:r><w:t>m</w:t></w:r><w:r><w:rPr><w:rStyle w:val="VerbatimChar"/><w:vertAlign w:val="subscript"/></w:rPr><w:t>2</w:t></w:r></w:p></w:body></w:document>',
        ]);

        $document = (new DocxReader())->read($bytes);
        $superscript = $document->children[0]->children[1];
        $subscript = $document->children[1]->children[1];

        $t->same('superscript', $superscript->type);
        $t->same('code', $superscript->children[0]->type);
        $t->same('2', $superscript->children[0]->attr('text'));
        $t->same('subscript', $subscript->type);
        $t->same('code', $subscript->children[0]->type);
        $t->same('2', $subscript->children[0]->attr('text'));
    },
    'trims docx paragraph boundary text while preserving interior spaces' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t xml:space="preserve">  List paragraph with more indent </w:t></w:r></w:p><w:p><w:r><w:t xml:space="preserve">Keep </w:t></w:r><w:r><w:t>middle</w:t></w:r><w:r><w:t xml:space="preserve"> space </w:t></w:r></w:p></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);

        $t->same('List paragraph with more indent', $document->children[0]->attr('text'));
        $t->same('List paragraph with more indent', $document->children[0]->children[0]->attr('text'));
        $t->same('Keep middle space', $document->children[1]->attr('text'));
        $t->same('Keep middle space', $document->children[1]->children[0]->attr('text'));
    },
    'normalizes docx scalar text whitespace at inline boundaries' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t xml:space="preserve">And back to the normal </w:t></w:r><w:r><w:t xml:space="preserve"> style.</w:t></w:r></w:p><w:p><w:r><w:t xml:space="preserve">State of Documentation </w:t></w:r></w:p><w:p><w:r><w:tab/></w:r><w:r><w:t>Tab-indented text.</w:t></w:r></w:p><w:p><w:r><w:t xml:space="preserve">See Table 5.1. </w:t></w:r></w:p><w:p><w:r><w:t xml:space="preserve">Trailing </w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t xml:space="preserve">formatting   </w:t></w:r></w:p><w:p><w:r><w:t xml:space="preserve">Turn my </w:t></w:r><w:r><w:rPr><w:i/></w:rPr><w:t xml:space="preserve">formatting </w:t></w:r><w:r><w:t>off after the spaces.</w:t></w:r></w:p></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);
        $paragraphs = $document->children;

        $t->same('And back to the normal style.', $paragraphs[0]->attr('text'));
        $t->same('And back to the normal style.', $paragraphs[0]->children[0]->attr('text'));
        $t->same('State of Documentation', $paragraphs[1]->attr('text'));
        $t->same('State of Documentation', $paragraphs[1]->children[0]->attr('text'));
        $t->same('Tab-indented text.', $paragraphs[2]->attr('text'));
        $t->same('Tab-indented text.', $paragraphs[2]->children[0]->attr('text'));
        $t->same('See Table 5.1.', $paragraphs[3]->attr('text'));
        $t->same('See Table 5.1.', $paragraphs[3]->children[0]->attr('text'));
        $t->same('Trailing formatting', $paragraphs[4]->attr('text'));
        $t->same('Trailing ', $paragraphs[4]->children[0]->attr('text'));
        $t->same('strong', $paragraphs[4]->children[1]->type);
        $t->same('formatting', $paragraphs[4]->children[1]->children[0]->attr('text'));
        $t->same('Turn my formatting off after the spaces.', $paragraphs[5]->attr('text'));
        $t->same('Turn my ', $paragraphs[5]->children[0]->attr('text'));
        $t->same('emph', $paragraphs[5]->children[1]->type);
        $t->same('formatting', $paragraphs[5]->children[1]->children[0]->attr('text'));
        $t->same(' off after the spaces.', $paragraphs[5]->children[2]->attr('text'));
    },
    'merges docx drop-cap frame paragraphs into following paragraph text' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:pPr><w:framePr w:dropCap="drop" w:lines="3"/></w:pPr><w:r><w:t>D</w:t></w:r></w:p><w:p><w:r><w:t>rop cap.</w:t></w:r></w:p><w:p><w:r><w:t>Next paragraph.</w:t></w:r></w:p><w:p><w:pPr><w:framePr w:dropCap="margin" w:lines="3"/></w:pPr><w:r><w:t>D</w:t></w:r></w:p><w:p><w:r><w:t>rop cap in margin.</w:t></w:r></w:p><w:p><w:r><w:t>Drop cap (not really).</w:t></w:r></w:p></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);

        $t->same(['Drop cap.', 'Next paragraph.', 'Drop cap in margin.', 'Drop cap (not really).'], array_map(static fn ($node): string => (string) $node->attr('text', ''), $document->children));
    },
    'resolves docx paragraph style numbering and explicit numbering suppression' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/styles.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="ReviewStep">
    <w:name w:val="Review Step"/>
    <w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="42"/></w:numPr></w:pPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="DerivedReviewStep">
    <w:name w:val="Derived Review Step"/>
    <w:basedOn w:val="ReviewStep"/>
  </w:style>
  <w:style w:type="paragraph" w:styleId="ChecklistItem">
    <w:name w:val="Checklist Item"/>
  </w:style>
</w:styles>
XML],
            ['name' => 'word/numbering.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="7">
    <w:lvl w:ilvl="0"><w:start w:val="5"/><w:numFmt w:val="lowerLetter"/><w:lvlText w:val="%1)"/></w:lvl>
  </w:abstractNum>
  <w:num w:numId="42"><w:abstractNumId w:val="7"/></w:num>
  <w:abstractNum w:abstractNumId="8">
    <w:lvl w:ilvl="0"><w:pStyle w:val="ChecklistItem"/><w:numFmt w:val="bullet"/><w:lvlText w:val="-"/></w:lvl>
  </w:abstractNum>
  <w:num w:numId="43"><w:abstractNumId w:val="8"/></w:num>
</w:numbering>
XML],
            ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="DerivedReviewStep"/></w:pPr><w:r><w:t>Review source</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="DerivedReviewStep"/></w:pPr><w:r><w:t>Publish migration</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="ChecklistItem"/></w:pPr><w:r><w:t>Check footers</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="DerivedReviewStep"/><w:numPr><w:numId w:val="0"/></w:numPr></w:pPr><w:r><w:t>Plain exception</w:t></w:r></w:p>
  </w:body>
</w:document>
XML],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);
        $ordered = $document->children[0];
        $bullet = $document->children[1];
        $suppressed = $document->children[2];

        $t->same('ordered_list', $ordered->type);
        $t->true(!array_key_exists('numId', $ordered->attrs), 'DOCX list numId should not be exposed as a Pandoc list attr');
        $t->true(!array_key_exists('level', $ordered->attrs), 'DOCX list level should not be exposed as a Pandoc list attr');
        $t->same(5, $ordered->attr('start'));
        $t->same('lower_alpha', $ordered->attr('style'));
        $t->same('one_paren', $ordered->attr('delimiter'));
        $t->same('Review source', $ordered->children[0]->children[0]->attr('text'));
        $t->same('Publish migration', $ordered->children[1]->children[0]->attr('text'));
        $t->same('bullet_list', $bullet->type);
        $t->true(!array_key_exists('numId', $bullet->attrs), 'DOCX bullet numId should not be exposed as a Pandoc list attr');
        $t->true(!array_key_exists('level', $bullet->attrs), 'DOCX bullet level should not be exposed as a Pandoc list attr');
        $t->same('Check footers', $bullet->children[0]->children[0]->attr('text'));
        $t->same('paragraph', $suppressed->type);
        $t->same('Plain exception', $suppressed->attr('text'));
        $t->contains('<ol start="5" type="a"><li>Review source</li><li>Publish migration</li></ol>', $blocks);
        $t->contains('<ul><li>Check footers</li></ul>', $blocks);
        $t->contains('<p>Plain exception</p>', $blocks);
    },
    'maps compact styled docx list item paragraphs to plain blocks' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/styles.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="CompactList">
    <w:name w:val="Compact"/>
  </w:style>
  <w:style w:type="paragraph" w:styleId="BodyList">
    <w:name w:val="Body List"/>
  </w:style>
</w:styles>
XML],
            ['name' => 'word/numbering.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="10">
    <w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="decimal"/><w:lvlText w:val="%1."/></w:lvl>
  </w:abstractNum>
  <w:abstractNum w:abstractNumId="11">
    <w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="decimal"/><w:lvlText w:val="%1."/></w:lvl>
  </w:abstractNum>
  <w:num w:numId="10"><w:abstractNumId w:val="10"/></w:num>
  <w:num w:numId="11"><w:abstractNumId w:val="11"/></w:num>
</w:numbering>
XML],
            ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="CompactList"/><w:numPr><w:ilvl w:val="0"/><w:numId w:val="10"/></w:numPr></w:pPr><w:r><w:t>Compact one</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="CompactList"/><w:numPr><w:ilvl w:val="0"/><w:numId w:val="10"/></w:numPr></w:pPr><w:r><w:t>Compact two</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="BodyList"/><w:numPr><w:ilvl w:val="0"/><w:numId w:val="11"/></w:numPr></w:pPr><w:r><w:t>Regular one</w:t></w:r></w:p>
  </w:body>
</w:document>
XML],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $native = (new NativeWriter())->write($document);
        $compact = $document->children[0];
        $regular = $document->children[1];

        $t->same(['ordered_list', 'ordered_list'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('plain', $compact->children[0]->children[0]->type);
        $t->same('plain', $compact->children[1]->children[0]->type);
        $t->same('Compact one', $compact->children[0]->children[0]->attr('text'));
        $t->same('Compact two', $compact->children[1]->children[0]->attr('text'));
        $t->same('paragraph', $regular->children[0]->children[0]->type);
        $t->same('Regular one', $regular->children[0]->children[0]->attr('text'));
        $t->contains('Plain [ Str "Compact" , Space , Str "one" ]', $native);
        $t->contains('Para [ Str "Regular" , Space , Str "one" ]', $native);
    },
    'keeps explicitly suppressed same-style numbering as a list item continuation' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/styles.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="ListNumber">
    <w:name w:val="List Number"/>
    <w:pPr><w:numPr><w:numId w:val="7"/></w:numPr></w:pPr>
  </w:style>
</w:styles>
XML],
            ['name' => 'word/numbering.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="8">
    <w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="decimal"/><w:pStyle w:val="ListNumber"/><w:lvlText w:val="%1."/></w:lvl>
  </w:abstractNum>
  <w:num w:numId="7"><w:abstractNumId w:val="8"/></w:num>
</w:numbering>
XML],
            ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="ListNumber"/></w:pPr><w:r><w:t>One</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="ListNumber"/><w:numPr><w:numId w:val="0"/></w:numPr></w:pPr><w:r><w:t>Two</w:t></w:r><w:r><w:br/></w:r><w:r><w:br/><w:t>Three</w:t></w:r></w:p>
  </w:body>
</w:document>
XML],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $native = (new NativeWriter())->write($document);
        $list = $document->children[0];
        $item = $list->children[0];

        $t->same(1, count($document->children));
        $t->same('ordered_list', $list->type);
        $t->same(1, count($list->children));
        $t->same(2, count($item->children));
        $t->same('One', $item->children[0]->attr('text'));
        $t->contains('Para [ Str "Two" , LineBreak , LineBreak , Str "Three" ]', $native);
    },
    'continues direct numbering through same-style unnumbered paragraphs' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/styles.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="Bodytext21">
    <w:name w:val="Body Text 2"/>
  </w:style>
</w:styles>
XML],
            ['name' => 'word/numbering.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="8">
    <w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="decimal"/><w:lvlText w:val="%1."/></w:lvl>
  </w:abstractNum>
  <w:num w:numId="7"><w:abstractNumId w:val="8"/></w:num>
</w:numbering>
XML],
            ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="Bodytext21"/><w:numPr><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>Foo</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="Bodytext21"/><w:numPr><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>Bar</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="Bodytext21"/><w:numPr><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>Baz</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="Bodytext21"/></w:pPr><w:r><w:t>Interruption.</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="Bodytext21"/><w:numPr><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>Bop</w:t></w:r></w:p>
  </w:body>
</w:document>
XML],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(['ordered_list', 'paragraph', 'ordered_list'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Foo', $document->children[0]->children[0]->children[0]->attr('text'));
        $t->same('Baz', $document->children[0]->children[2]->children[0]->attr('text'));
        $t->same('Interruption.', $document->children[1]->attr('text'));
        $t->same(4, $document->children[2]->attr('start'));
        $t->same('Bop', $document->children[2]->children[0]->children[0]->attr('text'));
        $t->contains('<ol><li>Foo</li><li>Bar</li><li>Baz</li></ol>', $blocks);
        $t->contains('<p>Interruption.</p>', $blocks);
        $t->contains('<ol start="4"><li>Bop</li></ol>', $blocks);
    },
    'keeps indented list interruptions between restarted ordered lists as blockquotes' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/numbering.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="8">
    <w:lvl w:ilvl="0"><w:start w:val="2"/><w:numFmt w:val="decimal"/><w:lvlText w:val="%1."/></w:lvl>
  </w:abstractNum>
  <w:abstractNum w:abstractNumId="9">
    <w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="decimal"/><w:lvlText w:val="%1."/></w:lvl>
  </w:abstractNum>
  <w:num w:numId="4"><w:abstractNumId w:val="8"/></w:num>
  <w:num w:numId="5"><w:abstractNumId w:val="9"/></w:num>
</w:numbering>
XML],
            ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="4"/></w:numPr></w:pPr><w:r><w:t>Foo</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="4"/></w:numPr></w:pPr><w:r><w:t>Bar</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="4"/></w:numPr></w:pPr><w:r><w:t>Baz</w:t></w:r></w:p>
    <w:p><w:pPr><w:ind w:left="360"/></w:pPr></w:p>
    <w:p><w:pPr><w:ind w:left="360"/><w:outlineLvl w:val="2"/></w:pPr><w:r><w:t>Interruption</w:t></w:r></w:p>
    <w:p><w:pPr><w:ind w:left="360"/></w:pPr></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="5"/></w:numPr></w:pPr><w:r><w:t>Bop.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(['ordered_list', 'blockquote', 'ordered_list'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(2, $document->children[0]->attr('start'));
        $t->same('Foo', $document->children[0]->children[0]->children[0]->attr('text'));
        $t->same('Baz', $document->children[0]->children[2]->children[0]->attr('text'));
        $t->same('paragraph', $document->children[1]->children[0]->type);
        $t->same('Interruption', $document->children[1]->children[0]->attr('text'));
        $t->same(1, $document->children[2]->attr('start'));
        $t->same('Bop.', $document->children[2]->children[0]->children[0]->attr('text'));
        $t->contains('<ol start="2"><li>Foo</li><li>Bar</li><li>Baz</li></ol>', $blocks);
        $t->contains('<blockquote class="wp-block-quote">', $blocks);
        $t->contains('<p>Interruption</p>', $blocks);
        $t->contains('<ol><li>Bop.</li></ol>', $blocks);
    },
    'keeps indented explicitly suppressed list paragraphs with the active item' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/numbering.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="8">
    <w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/><w:lvlText w:val="•"/></w:lvl>
    <w:lvl w:ilvl="1"><w:numFmt w:val="bullet"/><w:lvlText w:val="◦"/></w:lvl>
  </w:abstractNum>
  <w:num w:numId="7"><w:abstractNumId w:val="8"/></w:num>
</w:numbering>
XML],
            ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>one</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="1"/><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>four</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="0"/></w:numPr><w:ind w:left="1920"/></w:pPr><w:r><w:t>Sub paragraph</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>two</w:t></w:r></w:p>
  </w:body>
</w:document>
XML],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);
        $list = $document->children[0];
        $firstItem = $list->children[0];
        $nestedList = $firstItem->children[1];
        $nestedItem = $nestedList->children[0];

        $t->same(['bullet_list'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(2, count($list->children));
        $t->same('one', $firstItem->children[0]->attr('text'));
        $t->same('bullet_list', $nestedList->type);
        $t->same(2, count($nestedItem->children));
        $t->same('four', $nestedItem->children[0]->attr('text'));
        $t->same('Sub paragraph', $nestedItem->children[1]->attr('text'));
        $t->same('two', $list->children[1]->children[0]->attr('text'));
        $t->true(!str_contains($blocks, '<blockquote>'), 'suppressed indented list continuations should not become blockquotes');
    },
    'preserves docx task-list numbering glyphs and blank bullet continuations' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/numbering.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="990">
    <w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/><w:lvlText w:val=" "/></w:lvl>
    <w:lvl w:ilvl="1"><w:numFmt w:val="bullet"/><w:lvlText w:val=" "/></w:lvl>
    <w:lvl w:ilvl="2"><w:numFmt w:val="bullet"/><w:lvlText w:val=" "/></w:lvl>
  </w:abstractNum>
  <w:abstractNum w:abstractNumId="992">
    <w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/><w:lvlText w:val="☐"/></w:lvl>
    <w:lvl w:ilvl="1"><w:numFmt w:val="bullet"/><w:lvlText w:val="☐"/></w:lvl>
    <w:lvl w:ilvl="2"><w:numFmt w:val="bullet"/><w:lvlText w:val="☐"/></w:lvl>
  </w:abstractNum>
  <w:abstractNum w:abstractNumId="993">
    <w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/><w:lvlText w:val="☒"/></w:lvl>
    <w:lvl w:ilvl="1"><w:numFmt w:val="bullet"/><w:lvlText w:val="☒"/></w:lvl>
    <w:lvl w:ilvl="2"><w:numFmt w:val="bullet"/><w:lvlText w:val="☒"/></w:lvl>
  </w:abstractNum>
  <w:abstractNum w:abstractNumId="994">
    <w:lvl w:ilvl="3"><w:start w:val="1"/><w:numFmt w:val="decimal"/><w:lvlText w:val="%4."/></w:lvl>
  </w:abstractNum>
  <w:num w:numId="1000"><w:abstractNumId w:val="990"/></w:num>
  <w:num w:numId="1001"><w:abstractNumId w:val="992"/></w:num>
  <w:num w:numId="1002"><w:abstractNumId w:val="993"/></w:num>
  <w:num w:numId="1003"><w:abstractNumId w:val="992"/></w:num>
  <w:num w:numId="1004"><w:abstractNumId w:val="993"/></w:num>
  <w:num w:numId="1005"><w:abstractNumId w:val="992"/></w:num>
  <w:num w:numId="1006"><w:abstractNumId w:val="994"/></w:num>
</w:numbering>
XML],
            ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1001"/></w:numPr></w:pPr><w:r><w:t>Unchecked</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1002"/></w:numPr></w:pPr><w:r><w:t>Checked</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1000"/></w:numPr></w:pPr><w:r><w:t>with continuation paragraph</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1003"/></w:numPr></w:pPr><w:r><w:t>Unchecked</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="1"/><w:numId w:val="1004"/></w:numPr></w:pPr><w:r><w:t>Checked sublist</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="2"/><w:numId w:val="1005"/></w:numPr></w:pPr><w:r><w:t>Unchecked subsublist</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="3"/><w:numId w:val="1006"/></w:numPr></w:pPr><w:r><w:t>Numbered child</w:t></w:r></w:p>
  </w:body>
</w:document>
XML],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $native = (new NativeWriter())->write($document);
        $list = $document->children[0];
        $checkedItem = $list->children[1];
        $nestedChecked = $list->children[2]->children[1];
        $nestedUnchecked = $nestedChecked->children[0]->children[1];
        $numberedChild = $nestedUnchecked->children[0]->children[1];

        $t->same('bullet_list', $list->type);
        $t->same(3, count($list->children));
        $t->same("☐ Unchecked", $list->children[0]->children[0]->attr('text'));
        $t->same("☒ Checked", $checkedItem->children[0]->attr('text'));
        $t->same('with continuation paragraph', $checkedItem->children[1]->attr('text'));
        $t->same("☐ Unchecked", $list->children[2]->children[0]->attr('text'));
        $t->same('bullet_list', $nestedChecked->type);
        $t->same("☒ Checked sublist", $nestedChecked->children[0]->children[0]->attr('text'));
        $t->same('bullet_list', $nestedUnchecked->type);
        $t->same("☐ Unchecked subsublist", $nestedUnchecked->children[0]->children[0]->attr('text'));
        $t->same('ordered_list', $numberedChild->type);
        $t->same('Numbered child', $numberedChild->children[0]->children[0]->attr('text'));
        $t->contains('Str "\\9744" , Space , Str "Unchecked"', $native);
        $t->contains('Str "\\9746" , Space , Str "Checked"', $native);
        $t->contains('Para [ Str "with" , Space , Str "continuation" , Space , Str "paragraph" ]', $native);
        $t->contains('OrderedList ( 1 , Decimal , Period )', $native);
    },
    'reads current upstream docx list restart fixture as restarted lists' => static function (TestRunner $t): void {
        $root = dirname(__DIR__) . '/fixtures/upstream-current-docx';
        $docxPath = $root . '/lists_restart_8367.docx';
        $nativePath = $root . '/lists_restart_8367.native';

        $t->same('82a7d9ef72325b53a2a2de927406f17ed6cc9625e74a339d523c11434d756a0b', hash_file('sha256', $docxPath));
        $t->same('23c7fa0b06905e5702f2cb1f7aa6a619ffea3b4365366c9a2ebfe7531472ba15', hash_file('sha256', $nativePath));

        $document = (new DocxReader())->readDocxFile($docxPath);
        $native = (string) file_get_contents($nativePath);

        $t->same(6, count($document->children));
        $t->same('heading', $document->children[0]->type);
        $t->same('Section 1', $document->children[0]->attr('text'));
        $t->same('section-1', $document->children[0]->attr('id'));
        $t->same('ordered_list', $document->children[1]->type);
        $t->same(3, count($document->children[1]->children));
        $t->same(1, $document->children[1]->attr('start'));
        $t->true(!array_key_exists('numId', $document->children[1]->attrs), 'first restarted list should not expose DOCX numId');
        $t->true(!array_key_exists('level', $document->children[1]->attrs), 'first restarted list should not expose DOCX level');
        $t->same('Item 1', $document->children[1]->children[0]->children[0]->attr('text'));
        $t->same('Item 3', $document->children[1]->children[2]->children[0]->attr('text'));
        $t->same('Conclusion', $document->children[2]->attr('text'));
        $t->same('Section 2', $document->children[3]->attr('text'));
        $t->same('section-2', $document->children[3]->attr('id'));
        $t->same('ordered_list', $document->children[4]->type);
        $t->same(4, count($document->children[4]->children));
        $t->same(1, $document->children[4]->attr('start'));
        $t->true(!array_key_exists('numId', $document->children[4]->attrs), 'second restarted list should not expose DOCX numId');
        $t->true(!array_key_exists('level', $document->children[4]->attrs), 'second restarted list should not expose DOCX level');
        $t->same('Item 1', $document->children[4]->children[0]->children[0]->attr('text'));
        $t->same('Item 4', $document->children[4]->children[3]->children[0]->attr('text'));
        $t->same('Conclusion', $document->children[5]->attr('text'));
        $t->contains('OrderedList (1,Decimal,Period)', $native);
    },
    'reads current upstream ns0 prefixed docx reference package' => static function (TestRunner $t): void {
        $path = dirname(__DIR__) . '/fixtures/upstream-current-docx/ns0-reference.docx';

        $t->same('5310462e44b2b601a259d1b0ae83c8407f81a9e966b623d235c9240ce7929d67', hash_file('sha256', $path));

        $document = (new DocxReader())->readDocxFile($path);
        $meta = $document->attr('meta');

        $t->same(1, count($document->children));
        $t->same('paragraph', $document->children[0]->type);
        $t->same('ref', $document->children[0]->attr('text'));
        $t->same(5, $meta['docxPackageEntries']);
        $t->same(1, $meta['docxRelationshipCount']);
    },
    'resolves numeric docx style ids without losing inherited properties' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/styles.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="1">
    <w:name w:val="Numeric Heading Base"/>
    <w:pPr><w:outlineLvl w:val="0"/></w:pPr>
  </w:style>
  <w:style w:type="paragraph" w:styleId="2">
    <w:name w:val="Numeric Heading Derived"/>
    <w:basedOn w:val="1"/>
  </w:style>
  <w:style w:type="character" w:styleId="3">
    <w:name w:val="Numeric Strong Base"/>
    <w:rPr><w:b/></w:rPr>
  </w:style>
  <w:style w:type="character" w:styleId="4">
    <w:name w:val="Numeric Strong Derived"/>
    <w:basedOn w:val="3"/>
    <w:rPr><w:i/></w:rPr>
  </w:style>
</w:styles>
XML],
            ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="2"/></w:pPr><w:r><w:t>Numeric style heading</w:t></w:r></w:p>
    <w:p><w:r><w:t xml:space="preserve">A </w:t></w:r><w:r><w:rPr><w:rStyle w:val="4"/></w:rPr><w:t>derived run</w:t></w:r></w:p>
  </w:body>
</w:document>
XML],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('heading', $document->children[0]->type);
        $t->same(1, $document->children[0]->attr('level'));
        $t->same('numeric-style-heading', $document->children[0]->attr('id'));
        $t->same(['Numeric-Heading-Derived'], $document->children[0]->attr('classes'));
        $t->same('Numeric style heading', $document->children[0]->attr('text'));
        $t->same('paragraph', $document->children[1]->type);
        $t->same('A derived run', $document->children[1]->attr('text'));
        $t->same('emph', $document->children[1]->children[1]->type);
        $t->same('strong', $document->children[1]->children[1]->children[0]->type);
        $t->same('derived run', $document->children[1]->children[1]->children[0]->children[0]->attr('text'));
        $t->contains('<h1 id="numeric-style-heading" class="Numeric-Heading-Derived">Numeric style heading</h1>', $blocks);
        $t->contains('A <em><strong>derived run</strong></em>', $blocks);
    },
    'reads docx package body metadata notes headers footers and review spans into shared ast' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-docx-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary DOCX path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary DOCX package');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/><Override PartName="/word/footnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml"/><Override PartName="/word/endnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.endnotes+xml"/><Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/><Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/><Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test" TargetMode="External"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image1.png"/></Relationships>');
        $zip->addFromString('word/styles.xml', '<?xml version="1.0"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:pPr><w:outlineLvl w:val="0"/></w:pPr></w:style><w:style w:type="character" w:styleId="StrongStyle"><w:rPr><w:b/></w:rPr></w:style></w:styles>');
        $zip->addFromString('word/numbering.xml', '<?xml version="1.0"?><w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:abstractNum w:abstractNumId="0"><w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/></w:lvl></w:abstractNum><w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num></w:numbering>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/"><dc:title>DOCX Reader Demo</dc:title><dc:creator>Port Libs</dc:creator><dc:description>Bounded DOCX reader smoke.</dc:description><dcterms:created>2026-06-18T00:00:00Z</dcterms:created></cp:coreProperties>');
        $zip->addFromString('word/footnotes.xml', '<?xml version="1.0"?><w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:footnote w:id="1"><w:p><w:r><w:t>Footnote body.</w:t></w:r></w:p></w:footnote></w:footnotes>');
        $zip->addFromString('word/endnotes.xml', '<?xml version="1.0"?><w:endnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:endnote w:id="2"><w:p><w:r><w:t>Endnote body.</w:t></w:r></w:p></w:endnote></w:endnotes>');
        $zip->addFromString('word/comments.xml', '<?xml version="1.0"?><w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:comment w:id="3" w:author="Reviewer" w:date="2026-06-18T00:00:00Z"><w:p><w:r><w:t>Comment body.</w:t></w:r></w:p></w:comment></w:comments>');
        $zip->addFromString('word/header1.xml', '<?xml version="1.0"?><w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Header text.</w:t></w:r></w:p></w:hdr>');
        $zip->addFromString('word/footer1.xml', '<?xml version="1.0"?><w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Footer text.</w:t></w:r></w:p></w:ftr>');
        $zip->addFromString('word/media/image1.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><w:body><w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>DOCX Reader Demo</w:t></w:r></w:p><w:p><w:r><w:t>A </w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>bold</w:t></w:r><w:r><w:t> and </w:t></w:r><w:r><w:rPr><w:i/></w:rPr><w:t>italic</w:t></w:r><w:r><w:t> run with </w:t></w:r><w:hyperlink r:id="rId1"><w:r><w:t>a link</w:t></w:r></w:hyperlink><w:r><w:t>, a footnote</w:t></w:r><w:r><w:footnoteReference w:id="1"/></w:r><w:r><w:t>, an endnote</w:t></w:r><w:r><w:endnoteReference w:id="2"/></w:r><w:r><w:t>, and a comment</w:t></w:r><w:r><w:commentReference w:id="3"/></w:r><w:r><w:t>.</w:t></w:r><w:ins w:author="Editor" w:date="2026-06-18T00:00:00Z"><w:r><w:t> inserted text</w:t></w:r></w:ins><w:del w:author="Editor" w:date="2026-06-18T00:00:00Z"><w:r><w:delText> removed text</w:delText></w:r></w:del></w:p><w:p><w:pPr><w:numPr><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>First list item</w:t></w:r></w:p><w:p><w:pPr><w:numPr><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>Second list item</w:t></w:r></w:p><w:tbl><w:tr><w:tc><w:p><w:r><w:t>Cell A</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Cell B</w:t></w:r></w:p></w:tc></w:tr></w:tbl><w:p><w:r><w:drawing><wp:inline><a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rId2"/></pic:blipFill></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p></w:body></w:document>');
        $zip->close();

        try {
            $document = (new DocxReader())->readDocxFile($path);
            $blocks = (new WordPressBlockWriter())->write($document);
            $converterBlocks = PandocConverter::convertFile($path, 'docx', 'blocks');
            $meta = $document->attr('meta');
        } finally {
            @unlink($path);
        }

        $t->same('DOCX Reader Demo', $meta['title']);
        $t->same('Port Libs', $meta['author']);
        $t->same('Bounded DOCX reader smoke.', $meta['description']);
        $t->same(1, $meta['docxFootnotes']);
        $t->same(1, $meta['docxEndnotes']);
        $t->same(1, $meta['docxComments']);
        $t->same(1, $meta['docxHeaders']);
        $t->same(1, $meta['docxFooters']);
        $noteParagraph = $document->children[1];
        $notes = array_values(array_filter($noteParagraph->children, static fn ($node): bool => $node->type === 'note'));
        $t->same(3, count($notes));
        $t->same('Footnote body.', $notes[0]->children[0]->attr('text'));
        $t->same('Endnote body.', $notes[1]->children[0]->attr('text'));
        $t->same('Comment body.', $notes[2]->children[0]->attr('text'));
        $t->true(!in_array('superscript', array_map(static fn ($node): string => $node->type, $noteParagraph->children), true), 'DOCX note references should not be wrapped in superscript AST nodes');
        $t->true(!str_contains($blocks, 'class="docx-header"'), 'Header parts should remain out of normal DOCX reader body output');
        $t->true(!str_contains($blocks, 'Header text.'), 'Header text should remain metadata-only in normal DOCX reader output');
        $t->true(!str_contains($blocks, 'Footer text.'), 'Footer text should remain metadata-only in normal DOCX reader output');
        $t->contains('<strong>bold</strong>', $blocks);
        $t->contains('<em>italic</em>', $blocks);
        $t->contains('<a href="https://example.test">a link</a>', $blocks);
        $t->contains('Footnote body.', $blocks);
        $t->contains('Endnote body.', $blocks);
        $t->contains('Comment body.', $blocks);
        $t->contains('<ins', $blocks);
        $t->contains('<del', $blocks);
        $t->contains('<!-- wp:table -->', $blocks);
        $t->contains('media/image1.png', $blocks);
        $t->contains('<!-- wp:list -->', $converterBlocks);
    },
    'selects section-specific docx header and footer references' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/_rels/document.xml.rels', 'data' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdHeader1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/><Relationship Id="rIdFooter1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/><Relationship Id="rIdHeader2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header2.xml"/><Relationship Id="rIdFooter2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer2.xml"/><Relationship Id="rIdHeaderUnused" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header3.xml"/><Relationship Id="rIdFooterUnused" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer3.xml"/></Relationships>'],
            ['name' => 'word/header1.xml', 'data' => '<?xml version="1.0"?><w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Section one header</w:t></w:r></w:p></w:hdr>'],
            ['name' => 'word/header2.xml', 'data' => '<?xml version="1.0"?><w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Section two even header</w:t></w:r></w:p></w:hdr>'],
            ['name' => 'word/header3.xml', 'data' => '<?xml version="1.0"?><w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Unreferenced header</w:t></w:r></w:p></w:hdr>'],
            ['name' => 'word/footer1.xml', 'data' => '<?xml version="1.0"?><w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Section one first footer</w:t></w:r></w:p></w:ftr>'],
            ['name' => 'word/footer2.xml', 'data' => '<?xml version="1.0"?><w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Section two footer</w:t></w:r></w:p></w:ftr>'],
            ['name' => 'word/footer3.xml', 'data' => '<?xml version="1.0"?><w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Unreferenced footer</w:t></w:r></w:p></w:ftr>'],
            ['name' => 'word/document.xml', 'data' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body><w:p><w:r><w:t>Section one body</w:t></w:r><w:pPr><w:sectPr><w:headerReference w:type="default" r:id="rIdHeader1"/><w:footerReference w:type="first" r:id="rIdFooter1"/></w:sectPr></w:pPr></w:p><w:p><w:r><w:t>Section two body</w:t></w:r></w:p><w:sectPr><w:headerReference w:type="even" r:id="rIdHeader2"/><w:footerReference w:type="default" r:id="rIdFooter2"/></w:sectPr></w:body></w:document>'],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);
        $meta = $document->attr('meta');

        $t->same(2, $meta['docxHeaders']);
        $t->same(2, $meta['docxFooters']);
        $t->same(3, $meta['docxHeaderPartCount']);
        $t->same(3, $meta['docxFooterPartCount']);
        $t->same(4, $meta['docxSectionReferenceCount']);
        $t->same(['word/header1.xml', 'word/header2.xml'], $meta['docxAppliedHeaderFiles']);
        $t->same(['word/footer1.xml', 'word/footer2.xml'], $meta['docxAppliedFooterFiles']);
        $t->same('default', $meta['docxSectionReferences'][0]['headers'][0]['type']);
        $t->same('first', $meta['docxSectionReferences'][0]['footers'][0]['type']);
        $t->same('even', $meta['docxSectionReferences'][1]['headers'][0]['type']);

        $t->same(2, count($document->children));
        $t->same('Section one body', $document->children[0]->attr('text'));
        $t->same('Section two body', $document->children[1]->attr('text'));

        $t->true(!str_contains($blocks, 'Section one header'), 'Section-referenced header parts should remain out of normal DOCX reader body output');
        $t->true(!str_contains($blocks, 'data-docx-section-index='), 'Section header/footer attributes should not be emitted in normal body output');
        $t->true(!str_contains($blocks, 'data-docx-section-reference-type='), 'Section header/footer reference attributes should not be emitted in normal body output');
        $t->true(!str_contains($blocks, 'Section two footer'), 'Section-referenced footer parts should remain out of normal DOCX reader body output');
        $t->true(!str_contains($blocks, 'Unreferenced header'), 'Unreferenced header parts should not be emitted when section references are available');
        $t->true(!str_contains($blocks, 'Unreferenced footer'), 'Unreferenced footer parts should not be emitted when section references are available');
    },
    'tracks related section header and footer targets without leaking nested header anchors' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/_rels/document.xml.rels', 'data' => <<<'XML'
<?xml version="1.0"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rCustomHeader" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="headers/custom-header.xml"/>
  <Relationship Id="rCustomFooter" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footers/custom-footer.xml"/>
</Relationships>
XML],
            ['name' => 'word/headers/custom-header.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:p>
    <w:r><w:fldChar w:fldCharType="begin"/></w:r>
    <w:r><w:instrText> HYPERLINK "https://example.test/header" </w:instrText></w:r>
    <w:r><w:fldChar w:fldCharType="separate"/></w:r>
    <w:r><w:t xml:space="preserve">Header page </w:t></w:r>
    <w:r><w:fldChar w:fldCharType="begin"/></w:r>
    <w:r><w:instrText> PAGEREF HeaderOnly \h </w:instrText></w:r>
    <w:r><w:fldChar w:fldCharType="separate"/></w:r>
    <w:r><w:t>1</w:t></w:r>
    <w:r><w:fldChar w:fldCharType="end"/></w:r>
    <w:r><w:fldChar w:fldCharType="end"/></w:r>
    <w:bookmarkStart w:id="10" w:name="HeaderOnly"/>
    <w:r><w:t>Header anchored label</w:t></w:r>
    <w:bookmarkEnd w:id="10"/>
  </w:p>
</w:hdr>
XML],
            ['name' => 'word/footers/custom-footer.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:p><w:r><w:t>Footer boundary text</w:t></w:r></w:p>
</w:ftr>
XML],
            ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:body>
    <w:p><w:hyperlink w:anchor="BodyAnchor"><w:r><w:t>Body jump</w:t></w:r></w:hyperlink></w:p>
    <w:p><w:bookmarkStart w:id="20" w:name="BodyAnchor"/><w:r><w:t>Body target</w:t></w:r><w:bookmarkEnd w:id="20"/></w:p>
    <w:sectPr><w:headerReference w:type="default" r:id="rCustomHeader"/><w:footerReference w:type="default" r:id="rCustomFooter"/></w:sectPr>
  </w:body>
</w:document>
XML],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);
        $meta = $document->attr('meta');
        $bodyLink = $document->children[0]->children[0];
        $bodyTarget = $document->children[1];

        $t->same(1, $meta['docxHeaders']);
        $t->same(1, $meta['docxFooters']);
        $t->same(1, $meta['docxHeaderPartCount']);
        $t->same(1, $meta['docxFooterPartCount']);
        $t->same(['word/headers/custom-header.xml'], $meta['docxHeaderFiles']);
        $t->same(['word/footers/custom-footer.xml'], $meta['docxFooterFiles']);
        $t->same(['word/headers/custom-header.xml'], $meta['docxAppliedHeaderFiles']);
        $t->same(['word/footers/custom-footer.xml'], $meta['docxAppliedFooterFiles']);
        $t->same('word/headers/custom-header.xml', $meta['docxSectionReferences'][0]['headers'][0]['part']);
        $t->same('word/footers/custom-footer.xml', $meta['docxSectionReferences'][0]['footers'][0]['part']);

        $t->same('link', $bodyLink->type);
        $t->same('#BodyAnchor', $bodyLink->attr('url'));
        $t->same('span', $bodyTarget->children[0]->type);
        $t->same('BodyAnchor', $bodyTarget->children[0]->attr('id'));
        $t->same(['anchor'], $bodyTarget->children[0]->attr('classes'));
        $t->contains('<a href="#BodyAnchor">Body jump</a>', $blocks);
        $t->contains('<span id="BodyAnchor" class="anchor" data-pandoc-anchor="empty-target"></span>Body target', $blocks);
        $t->true(!str_contains($blocks, 'Header page'), 'Nested header link labels should stay out of normal body output');
        $t->true(!str_contains($blocks, 'HeaderOnly'), 'Header-only anchors should not promote body anchors or leak into body output');
        $t->true(!str_contains($blocks, 'Footer boundary text'), 'Related footer parts should stay metadata-only');
    },
    'keeps header-only nested anchors metadata-only without fallback body text' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/_rels/document.xml.rels', 'data' => <<<'XML'
<?xml version="1.0"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rHeader" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>
</Relationships>
XML],
            ['name' => 'word/header1.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:p>
    <w:r><w:fldChar w:fldCharType="begin"/></w:r>
    <w:r><w:instrText> HYPERLINK "https://example.test/header" </w:instrText></w:r>
    <w:r><w:fldChar w:fldCharType="separate"/></w:r>
    <w:r><w:t xml:space="preserve">Header page </w:t></w:r>
    <w:r><w:fldChar w:fldCharType="begin"/></w:r>
    <w:r><w:instrText> PAGEREF HeaderOnly \h </w:instrText></w:r>
    <w:r><w:fldChar w:fldCharType="separate"/></w:r>
    <w:r><w:t>1</w:t></w:r>
    <w:r><w:fldChar w:fldCharType="end"/></w:r>
    <w:r><w:fldChar w:fldCharType="end"/></w:r>
    <w:bookmarkStart w:id="10" w:name="HeaderOnly"/>
    <w:r><w:t>Header anchored label</w:t></w:r>
    <w:bookmarkEnd w:id="10"/>
  </w:p>
</w:hdr>
XML],
            ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:body>
    <w:sectPr><w:headerReference w:type="default" r:id="rHeader"/></w:sectPr>
  </w:body>
</w:document>
XML],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);
        $meta = $document->attr('meta');

        $t->same([], $document->children);
        $t->same(1, $meta['docxHeaders']);
        $t->same(0, $meta['docxFooters']);
        $t->same(['word/header1.xml'], $meta['docxHeaderFiles']);
        $t->same(['word/header1.xml'], $meta['docxAppliedHeaderFiles']);
        $t->true(!str_contains($blocks, 'Header page'), 'Header-only text should remain metadata-only');
        $t->true(!str_contains($blocks, 'HeaderOnly'), 'Header-only anchors should remain metadata-only');
        $t->true(!str_contains($blocks, 'No readable DOCX body content was found.'), 'Header-only packages should not synthesize body fallback text');
    },
    'reads docx bytes through the converter input path' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-docx-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary DOCX path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary DOCX package');
        }
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Byte DOCX</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new RuntimeException('Unable to read temporary DOCX package');
            }
            $document = PandocConverter::read($bytes, 'docx');
        } finally {
            @unlink($path);
        }

        $t->same('paragraph', $document->children[0]->type);
        $t->same('Byte DOCX', $document->children[0]->attr('text'));
    },
    'reads native zip docx bytes while leaving unsupported media entries metadata-only' => static function (TestRunner $t) use ($buildDocxReaderNativeZipPackageBytes): void {
        $documentXml = <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
  <w:body>
    <w:p>
      <w:r><w:t xml:space="preserve">Native ZIP DOCX </w:t></w:r>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:docPr id="7" name="Unsupported media" title="Unsupported media title" descr="Unsupported media alt"/>
            <a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rImage"/></pic:blipFill></pic:pic></a:graphicData></a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
    </w:p>
  </w:body>
</w:document>
XML;
        $relationshipsXml = <<<'XML'
<?xml version="1.0"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/unsupported.bin"/>
</Relationships>
XML;
        $contentTypesXml = <<<'XML'
<?xml version="1.0"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="bin" ContentType="application/octet-stream"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;
        $bytes = $buildDocxReaderNativeZipPackageBytes(
            [
                '[Content_Types].xml' => $contentTypesXml,
                'word/document.xml' => $documentXml,
                'word/_rels/document.xml.rels' => $relationshipsXml,
                'word/media/unsupported.bin' => 'metadata-only unsupported compression payload',
            ],
            [
                '[Content_Types].xml' => 0,
                'word/media/unsupported.bin' => 12,
            ]
        );

        $packageDocument = (new DocxReader())->readDocument(ZipPackage::fromString($bytes));
        $converterDocument = PandocConverter::read($bytes, 'docx');
        $blocks = (new WordPressBlockWriter())->write($converterDocument);
        $meta = $converterDocument->attr('meta');
        $image = $converterDocument->children[0]->children[1];

        $t->same('Native ZIP DOCX Unsupported media alt', $packageDocument->children[0]->attr('text'));
        $t->same('Native ZIP DOCX Unsupported media alt', $converterDocument->children[0]->attr('text'));
        $t->same(4, $meta['docxPackageEntries']);
        $t->same(['word/media/unsupported.bin'], $meta['docxMediaFiles']);
        $t->same(1, $meta['docxRelationshipCount']);
        $t->same('image', $image->type);
        $t->same('media/unsupported.bin', $image->attr('url'));
        $t->same('Unsupported media alt', $image->attr('alt'));
        $t->same('Unsupported media alt', $image->children[0]->attr('text'));
        $t->contains('media/unsupported.bin', $blocks);
    },
    'preserves docx numbering levels styles starts and delimiters' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-docx-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary DOCX path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary DOCX package');
        }
        $zip->addFromString('word/numbering.xml', '<?xml version="1.0"?><w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:abstractNum w:abstractNumId="42"><w:lvl w:ilvl="0"><w:start w:val="3"/><w:numFmt w:val="lowerLetter"/><w:lvlText w:val="%1)"/></w:lvl><w:lvl w:ilvl="1"><w:numFmt w:val="upperRoman"/><w:lvlText w:val="(%2)"/></w:lvl></w:abstractNum><w:num w:numId="7"><w:abstractNumId w:val="42"/></w:num></w:numbering>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>Alpha three</w:t></w:r></w:p><w:p><w:pPr><w:numPr><w:ilvl w:val="1"/><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>Nested roman</w:t></w:r></w:p><w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>Alpha four</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        try {
            $document = (new DocxReader())->readDocxFile($path);
            $blocks = (new WordPressBlockWriter(['preserveListAttributes' => true]))->write($document);
            $converterBlocks = PandocConverter::convertFile($path, 'docx', 'blocks');
        } finally {
            @unlink($path);
        }

        $list = $document->children[0];
        $firstItem = $list->children[0];
        $nested = $firstItem->children[1];

        $t->same('ordered_list', $list->type);
        $t->same(3, $list->attr('start'));
        $t->same('lower_alpha', $list->attr('style'));
        $t->same('one_paren', $list->attr('delimiter'));
        $t->same('list_item', $firstItem->type);
        $t->same('Alpha three', $firstItem->children[0]->attr('text'));
        $t->same('ordered_list', $nested->type);
        $t->same('upper_roman', $nested->attr('style'));
        $t->same('two_parens', $nested->attr('delimiter'));
        $t->same('Nested roman', $nested->children[0]->children[0]->attr('text'));
        $t->same('Alpha four', $list->children[1]->children[0]->attr('text'));
        $t->contains('<!-- wp:list {"ordered":true,"start":3} -->', $blocks);
        $t->contains('<ol start="3" type="a" data-pandoc-list-style="lower_alpha" data-pandoc-list-delimiter="one_paren">', $blocks);
        $t->contains('<ol type="I" data-pandoc-list-style="upper_roman" data-pandoc-list-delimiter="two_parens">', $blocks);
        $t->contains('<ol start="3" type="a"><li>Alpha three<ol type="I"><li>Nested roman</li></ol></li><li>Alpha four</li></ol>', $converterBlocks);
    },
    'continues docx ordered list starts after intervening paragraphs' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/numbering.xml', 'data' => '<?xml version="1.0"?><w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:abstractNum w:abstractNumId="9"><w:lvl w:ilvl="0"><w:numFmt w:val="decimal"/><w:lvlText w:val="%1."/></w:lvl></w:abstractNum><w:num w:numId="21"><w:abstractNumId w:val="9"/></w:num></w:numbering>'],
            ['name' => 'word/document.xml', 'data' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:pPr><w:numPr><w:numId w:val="21"/></w:numPr></w:pPr><w:r><w:t>One</w:t></w:r></w:p><w:p><w:pPr><w:numPr><w:numId w:val="21"/></w:numPr></w:pPr><w:r><w:t>Two</w:t></w:r></w:p><w:p><w:pPr><w:numPr><w:numId w:val="21"/></w:numPr></w:pPr><w:r><w:t>Three</w:t></w:r></w:p><w:p><w:r><w:t>Break</w:t></w:r></w:p><w:p><w:pPr><w:numPr><w:numId w:val="21"/></w:numPr></w:pPr><w:r><w:t>Four</w:t></w:r></w:p></w:body></w:document>'],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter(['preserveListAttributes' => true]))->write($document);

        $t->same(['ordered_list', 'paragraph', 'ordered_list'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(1, $document->children[0]->attr('start'));
        $t->same(3, count($document->children[0]->children));
        $t->same('Break', $document->children[1]->attr('text'));
        $t->same(4, $document->children[2]->attr('start'));
        $t->same('Four', $document->children[2]->children[0]->children[0]->attr('text'));
        $t->contains('<ol start="4"><li>Four</li></ol>', $blocks);
    },
    'reads docx bookmarks reference fields and omml equations into shared ast' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-docx-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary DOCX path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary DOCX package');
        }
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math"><w:body><w:p><w:bookmarkStart w:id="7" w:name="_RefEquation"/><w:r><w:t>Equation target</w:t></w:r><w:bookmarkEnd w:id="7"/></w:p><w:p><w:r><w:t>See </w:t></w:r><w:fldSimple w:instr=" REF _RefEquation \h "><w:r><w:t>Equation target</w:t></w:r></w:fldSimple><w:r><w:t>: </w:t></w:r><m:oMath><m:sSup><m:e><m:r><m:t>x</m:t></m:r></m:e><m:sup><m:r><m:t>2</m:t></m:r></m:sup></m:sSup><m:r><m:t>+y</m:t></m:r></m:oMath></w:p><m:oMathPara><m:oMath><m:f><m:num><m:r><m:t>1</m:t></m:r></m:num><m:den><m:r><m:t>n</m:t></m:r></m:den></m:f></m:oMath></m:oMathPara></w:body></w:document>');
        $zip->close();

        try {
            $document = (new DocxReader())->readDocxFile($path);
            $blocks = (new WordPressBlockWriter())->write($document);
        } finally {
            @unlink($path);
        }

        $target = $document->children[0];
        $reference = $document->children[1];
        $display = $document->children[2];

        $t->same('Equation target', $target->attr('text'));
        $t->same('See Equation target: x^{2}+y', $reference->attr('text'));
        $t->same('span', $target->children[0]->type);
        $t->same('_RefEquation', $target->children[0]->attr('id'));
        $t->same(['anchor'], $target->children[0]->attr('classes'));
        $t->same('text', $target->children[1]->type);
        $t->same('link', $reference->children[1]->type);
        $t->same('#_RefEquation', $reference->children[1]->attr('url'));
        $t->same('math', $reference->children[3]->type);
        $t->same('x^{2}+y', $reference->children[3]->attr('text'));
        $t->same('plain', $display->type);
        $t->same('math', $display->children[0]->type);
        $t->same(true, $display->children[0]->attr('display'));
        $t->same('\\frac{1}{n}', $display->children[0]->attr('text'));
        $t->contains('<span id="_RefEquation" class="anchor" data-pandoc-anchor="empty-target"></span>', $blocks);
        $t->contains('<a href="#_RefEquation"', $blocks);
        $t->contains('>Equation target</a>', $blocks);
        $t->contains('<span class="math inline">\\(x^{2}+y\\)</span>', $blocks);
        $t->contains('<span class="math display">\\[\\frac{1}{n}\\]</span>', $blocks);
    },
    'parses docx hyperlink field instructions into links' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes(<<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:fldSimple w:instr=' HYPERLINK "https://example.test/simple" \o "Simple title" '>
        <w:r><w:t>Simple field</w:t></w:r>
      </w:fldSimple>
    </w:p>
    <w:p>
      <w:r><w:fldChar w:fldCharType="begin"/></w:r>
      <w:r><w:instrText xml:space="preserve"> HYPERLINK "https://example.test/complex" \o "Complex title" </w:instrText></w:r>
      <w:r><w:fldChar w:fldCharType="separate"/></w:r>
      <w:r><w:t>Complex field</w:t></w:r>
      <w:r><w:fldChar w:fldCharType="end"/></w:r>
    </w:p>
    <w:p>
      <w:r><w:fldChar w:fldCharType="begin"/></w:r>
      <w:r><w:instrText xml:space="preserve"> HYPERLINK \l "LocalTarget" </w:instrText></w:r>
      <w:r><w:fldChar w:fldCharType="separate"/></w:r>
      <w:r><w:t>Local field</w:t></w:r>
      <w:r><w:fldChar w:fldCharType="end"/></w:r>
    </w:p>
  </w:body>
</w:document>
XML);

        $document = (new DocxReader())->read($bytes);
        $blocks = (new WordPressBlockWriter())->write($document);
        $simple = $document->children[0]->children[0];
        $complex = $document->children[1]->children[0];
        $local = $document->children[2]->children[0];

        $t->same('link', $simple->type);
        $t->same('https://example.test/simple', $simple->attr('url'));
        $t->same('Simple title', $simple->attr('title'));
        $t->same(null, $simple->attr('attributes'));
        $t->same('Simple field', $simple->children[0]->attr('text'));

        $t->same('link', $complex->type);
        $t->same('https://example.test/complex', $complex->attr('url'));
        $t->same('Complex title', $complex->attr('title'));
        $t->same(null, $complex->attr('attributes'));
        $t->same('Complex field', $complex->children[0]->attr('text'));

        $t->same('link', $local->type);
        $t->same('#LocalTarget', $local->attr('url'));
        $t->same(null, $local->attr('attributes'));
        $t->same('Local field', $local->children[0]->attr('text'));

        $t->contains('<a href="https://example.test/simple" title="Simple title"', $blocks);
        $t->true(!str_contains($blocks, 'data-docx-field='), 'Field-derived links should not carry local DOCX field attributes');
        $t->contains('<a href="#LocalTarget"', $blocks);
    },
    'canonicalizes docx heading bookmark reference targets' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:pPr><w:pStyle w:val="Heading1"/></w:pPr>
      <w:bookmarkStart w:id="7" w:name="_RefHeading"/>
      <w:bookmarkStart w:id="8" w:name="_GoBack"/>
      <w:bookmarkEnd w:id="8"/>
      <w:r><w:t>Target Heading</w:t></w:r>
      <w:bookmarkEnd w:id="7"/>
    </w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Page </w:t></w:r>
      <w:fldSimple w:instr=" PAGEREF _RefHeading \h "><w:r><w:t>2</w:t></w:r></w:fldSimple>
      <w:r><w:t xml:space="preserve"> and ref </w:t></w:r>
      <w:fldSimple w:instr=" REF _RefHeading \h "><w:r><w:t>Target Heading</w:t></w:r></w:fldSimple>
    </w:p>
    <w:p>
      <w:hyperlink w:anchor="_RefHeading"><w:r><w:t>Direct heading link</w:t></w:r></w:hyperlink>
    </w:p>
  </w:body>
</w:document>
XML],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);
        $heading = $document->children[0];
        $fieldParagraph = $document->children[1];
        $pageRef = $fieldParagraph->children[1];
        $crossRef = $fieldParagraph->children[3];
        $directLink = $document->children[2]->children[0];

        $t->same('heading', $heading->type);
        $t->same('target-heading', $heading->attr('id'));
        $t->same('Target Heading', $heading->attr('text'));
        $t->same(1, count($heading->children));
        $t->same('Target Heading', $heading->children[0]->attr('text'));

        $t->same('link', $pageRef->type);
        $t->same('#target-heading', $pageRef->attr('url'));
        $t->same('link', $crossRef->type);
        $t->same('#target-heading', $crossRef->attr('url'));
        $t->same('link', $directLink->type);
        $t->same('#target-heading', $directLink->attr('url'));

        $t->contains('<h1 id="target-heading">Target Heading</h1>', $blocks);
        $t->contains('<a href="#target-heading">2</a>', $blocks);
        $t->contains('<a href="#target-heading">Target Heading</a>', $blocks);
        $t->contains('<a href="#target-heading">Direct heading link</a>', $blocks);
        $t->true(!str_contains($blocks, 'data-docx-field='), 'Canonicalized field links should render as plain Pandoc links');
        $t->true(!str_contains($blocks, '_GoBack'), 'Word _GoBack bookmarks should not leak into rendered output');
        $t->true(!str_contains($blocks, 'pandoc-openxml-bookmark-start'), 'Heading bookmarks should canonicalize to the heading id instead of raw bookmark spans');
    },
    'promotes referenced docx bookmark-only paragraph before heading' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes(<<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>See </w:t></w:r><w:fldSimple w:instr=" REF _RefBeforeHeading \h "><w:r><w:t>0</w:t></w:r></w:fldSimple></w:p>
    <w:p><w:bookmarkStart w:id="9" w:name="_RefBeforeHeading"/><w:bookmarkEnd w:id="9"/></w:p>
    <w:p><w:pPr><w:pStyle w:val="Heading2"/></w:pPr><w:r><w:t>Validation Type Enumeration</w:t></w:r></w:p>
  </w:body>
</w:document>
XML);

        $document = (new DocxReader())->read($bytes);
        $blocks = (new WordPressBlockWriter())->write($document);
        $html = (new HtmlWriter())->write($document);

        $t->same(['paragraph', 'paragraph', 'heading'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same('link', $document->children[0]->children[1]->type);
        $t->same('#_RefBeforeHeading', $document->children[0]->children[1]->attr('url'));
        $t->same('span', $document->children[1]->children[0]->type);
        $t->same('_RefBeforeHeading', $document->children[1]->children[0]->attr('id'));
        $t->same(['anchor'], $document->children[1]->children[0]->attr('classes'));
        $t->contains('<a href="#_RefBeforeHeading">0</a>', $blocks);
        $t->contains('<span id="_RefBeforeHeading" class="anchor"', $blocks);
        $t->contains('<a href="#_RefBeforeHeading">0</a>', $html);
        $t->contains('<span id="_RefBeforeHeading" class="anchor"', $html);
    },
    'maps explicit docx heading zero styles without broad heading fallback' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/styles.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="Heading0"><w:name w:val="Heading 0"/></w:style>
  <w:style w:type="paragraph" w:styleId="NotHeading0"><w:name w:val="Not Heading 0"/></w:style>
</w:styles>
XML],
            ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="Heading0"/></w:pPr><w:r><w:t>CONTENTS</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="NotHeading0"/></w:pPr><w:r><w:t>Plain zero label</w:t></w:r></w:p>
  </w:body>
</w:document>
XML],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $heading = $document->children[0];
        $paragraph = $document->children[1];

        $t->same('heading', $heading->type);
        $t->same(1, $heading->attr('level'));
        $t->same('contents', $heading->attr('id'));
        $t->same(['Heading-0'], $heading->attr('classes'));
        $t->same('paragraph', $paragraph->type);
        $t->same('Plain zero label', $paragraph->attr('text'));
    },
    'preserves custom docx heading style classes while ignoring body-text outline reset' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/styles.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:pPr><w:outlineLvl w:val="0"/></w:pPr></w:style>
  <w:style w:type="paragraph" w:styleId="TOCHeading"><w:name w:val="TOC Heading"/><w:basedOn w:val="Heading1"/><w:pPr><w:outlineLvl w:val="9"/></w:pPr></w:style>
  <w:style w:type="paragraph" w:styleId="Myheading2"><w:name w:val="Myheading2"/></w:style>
</w:styles>
XML],
            ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="TOCHeading"/></w:pPr><w:r><w:t>Contents</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Standard Heading</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="Myheading2"/></w:pPr><w:r><w:t>Custom Two</w:t></w:r></w:p>
  </w:body>
</w:document>
XML],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(1, $document->children[0]->attr('level'));
        $t->same(['TOC-Heading'], $document->children[0]->attr('classes'));
        $t->same(1, $document->children[1]->attr('level'));
        $t->same([], $document->children[1]->attr('classes', []));
        $t->same(2, $document->children[2]->attr('level'));
        $t->same(['Myheading2'], $document->children[2]->attr('classes'));
        $t->contains('<h1 id="contents" class="TOC-Heading">Contents</h1>', $blocks);
        $t->contains('<h1 id="standard-heading">Standard Heading</h1>', $blocks);
        $t->contains('<h2 id="custom-two" class="Myheading2">Custom Two</h2>', $blocks);
    },
    'maps direct docx paragraph outline level zero to heading' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:outlineLvl w:val="0"/></w:pPr><w:r><w:t>CONTENTS</w:t></w:r></w:p>
    <w:p><w:r><w:t>Section body</w:t></w:r></w:p>
  </w:body>
</w:document>
XML],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);
        $heading = $document->children[0];
        $paragraph = $document->children[1];

        $t->same('heading', $heading->type);
        $t->same(1, $heading->attr('level'));
        $t->same('contents', $heading->attr('id'));
        $t->same('CONTENTS', $heading->attr('text'));
        $t->same('paragraph', $paragraph->type);
        $t->same('Section body', $paragraph->attr('text'));
        $t->contains('<h1 id="contents">CONTENTS</h1>', $blocks);
    },
    'preserves nested docx instrText fields inside linked field results' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes(<<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r><w:fldChar w:fldCharType="begin"/></w:r>
      <w:r><w:instrText> HYPERLINK "https://example.test/nested" \o "Nested title" </w:instrText></w:r>
      <w:r><w:fldChar w:fldCharType="separate"/></w:r>
      <w:r><w:t xml:space="preserve">Source p. </w:t></w:r>
      <w:r><w:fldChar w:fldCharType="begin"/></w:r>
      <w:r><w:instrText> PAGEREF TargetAnchor \h </w:instrText></w:r>
      <w:r><w:fldChar w:fldCharType="separate"/></w:r>
      <w:r><w:t>7</w:t></w:r>
      <w:r><w:fldChar w:fldCharType="end"/></w:r>
      <w:r><w:t xml:space="preserve"> checked</w:t></w:r>
      <w:r><w:fldChar w:fldCharType="end"/></w:r>
    </w:p>
    <w:p><w:bookmarkStart w:id="9" w:name="TargetAnchor"/><w:r><w:t>Target paragraph</w:t></w:r><w:bookmarkEnd w:id="9"/></w:p>
  </w:body>
</w:document>
XML);

        $document = (new DocxReader())->read($bytes);
        $paragraph = $document->children[0];
        $outer = $paragraph->children[0];
        $pageRef = $outer->children[1];
        $target = $document->children[1];
        $native = (new NativeWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('Source p. 7 checked', $paragraph->attr('text'));
        $t->same('link', $outer->type);
        $t->same('https://example.test/nested', $outer->attr('url'));
        $t->same('Nested title', $outer->attr('title'));
        $t->same('text', $outer->children[0]->type);
        $t->same('Source p. ', $outer->children[0]->attr('text'));
        $t->same('link', $pageRef->type);
        $t->same('#TargetAnchor', $pageRef->attr('url'));
        $t->same(null, $outer->attr('attributes'));
        $t->same(null, $pageRef->attr('attributes'));
        $t->same('7', $pageRef->children[0]->attr('text'));
        $t->same(' checked', $outer->children[2]->attr('text'));
        $t->same('span', $target->children[0]->type);
        $t->same('TargetAnchor', $target->children[0]->attr('id'));
        $t->same(['anchor'], $target->children[0]->attr('classes'));
        $t->contains('Link ( "" , [  ] , [  ] ) [ Str "7" ] ( "#TargetAnchor" , "" )', $native);
        $t->contains('<a href="https://example.test/nested" title="Nested title">Source p. <span>7</span> checked</a>', $blocks);
    },
    'promotes referenced docx bookmarks to anchor spans while preserving unused anchors as raw openxml' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes(<<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:hyperlink w:anchor="Fizz"><w:r><w:t>One link to one target.</w:t></w:r></w:hyperlink></w:p>
    <w:p><w:bookmarkStart w:id="1" w:name="Fizz"/><w:bookmarkStart w:id="2" w:name="UnusedAnchor"/><w:r><w:t>This is a target with two names.</w:t></w:r><w:bookmarkEnd w:id="1"/><w:bookmarkEnd w:id="2"/></w:p>
  </w:body>
</w:document>
XML);

        $document = (new DocxReader())->read($bytes);
        $link = $document->children[0]->children[0];
        $target = $document->children[1];
        $native = (new NativeWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('link', $link->type);
        $t->same('#Fizz', $link->attr('url'));
        $t->same('span', $target->children[0]->type);
        $t->same('Fizz', $target->children[0]->attr('id'));
        $t->same(['anchor'], $target->children[0]->attr('classes'));
        $t->same('raw_inline', $target->children[1]->type);
        $t->contains('w:name="UnusedAnchor"', $target->children[1]->attr('text'));
        $t->same('text', $target->children[2]->type);
        $t->same('This is a target with two names.', $target->children[2]->attr('text'));
        $t->same('raw_inline', $target->children[3]->type);
        $t->contains('w:id="2"', $target->children[3]->attr('text'));
        $t->contains('Span ( "Fizz" , [ "anchor" ] , [  ] ) [  ]', $native);
        $t->contains('RawInline (Format "openxml") "<w:bookmarkStart w:id=\\"2\\" w:name=\\"UnusedAnchor\\"/>"', $native);
        $t->contains('<span id="Fizz" class="anchor" data-pandoc-anchor="empty-target"></span>', $blocks);
        $t->contains('data-pandoc-bookmark-name="UnusedAnchor"', $blocks);
    },
    'canonicalizes overlapping referenced docx bookmark aliases to the first anchor' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes(<<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:hyperlink w:anchor="Fizz"><w:r><w:t>One link.</w:t></w:r></w:hyperlink></w:p>
    <w:p><w:bookmarkStart w:id="1" w:name="Fizz"/><w:bookmarkStart w:id="2" w:name="Pop"/><w:r><w:t>Shared target.</w:t></w:r><w:bookmarkEnd w:id="1"/><w:bookmarkEnd w:id="2"/></w:p>
    <w:p><w:hyperlink w:anchor="Pop"><w:r><w:t>Alias link.</w:t></w:r></w:hyperlink></w:p>
  </w:body>
</w:document>
XML);

        $document = (new DocxReader())->read($bytes);
        $target = $document->children[1];

        $t->same('#Fizz', $document->children[0]->children[0]->attr('url'));
        $t->same('#Fizz', $document->children[2]->children[0]->attr('url'));
        $t->same(['span', 'text'], array_map(static fn ($node): string => $node->type, $target->children));
        $t->same('Fizz', $target->children[0]->attr('id'));
        $t->same('Shared target.', $target->children[1]->attr('text'));
    },
    'canonicalizes overlapping docx bookmark aliases to the first unused anchor' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes(<<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:hyperlink w:anchor="Bar"><w:r><w:t>Alias link.</w:t></w:r></w:hyperlink></w:p>
    <w:p><w:bookmarkStart w:id="2" w:name="Foo"/><w:bookmarkStart w:id="3" w:name="Bar"/><w:r><w:t>Shared target.</w:t></w:r><w:bookmarkEnd w:id="3"/><w:bookmarkEnd w:id="2"/></w:p>
  </w:body>
</w:document>
XML);

        $document = (new DocxReader())->read($bytes);
        $target = $document->children[1];

        $t->same('#Foo', $document->children[0]->children[0]->attr('url'));
        $t->same(['span', 'text'], array_map(static fn ($node): string => $node->type, $target->children));
        $t->same('Foo', $target->children[0]->attr('id'));
        $t->same('Shared target.', $target->children[1]->attr('text'));
    },
    'preserves empty docx index fields without leaking field instructions' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes(<<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Index marker </w:t></w:r><w:fldSimple w:instr=" XE &quot;French&quot; "/><w:r><w:t>after</w:t></w:r></w:p>
  </w:body>
</w:document>
XML);

        $document = (new DocxReader())->read($bytes);
        $paragraph = $document->children[0];
        $index = $paragraph->children[1];
        $native = (new NativeWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('Index marker after', $paragraph->attr('text'));
        $t->same('span', $index->type);
        $t->same(['indexref'], $index->attr('classes'));
        $t->same(['entry' => 'French'], $index->attr('attributes'));
        $t->contains('Span ( "" , [ "indexref" ] , [ ( "entry" , "French" ) ] )', $native);
        $t->contains('data-pandoc-index-entry="French"', $blocks);
        $t->true(!str_contains($native . $blocks, 'data-docx-'), 'index reference fields should not retain local DOCX provenance attrs');
        $t->true(!str_contains(strip_tags($blocks), 'XE "French"'), 'empty field instructions should not render as visible text');
    },
    'suppresses generated docx ole link bookmarks without dropping real bookmarks' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:bookmarkStart w:id="4" w:name="KeepMe"/><w:r><w:t>Kept bookmark</w:t></w:r><w:bookmarkEnd w:id="4"/></w:p><w:p><w:bookmarkStart w:id="9" w:name="OLE_LINK12"/><w:r><w:t>Generated bookmark wrapper</w:t></w:r><w:bookmarkEnd w:id="9"/></w:p></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same('raw_inline', $document->children[0]->children[0]->type);
        $t->contains('w:name="KeepMe"', $document->children[0]->children[0]->attr('text'));
        $t->contains('data-pandoc-bookmark-name="KeepMe"', $blocks);
        $t->true(!str_contains($blocks, 'OLE_LINK12'), 'generated OLE_LINK bookmarks should not leak into rendered output');
        $t->true(!str_contains((new NativeWriter())->write($document), 'OLE_LINK12'), 'generated OLE_LINK bookmarks should not leak into native output');
    },
    'preserves docx hyperlink relationship anchors and tracked change contents' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/_rels/document.xml.rels', 'data' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/review" TargetMode="External"/></Relationships>'],
            ['name' => 'word/document.xml', 'data' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body><w:p><w:hyperlink r:id="rIdExternal" w:anchor="Section_2"><w:r><w:t xml:space="preserve">See </w:t></w:r><w:ins w:author="Anchor Reviewer" w:date="2026-06-30T00:00:00Z"><w:r><w:t>inserted anchor</w:t></w:r></w:ins></w:hyperlink><w:r><w:t> after.</w:t></w:r></w:p></w:body></w:document>'],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);
        $paragraph = $document->children[0];
        $link = $paragraph->children[0];

        $t->same('See inserted anchor after.', $paragraph->attr('text'));
        $t->same('link', $link->type);
        $t->same('https://example.test/review#Section_2', $link->attr('url'));
        $t->same('See ', $link->children[0]->attr('text'));
        $t->same('span', $link->children[1]->type);
        $t->same(['insertion'], $link->children[1]->attr('classes'));
        $t->same('Anchor Reviewer', $link->children[1]->attr('attributes')['author']);
        $t->same('2026-06-30T00:00:00Z', $link->children[1]->attr('attributes')['date']);
        $t->contains('<a href="https://example.test/review#Section_2">See <ins class="insertion" data-pandoc-change-author="Anchor Reviewer"', $blocks);
        $t->contains('data-pandoc-change-date="2026-06-30T00:00:00Z"', $blocks);
        $t->contains('>inserted anchor</ins></a> after.', $blocks);
    },
    'resolves docx footnote hyperlinks against footnote relationships' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/_rels/document.xml.rels', 'data' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>'],
            ['name' => 'word/_rels/footnotes.xml.rels', 'data' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="http://wikipedia.org/" TargetMode="External"/></Relationships>'],
            ['name' => 'word/footnotes.xml', 'data' => '<?xml version="1.0"?><w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:footnote w:id="1"><w:p><w:hyperlink r:id="rId1"><w:r><w:t>Wikipedia</w:t></w:r></w:hyperlink></w:p></w:footnote></w:footnotes>'],
            ['name' => 'word/document.xml', 'data' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>See note</w:t></w:r><w:r><w:footnoteReference w:id="1"/></w:r></w:p></w:body></w:document>'],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $notes = array_values(array_filter($document->children[0]->children, static fn ($node): bool => $node->type === 'note'));
        $link = $notes[0]->children[0]->children[0];

        $t->same('link', $link->type);
        $t->same('http://wikipedia.org/', $link->attr('url'));
        $t->same('Wikipedia', $link->children[0]->attr('text'));
    },
    'reads docx packages whose office document part uses an alternate path' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => '_rels/.rels', 'data' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdDoc" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="/word/document2.xml"/></Relationships>'],
            ['name' => '[Content_Types].xml', 'data' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document2.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>'],
            ['name' => 'word/_rels/document2.xml.rels', 'data' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/alternate" TargetMode="External"/></Relationships>'],
            ['name' => 'word/document2.xml', 'data' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body><w:p><w:r><w:t>Alternate </w:t></w:r><w:hyperlink r:id="rIdExternal"><w:r><w:t>document path</w:t></w:r></w:hyperlink><w:r><w:rPr><w:b/><w:i/><w:u w:val="single"/></w:rPr><w:t xml:space="preserve"> styled</w:t></w:r></w:p></w:body></w:document>'],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $paragraph = $document->children[0];
        $link = $paragraph->children[1];
        $styled = $paragraph->children[2];

        $t->same('Alternate document path styled', $paragraph->attr('text'));
        $t->same('link', $link->type);
        $t->same('https://example.test/alternate', $link->attr('url'));
        $t->same('document path ', $link->children[0]->attr('text'));
        $t->same('emph', $styled->type);
        $t->same('strong', $styled->children[0]->type);
        $t->same('underline', $styled->children[0]->children[0]->type);
        $t->same('styled', $styled->children[0]->children[0]->children[0]->attr('text'));
    },
    'normalizes alternate part drawing and vml image relationship targets' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => '_rels/.rels', 'data' => <<<'XML'
<?xml version="1.0"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDoc" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="/word/sub/document.xml"/>
</Relationships>
XML],
            ['name' => '[Content_Types].xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/sub/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML],
            ['name' => 'word/sub/_rels/document.xml.rels', 'data' => <<<'XML'
<?xml version="1.0"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDrawing" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/drawing.png"/>
  <Relationship Id="rVml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="..\media\vml-preview.png"/>
</Relationships>
XML],
            ['name' => 'word/sub/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document
  xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"
  xmlns:v="urn:schemas-microsoft-com:vml"
  xmlns:o="urn:schemas-microsoft-com:office:office">
  <w:body>
    <w:p>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:extent cx="914400" cy="457200"/>
            <wp:docPr id="11" name="Nested Drawing" descr="Nested drawing alt"/>
            <a:graphic>
              <a:graphicData>
                <pic:pic>
                  <pic:blipFill><a:blip r:embed="rDrawing"/></pic:blipFill>
                </pic:pic>
              </a:graphicData>
            </a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
    </w:p>
    <w:p>
      <w:r>
        <w:pict>
          <v:shape id="NestedVml" style="width:36pt;height:18pt">
            <v:imagedata r:id="rVml" o:title="Nested VML"/>
          </v:shape>
        </w:pict>
      </w:r>
    </w:p>
  </w:body>
</w:document>
XML],
            ['name' => 'word/media/drawing.png', 'data' => 'drawing bytes'],
            ['name' => 'word/media/vml-preview.png', 'data' => 'vml bytes'],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);
        $drawing = $document->children[0]->children[0];
        $vml = $document->children[1]->children[0];

        $t->same('image', $drawing->type);
        $t->same('media/drawing.png', $drawing->attr('url'));
        $t->same('1in', $drawing->attr('width'));
        $t->same('0.5in', $drawing->attr('height'));
        $t->same('Nested drawing alt', $drawing->attr('alt'));
        $t->same('Nested drawing alt', $drawing->children[0]->attr('text'));
        $t->same('rDrawing', $drawing->attr('attributes')['data-docx-image-relationship-id']);

        $t->same('image', $vml->type);
        $t->same('media/vml-preview.png', $vml->attr('url'));
        $t->same('36pt', $vml->attr('width'));
        $t->same('18pt', $vml->attr('height'));
        $t->same('Nested VML', $vml->attr('title'));
        $t->same('Nested VML', $vml->children[0]->attr('text'));
        $t->same('rVml', $vml->attr('attributes')['data-docx-image-relationship-id']);

        $t->contains('src="media/drawing.png"', $blocks);
        $t->contains('src="media/vml-preview.png"', $blocks);
    },
    'preserves drawingml diagram and chart placeholders' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/_rels/document.xml.rels', 'data' => <<<'XML'
<?xml version="1.0"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDiagramData" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramData" Target="diagrams/review-data.xml"/>
  <Relationship Id="rDiagramLayout" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramLayout" Target="diagrams/review-layout.xml"/>
  <Relationship Id="rDiagramStyle" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramQuickStyle" Target="diagrams/review-style.xml"/>
  <Relationship Id="rDiagramColors" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramColors" Target="diagrams/review-colors.xml"/>
  <Relationship Id="rChart" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="charts/review-chart.xml"/>
</Relationships>
XML],
            ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document
  xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
  xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
  xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
  xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram"
  xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart">
  <w:body>
    <w:p>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:docPr id="19" name="Review workflow" descr="Imported workflow diagram" title="Review workflow"/>
            <a:graphic>
              <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/diagram">
                <dgm:relIds r:dm="rDiagramData" r:lo="rDiagramLayout" r:qs="rDiagramStyle" r:cs="rDiagramColors"/>
              </a:graphicData>
            </a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
    </w:p>
    <w:p>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:docPr id="18" name="Review chart" descr="Imported review chart" title="Review chart"/>
            <a:graphic>
              <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/chart">
                <c:chart r:id="rChart"/>
              </a:graphicData>
            </a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
    </w:p>
  </w:body>
</w:document>
XML],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);
        $diagram = $document->children[0]->children[0];
        $chart = $document->children[1]->children[0];

        $t->same('[DIAGRAM]', $document->children[0]->attr('text'));
        $t->same('span', $diagram->type);
        $t->same(['diagram'], $diagram->attr('classes'));
        $t->same([
            'data-docx-diagram-uri' => 'http://schemas.openxmlformats.org/drawingml/2006/diagram',
            'data-docx-diagram-id' => '19',
            'data-docx-diagram-name' => 'Review workflow',
            'data-docx-diagram-title' => 'Review workflow',
            'data-docx-diagram-description' => 'Imported workflow diagram',
            'data-docx-diagram-data-relationship-id' => 'rDiagramData',
            'data-docx-diagram-data-target' => 'word/diagrams/review-data.xml',
            'data-docx-diagram-layout-relationship-id' => 'rDiagramLayout',
            'data-docx-diagram-layout-target' => 'word/diagrams/review-layout.xml',
            'data-docx-diagram-quick-style-relationship-id' => 'rDiagramStyle',
            'data-docx-diagram-quick-style-target' => 'word/diagrams/review-style.xml',
            'data-docx-diagram-colors-relationship-id' => 'rDiagramColors',
            'data-docx-diagram-colors-target' => 'word/diagrams/review-colors.xml',
        ], $diagram->attr('attributes', []));
        $t->same('[DIAGRAM]', $diagram->children[0]->attr('text'));

        $t->same('[CHART]', $document->children[1]->attr('text'));
        $t->same('span', $chart->type);
        $t->same(['chart'], $chart->attr('classes'));
        $t->same('[CHART]', $chart->children[0]->attr('text'));

        $t->contains('class="diagram"', $blocks);
        $t->contains('data-docx-diagram-data-target="word/diagrams/review-data.xml"', $blocks);
        $t->contains('data-pandoc-diagram="unsupported-docx-diagram"', $blocks);
        $t->contains('<span class="chart">[CHART]</span>', $blocks);
    },
    'maps visible vml textbox shape text as document blocks' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:v="urn:schemas-microsoft-com:vml"><w:body><w:p><w:r><w:pict><v:shape id="TextBoxUpdated" type="#_x0000_t202" style="visibility:visible"><v:textbox><w:txbxContent><w:p><w:r><w:t xml:space="preserve">Last update: </w:t></w:r><w:r><w:t>May 1, 2017</w:t></w:r></w:p></w:txbxContent></v:textbox></v:shape></w:pict></w:r><w:r><w:pict><v:shape id="TextBoxTitle" type="#_x0000_t202" style="visibility:visible"><v:textbox><w:txbxContent><w:p><w:r><w:t>Using Microsoft Word 2007/2010</w:t></w:r><w:r><w:br/></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>for Writing Technical Documents</w:t></w:r></w:p><w:p><w:r><w:t>Valter Kiisk</w:t></w:r></w:p><w:p><w:bookmarkStart w:id="4" w:name="_RefGenerated"/><w:bookmarkEnd w:id="4"/><w:r><w:t>Institute of Physics, University of Tartu</w:t></w:r></w:p></w:txbxContent></v:textbox></v:shape></w:pict></w:r></w:p><w:p><w:bookmarkStart w:id="1" w:name="_Toc219459029"/><w:bookmarkEnd w:id="1"/></w:p></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);
        $blocks = (new WordPressBlockWriter())->write($document);
        $native = (new NativeWriter())->write($document);

        $t->same(['paragraph', 'paragraph', 'paragraph', 'paragraph', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Last update: May 1, 2017', $document->children[0]->attr('text'));
        $t->same('Using Microsoft Word 2007/2010 for Writing Technical Documents', $document->children[1]->attr('text'));
        $t->same('linebreak', $document->children[1]->children[1]->type);
        $t->same('strong', $document->children[1]->children[2]->type);
        $t->same('for Writing Technical Documents', $document->children[1]->children[2]->children[0]->attr('text'));
        $t->same('Valter Kiisk', $document->children[2]->attr('text'));
        $t->same('Institute of Physics, University of Tartu', $document->children[3]->attr('text'));
        $t->same('', $document->children[4]->attr('text'));
        $t->true(!str_contains($native, '_RefGenerated'), 'Generated bookmarks inside visible VML textbox text should not leak into native output');
        $t->true(!str_contains($native, '_Toc219459029'), 'Generated trailing shape bookmarks should not leak into native output');
        $t->true(!str_contains($blocks, 'docx-textbox'), 'Visible shape-only textboxes should surface as document blocks');
        $t->contains('<strong>for Writing Technical Documents</strong>', $blocks);
    },
    'maps upstream docx paragraph block styles to code quotes and definitions' => static function (TestRunner $t): void {
        $stylesXml = <<<'XML'
<?xml version="1.0"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="SourceCode"><w:name w:val="Source Code"/></w:style>
  <w:style w:type="paragraph" w:styleId="Quote"><w:name w:val="Quote"/></w:style>
  <w:style w:type="paragraph" w:styleId="a4"><w:name w:val="Block Text"/></w:style>
  <w:style w:type="paragraph" w:styleId="DefinitionTerm"><w:name w:val="Definition Term"/></w:style>
  <w:style w:type="paragraph" w:styleId="Definition"><w:name w:val="Definition"/></w:style>
</w:styles>
XML;
        $documentXml = <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>Before</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="SourceCode"/></w:pPr><w:r><w:t>alpha</w:t><w:br/><w:t>beta</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="SourceCode"/></w:pPr></w:p>
    <w:p><w:pPr><w:pStyle w:val="SourceCode"/></w:pPr><w:r><w:t>gamma</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="Quote"/></w:pPr><w:r><w:t>Styled quote</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="a4"/></w:pPr><w:r><w:t>Block text quote</w:t></w:r></w:p>
    <w:p><w:pPr><w:ind w:left="1440"/></w:pPr><w:r><w:t>Indented quote</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="DefinitionTerm"/></w:pPr><w:r><w:t>Term</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="Definition"/></w:pPr><w:r><w:t>Definition one</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="Definition"/></w:pPr><w:r><w:t>Definition two</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;
        $document = (new DocxReader())->readDocument(ZipPackage::fromParts([
            ['name' => 'word/styles.xml', 'data' => $stylesXml],
            ['name' => 'word/document.xml', 'data' => $documentXml],
        ]));

        $t->same(['paragraph', 'code_block', 'blockquote', 'definition_list'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same("alpha\nbeta\n\ngamma", $document->children[1]->attr('text'));
        $t->same(['Styled quote', 'Block text quote', 'Indented quote'], array_map(static fn ($node): string => (string) $node->attr('text', ''), $document->children[2]->children));
        $definitionItem = $document->children[3]->children[0];
        $t->same('Term', $definitionItem->children[0]->attr('text'));
        $t->same(['Definition one', 'Definition two'], array_map(static fn ($node): string => (string) $node->attr('text', ''), $definitionItem->children[1]->children));
    },
    'keeps styled indented docx paragraphs out of quote fallback' => static function (TestRunner $t): void {
        $stylesXml = <<<'XML'
<?xml version="1.0"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="ImagePara"><w:name w:val="Image Paragraph"/><w:pPr><w:ind w:left="2234"/></w:pPr></w:style>
  <w:style w:type="paragraph" w:styleId="ListParagraph"><w:name w:val="List Paragraph"/><w:pPr><w:ind w:left="720"/></w:pPr></w:style>
</w:styles>
XML;
        $documentXml = <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="ImagePara"/><w:ind w:left="1440"/></w:pPr><w:r><w:t>Styled indent</w:t></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="ListParagraph"/><w:ind w:left="1134"/></w:pPr><w:r><w:t>Relative indent</w:t></w:r></w:p>
    <w:p><w:pPr><w:ind w:left="1440"/></w:pPr><w:r><w:t>Plain indent</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;

        $document = (new DocxReader())->readDocument(ZipPackage::fromParts([
            ['name' => 'word/styles.xml', 'data' => $stylesXml],
            ['name' => 'word/document.xml', 'data' => $documentXml],
        ]));

        $t->same(['paragraph', 'blockquote'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Styled indent', $document->children[0]->attr('text'));
        $t->same(['Relative indent', 'Plain indent'], array_map(static fn ($node): string => (string) $node->attr('text', ''), $document->children[1]->children));
    },
    'unwraps docx smart tags and alternate content fallback in body inline field and table scopes' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"><w:body><w:smartTag w:uri="urn:example" w:element="body"><w:p><w:r><w:t>Wrapped block</w:t></w:r></w:p></w:smartTag><w:p><w:r><w:t>Inline </w:t></w:r><w:smartTag w:uri="urn:example" w:element="inline"><w:r><w:rPr><w:b/></w:rPr><w:t>smart</w:t></w:r></w:smartTag><w:r><w:t> and </w:t></w:r><w:r><mc:AlternateContent><mc:Choice Requires="wps"><w:t>choice</w:t></mc:Choice><mc:Fallback><w:t>fallback</w:t></mc:Fallback></mc:AlternateContent></w:r></w:p><w:p><w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText> REF _SmartTarget \h </w:instrText></w:r><w:r><w:fldChar w:fldCharType="separate"/></w:r><w:smartTag w:uri="urn:example" w:element="field-result"><w:r><w:t>Smart target</w:t></w:r></w:smartTag><w:r><w:fldChar w:fldCharType="end"/></w:r></w:p><w:tbl><w:tr><w:tc><w:smartTag w:uri="urn:example" w:element="cell"><w:p><w:r><w:t>Cell smart</w:t></w:r></w:p></w:smartTag></w:tc></w:tr></w:tbl></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(4, count($document->children));
        $t->same('Wrapped block', $document->children[0]->attr('text'));

        $inline = $document->children[1];
        $t->same('Inline smart and fallback', $inline->attr('text'));
        $t->same('strong', $inline->children[1]->type);
        $t->same('smart', $inline->children[1]->children[0]->attr('text'));
        $t->same(' and fallback', $inline->children[2]->attr('text'));

        $field = $document->children[2]->children[0];
        $t->same('link', $field->type);
        $t->same('#_SmartTarget', $field->attr('url'));
        $t->same('Smart target', $field->children[0]->attr('text'));

        $cell = $document->children[3]->children[1]->children[0]->children[0];
        $t->same('Cell smart', $cell->attr('text'));
        $t->same('Cell smart', $cell->children[0]->children[0]->attr('text'));

        $t->contains('<p>Wrapped block</p>', $blocks);
        $t->contains('Inline <strong>smart</strong> and fallback', $blocks);
        $t->contains('<a href="#_SmartTarget"', $blocks);
        $t->contains('<td>Cell smart</td>', $blocks);
        $t->true(!str_contains($blocks, 'choice'), 'AlternateContent choice branch should not be emitted when a fallback is available');
    },
    'preserves docx content controls with block inline and table metadata' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:sdt><w:sdtPr><w:alias w:val="Customer Name"/><w:tag w:val="customer.name"/><w:id w:val="42"/><w:lock w:val="sdtContentLocked"/><w:placeholder><w:docPart w:val="CustomerPlaceholder"/></w:placeholder><w:dataBinding w:xpath="/root/customer/name" w:storeItemID="{11111111-1111-1111-1111-111111111111}" w:prefixMappings="xmlns:c=&apos;urn:customer&apos;"/><w:text w:multiLine="1"/></w:sdtPr><w:sdtContent><w:p><w:r><w:t>Ada Lovelace</w:t></w:r></w:p></w:sdtContent></w:sdt><w:p><w:r><w:t>Status: </w:t></w:r><w:sdt><w:sdtPr><w:alias w:val="Status choice"/><w:tag w:val="status"/><w:id w:val="43"/><w:dropDownList><w:listItem w:displayText="Draft" w:value="draft"/><w:listItem w:displayText="Approved" w:value="approved"/></w:dropDownList></w:sdtPr><w:sdtContent><w:r><w:t>Approved</w:t></w:r></w:sdtContent></w:sdt></w:p><w:tbl><w:tr><w:tc><w:sdt><w:sdtPr><w:alias w:val="Signed date"/><w:tag w:val="signed.date"/><w:id w:val="44"/><w:date w:fullDate="2026-06-26T00:00:00Z"><w:dateFormat w:val="MMMM d, yyyy"/><w:lid w:val="en-US"/></w:date></w:sdtPr><w:sdtContent><w:p><w:r><w:t>June 26, 2026</w:t></w:r></w:p></w:sdtContent></w:sdt></w:tc></w:tr></w:tbl></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);
        $blocks = (new WordPressBlockWriter())->write($document);

        $blockControl = $document->children[0];
        $blockAttrs = $blockControl->attr('attributes');
        $paragraph = $document->children[1];
        $inlineControl = $paragraph->children[1];
        $inlineAttrs = $inlineControl->attr('attributes');
        $table = $document->children[2];
        $tableCell = $table->children[1]->children[0]->children[0];
        $dateControl = $tableCell->children[0];
        $dateAttrs = $dateControl->attr('attributes');

        $t->same('div', $blockControl->type);
        $t->same(['docx-content-control', 'docx-content-control-block'], $blockControl->attr('classes'));
        $t->same('block', $blockAttrs['data-docx-content-control-display']);
        $t->same('text', $blockAttrs['data-docx-content-control-type']);
        $t->same('Customer Name', $blockAttrs['data-docx-content-control-alias']);
        $t->same('customer.name', $blockAttrs['data-docx-content-control-tag']);
        $t->same('42', $blockAttrs['data-docx-content-control-id']);
        $t->same('sdtContentLocked', $blockAttrs['data-docx-content-control-lock']);
        $t->same('CustomerPlaceholder', $blockAttrs['data-docx-content-control-placeholder-doc-part']);
        $t->same('/root/customer/name', $blockAttrs['data-docx-content-control-binding-xpath']);
        $t->same('{11111111-1111-1111-1111-111111111111}', $blockAttrs['data-docx-content-control-binding-store-item-id']);
        $t->same("xmlns:c='urn:customer'", $blockAttrs['data-docx-content-control-binding-prefix-mappings']);
        $t->same('true', $blockAttrs['data-docx-content-control-text-multiline']);
        $t->same('Ada Lovelace', $blockControl->children[0]->attr('text'));

        $t->same('Status: Approved', $paragraph->attr('text'));
        $t->same('span', $inlineControl->type);
        $t->same(['docx-content-control', 'docx-content-control-inline'], $inlineControl->attr('classes'));
        $t->same('inline', $inlineAttrs['data-docx-content-control-display']);
        $t->same('dropDownList', $inlineAttrs['data-docx-content-control-type']);
        $t->same('Status choice', $inlineAttrs['data-docx-content-control-alias']);
        $t->same('2', $inlineAttrs['data-docx-content-control-list-item-count']);
        $t->same('Draft Approved', $inlineAttrs['data-docx-content-control-list-display-texts']);
        $t->same('draft approved', $inlineAttrs['data-docx-content-control-list-values']);

        $t->same('June 26, 2026', $tableCell->attr('text'));
        $t->same('div', $dateControl->type);
        $t->same('date', $dateAttrs['data-docx-content-control-type']);
        $t->same('Signed date', $dateAttrs['data-docx-content-control-alias']);
        $t->same('2026-06-26T00:00:00Z', $dateAttrs['data-docx-content-control-date-full']);
        $t->same('MMMM d, yyyy', $dateAttrs['data-docx-content-control-date-format']);
        $t->same('en-US', $dateAttrs['data-docx-content-control-date-language-id']);

        $t->contains('<div class="docx-content-control docx-content-control-block" data-docx-content-control-display="block"', $blocks);
        $t->contains('data-docx-content-control-binding-xpath="/root/customer/name"', $blocks);
        $t->contains('<span class="docx-content-control docx-content-control-inline" data-docx-content-control-display="inline"', $blocks);
        $t->contains('data-docx-content-control-list-values="draft approved"', $blocks);
        $t->contains('data-docx-content-control-date-full="2026-06-26T00:00:00Z"', $blocks);
    },
    'unwraps docx content controls that only carry generated ids' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:sdt><w:sdtPr><w:id w:val="100"/></w:sdtPr><w:sdtContent><w:sdt><w:sdtPr><w:id w:val="101"/></w:sdtPr><w:sdtContent><w:p><w:r><w:t>Generated block</w:t></w:r></w:p></w:sdtContent></w:sdt></w:sdtContent></w:sdt><w:p><w:r><w:t>Inline </w:t></w:r><w:sdt><w:sdtPr><w:id w:val="102"/></w:sdtPr><w:sdtContent><w:r><w:t>generated</w:t></w:r></w:sdtContent></w:sdt><w:r><w:t> control</w:t></w:r></w:p></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(2, count($document->children));
        $t->same('paragraph', $document->children[0]->type);
        $t->same('Generated block', $document->children[0]->attr('text'));
        $t->same('paragraph', $document->children[1]->type);
        $t->same('Inline generated control', $document->children[1]->attr('text'));
        $t->true(!str_contains($blocks, 'docx-content-control'), 'generated-id-only content controls should not emit provenance wrappers');
        $t->contains('<p>Generated block</p>', $blocks);
        $t->contains('<p>Inline generated control</p>', $blocks);
    },
    'unwraps metadata-light nested docx content controls' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:sdt><w:sdtPr><w:id w:val="2002772120"/></w:sdtPr><w:sdtContent><w:p><w:r><w:t>Test Paragraph1</w:t></w:r></w:p><w:p/><w:sdt><w:sdtPr><w:id w:val="725036187"/></w:sdtPr><w:sdtContent><w:p><w:r><w:t>Test Paragraph2</w:t></w:r></w:p></w:sdtContent></w:sdt><w:p/><w:p><w:r><w:t>Test Paragraph3</w:t></w:r></w:p></w:sdtContent></w:sdt></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(['paragraph', 'paragraph', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same(['Test Paragraph1', 'Test Paragraph2', 'Test Paragraph3'], array_map(static fn ($node): string => (string) $node->attr('text', ''), $document->children));
        $t->true(!str_contains($blocks, 'docx-content-control'), 'Metadata-light SDTs should not introduce wrapper blocks');
    },
    'keeps generated field result rows whose outer fields end in later paragraphs' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes(<<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:sdt>
      <w:sdtPr><w:id w:val="675549173"/><w:docPartObj/></w:sdtPr>
      <w:sdtContent>
        <w:p><w:r><w:t>Contents</w:t></w:r></w:p>
        <w:p>
          <w:r><w:fldChar w:fldCharType="begin"/></w:r>
          <w:r><w:instrText> TOC \o "1-3" \h \z \u </w:instrText></w:r>
          <w:r><w:fldChar w:fldCharType="separate"/></w:r>
          <w:r><w:t xml:space="preserve">Title </w:t></w:r>
          <w:r><w:fldChar w:fldCharType="begin"/></w:r>
          <w:r><w:instrText> PAGEREF _TocA \h </w:instrText></w:r>
          <w:r><w:fldChar w:fldCharType="separate"/></w:r>
          <w:r><w:t>2</w:t></w:r>
          <w:r><w:fldChar w:fldCharType="end"/></w:r>
        </w:p>
        <w:p>
          <w:r><w:t xml:space="preserve">Second </w:t></w:r>
          <w:r><w:fldChar w:fldCharType="begin"/></w:r>
          <w:r><w:instrText> PAGEREF _TocB \h </w:instrText></w:r>
          <w:r><w:fldChar w:fldCharType="separate"/></w:r>
          <w:r><w:t>3</w:t></w:r>
          <w:r><w:fldChar w:fldCharType="end"/></w:r>
        </w:p>
        <w:p><w:r><w:fldChar w:fldCharType="end"/></w:r></w:p>
      </w:sdtContent>
    </w:sdt>
    <w:p><w:r><w:t>Index:</w:t></w:r></w:p>
    <w:p>
      <w:r><w:fldChar w:fldCharType="begin"/></w:r>
      <w:r><w:instrText> INDEX \* MERGEFORMAT </w:instrText></w:r>
      <w:r><w:fldChar w:fldCharType="separate"/></w:r>
      <w:r><w:t>French</w:t></w:r>
      <w:r><w:t>, 1</w:t></w:r>
    </w:p>
    <w:p><w:r><w:fldChar w:fldCharType="end"/></w:r></w:p>
    <w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:bookmarkStart w:id="1" w:name="_TocA"/><w:r><w:t>Title</w:t></w:r><w:bookmarkEnd w:id="1"/></w:p>
    <w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:bookmarkStart w:id="2" w:name="_TocB"/><w:r><w:t>Second</w:t></w:r><w:bookmarkEnd w:id="2"/></w:p>
  </w:body>
</w:document>
XML);

        $document = (new DocxReader())->read($bytes);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(
            ['paragraph', 'paragraph', 'paragraph', 'paragraph', 'paragraph', 'heading', 'heading'],
            array_map(static fn ($node): string => $node->type, $document->children)
        );
        $t->same(
            ['Contents', 'Title 2', 'Second 3', 'Index:', 'French, 1', 'Title', 'Second'],
            array_map(static fn ($node): string => (string) $node->attr('text', ''), $document->children)
        );
        $t->same('span', $document->children[1]->children[0]->type);
        $t->true(in_array('docx-field-toc', $document->children[1]->children[0]->attr('classes'), true));
        $t->same('link', $document->children[1]->children[0]->children[0]->type);
        $t->same('#title', $document->children[1]->children[0]->children[0]->attr('url'));
        $t->same('Title 2', implode('', array_map(static fn ($node): string => (string) $node->attr('text', ''), $document->children[1]->children[0]->children[0]->children)));
        $t->same('link', $document->children[2]->children[0]->children[0]->type);
        $t->same('#second', $document->children[2]->children[0]->children[0]->attr('url'));
        $t->same('Second 3', implode('', array_map(static fn ($node): string => (string) $node->attr('text', ''), $document->children[2]->children[0]->children[0]->children)));
        $t->same('span', $document->children[4]->children[0]->type);
        $t->true(in_array('docx-field-index', $document->children[4]->children[0]->attr('classes'), true));
        $t->true(!str_contains($blocks, 'docx-content-control'), 'Generated docPartObj SDTs should not introduce wrapper blocks');
        $t->contains('<span class="docx-field docx-field-toc', $blocks);
        $t->contains('data-docx-field-target="#title"', $blocks);
        $t->contains('<a href="#title">Title 2</a>', $blocks);
        $t->contains('data-docx-field-target="#second"', $blocks);
        $t->contains('<a href="#second">Second 3</a>', $blocks);
        $t->true(!str_contains(strip_tags($blocks), 'TOC \o'), 'TOC instructions should not render as visible text');
        $t->true(!str_contains(strip_tags($blocks), 'INDEX \*'), 'INDEX instructions should not render as visible text');
    },
    'preserves non-toc docpart content control wrappers' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes(<<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:sdt>
      <w:sdtPr>
        <w:id w:val="17"/>
        <w:docPartObj><w:docPartGallery w:val="Cover Pages"/></w:docPartObj>
      </w:sdtPr>
      <w:sdtContent><w:p><w:r><w:t>Cover page content</w:t></w:r></w:p></w:sdtContent>
    </w:sdt>
  </w:body>
</w:document>
XML);

        $document = (new DocxReader())->read($bytes);
        $blocks = (new WordPressBlockWriter())->write($document);
        $control = $document->children[0];
        $attrs = $control->attr('attributes');

        $t->same('div', $control->type);
        $t->same(['docx-content-control', 'docx-content-control-block'], $control->attr('classes'));
        $t->same('docPartObj', $attrs['data-docx-content-control-type']);
        $t->same('Cover page content', $control->children[0]->attr('text'));
        $t->contains('data-docx-content-control-type="docPartObj"', $blocks);
    },
    'folds docx table caption paragraphs into adjacent tables' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>See Table 1.</w:t></w:r></w:p><w:p><w:pPr><w:pStyle w:val="Caption"/></w:pPr><w:bookmarkStart w:id="1" w:name="_RefTable1"/><w:r><w:t xml:space="preserve">Table </w:t></w:r><w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText xml:space="preserve"> SEQ Table \* ARABIC </w:instrText></w:r><w:r><w:fldChar w:fldCharType="separate"/></w:r><w:r><w:t>1</w:t></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r><w:bookmarkEnd w:id="1"/></w:p><w:tbl><w:tr><w:tc><w:p><w:r><w:t>Count</w:t></w:r></w:p></w:tc></w:tr></w:tbl><w:p><w:pPr><w:pStyle w:val="Heading2"/></w:pPr><w:bookmarkStart w:id="2" w:name="_TocHeading"/><w:bookmarkEnd w:id="2"/></w:p><w:tbl><w:tr><w:tc><w:p><w:r><w:t>One</w:t></w:r></w:p></w:tc></w:tr></w:tbl><w:p><w:pPr><w:pStyle w:val="Caption"/></w:pPr><w:bookmarkStart w:id="3" w:name="_RefTable2"/><w:r><w:t xml:space="preserve">Table </w:t></w:r><w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText xml:space="preserve"> SEQ Table \* ARABIC </w:instrText></w:r><w:r><w:fldChar w:fldCharType="separate"/></w:r><w:r><w:t>2</w:t></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r><w:bookmarkEnd w:id="3"/></w:p><w:p><w:r><w:t>See Table 2.</w:t></w:r></w:p></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);
        $blocks = (new WordPressBlockWriter())->write($document);
        $firstTable = $document->children[1];
        $heading = $document->children[2];
        $secondTable = $document->children[3];

        $t->same(['paragraph', 'table', 'heading', 'table', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Table 1', $firstTable->attr('caption'));
        $t->same('Table 2', $secondTable->attr('caption'));
        $t->same('preceding-table', $firstTable->attr('captionSource')['sourcePosition']);
        $t->same('following-table', $secondTable->attr('captionSource')['sourcePosition']);
        $t->same('span', $firstTable->attr('captionInlines')[0]->type);
        $t->same('_RefTable1', $firstTable->attr('captionInlines')[0]->attr('id'));
        $t->same('heading', $heading->type);
        $t->same(2, $heading->attr('level'));
        $t->contains('data-docx-table-caption-source="preceding-table"><p><span id="_RefTable1" class="anchor"', $blocks);
        $t->contains('data-docx-table-caption-source="following-table"><p><span id="_RefTable2" class="anchor"', $blocks);
    },
    'folds docx table caption paragraphs without sequence fields' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $documentXml = <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="Caption"/></w:pPr><w:r><w:t>Quarterly audit totals</w:t></w:r></w:p>
    <w:tbl><w:tr><w:tc><w:p><w:r><w:t>Count</w:t></w:r></w:p></w:tc></w:tr></w:tbl>
    <w:tbl><w:tr><w:tc><w:p><w:r><w:t>Status</w:t></w:r></w:p></w:tc></w:tr></w:tbl>
    <w:p><w:pPr><w:pStyle w:val="Caption"/></w:pPr><w:r><w:t>Trailing table summary</w:t></w:r></w:p>
    <w:p><w:r><w:t>After tables.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML;
        $document = (new DocxReader())->read($buildDocxReaderPackageBytes($documentXml));
        $blocks = (new WordPressBlockWriter())->write($document);
        $firstTable = $document->children[0];
        $secondTable = $document->children[1];

        $t->same(['table', 'table', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Quarterly audit totals', $firstTable->attr('caption'));
        $t->same('Trailing table summary', $secondTable->attr('caption'));
        $t->same('preceding-table', $firstTable->attr('captionSource')['sourcePosition']);
        $t->same('following-table', $secondTable->attr('captionSource')['sourcePosition']);
        $t->contains('data-docx-table-caption-source="preceding-table"><p>Quarterly audit totals</p>', $blocks);
        $t->contains('data-docx-table-caption-source="following-table"><p>Trailing table summary</p>', $blocks);
    },
    'cleans nested sequence fields and bookmarks in folded docx table captions' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $documentXml = <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:pPr><w:pStyle w:val="Caption"/></w:pPr>
      <w:r><w:t xml:space="preserve">Table </w:t></w:r>
      <w:fldSimple w:instr=" SEQ Table \* ARABIC ">
        <w:bookmarkStart w:id="9" w:name="_RefNestedTable"/>
        <w:r><w:t>7</w:t></w:r>
        <w:bookmarkEnd w:id="9"/>
      </w:fldSimple>
      <w:r><w:t>: Nested field caption</w:t></w:r>
    </w:p>
    <w:tbl><w:tr><w:tc><w:p><w:r><w:t>Total</w:t></w:r></w:p></w:tc></w:tr></w:tbl>
  </w:body>
</w:document>
XML;
        $document = (new DocxReader())->read($buildDocxReaderPackageBytes($documentXml));
        $blocks = (new WordPressBlockWriter())->write($document);
        $native = (new NativeWriter())->write($document);
        $table = $document->children[0];
        $captionInlines = $table->attr('captionInlines');

        $t->same(['table'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Table 7: Nested field caption', $table->attr('caption'));
        $t->same(['text', 'span', 'text'], array_map(static fn ($node): string => $node->type, $captionInlines));
        $t->same('_RefNestedTable', $captionInlines[1]->attr('id'));
        $t->contains('<span id="_RefNestedTable" class="anchor"', $blocks);
        $t->contains('>7: Nested field caption</p>', $blocks);
        $t->true(!str_contains($blocks, 'pandoc-openxml-bookmark'), 'Folded table captions should not leak raw bookmark spans into WordPress output');
        $t->true(!str_contains($blocks, 'docx-field'), 'Folded table captions should not keep raw field wrappers in WordPress output');
        $t->true(!str_contains($native, 'RawInline'), 'Folded table captions should not keep raw bookmark inlines in native output');
        $t->true(!str_contains($native, 'docx-field'), 'Folded table captions should not keep field wrapper attributes in native output');
    },
    'preserves overlapping docx table caption bookmark aliases as linkable anchors' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $documentXml = <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:tbl><w:tr><w:tc><w:p><w:r><w:t>Count</w:t></w:r></w:p></w:tc></w:tr></w:tbl>
    <w:p>
      <w:pPr><w:pStyle w:val="Caption"/></w:pPr>
      <w:bookmarkStart w:id="10" w:name="_RefTable14"/>
      <w:bookmarkStart w:id="11" w:name="_TocTable14"/>
      <w:r><w:t>Table 14: Shared caption</w:t></w:r>
      <w:bookmarkEnd w:id="10"/>
      <w:bookmarkEnd w:id="11"/>
    </w:p>
    <w:p><w:hyperlink w:anchor="_TocTable14"><w:r><w:t>Table 14</w:t></w:r></w:hyperlink></w:p>
  </w:body>
</w:document>
XML;
        $document = (new DocxReader())->read($buildDocxReaderPackageBytes($documentXml));
        $blocks = (new WordPressBlockWriter())->write($document);
        $html = (new HtmlWriter())->write($document);
        $table = $document->children[0];
        $captionInlines = $table->attr('captionInlines');

        $t->same(['table', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Table 14: Shared caption', $table->attr('caption'));
        $t->same(['span', 'span', 'text'], array_map(static fn ($node): string => $node->type, $captionInlines));
        $t->same('_RefTable14', $captionInlines[0]->attr('id'));
        $t->same('_TocTable14', $captionInlines[1]->attr('id'));
        $t->contains('<span id="_TocTable14" class="anchor"', $blocks);
        $t->contains('<a href="#_TocTable14">Table 14</a>', $blocks);
        $t->contains('<span id="_TocTable14" class="anchor"', $html);
        $t->contains('<a href="#_TocTable14">Table 14</a>', $html);
    },
    'places docx table header rows in table head' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl><w:tr><w:trPr><w:tblHeader/></w:trPr><w:tc><w:p><w:r><w:t>Field</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Value</w:t></w:r></w:p></w:tc></w:tr><w:tr><w:trPr><w:tblHeader w:val="0"/></w:trPr><w:tc><w:p><w:r><w:t>Draft flag</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>False header row</w:t></w:r></w:p></w:tc></w:tr><w:tr><w:tc><w:p><w:r><w:t>Total</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>12</w:t></w:r></w:p></w:tc></w:tr></w:tbl></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);
        $blocks = (new WordPressBlockWriter())->write($document);
        $table = $document->children[0];
        $head = $table->children[0];
        $body = $table->children[1];
        $foot = $table->children[2];

        $t->same('table', $table->type);
        $t->same(['table_head', 'table_body', 'table_foot'], array_map(static fn ($node): string => $node->type, $table->children));
        $t->same('table_head', $head->type);
        $t->same(1, count($head->children));
        $t->same('Field', $head->children[0]->children[0]->attr('text'));
        $t->same('Value', $head->children[0]->children[1]->attr('text'));
        $t->same('table_body', $body->type);
        $t->same(2, count($body->children));
        $t->same('Draft flag', $body->children[0]->children[0]->attr('text'));
        $t->same('Total', $body->children[1]->children[0]->attr('text'));
        $t->same([], $foot->children);

        $t->contains('<thead><tr><th>Field</th><th>Value</th></tr></thead>', $blocks);
        $t->contains('<tbody><tr><td>Draft flag</td><td>False header row</td></tr><tr><td>Total</td><td>12</td></tr></tbody>', $blocks);
        $t->true(!str_contains($blocks, '<th>Draft flag</th>'), 'Explicitly disabled tblHeader rows should stay in the table body');
    },
    'keeps docx tblLook rowspan continuation rows in table head' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes(<<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:tbl>
      <w:tblPr><w:tblLook w:firstRow="1"/></w:tblPr>
      <w:tr>
        <w:tc><w:tcPr><w:vMerge w:val="restart"/></w:tcPr><w:p><w:r><w:t>A</w:t></w:r></w:p></w:tc>
        <w:tc><w:tcPr><w:gridSpan w:val="2"/></w:tcPr><w:p><w:r><w:t>B</w:t></w:r></w:p></w:tc>
      </w:tr>
      <w:tr>
        <w:tc><w:tcPr><w:vMerge/></w:tcPr><w:p/></w:tc>
        <w:tc><w:p><w:r><w:t>G</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>H</w:t></w:r></w:p></w:tc>
      </w:tr>
      <w:tr>
        <w:tc><w:p><w:r><w:t>1</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>2</w:t></w:r></w:p></w:tc>
        <w:tc><w:p><w:r><w:t>3</w:t></w:r></w:p></w:tc>
      </w:tr>
    </w:tbl>
  </w:body>
</w:document>
XML);

        $document = (new DocxReader())->read($bytes);
        $table = $document->children[0];
        $head = $table->children[0];
        $body = $table->children[1];

        $t->same(2, count($head->children));
        $t->same(1, count($body->children));
        $t->same('A', $head->children[0]->children[0]->attr('text'));
        $t->same(2, $head->children[0]->children[0]->attr('rowspan'));
        $t->same('B', $head->children[0]->children[1]->attr('text'));
        $t->same(2, $head->children[0]->children[1]->attr('colspan'));
        $t->same(['G', 'H'], array_map(static fn ($cell): string => $cell->attr('text'), $head->children[1]->children));
        $t->same(['1', '2', '3'], array_map(static fn ($cell): string => $cell->attr('text'), $body->children[0]->children));
    },
    'preserves docx table gridBefore omitted leading cells' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl><w:tr><w:trPr><w:tblHeader/><w:gridBefore w:val="1"/></w:trPr><w:tc><w:p><w:r><w:t>Field</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Value</w:t></w:r></w:p></w:tc></w:tr><w:tr><w:trPr><w:gridBefore w:val="2"/></w:trPr><w:tc><w:p><w:r><w:t>North</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>12</w:t></w:r></w:p></w:tc></w:tr></w:tbl></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);
        $blocks = (new WordPressBlockWriter())->write($document);
        $table = $document->children[0];
        $headRow = $table->children[0]->children[0];
        $bodyRow = $table->children[1]->children[0];

        $t->same(3, count($headRow->children));
        $t->same('', $headRow->children[0]->attr('text'));
        $t->same(['data-docx-omitted-cell' => 'gridBefore'], $headRow->children[0]->attr('htmlAttributes'));
        $t->same('Field', $headRow->children[1]->attr('text'));
        $t->same('Value', $headRow->children[2]->attr('text'));

        $t->same(4, count($bodyRow->children));
        $t->same(['data-docx-omitted-cell' => 'gridBefore'], $bodyRow->children[0]->attr('htmlAttributes'));
        $t->same(['data-docx-omitted-cell' => 'gridBefore'], $bodyRow->children[1]->attr('htmlAttributes'));
        $t->same('North', $bodyRow->children[2]->attr('text'));
        $t->same('12', $bodyRow->children[3]->attr('text'));

        $t->contains('<thead><tr><th data-docx-omitted-cell="gridBefore"></th><th>Field</th><th>Value</th></tr></thead>', $blocks);
        $t->contains('<tbody><tr><td data-docx-omitted-cell="gridBefore"></td><td data-docx-omitted-cell="gridBefore"></td><td>North</td><td>12</td></tr></tbody>', $blocks);
    },
    'normalizes docx default table widths to native defaults' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl><w:tr><w:tc><w:p><w:r><w:t>Left</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Right</w:t></w:r></w:p></w:tc></w:tr></w:tbl></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);
        $table = $document->children[0];
        $native = (new NativeWriter())->write($document);

        $t->same('table', $table->type);
        $t->same(['default', 'default'], $table->attr('alignments'));
        $t->same([0.0, 0.0], $table->attr('widths'));
        $t->same(2, substr_count($native, 'ColWidthDefault'));
    },
    'derives left docx table column alignment from single column cells' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl><w:tr><w:tc><w:p><w:pPr><w:jc w:val="left"/></w:pPr><w:r><w:t>Left aligned</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Default</w:t></w:r></w:p></w:tc></w:tr></w:tbl></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);
        $table = $document->children[0];
        $native = (new NativeWriter())->write($document);

        $t->same(['left', 'default'], $table->attr('alignments'));
        $t->same('left', $table->children[1]->children[0]->children[0]->attr('align'));
        $t->contains('[ ( AlignLeft , ColWidthDefault )', $native);
    },
    'maps docx table grid column widths to native fractions' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl><w:tblGrid><w:gridCol w:w="2000"/><w:gridCol w:w="3990"/></w:tblGrid><w:tr><w:tc><w:p><w:r><w:t>Left</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Right</w:t></w:r></w:p></w:tc></w:tr></w:tbl><w:sectPr><w:pgSz w:w="10000" w:h="12000"/><w:pgMar w:left="1000" w:right="1000" w:gutter="0"/></w:sectPr></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);
        $table = $document->children[0];

        $t->same([2000 / 7990, 3990 / 7990], $table->attr('widths'));
    },
    'reads docx content control wrapped table cells' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl><w:tr><w:tc><w:p><w:r><w:t>Head</w:t></w:r></w:p></w:tc><w:sdt><w:sdtContent><w:tc><w:p><w:r><w:t>Body copy</w:t></w:r></w:p></w:tc></w:sdtContent></w:sdt><w:tc><w:p><w:r><w:t>Tail</w:t></w:r></w:p></w:tc></w:tr></w:tbl></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);
        $blocks = (new WordPressBlockWriter())->write($document);
        $row = $document->children[0]->children[1]->children[0];

        $t->same(3, count($row->children));
        $t->same('Head', $row->children[0]->attr('text'));
        $t->same('Body copy', $row->children[1]->attr('text'));
        $t->same('Tail', $row->children[2]->attr('text'));
        $t->contains('<td>Body copy</td>', $blocks);
    },
    'maps docx shape-only textbox paragraphs as document blocks' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:v="urn:schemas-microsoft-com:vml"><w:body><w:p><w:r><w:pict><v:shape id="TextBoxTitle" type="#_x0000_t202"><v:textbox><w:txbxContent><w:p><w:r><w:t xml:space="preserve">Last update: </w:t></w:r><w:r><w:t>May 1, 2017</w:t></w:r></w:p><w:p><w:r><w:t>Using Microsoft Word 2007/2010</w:t></w:r><w:r><w:br/></w:r><w:r><w:rPr><w:b/></w:rPr><w:t>for Writing Technical Documents</w:t></w:r></w:p><w:p><w:r><w:t>Valter Kiisk</w:t></w:r></w:p></w:txbxContent></v:textbox></v:shape></w:pict></w:r></w:p><w:p><w:bookmarkStart w:id="1" w:name="_Toc219459029"/><w:bookmarkEnd w:id="1"/></w:p></w:body></w:document>');

        $document = (new DocxReader())->read($bytes);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(['paragraph', 'paragraph', 'paragraph', 'paragraph'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('Last update: May 1, 2017', $document->children[0]->attr('text'));
        $t->same('Using Microsoft Word 2007/2010 for Writing Technical Documents', $document->children[1]->attr('text'));
        $t->same('linebreak', $document->children[1]->children[1]->type);
        $t->same('strong', $document->children[1]->children[2]->type);
        $t->same('for Writing Technical Documents', $document->children[1]->children[2]->children[0]->attr('text'));
        $t->same('Valter Kiisk', $document->children[2]->attr('text'));
        $t->same('', $document->children[3]->attr('text'));
        $t->true(!str_contains((new NativeWriter())->write($document), '_Toc219459029'), 'Generated shape bookmarks should not leak into native output');
        $t->true(!str_contains($blocks, 'docx-textbox'), 'Shape-only textboxes should surface as body blocks rather than inline textbox spans');
        $t->contains('<strong>for Writing Technical Documents</strong>', $blocks);
    },
    'promotes docx image paragraphs with textbox captions to figures' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/_rels/document.xml.rels', 'data' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/captioned.emf"/></Relationships>'],
            ['name' => 'word/document.xml', 'data' => <<<'XML'
<?xml version="1.0"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture" xmlns:v="urn:schemas-microsoft-com:vml">
  <w:body>
    <w:p>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:extent cx="2165350" cy="854075"/>
            <wp:docPr id="1" name="Picture 1"/>
            <a:graphic>
              <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">
                <pic:pic>
                  <pic:blipFill><a:blip r:embed="rIdImage"/></pic:blipFill>
                </pic:pic>
              </a:graphicData>
            </a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
      <w:r>
        <w:pict>
          <v:shape id="CaptionBox" type="#_x0000_t202" style="width:198pt;height:12pt">
            <v:textbox>
              <w:txbxContent>
                <w:p>
                  <w:r><w:fldChar w:fldCharType="begin"/></w:r>
                  <w:r><w:instrText xml:space="preserve"> SEQ Figure \* ARABIC </w:instrText></w:r>
                  <w:r><w:fldChar w:fldCharType="separate"/></w:r>
                  <w:r><w:t>1</w:t></w:r>
                  <w:r><w:fldChar w:fldCharType="end"/></w:r>
                  <w:r><w:t xml:space="preserve"> Caption text</w:t></w:r>
                </w:p>
              </w:txbxContent>
            </v:textbox>
          </v:shape>
        </w:pict>
      </w:r>
    </w:p>
  </w:body>
</w:document>
XML],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);
        $figure = $document->children[0];
        $image = $figure->children[0];

        $t->same(['figure'], array_map(static fn ($node): string => $node->type, $document->children));
        $t->same('1 Caption text', $figure->attr('caption'));
        $t->same('image', $image->type);
        $t->same('paragraph', $figure->attr('captionBlocks')[0]->type);
        $t->same('media/captioned.emf', $image->attr('url'));
        $t->same('2.3680555555555554in', $image->attr('width'));
        $t->same('0.9340277777777778in', $image->attr('height'));
        $t->same('textbox', $figure->attr('attributes')['data-docx-figure-caption-source']);
        $t->same('CaptionBox', $figure->attr('attributes')['data-docx-vml-shape-id']);
        $t->contains('<figure', $blocks);
        $t->contains('Caption text', $blocks);
    },
    'preserves docx comment ranges moves table merges styles and image metadata' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-docx-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary DOCX path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary DOCX package');
        }
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/chart.png"/></Relationships>');
        $zip->addFromString('word/comments.xml', '<?xml version="1.0"?><w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:comment w:id="5" w:author="Range Reviewer" w:date="2026-06-26T00:00:00Z"><w:p><w:r><w:t>Range body.</w:t></w:r></w:p></w:comment></w:comments>');
        $zip->addFromString('word/media/chart.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><w:body><w:p><w:commentRangeStart w:id="5"/><w:r><w:t>Ranged comment</w:t></w:r><w:commentRangeEnd w:id="5"/><w:r><w:commentReference w:id="5"/></w:r></w:p><w:p><w:moveFrom w:id="8" w:author="Mover" w:date="2026-06-26T00:00:00Z"><w:r><w:delText>old spot</w:delText></w:r></w:moveFrom><w:r><w:t> to </w:t></w:r><w:moveTo w:id="8" w:author="Mover" w:date="2026-06-26T00:01:00Z"><w:r><w:t>new spot</w:t></w:r></w:moveTo><w:r><w:rPr><w:u w:val="single"/><w:strike/><w:vertAlign w:val="superscript"/></w:rPr><w:t> styled</w:t></w:r></w:p><w:tbl><w:tblPr><w:tblStyle w:val="ReviewTable"/></w:tblPr><w:tr><w:tc><w:tcPr><w:vMerge w:val="restart"/><w:shd w:fill="FFFF00"/><w:vAlign w:val="center"/></w:tcPr><w:p><w:r><w:t>Group</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Top</w:t></w:r></w:p></w:tc></w:tr><w:tr><w:tc><w:tcPr><w:vMerge/></w:tcPr><w:p><w:r><w:t>Skipped continuation</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Bottom</w:t></w:r></w:p></w:tc></w:tr></w:tbl><w:p><w:r><w:drawing><wp:inline><wp:extent cx="1828800" cy="914400"/><wp:docPr id="9" name="Chart 1" descr="Chart alt" title="Chart title"/><a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rIdImage"/></pic:blipFill></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p></w:body></w:document>');
        $zip->close();

        try {
            $document = (new DocxReader())->readDocxFile($path);
            $blocks = (new WordPressBlockWriter())->write($document);
        } finally {
            @unlink($path);
        }

        $comment = $document->children[0];
        $move = $document->children[1];
        $table = $document->children[2];
        $image = $document->children[3]->children[0];

        $t->same('span', $comment->children[0]->type);
        $t->same(['comment-start'], $comment->children[0]->attr('classes'));
        $t->same('5', $comment->children[0]->attr('attributes')['id']);
        $t->same('Range Reviewer', $comment->children[0]->attr('attributes')['author']);
        $t->same(['comment-end'], $comment->children[2]->attr('classes'));
        $t->same(['id' => '5'], $comment->children[2]->attr('attributes'));
        $t->same(['deletion', 'move-from'], $move->children[0]->attr('classes'));
        $t->same(['insertion', 'move-to'], $move->children[2]->attr('classes'));
        $t->same('superscript', $move->children[3]->type);
        $t->same('strikeout', $move->children[3]->children[0]->type);
        $t->same('underline', $move->children[3]->children[0]->children[0]->type);
        $t->same('ReviewTable', $table->attr('htmlAttributes')['data-docx-table-style']);
        $firstCell = $table->children[1]->children[0]->children[0];
        $secondRow = $table->children[1]->children[1];
        $t->same(2, $firstCell->attr('rowspan'));
        $t->same('restart', $firstCell->attr('htmlAttributes')['data-docx-vmerge']);
        $t->same('background-color:#FFFF00; vertical-align:middle', $firstCell->attr('htmlAttributes')['style']);
        $t->same(1, count($secondRow->children));
        $t->same('Bottom', $secondRow->children[0]->attr('text'));
        $t->same('Chart alt', $image->attr('alt'));
        $t->same('Chart title', $image->attr('title'));
        $t->same('2in', $image->attr('width'));
        $t->same('1in', $image->attr('height'));
        $t->same('Chart 1', $image->attr('attributes')['data-docx-image-name']);
        $t->same('9', $image->attr('attributes')['data-docx-image-id']);
        $t->contains('class="comment-start" data-pandoc-comment-id="5" data-pandoc-comment-author="Range Reviewer"', $blocks);
        $t->contains('<del class="deletion move-from" data-pandoc-change-author="Mover"', $blocks);
        $t->contains('<ins class="insertion move-to" data-pandoc-change-author="Mover"', $blocks);
        $t->contains('new spot </ins><sup><del><u>styled</u></del></sup>', $blocks);
        $t->contains('<table data-docx-table-style="ReviewTable">', $blocks);
        $t->contains('data-docx-vmerge="restart" rowspan="2" style="background-color:#FFFF00; vertical-align:middle"', $blocks);
        $t->contains('<td>Bottom</td>', $blocks);
        $t->contains('alt="Chart alt" title="Chart title" data-pandoc-width="2in" data-pandoc-height="1in"', $blocks);
        $t->contains('data-docx-image-name="Chart 1"', $blocks);
    },
    'preserves docx smartart diagrams as pandoc placeholder spans' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/_rels/document.xml.rels', 'data' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rData" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramData" Target="diagrams/data1.xml"/><Relationship Id="rLayout" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramLayout" Target="diagrams/layout1.xml"/><Relationship Id="rStyle" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramQuickStyle" Target="diagrams/quickStyle1.xml"/><Relationship Id="rColors" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramColors" Target="diagrams/colors1.xml"/></Relationships>'],
            ['name' => 'word/document.xml', 'data' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram"><w:body><w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Diagram after:</w:t></w:r></w:p><w:p><w:r><w:drawing><wp:inline><wp:extent cx="914400" cy="457200"/><wp:docPr id="19" name="Review workflow" descr="Imported workflow diagram" title="Review workflow"/><a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/diagram"><dgm:relIds r:dm="rData" r:lo="rLayout" r:qs="rStyle" r:cs="rColors"/></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p></w:body></w:document>'],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);
        $heading = $document->children[0];
        $paragraph = $document->children[1];
        $diagram = $paragraph->children[0];
        $attrs = $diagram->attr('attributes');

        $t->same('heading', $heading->type);
        $t->same(1, $heading->attr('level'));
        $t->same('Diagram after:', $heading->attr('text'));
        $t->same('paragraph', $paragraph->type);
        $t->same('[DIAGRAM]', $paragraph->attr('text'));
        $t->same('span', $diagram->type);
        $t->same(['diagram'], $diagram->attr('classes'));
        $t->same('[DIAGRAM]', $diagram->children[0]->attr('text'));
        $t->same('http://schemas.openxmlformats.org/drawingml/2006/diagram', $attrs['data-docx-diagram-uri']);
        $t->same('19', $attrs['data-docx-diagram-id']);
        $t->same('Review workflow', $attrs['data-docx-diagram-name']);
        $t->same('Review workflow', $attrs['data-docx-diagram-title']);
        $t->same('Imported workflow diagram', $attrs['data-docx-diagram-description']);
        $t->same('1in', $attrs['data-docx-diagram-width']);
        $t->same('0.5in', $attrs['data-docx-diagram-height']);
        $t->same('rData', $attrs['data-docx-diagram-data-relationship-id']);
        $t->same('word/diagrams/data1.xml', $attrs['data-docx-diagram-data-target']);
        $t->same('rLayout', $attrs['data-docx-diagram-layout-relationship-id']);
        $t->same('word/diagrams/layout1.xml', $attrs['data-docx-diagram-layout-target']);
        $t->same('rStyle', $attrs['data-docx-diagram-quick-style-relationship-id']);
        $t->same('word/diagrams/quickStyle1.xml', $attrs['data-docx-diagram-quick-style-target']);
        $t->same('rColors', $attrs['data-docx-diagram-colors-relationship-id']);
        $t->same('word/diagrams/colors1.xml', $attrs['data-docx-diagram-colors-target']);
        $t->contains('<span class="diagram"', $blocks);
        $t->contains('data-docx-diagram-name="Review workflow"', $blocks);
        $t->contains('data-docx-diagram-data-target="word/diagrams/data1.xml"', $blocks);
    },
    'preserves docx table header rows and grid before after placeholders' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl><w:tr><w:trPr><w:tblHeader/><w:gridBefore w:val="1"/><w:gridAfter w:val="1"/></w:trPr><w:tc><w:p><w:r><w:t>Head A</w:t></w:r></w:p></w:tc><w:tc><w:tcPr><w:gridSpan w:val="2"/></w:tcPr><w:p><w:r><w:t>Head BC</w:t></w:r></w:p></w:tc></w:tr><w:tr><w:tc><w:p><w:r><w:t>Body A</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Body B</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Body C</w:t></w:r></w:p></w:tc></w:tr></w:tbl></w:body></w:document>'],
        ]);

        $document = (new DocxReader())->readDocument($package);
        $blocks = (new WordPressBlockWriter())->write($document);
        $table = $document->children[0];
        $head = $table->children[0];
        $body = $table->children[1];
        $headRow = $head->children[0];
        $bodyRow = $body->children[0];

        $t->same('table', $table->type);
        $t->same('table_head', $head->type);
        $t->same('table_body', $body->type);
        $t->same(1, count($head->children));
        $t->same(1, count($body->children));
        $t->same(4, count($headRow->children));
        $t->same('', $headRow->children[0]->attr('text'));
        $t->same('gridBefore', $headRow->children[0]->attr('htmlAttributes')['data-docx-omitted-cell']);
        $t->same('Head A', $headRow->children[1]->attr('text'));
        $t->same('Head BC', $headRow->children[2]->attr('text'));
        $t->same(2, $headRow->children[2]->attr('colspan'));
        $t->same('gridAfter', $headRow->children[3]->attr('htmlAttributes')['data-docx-omitted-cell']);
        $t->same(3, count($bodyRow->children));
        $t->same('Body A', $bodyRow->children[0]->attr('text'));
        $t->contains('<thead><tr>', $blocks);
        $t->contains('<th data-docx-omitted-cell="gridBefore"></th>', $blocks);
        $t->contains('<th colspan="2">Head BC</th>', $blocks);
        $t->contains('<th data-docx-omitted-cell="gridAfter"></th>', $blocks);
        $t->contains('<tbody><tr><td>Body A</td>', $blocks);
    },
    'reads docx text boxes vml object images and inherited table style metadata' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-docx-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary DOCX path');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create temporary DOCX package');
        }
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdObject" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/object-preview.png"/></Relationships>');
        $zip->addFromString('word/styles.xml', '<?xml version="1.0"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:style w:type="character" w:styleId="BaseChar"><w:name w:val="Base Character"/><w:rPr><w:b/></w:rPr></w:style><w:style w:type="character" w:styleId="DerivedChar"><w:name w:val="Derived Character"/><w:basedOn w:val="BaseChar"/><w:rPr><w:i/></w:rPr></w:style><w:style w:type="table" w:styleId="BaseTable"><w:name w:val="Base Table"/><w:tblPr><w:shd w:fill="D9EAF7"/><w:jc w:val="center"/></w:tblPr></w:style><w:style w:type="table" w:styleId="DerivedTable"><w:name w:val="Derived Table"/><w:basedOn w:val="BaseTable"/></w:style></w:styles>');
        $zip->addFromString('word/media/object-preview.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office"><w:body><w:p><w:r><w:t>Before </w:t></w:r><w:r><w:pict><v:shape id="TextBox1" type="#_x0000_t202" style="width:120pt;height:40pt"><v:textbox><w:txbxContent><w:p><w:r><w:t>Boxed </w:t></w:r><w:r><w:rPr><w:rStyle w:val="DerivedChar"/></w:rPr><w:t>strong italic</w:t></w:r></w:p></w:txbxContent></v:textbox></v:shape></w:pict></w:r><w:r><w:t> after.</w:t></w:r></w:p><w:p><w:r><w:object><v:shape id="_x0000_i1025" type="#_x0000_t75" style="width:48pt;height:24pt"><v:imagedata r:id="rIdObject" o:title="Object preview"/></v:shape><w:dxaOrig w:val="960"/><w:dyaOrig w:val="480"/></w:object></w:r></w:p><w:tbl><w:tblPr><w:tblStyle w:val="DerivedTable"/></w:tblPr><w:tr><w:tc><w:p><w:r><w:t>Styled cell</w:t></w:r></w:p></w:tc></w:tr></w:tbl></w:body></w:document>');
        $zip->close();

        try {
            $document = (new DocxReader())->readDocxFile($path);
            $blocks = (new WordPressBlockWriter())->write($document);
        } finally {
            @unlink($path);
        }

        $paragraph = $document->children[0];
        $textBox = $paragraph->children[1];
        $styledText = $textBox->children[1];
        $image = $document->children[1]->children[0];
        $table = $document->children[2];
        $tableAttributes = $table->attr('htmlAttributes');

        $t->same('Before Boxed strong italic after.', $paragraph->attr('text'));
        $t->same('span', $textBox->type);
        $t->same(['docx-textbox'], $textBox->attr('classes'));
        $t->same('vml-pict', $textBox->attr('attributes')['data-docx-textbox-source']);
        $t->same('TextBox1', $textBox->attr('attributes')['data-docx-vml-shape-id']);
        $t->same('emph', $styledText->type);
        $t->same('strong', $styledText->children[0]->type);
        $t->same('strong italic', $styledText->children[0]->children[0]->attr('text'));

        $t->same('image', $image->type);
        $t->same('media/object-preview.png', $image->attr('url'));
        $t->same('Object preview', $image->attr('alt'));
        $t->same('Object preview', $image->attr('title'));
        $t->same(null, $image->attr('width'));
        $t->same(null, $image->attr('height'));
        $t->same('vml-object', $image->attr('attributes')['data-docx-image-source']);
        $t->same('rIdObject', $image->attr('attributes')['data-docx-image-relationship-id']);
        $t->same('_x0000_i1025', $image->attr('attributes')['data-docx-vml-shape-id']);
        $t->same('48pt', $image->attr('attributes')['data-docx-vml-image-width']);
        $t->same('24pt', $image->attr('attributes')['data-docx-vml-image-height']);
        $t->same('960', $image->attr('attributes')['data-docx-object-dxa-orig']);
        $t->same('480', $image->attr('attributes')['data-docx-object-dya-orig']);

        $t->same(['default'], $table->attr('alignments'));
        $t->same([0.0], $table->attr('widths'));
        $t->same('DerivedTable', $tableAttributes['data-docx-table-style']);
        $t->same('Derived Table', $tableAttributes['data-docx-table-style-name']);
        $t->same('BaseTable', $tableAttributes['data-docx-table-style-based-on']);
        $t->same('BaseTable DerivedTable', $tableAttributes['data-docx-table-style-chain']);
        $t->same('D9EAF7', $tableAttributes['data-docx-table-style-fill']);
        $t->same('center', $tableAttributes['data-docx-table-style-align']);

        $t->contains('<span class="docx-textbox" data-docx-textbox-source="vml-pict"', $blocks);
        $t->contains('Boxed <em><strong>strong italic</strong></em>', $blocks);
        $t->contains('data-docx-image-source="vml-object"', $blocks);
        $t->contains('data-docx-vml-shape-id="_x0000_i1025"', $blocks);
        $t->contains('data-docx-table-style-chain="BaseTable DerivedTable"', $blocks);
        $t->contains('data-docx-table-style-fill="D9EAF7"', $blocks);
    },
    'resolves tracked paragraph marks according to the selected revision mode' => static function (TestRunner $t) use ($buildDocxReaderPackageBytes): void {
        $bytes = $buildDocxReaderPackageBytes('<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:pPr><w:rPr><w:ins w:author="Editor" w:date="2026-07-11T00:00:00Z"/></w:rPr></w:pPr><w:r><w:t>This is a</w:t></w:r></w:p><w:p><w:pPr><w:rPr><w:del w:author="Editor" w:date="2026-07-11T00:00:00Z"/></w:rPr></w:pPr><w:r><w:t xml:space="preserve"> split</w:t></w:r></w:p><w:p><w:r><w:t>Paragraph.</w:t></w:r></w:p></w:body></w:document>');

        $accept = (new DocxReader(['revisionMode' => 'accept']))->read($bytes);
        $reject = (new DocxReader(['revisionMode' => 'reject']))->read($bytes);
        $preserve = (new DocxReader(['revisionMode' => 'preserve']))->read($bytes);

        $t->same(['This is a', 'split Paragraph.'], array_map(static fn (AstNode $node): string => (string) $node->attr('text'), $accept->children));
        $t->same(['This is a split', 'Paragraph.'], array_map(static fn (AstNode $node): string => (string) $node->attr('text'), $reject->children));
        $t->same(['This is a', 'split', 'Paragraph.'], array_map(static fn (AstNode $node): string => (string) $node->attr('text'), $preserve->children));
        $t->same(['paragraph-insertion'], $preserve->children[0]->children[1]->attr('classes'));
        $t->same(['paragraph-deletion'], $preserve->children[1]->children[1]->attr('classes'));
        $t->same(['author' => 'Editor', 'date' => '2026-07-11T00:00:00Z'], $preserve->children[0]->children[1]->attr('attributes'));
    },
    'maps docx styles extension to pandoc custom-style divs and spans' => static function (TestRunner $t) use ($buildDocxReaderPackagePartsBytes): void {
        $bytes = $buildDocxReaderPackagePartsBytes([
            '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>',
            'word/styles.xml' => '<?xml version="1.0"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:style w:type="paragraph" w:styleId="BodyText"><w:name w:val="Body Text"/></w:style><w:style w:type="paragraph" w:styleId="FirstParagraph"><w:name w:val="First Paragraph"/><w:basedOn w:val="BodyText"/></w:style><w:style w:type="paragraph" w:styleId="BlockText"><w:name w:val="Block Text"/></w:style><w:style w:type="paragraph" w:styleId="MyBlockStyle"><w:name w:val="My Block Style"/><w:basedOn w:val="BlockText"/></w:style><w:style w:type="character" w:styleId="Emphatic"><w:name w:val="Emphatic"/><w:rPr><w:i/></w:rPr></w:style><w:style w:type="character" w:styleId="Strengthened"><w:name w:val="Strengthened"/><w:rPr><w:b/></w:rPr></w:style></w:styles>',
            'word/document.xml' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:pPr><w:pStyle w:val="FirstParagraph"/></w:pPr><w:r><w:t>First.</w:t></w:r></w:p><w:p><w:pPr><w:pStyle w:val="BodyText"/></w:pPr><w:r><w:t xml:space="preserve">A </w:t></w:r><w:r><w:rPr><w:rStyle w:val="Emphatic"/></w:rPr><w:t>styled</w:t></w:r><w:r><w:t xml:space="preserve"> and </w:t></w:r><w:r><w:rPr><w:rStyle w:val="Strengthened"/></w:rPr><w:t>strong</w:t></w:r><w:r><w:t> run.</w:t></w:r></w:p><w:p><w:pPr><w:pStyle w:val="MyBlockStyle"/></w:pPr><w:r><w:t>Quoted.</w:t></w:r></w:p></w:body></w:document>',
        ]);

        $default = (new DocxReader())->read($bytes);
        $document = (new DocxReader(['stylesExtension' => true]))->read($bytes);
        $blocks = (new WordPressBlockWriter())->write($document);
        $inlineStyles = array_values(array_filter(
            $document->children[1]->children[0]->children,
            static fn (AstNode $node): bool => $node->type === 'span'
        ));

        $t->same(['paragraph', 'paragraph', 'blockquote'], array_map(static fn (AstNode $node): string => $node->type, $default->children));
        $t->same(['div', 'div', 'div'], array_map(static fn (AstNode $node): string => $node->type, $document->children));
        $t->same('First Paragraph', $document->children[0]->attr('attributes')['custom-style']);
        $t->same('Body Text', $document->children[1]->attr('attributes')['custom-style']);
        $t->same('My Block Style', $document->children[2]->attr('attributes')['custom-style']);
        $t->same('blockquote', $document->children[2]->children[0]->type);
        $t->same(['Emphatic', 'Strengthened'], array_map(static fn (AstNode $node): string => (string) $node->attr('attributes')['custom-style'], $inlineStyles));
        $t->same(['span', 'span'], array_map(static fn (AstNode $node): string => $node->type, $inlineStyles));
        $t->contains('<div class="wp-block-group" data-pandoc-custom-style="Body Text">', $blocks);
        $t->contains('<p>A <span data-pandoc-custom-style="Emphatic">styled</span> and <span data-pandoc-custom-style="Strengthened">strong</span> run.</p>', $blocks);

        $viaFormat = PandocConverter::read($bytes, 'docx+styles');
        $t->true(PandocConverter::canRead('docx+styles'));
        $t->same(['div', 'div', 'div'], array_map(static fn (AstNode $node): string => $node->type, $viaFormat->children));
        $t->same('Body Text', $viaFormat->children[1]->attr('attributes')['custom-style']);
    },
];
