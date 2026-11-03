<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DocxReader
{
    private const INTERNAL_STYLE_ORIGIN_ATTR = '__docxStyleOrigin';
    private const PARAGRAPH_END_REVISION_ATTR = '__docxParagraphEndRevision';
    private const PARAGRAPH_STYLE_ATTR = '__docxParagraphStyle';
    private const MAX_WARNING_COUNT = 32;
    private const MAX_DOCX_WARNING_LENGTH = 240;

    private const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const R_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const A_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const WP_NS = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';
    private const M_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/math';
    private const DGM_NS = 'http://schemas.openxmlformats.org/drawingml/2006/diagram';
    private const REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';
    private const OFFICE_NS = 'urn:schemas-microsoft-com:office:office';

    /** @var array<string, array<string, mixed>> */
    private array $styles = [];

    /** @var array<string, array<int, array{ordered: bool, style?: string, delimiter?: string, start?: int, styleId?: string, text?: string, continuation?: bool, taskChecked?: bool}>> */
    private array $numbering = [];

    /** @var array<string, array{target: string, type: string, mode: string}> */
    private array $relationships = [];

    private string $documentPartName = 'word/document.xml';

    /** @var array<string, list<AstNode>> */
    private array $footnotes = [];

    /** @var array<string, list<AstNode>> */
    private array $endnotes = [];

    /** @var array<string, array{author: string, date: string, children: list<AstNode>, inlines: list<AstNode>, text: string}> */
    private array $comments = [];

    /** @var array<string, true> */
    private array $commentRangeIds = [];

    /** @var list<string> */
    private array $warnings = [];

    /** @var array<string, true> */
    private array $warningKeys = [];

    private int $warningOverflowCount = 0;

    /** @var array<string, mixed> */
    private array $bodyMetadata = [];

    /** @var array<string, int> */
    private array $headingIds = [];

    /** @var array<string, true> */
    private array $suppressedBookmarkIds = [];

    private int $textWidthTwips = 9360;

    /** @var array<string, true> */
    private array $openRawBookmarkIds = [];

    private string $revisionMode;

    private string $commentsMode;

    private bool $collectWarnings;

    private bool $stylesExtension;

    private bool $preserveRunStyles;

    private bool $preserveImportStyles;

    /**
     * @param array{revisionMode?: string, commentsMode?: string, collectWarnings?: bool, stylesExtension?: bool, preserveRunStyles?: bool, preserveImportStyles?: bool} $options
     */
    public function __construct(array $options = [])
    {
        $this->revisionMode = $this->normalizeRevisionMode((string) ($options['revisionMode'] ?? 'preserve'));
        $this->commentsMode = $this->normalizeCommentsMode((string) ($options['commentsMode'] ?? 'preserve'));
        $this->collectWarnings = (bool) ($options['collectWarnings'] ?? false);
        $this->stylesExtension = (bool) ($options['stylesExtension'] ?? false);
        $this->preserveRunStyles = (bool) ($options['preserveRunStyles'] ?? false);
        $this->preserveImportStyles = (bool) ($options['preserveImportStyles'] ?? $this->preserveRunStyles);
    }

    public function read(string $bytes): AstNode
    {
        try {
            $package = ZipPackage::fromString($bytes);
        } catch (\RuntimeException|\InvalidArgumentException $exception) {
            return $this->fallbackDocument($bytes, $exception->getMessage());
        }

        return $this->readDocument($package);
    }

    private function fallbackDocument(string $bytes, string $error): AstNode
    {
        return new AstNode('document', [
            'sourceFormat' => 'docx',
            'meta' => [
                'sourceFormat' => 'docx',
                'docxFallback' => [
                    'reason' => 'invalid-docx-package',
                    'error' => $error,
                    'sourceBytes' => strlen($bytes),
                    'sourceSha256' => hash('sha256', $bytes),
                ],
            ],
        ], [
            new AstNode('code_block', [
                'text' => strlen($bytes) <= 8192 ? $bytes : substr($bytes, 0, 8192) . "\n...",
                'classes' => ['docx-source'],
            ]),
        ]);
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function readDocument(ZipPackage $package): AstNode
    {
        $this->warnings = [];
        $this->warningKeys = [];
        $this->warningOverflowCount = 0;
        $entriesByName = [];
        $entries = [];
        $media = [];
        $header_xmls = [];
        $footer_xmls = [];

        foreach ($package->entries() as $entry) {
            if ($entry->isDirectory()) {
                continue;
            }

            $name = $entry->name;
            $entriesByName[$name] = $entry;
            $entries[] = $name;

            if (str_starts_with($name, 'word/media/')) {
                $media[] = $name;
            }
            if (preg_match('#^word/header\d*\.xml$#', $name) === 1 && $this->isReadablePackageEntry($entry)) {
                $header_xmls[$name] = $package->read($name);
            }
            if (preg_match('#^word/footer\d*\.xml$#', $name) === 1 && $this->isReadablePackageEntry($entry)) {
                $footer_xmls[$name] = $package->read($name);
            }
        }

        $documentPartName = $this->mainDocumentPartName($package, $entriesByName);
        $documentRelationshipsPartName = $this->relationshipsPartName($documentPartName);
        $documentRelationshipsXml = $this->readOptionalPackagePart($package, $entriesByName, $documentRelationshipsPartName);
        if ($documentRelationshipsXml !== '') {
            $this->collectRelatedHeaderFooterParts(
                $package,
                $entriesByName,
                $documentPartName,
                $documentRelationshipsXml,
                $header_xmls,
                $footer_xmls
            );
        }

        ksort($header_xmls);
        ksort($footer_xmls);

        return $this->readPackage(
            $this->readRequiredPackagePart($package, $entriesByName, $documentPartName),
            $this->readOptionalPackagePart($package, $entriesByName, 'word/styles.xml'),
            $this->readOptionalPackagePart($package, $entriesByName, 'word/numbering.xml'),
            $documentRelationshipsXml,
            $this->readOptionalPackagePart($package, $entriesByName, 'docProps/core.xml'),
            $this->readOptionalPackagePart($package, $entriesByName, 'word/footnotes.xml'),
            $this->readOptionalPackagePart($package, $entriesByName, 'word/endnotes.xml'),
            $this->readOptionalPackagePart($package, $entriesByName, 'word/comments.xml'),
            $this->readOptionalPackagePart($package, $entriesByName, $this->relationshipsPartName('word/footnotes.xml')),
            $this->readOptionalPackagePart($package, $entriesByName, $this->relationshipsPartName('word/endnotes.xml')),
            $this->readOptionalPackagePart($package, $entriesByName, $this->relationshipsPartName('word/comments.xml')),
            $header_xmls,
            $footer_xmls,
            $entries,
            $media,
            $documentPartName,
        );
    }

    public function readDocxFile(string $path): AstNode
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException("DOCX package does not exist: '{$path}'.");
        }

        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new \RuntimeException("Unable to read DOCX package '{$path}'.");
        }

        return $this->read($bytes);
    }

    /**
     * @param array<string, ZipPackageEntry> $entriesByName
     * @param array<string, string> $headerXmls
     * @param array<string, string> $footerXmls
     */
    private function collectRelatedHeaderFooterParts(
        ZipPackage $package,
        array $entriesByName,
        string $documentPartName,
        string $relationshipsXml,
        array &$headerXmls,
        array &$footerXmls
    ): void {
        $relationships = $this->relationships($this->loadXml($relationshipsXml, 'DOCX document relationships'));
        foreach ($relationships as $relationship) {
            $type = (string) ($relationship['type'] ?? '');
            if ($type !== self::R_NS . '/header' && $type !== self::R_NS . '/footer') {
                continue;
            }
            if ((string) ($relationship['mode'] ?? '') === 'External') {
                continue;
            }

            $partName = $this->normalizePackageTarget($documentPartName, (string) ($relationship['target'] ?? ''));
            $entry = $entriesByName[$partName] ?? null;
            if (!$entry instanceof ZipPackageEntry || !$this->isReadablePackageEntry($entry)) {
                continue;
            }

            if ($type === self::R_NS . '/header') {
                $headerXmls[$partName] ??= $package->read($partName);
            } else {
                $footerXmls[$partName] ??= $package->read($partName);
            }
        }
    }

    /**
     * @param array<string, ZipPackageEntry> $entriesByName
     */
    private function mainDocumentPartName(ZipPackage $package, array $entriesByName): string
    {
        $rootRelsXml = $this->readOptionalPackagePart($package, $entriesByName, '_rels/.rels');
        if ($rootRelsXml !== '') {
            $relationships = $this->relationships($this->loadXml($rootRelsXml, 'DOCX package relationships'));
            foreach ($relationships as $relationship) {
                if ($relationship['type'] !== self::R_NS . '/officeDocument') {
                    continue;
                }

                return $this->normalizePackageTarget('', $relationship['target']);
            }
        }

        $contentTypesXml = $this->readOptionalPackagePart($package, $entriesByName, '[Content_Types].xml');
        if ($contentTypesXml !== '') {
            $contentTypes = $this->loadXml($contentTypesXml, 'DOCX content types');
            foreach ($contentTypes->getElementsByTagNameNS('http://schemas.openxmlformats.org/package/2006/content-types', 'Override') as $override) {
                if (!$override instanceof \DOMElement) {
                    continue;
                }
                if ($override->getAttribute('ContentType') !== 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml') {
                    continue;
                }

                return $this->normalizePackageTarget('', $override->getAttribute('PartName'));
            }
        }

        return 'word/document.xml';
    }

    private function relationshipsPartName(string $partName): string
    {
        $slash = strrpos($partName, '/');
        if ($slash === false) {
            return '_rels/' . $partName . '.rels';
        }

        return substr($partName, 0, $slash) . '/_rels/' . substr($partName, $slash + 1) . '.rels';
    }

    /**
     * @param array<string, ZipPackageEntry> $entriesByName
     */
    private function readRequiredPackagePart(ZipPackage $package, array $entriesByName, string $partName): string
    {
        if (!isset($entriesByName[$partName])) {
            throw new \InvalidArgumentException("DOCX package is missing {$partName}.");
        }

        return $package->read($partName);
    }

    /**
     * @param array<string, ZipPackageEntry> $entriesByName
     */
    private function readOptionalPackagePart(ZipPackage $package, array $entriesByName, string $partName): string
    {
        $entry = $entriesByName[$partName] ?? null;
        if (!$entry instanceof ZipPackageEntry) {
            return '';
        }
        if (!$this->isReadablePackageEntry($entry)) {
            return '';
        }

        return $package->read($partName);
    }

    private function isReadablePackageEntry(ZipPackageEntry $entry): bool
    {
        return $entry->compressionMethod === 0 || $entry->compressionMethod === 8;
    }

    /**
     * @param array<string, string> $header_xmls
     * @param array<string, string> $footer_xmls
     * @param list<string> $entries
     * @param list<string> $media
     */
    private function readPackage(
        string $document_xml,
        string $styles_xml,
        string $numbering_xml,
        string $rels_xml,
        string $core_xml,
        string $footnotes_xml,
        string $endnotes_xml,
        string $comments_xml,
        string $footnotes_rels_xml,
        string $endnotes_rels_xml,
        string $comments_rels_xml,
        array $header_xmls,
        array $footer_xmls,
        array $entries,
        array $media,
        string $documentPartName
    ): AstNode {
        $this->documentPartName = $documentPartName;
        $this->warnings = [];
        $this->warningKeys = [];
        $this->warningOverflowCount = 0;
        $this->styles = $styles_xml !== '' ? $this->styles($this->loadXml($styles_xml, 'DOCX styles.xml')) : [];
        $this->numbering = $numbering_xml !== '' ? $this->numbering($this->loadXml($numbering_xml, 'DOCX numbering.xml')) : [];
        $this->relationships = $rels_xml !== '' ? $this->relationships($this->loadXml($rels_xml, 'DOCX document relationships')) : [];
        $footnoteRelationships = $footnotes_rels_xml !== '' ? $this->relationships($this->loadXml($footnotes_rels_xml, 'DOCX footnotes relationships')) : [];
        $endnoteRelationships = $endnotes_rels_xml !== '' ? $this->relationships($this->loadXml($endnotes_rels_xml, 'DOCX endnotes relationships')) : [];
        $commentRelationships = $comments_rels_xml !== '' ? $this->relationships($this->loadXml($comments_rels_xml, 'DOCX comments relationships')) : [];
        $this->footnotes = $footnotes_xml !== '' ? $this->notes($this->loadXml($footnotes_xml, 'DOCX footnotes.xml'), 'footnote', $footnoteRelationships) : [];
        $this->endnotes = $endnotes_xml !== '' ? $this->notes($this->loadXml($endnotes_xml, 'DOCX endnotes.xml'), 'endnote', $endnoteRelationships) : [];
        $this->comments = $comments_xml !== '' ? $this->comments($this->loadXml($comments_xml, 'DOCX comments.xml'), $commentRelationships) : [];

        $this->headingIds = [];
        $this->suppressedBookmarkIds = [];
        $this->commentRangeIds = [];
        $this->bodyMetadata = [];
        $document = $this->loadXml($document_xml, 'DOCX document.xml');
        $body = $this->firstElementByLocalName($document, 'body');
        $this->textWidthTwips = $body instanceof \DOMElement ? $this->bodyTextWidthTwips($body) : 9360;
        if ($body instanceof \DOMElement) {
            $this->commentRangeIds = $this->commentRangeIds($body);
        }
        $headers = $this->partBlocks($header_xmls, 'DOCX header');
        $footers = $this->partBlocks($footer_xmls, 'DOCX footer');
        $sectionReferences = $body instanceof \DOMElement ? $this->sectionReferences($body) : [];
        $headerDivs = $sectionReferences === []
            ? $this->partDivs($headers, 'docx-header')
            : $this->sectionReferenceDivs($sectionReferences, 'headers', $headers, 'docx-header');
        $footerDivs = $sectionReferences === []
            ? $this->partDivs($footers, 'docx-footer')
            : $this->sectionReferenceDivs($sectionReferences, 'footers', $footers, 'docx-footer');
        $this->headingIds = [];
        $this->suppressedBookmarkIds = [];
        $this->bodyMetadata = [];
        $children = $body instanceof \DOMElement ? $this->bodyBlocks($body, true) : [];
        $children = $this->canonicalizeHeadingBookmarkLinks($children);
        $children = $this->canonicalizeReferencedBookmarkAnchors($children);
        if ($children === [] && $header_xmls === [] && $footer_xmls === []) {
            $children[] = new AstNode('paragraph', ['text' => 'No readable DOCX body content was found.'], [
                new AstNode('text', ['text' => 'No readable DOCX body content was found.']),
            ]);
        }

        $metadata = $core_xml !== '' ? $this->coreProperties($this->loadXml($core_xml, 'DOCX core properties')) : [];
        $metadata = array_replace($metadata, $this->bodyMetadata);
        $metadata['docxPackageEntries'] = count($entries);
        $metadata['docxMediaFiles'] = $media;
        $metadata['docxRelationshipCount'] = count($this->relationships);
        $metadata['docxNumberingDefinitions'] = count($this->numbering);
        $metadata['docxFootnotes'] = count($this->footnotes);
        $metadata['docxEndnotes'] = count($this->endnotes);
        $metadata['docxComments'] = count($this->comments);
        $metadata['docxWarnings'] = $this->warnings;
        $metadata['docxWarningCount'] = count($this->warnings) + $this->warningOverflowCount;
        $metadata['docxWarningOverflowCount'] = $this->warningOverflowCount;
        $metadata['docxHeaders'] = count($headerDivs);
        $metadata['docxFooters'] = count($footerDivs);
        $metadata['docxHeaderFiles'] = array_keys($header_xmls);
        $metadata['docxFooterFiles'] = array_keys($footer_xmls);
        $metadata['docxHeaderPartCount'] = count($headers);
        $metadata['docxFooterPartCount'] = count($footers);
        $metadata['docxSectionReferences'] = $sectionReferences;
        $metadata['docxSectionReferenceCount'] = array_sum(array_map(
            static fn (array $section): int => count($section['headers']) + count($section['footers']),
            $sectionReferences
        ));
        $metadata['docxAppliedHeaderFiles'] = $this->appliedSectionReferenceFiles($sectionReferences, 'headers', $headers);
        $metadata['docxAppliedFooterFiles'] = $this->appliedSectionReferenceFiles($sectionReferences, 'footers', $footers);

        return new AstNode('document', ['meta' => $metadata], $children);
    }

    private function bodyTextWidthTwips(\DOMElement $body): int
    {
        $sectPr = $this->directChild($body, 'sectPr');
        if (!$sectPr instanceof \DOMElement) {
            return 9360;
        }

        $pgSz = $this->directChild($sectPr, 'pgSz');
        $pgMar = $this->directChild($sectPr, 'pgMar');
        if (!$pgSz instanceof \DOMElement || !$pgMar instanceof \DOMElement) {
            return 9360;
        }

        $pageWidth = $this->attr($pgSz, self::W_NS, 'w');
        $leftMargin = $this->attr($pgMar, self::W_NS, 'left');
        $rightMargin = $this->attr($pgMar, self::W_NS, 'right');
        $gutter = $this->attr($pgMar, self::W_NS, 'gutter');
        foreach ([$pageWidth, $leftMargin, $rightMargin, $gutter] as $value) {
            if ($value === '' || preg_match('/^-?\d+$/', $value) !== 1) {
                return 9360;
            }
        }

        $textWidth = (int) $pageWidth - ((int) $leftMargin + (int) $rightMargin + (int) $gutter);

        return $textWidth > 0 ? $textWidth : 9360;
    }

    /**
     * @param array<string, string> $xmlParts
     * @return array<string, list<AstNode>>
     */
    private function partBlocks(array $xmlParts, string $label): array
    {
        $parts = [];
        foreach ($xmlParts as $name => $xml) {
            if ($xml === '') {
                continue;
            }
            $root = $this->loadXml($xml, $label . ' ' . $name)->documentElement;
            if (!$root instanceof \DOMElement) {
                continue;
            }
            $blocks = $this->bodyBlocks($root);
            if ($blocks !== []) {
                $parts[$name] = $blocks;
            }
        }

        return $parts;
    }

    /**
     * @param array<string, list<AstNode>> $parts
     * @return list<AstNode>
     */
    private function partDivs(array $parts, string $class): array
    {
        $divs = [];
        $index = 1;
        foreach ($parts as $name => $blocks) {
            $divs[] = new AstNode('div', [
                'id' => $class . '-' . $index,
                'classes' => [$class],
                'attributes' => [
                    'data-docx-part' => $name,
                    'data-pandoc-source' => 'docx',
                ],
            ], $blocks);
            $index++;
        }

        return $divs;
    }

    /**
     * @param list<array{section: int, headers: list<array{type: string, relationshipId: string, target: string, part: string}>, footers: list<array{type: string, relationshipId: string, target: string, part: string}>}> $sectionReferences
     * @param array<string, list<AstNode>> $parts
     * @return list<AstNode>
     */
    private function sectionReferenceDivs(array $sectionReferences, string $kind, array $parts, string $class): array
    {
        $divs = [];
        $index = 1;
        foreach ($sectionReferences as $section) {
            foreach ($section[$kind] as $reference) {
                $part = $reference['part'];
                if (!isset($parts[$part])) {
                    continue;
                }

                $divs[] = new AstNode('div', [
                    'id' => $class . '-' . $index,
                    'classes' => [$class],
                    'attributes' => [
                        'data-docx-part' => $part,
                        'data-docx-section-index' => (string) $section['section'],
                        'data-docx-section-reference-type' => $reference['type'],
                        'data-docx-relationship-id' => $reference['relationshipId'],
                        'data-pandoc-source' => 'docx',
                    ],
                ], $parts[$part]);
                $index++;
            }
        }

        return $divs;
    }

    /**
     * @param list<array{section: int, headers: list<array{type: string, relationshipId: string, target: string, part: string}>, footers: list<array{type: string, relationshipId: string, target: string, part: string}>}> $sectionReferences
     * @param array<string, list<AstNode>> $availableParts
     * @return list<string>
     */
    private function appliedSectionReferenceFiles(array $sectionReferences, string $kind, array $availableParts): array
    {
        $files = [];
        foreach ($sectionReferences as $section) {
            foreach ($section[$kind] as $reference) {
                $part = $reference['part'];
                if (isset($availableParts[$part])) {
                    $files[] = $part;
                }
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * @return list<AstNode>
     */
    private function bodyBlocks(\DOMElement $body, bool $collectBodyMetadata = false): array
    {
        $previousOpenRawBookmarkIds = $this->openRawBookmarkIds;
        $this->openRawBookmarkIds = [];
        $blocks = [];
        $pendingListRecords = [];
        $pendingCodeLines = [];
        $pendingQuoteBlocks = [];
        $pendingDefinitionItems = [];
        $pendingDefinitionTerm = null;
        $pendingDefinitionBlocks = [];
        $pendingDropCap = null;
        $pendingTableCaption = null;
        $pendingBookmarkOnlyEmptyParagraph = null;
        $activeStyleNumbering = [];
        $listNextStarts = [];
        $seenVisibleContent = false;
        $preserveNextBookmarkOnlyEmptyParagraph = false;

        $flushList = function () use (&$blocks, &$pendingListRecords, &$listNextStarts): void {
            if ($pendingListRecords === []) {
                return;
            }
            array_push($blocks, ...$this->listBlocksFromRecords($pendingListRecords));
            $this->updateOrderedListNextStarts($pendingListRecords, $listNextStarts);
            $pendingListRecords = [];
        };

        $flushCodeBlock = function () use (&$blocks, &$pendingCodeLines): void {
            if ($pendingCodeLines === []) {
                return;
            }
            $blocks[] = new AstNode('code_block', [
                'text' => implode("\n", $pendingCodeLines),
            ]);
            $pendingCodeLines = [];
        };

        $flushQuote = function () use (&$blocks, &$pendingQuoteBlocks): void {
            if ($pendingQuoteBlocks === []) {
                return;
            }
            $blocks[] = new AstNode('blockquote', [], $pendingQuoteBlocks);
            $pendingQuoteBlocks = [];
        };

        $flushDefinitionItem = function () use (&$pendingDefinitionItems, &$pendingDefinitionTerm, &$pendingDefinitionBlocks): void {
            if (!$pendingDefinitionTerm instanceof AstNode) {
                return;
            }
            $pendingDefinitionItems[] = new AstNode('definition_item', [
                'term' => (string) $pendingDefinitionTerm->attr('text', ''),
            ], [
                $pendingDefinitionTerm,
                new AstNode('definition', [], $pendingDefinitionBlocks),
            ]);
            $pendingDefinitionTerm = null;
            $pendingDefinitionBlocks = [];
        };

        $flushDefinitionList = function () use (&$blocks, &$pendingDefinitionItems, $flushDefinitionItem): void {
            $flushDefinitionItem();
            if ($pendingDefinitionItems === []) {
                return;
            }
            $blocks[] = new AstNode('definition_list', [], $pendingDefinitionItems);
            $pendingDefinitionItems = [];
        };

        $flushDropCap = function () use (&$blocks, &$pendingDropCap): void {
            if (!$pendingDropCap instanceof AstNode) {
                return;
            }
            $blocks[] = $pendingDropCap;
            $pendingDropCap = null;
        };

        $flushPendingBlocks = function () use ($flushDropCap, $flushList, $flushCodeBlock, $flushQuote, $flushDefinitionList): void {
            $flushDropCap();
            $flushList();
            $flushCodeBlock();
            $flushQuote();
            $flushDefinitionList();
        };

        $flushTableCaption = function () use (&$blocks, &$pendingTableCaption): void {
            if (!$pendingTableCaption instanceof AstNode) {
                return;
            }

            $blocks[] = $pendingTableCaption;
            $pendingTableCaption = null;
        };

        $flushPendingBookmarkOnlyEmptyParagraph = function () use (&$blocks, &$pendingBookmarkOnlyEmptyParagraph): void {
            if (!$pendingBookmarkOnlyEmptyParagraph instanceof AstNode) {
                return;
            }

            $blocks[] = $pendingBookmarkOnlyEmptyParagraph;
            $pendingBookmarkOnlyEmptyParagraph = null;
        };

        $bookmarkOnlyEmptyParagraph = function (\DOMElement $paragraph): AstNode {
            return new AstNode('paragraph', ['text' => ''], $this->inlineChildren($paragraph));
        };

        foreach ($this->transparentChildElements($body) as $child) {
            if ($child->localName === 'p') {
                $styleId = $this->paragraphStyleId($child);
                $styleBlockKind = $this->paragraphStyleBlockKind($child, $styleId);
                if ($styleBlockKind === 'code') {
                    $pendingBookmarkOnlyEmptyParagraph = null;
                    $flushDropCap();
                    $flushList();
                    $flushQuote();
                    $flushDefinitionList();
                    $pendingCodeLines[] = $this->codeTextFromParagraph($child);
                    $seenVisibleContent = true;
                    $preserveNextBookmarkOnlyEmptyParagraph = false;
                    continue;
                }

                $shapeBlocks = $this->paragraphFloatingShapeBlocks($child);
                if ($shapeBlocks !== null) {
                    $pendingBookmarkOnlyEmptyParagraph = null;
                    $flushPendingBlocks();
                    array_push($blocks, ...$shapeBlocks);
                    $seenVisibleContent = true;
                    $preserveNextBookmarkOnlyEmptyParagraph = true;
                    continue;
                }

                $paragraph = $this->paragraph($child);
                if (!$paragraph instanceof AstNode) {
                    $flushCodeBlock();
                    if ($preserveNextBookmarkOnlyEmptyParagraph && $this->paragraphHasOnlyBookmarkMarkup($child)) {
                        $flushDropCap();
                        $flushList();
                        $flushQuote();
                        $flushDefinitionList();
                        $blocks[] = new AstNode('paragraph', ['text' => '']);
                        $seenVisibleContent = true;
                    } elseif ($this->paragraphHasOnlyBookmarkMarkup($child)) {
                        $pendingBookmarkOnlyEmptyParagraph = $bookmarkOnlyEmptyParagraph($child);
                    } else {
                        $pendingBookmarkOnlyEmptyParagraph = null;
                    }
                    $preserveNextBookmarkOnlyEmptyParagraph = false;
                    continue;
                }
                if ($pendingBookmarkOnlyEmptyParagraph instanceof AstNode) {
                    if ($paragraph->type === 'heading') {
                        $flushPendingBlocks();
                        $flushTableCaption();
                        $flushPendingBookmarkOnlyEmptyParagraph();
                        $seenVisibleContent = true;
                    } else {
                        $pendingBookmarkOnlyEmptyParagraph = null;
                    }
                }
                $metadataField = $paragraph->type === 'paragraph' ? $this->paragraphMetadataField($styleId) : null;
                if ($collectBodyMetadata && $metadataField !== null && !$seenVisibleContent) {
                    $this->addBodyMetadataParagraph($metadataField, $paragraph);
                    $flushCodeBlock();
                    continue;
                }
                if ($paragraph->type === 'paragraph' && $this->isTableCaptionParagraph($child, $paragraph, $styleId)) {
                    $flushPendingBlocks();
                    if (!$this->attachCaptionToLastTable($blocks, $paragraph, 'following-table')) {
                        $flushTableCaption();
                        $pendingTableCaption = $paragraph;
                    }
                    continue;
                }
                if ($pendingTableCaption instanceof AstNode) {
                    $flushPendingBlocks();
                    $flushTableCaption();
                }
                $preserveNextBookmarkOnlyEmptyParagraph = false;
                if ($paragraph->type === 'paragraph' && $this->paragraphIsDropCapFrame($child)) {
                    $flushPendingBlocks();
                    $pendingDropCap = $paragraph;
                    $seenVisibleContent = true;
                    continue;
                }
                if ($pendingDropCap instanceof AstNode) {
                    if ($paragraph->type === 'paragraph') {
                        $paragraph = $this->prependParagraphInlines($paragraph, $pendingDropCap->children);
                        $pendingDropCap = null;
                    } else {
                        $flushDropCap();
                    }
                }
                $styleNumbering = $styleId === '' ? null : ($activeStyleNumbering[$styleId] ?? null);
                $numbering = $paragraph->type === 'paragraph' ? $this->paragraphNumbering($child, $styleNumbering) : null;
                if ($numbering !== null) {
                    $flushCodeBlock();
                    $flushQuote();
                    $flushDefinitionList();
                    if ($styleId !== '' && $this->paragraphNumberingSeedsStyleContinuation($child, $styleId, $numbering)) {
                        $activeStyleNumbering[$styleId] = [
                            'numId' => $numbering['numId'],
                            'level' => $numbering['level'],
                        ];
                    }
                    $list = $this->numberingListAttributes($numbering['numId'], $numbering['level']);
                    $attrs = $list['attrs'];
                    if (($list['ordered'] ?? false) === true && ($list['explicitStart'] ?? false) !== true) {
                        $counterKey = $this->orderedListCounterKey($numbering['numId'], $numbering['level']);
                        if (isset($listNextStarts[$counterKey])) {
                            $attrs['start'] = $listNextStarts[$counterKey];
                        }
                    }
                    $groupAttrs = $list['groupAttrs'] ?? array_replace($attrs, [
                        'docxNumId' => $numbering['numId'],
                        'docxLevel' => $numbering['level'],
                    ]);
                    if (($list['continuation'] ?? false) === true) {
                        for ($i = count($pendingListRecords) - 1; $i >= 0; --$i) {
                            if ((int) $pendingListRecords[$i]['level'] === (int) $numbering['level']) {
                                $groupAttrs = $pendingListRecords[$i]['groupAttrs'] ?? $pendingListRecords[$i]['attrs'];
                                break;
                            }
                        }
                    }
                    $record = [
                        'level' => $numbering['level'],
                        'ordered' => $list['ordered'],
                        'attrs' => $attrs,
                        'groupAttrs' => $groupAttrs,
                        'paragraph' => $paragraph,
                    ];
                    if ($this->paragraphUsesCompactListStyle($styleId)) {
                        $record['compact'] = true;
                    }
                    if (is_string($list['marker'] ?? null) && $list['marker'] !== '') {
                        $record['marker'] = $list['marker'];
                    }
                    if (($list['continuation'] ?? false) === true) {
                        $record['continuation'] = true;
                    }
                    $pendingListRecords[] = $record;
                    $seenVisibleContent = true;
                    continue;
                }

                if (
                    $paragraph->type === 'paragraph'
                    && $this->paragraphExplicitlySuppressesNumbering($child)
                    && $pendingListRecords !== []
                ) {
                    $styleListNumbering = $styleId === '' ? null : $this->paragraphStyleNumbering($styleId);
                    $lastIndex = array_key_last($pendingListRecords);
                    $lastRecord = $lastIndex === null ? null : $pendingListRecords[$lastIndex];
                    $lastGroupAttrs = is_array($lastRecord) ? ($lastRecord['groupAttrs'] ?? $lastRecord['attrs']) : [];
                    if (
                        $styleListNumbering !== null
                        && is_array($lastRecord)
                        && (string) ($lastGroupAttrs['docxNumId'] ?? '') === $styleListNumbering['numId']
                        && (int) ($lastGroupAttrs['docxLevel'] ?? $lastRecord['level']) === $styleListNumbering['level']
                    ) {
                        $flushCodeBlock();
                        $flushQuote();
                        $flushDefinitionList();
                        $pendingListRecords[] = [
                            'level' => $styleListNumbering['level'],
                            'ordered' => (bool) $lastRecord['ordered'],
                            'attrs' => $lastRecord['attrs'],
                            'groupAttrs' => $lastRecord['groupAttrs'] ?? $lastRecord['attrs'],
                            'paragraph' => $paragraph,
                            'continuation' => true,
                        ];
                        $seenVisibleContent = true;
                        continue;
                    }
                    if (
                        is_array($lastRecord)
                        && $this->paragraphDirectLeftIndent($child) >= 720
                    ) {
                        $flushCodeBlock();
                        $flushQuote();
                        $flushDefinitionList();
                        $pendingListRecords[] = [
                            'level' => (int) $lastRecord['level'],
                            'ordered' => (bool) $lastRecord['ordered'],
                            'attrs' => $lastRecord['attrs'],
                            'groupAttrs' => $lastRecord['groupAttrs'] ?? $lastRecord['attrs'],
                            'paragraph' => $paragraph,
                            'continuation' => true,
                        ];
                        $seenVisibleContent = true;
                        continue;
                    }
                }

                if ($styleBlockKind === 'definition-term') {
                    $flushList();
                    $flushCodeBlock();
                    $flushQuote();
                    $flushDefinitionItem();
                    $pendingDefinitionTerm = new AstNode('term', [
                        'text' => (string) $paragraph->attr('text', ''),
                    ], $paragraph->children);
                    $seenVisibleContent = true;
                    continue;
                }

                if ($styleBlockKind === 'definition' && $pendingDefinitionTerm instanceof AstNode) {
                    $flushList();
                    $flushCodeBlock();
                    $flushQuote();
                    $pendingDefinitionBlocks[] = $paragraph;
                    $seenVisibleContent = true;
                    continue;
                }

                if (
                    $styleBlockKind === 'blockquote'
                    || $this->isIndentedBlockQuoteParagraph($child, $styleId, $pendingListRecords !== [] || $pendingQuoteBlocks !== [])
                ) {
                    $flushList();
                    $flushCodeBlock();
                    $flushDefinitionList();
                    $pendingQuoteBlocks[] = $paragraph;
                    $seenVisibleContent = true;
                    continue;
                }

                $flushList();
                $flushCodeBlock();
                $flushQuote();
                $flushDefinitionList();
                $blocks[] = $paragraph;
                $seenVisibleContent = true;
                continue;
            }
            if ($child->localName === 'tbl') {
                $pendingBookmarkOnlyEmptyParagraph = null;
                $flushPendingBlocks();
                $table = $this->table($child);
                if ($pendingTableCaption instanceof AstNode) {
                    $table = $this->tableWithCaption($table, $pendingTableCaption, 'preceding-table');
                    $pendingTableCaption = null;
                }
                $blocks[] = $table;
                $seenVisibleContent = true;
                $preserveNextBookmarkOnlyEmptyParagraph = false;
                continue;
            }
            if ($child->localName === 'sdt') {
                $pendingBookmarkOnlyEmptyParagraph = null;
                $flushPendingBlocks();
                $flushTableCaption();
                array_push($blocks, ...$this->contentControlBlocks($child));
                $seenVisibleContent = true;
                $preserveNextBookmarkOnlyEmptyParagraph = false;
                continue;
            }
            if ($child->localName === 'oMathPara') {
                $pendingBookmarkOnlyEmptyParagraph = null;
                $flushPendingBlocks();
                $flushTableCaption();
                $math = $this->ommlMath($child, true);
                if ($math instanceof AstNode) {
                    $blocks[] = new AstNode('plain', [], [$math]);
                    $seenVisibleContent = true;
                }
                $preserveNextBookmarkOnlyEmptyParagraph = false;
            }
        }

        $flushPendingBlocks();
        $flushTableCaption();

        $this->openRawBookmarkIds = $previousOpenRawBookmarkIds;

        return $this->applyDocxStylesExtensionBlocks(
            $this->resolveParagraphEndRevisions($blocks)
        );
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function resolveParagraphEndRevisions(array $blocks): array
    {
        $resolved = [];
        foreach ($blocks as $block) {
            $children = $block->children === [] ? [] : $this->resolveParagraphEndRevisions($block->children);
            if ($children !== $block->children) {
                $block = new AstNode($block->type, $block->attrs, $children);
            }

            $previousIndex = array_key_last($resolved);
            if ($previousIndex !== null) {
                $previous = $resolved[$previousIndex];
                $revision = $previous->attr(self::PARAGRAPH_END_REVISION_ATTR);
                $previous = $this->withoutParagraphEndRevision($previous);
                $resolved[$previousIndex] = $previous;

                if (
                    $previous->type === 'paragraph'
                    && $block->type === 'paragraph'
                    && $this->paragraphEndRevisionRemovesBoundary($revision)
                ) {
                    $resolved[$previousIndex] = $this->mergeParagraphsAcrossRevisedBoundary($previous, $block);
                    continue;
                }
            }

            $resolved[] = $block;
        }

        foreach ($resolved as $index => $block) {
            $resolved[$index] = $this->withoutParagraphEndRevision($block);
        }

        return $resolved;
    }

    private function paragraphEndRevisionRemovesBoundary(mixed $revision): bool
    {
        if (!is_array($revision)) {
            return false;
        }

        $kind = (string) ($revision['kind'] ?? '');
        $isInsertion = in_array($kind, ['ins', 'moveTo'], true);
        $isDeletion = in_array($kind, ['del', 'moveFrom'], true);

        return match ($this->revisionMode) {
            'accept' => $isDeletion,
            'reject' => $isInsertion,
            default => false,
        };
    }

    private function withoutParagraphEndRevision(AstNode $node): AstNode
    {
        if (!array_key_exists(self::PARAGRAPH_END_REVISION_ATTR, $node->attrs)) {
            return $node;
        }

        $attrs = $node->attrs;
        unset($attrs[self::PARAGRAPH_END_REVISION_ATTR]);

        return new AstNode($node->type, $attrs, $node->children);
    }

    private function mergeParagraphsAcrossRevisedBoundary(AstNode $left, AstNode $right): AstNode
    {
        $children = $left->children;
        if ($this->paragraphBoundaryNeedsSpace($left, $right)) {
            $children[] = new AstNode('text', ['text' => ' ']);
        }
        array_push($children, ...$right->children);
        $children = $this->mergeAdjacentText($children);

        // Pandoc applies the succeeding paragraph's presentation and style
        // once a tracked paragraph mark is accepted or rejected away.
        $attrs = $right->attrs;
        $attrs['text'] = $this->plainText($children);

        return new AstNode('paragraph', $attrs, $children);
    }

    private function paragraphBoundaryNeedsSpace(AstNode $left, AstNode $right): bool
    {
        $leftText = $this->plainText($left->children);
        $rightText = $this->plainText($right->children);
        if (trim($leftText) === '' || trim($rightText) === '') {
            return false;
        }

        return preg_match('/\s$/u', $leftText) !== 1
            && preg_match('/^\s/u', $rightText) !== 1;
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function applyDocxStylesExtensionBlocks(array $blocks): array
    {
        if (!$this->stylesExtension) {
            return $blocks;
        }

        return array_map(fn (AstNode $block): AstNode => $this->applyDocxStylesExtensionNode($block), $blocks);
    }

    private function applyDocxStylesExtensionNode(AstNode $node): AstNode
    {
        $blockQuoteStyle = $node->type === 'blockquote'
            ? $this->singleParagraphStyleForBlockQuote($node->children)
            : null;
        if ($blockQuoteStyle !== null) {
            $children = array_map(
                fn (AstNode $child): AstNode => $this->applyDocxStylesExtensionNode($this->withoutParagraphStyle($child)),
                $node->children
            );
            $quote = new AstNode($node->type, $node->attrs, $children);

            return $this->docxCustomStyleDiv($quote, $blockQuoteStyle);
        }

        $children = array_map(
            fn (AstNode $child): AstNode => $this->applyDocxStylesExtensionNode($child),
            $node->children
        );
        $style = (string) $node->attr(self::PARAGRAPH_STYLE_ATTR, '');
        $node = $this->withoutParagraphStyle(new AstNode($node->type, $node->attrs, $children));

        return $style === '' ? $node : $this->docxCustomStyleDiv($node, $style);
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function singleParagraphStyleForBlockQuote(array $blocks): ?string
    {
        if (count($blocks) !== 1 || $blocks[0]->type !== 'paragraph') {
            return null;
        }

        $style = trim((string) $blocks[0]->attr(self::PARAGRAPH_STYLE_ATTR, ''));

        return $style === '' ? null : $style;
    }

    private function withoutParagraphStyle(AstNode $node): AstNode
    {
        if (!array_key_exists(self::PARAGRAPH_STYLE_ATTR, $node->attrs)) {
            return $node;
        }

        $attrs = $node->attrs;
        unset($attrs[self::PARAGRAPH_STYLE_ATTR]);

        return new AstNode($node->type, $attrs, $node->children);
    }

    private function docxCustomStyleDiv(AstNode $node, string $style): AstNode
    {
        return new AstNode('div', ['attributes' => ['custom-style' => $style]], [$node]);
    }

    private function isTableCaptionParagraph(\DOMElement $paragraph, AstNode $node, string $styleId): bool
    {
        if (!$this->paragraphHasCaptionStyle($styleId)) {
            return false;
        }

        $text = trim((string) $node->attr('text', ''));
        if (preg_match('/^Table\b/u', $text) === 1) {
            return true;
        }

        if ($this->paragraphHasSequenceField($paragraph, 'Table')) {
            return true;
        }

        return $text !== '';
    }

    private function paragraphHasCaptionStyle(string $styleId): bool
    {
        if ($styleId === '') {
            return false;
        }

        foreach ($this->styleChainIds($styleId) as $candidateStyleId) {
            $style = $this->styles[$candidateStyleId] ?? [];
            foreach ([$candidateStyleId, (string) ($style['styleName'] ?? '')] as $candidate) {
                $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', '', $candidate) ?? '');
                if ($normalized === 'caption') {
                    return true;
                }
            }
        }

        return false;
    }

    private function paragraphHasSequenceField(\DOMElement $paragraph, string $sequenceName): bool
    {
        $needle = 'seq ' . strtolower($sequenceName);
        foreach ($paragraph->getElementsByTagNameNS(self::W_NS, 'instrText') as $instruction) {
            if ($instruction instanceof \DOMElement && str_contains(strtolower($this->normalizeFieldInstruction($instruction->textContent)), $needle)) {
                return true;
            }
        }

        foreach ($paragraph->getElementsByTagNameNS(self::W_NS, 'fldSimple') as $field) {
            if (!$field instanceof \DOMElement) {
                continue;
            }
            if (str_contains(strtolower($this->normalizeFieldInstruction($this->attr($field, self::W_NS, 'instr'))), $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function attachCaptionToLastTable(array &$blocks, AstNode $caption, string $position): bool
    {
        $lastIndex = array_key_last($blocks);
        $last = $lastIndex === null ? null : $blocks[$lastIndex];
        if (!$last instanceof AstNode || $last->type !== 'table' || (string) $last->attr('caption', '') !== '') {
            return false;
        }

        $blocks[$lastIndex] = $this->tableWithCaption($last, $caption, $position);

        return true;
    }

    private function tableWithCaption(AstNode $table, AstNode $caption, string $position): AstNode
    {
        $captionInlines = $this->tableCaptionInlines($caption->children);
        $captionText = $this->plainText($captionInlines);
        if ($captionText === '') {
            return $table;
        }

        return new AstNode('table', array_replace($table->attrs, [
            'caption' => $captionText,
            'captionInlines' => $captionInlines,
            'captionBlocks' => [
                new AstNode('paragraph', ['text' => $captionText], $captionInlines),
            ],
            'captionSource' => [
                'source' => 'docx-table-caption-paragraph',
                'sourceElement' => 'w:p',
                'sourcePosition' => $position,
                'sourceAttributes' => [
                    'classes' => ['docx-table-caption'],
                    'attributes' => [
                        'data-docx-table-caption-source' => $position,
                    ],
                ],
            ],
        ]), $table->children);
    }

    /**
     * @param list<AstNode> $inlines
     * @return list<AstNode>
     */
    private function tableCaptionInlines(array $inlines): array
    {
        return $this->mergeAdjacentText($this->normalizeTableCaptionInlines($inlines));
    }

    /**
     * @param list<AstNode> $inlines
     * @return list<AstNode>
     */
    private function normalizeTableCaptionInlines(array $inlines): array
    {
        $captionInlines = [];
        foreach ($inlines as $inline) {
            $anchor = $this->captionBookmarkAnchor($inline);
            if ($anchor instanceof AstNode) {
                $captionInlines[] = $anchor;
                continue;
            }

            if ($this->isRawBookmarkInline($inline)) {
                continue;
            }

            if ($this->isSequenceFieldSpan($inline)) {
                array_push($captionInlines, ...$this->normalizeTableCaptionInlines($inline->children));
                continue;
            }

            if ($inline->children !== []) {
                $captionInlines[] = new AstNode(
                    $inline->type,
                    $inline->attrs,
                    $this->normalizeTableCaptionInlines($inline->children)
                );
                continue;
            }

            $captionInlines[] = $inline;
        }

        return $this->mergeAdjacentText($captionInlines);
    }

    private function captionBookmarkAnchor(AstNode $node): ?AstNode
    {
        $name = $this->rawBookmarkStartName($node);
        if ($name === null || $name === '') {
            return null;
        }

        return new AstNode('span', [
            'id' => $name,
            'classes' => ['anchor'],
        ]);
    }

    private function isSequenceFieldSpan(AstNode $node): bool
    {
        if ($node->type !== 'span') {
            return false;
        }

        $classes = $node->attr('classes', []);
        if (!is_array($classes) || !in_array('docx-field', $classes, true)) {
            return false;
        }

        $attributes = $node->attr('attributes', []);
        if (!is_array($attributes)) {
            return false;
        }

        $instruction = (string) ($attributes['data-docx-field-instruction'] ?? '');

        return str_starts_with(strtoupper($instruction), 'SEQ ');
    }

    /**
     * @return list<AstNode>|null
     */
    private function paragraphFloatingShapeBlocks(\DOMElement $paragraph): ?array
    {
        $records = [];

        foreach ($this->transparentChildElements($paragraph) as $child) {
            if ($this->isIgnorableParagraphShapeBlockElement($child)) {
                continue;
            }

            if ($child->localName !== 'r') {
                return null;
            }

            $runRecords = $this->runFloatingShapeRecords($child);
            if ($runRecords === null) {
                return null;
            }

            array_push($records, ...$runRecords);
        }

        if ($records === []) {
            return null;
        }

        $images = [];
        $captionBlocks = [];
        $textBoxBlocks = [];

        foreach ($records as $record) {
            if ($record['kind'] === 'image') {
                $images[] = $record['node'];
                continue;
            }

            array_push($textBoxBlocks, ...$record['blocks']);
            array_push($captionBlocks, ...$record['blocks']);
        }

        if ($textBoxBlocks === []) {
            return null;
        }

        $captionBlocks = $this->stripRawBookmarkInlines($captionBlocks);
        $textBoxBlocks = $this->stripRawBookmarkInlines($textBoxBlocks);

        if ($images !== []) {
            return null;
        }

        return $textBoxBlocks;
    }

    private function paragraphHasOnlyBookmarkMarkup(\DOMElement $paragraph): bool
    {
        $hasBookmark = false;
        foreach ($this->transparentChildElements($paragraph) as $child) {
            if ($child->localName === 'bookmarkStart' || $child->localName === 'bookmarkEnd') {
                $hasBookmark = true;
                continue;
            }
            if ($this->isIgnorableParagraphShapeBlockElement($child)) {
                continue;
            }

            return false;
        }

        return $hasBookmark;
    }

    private function isIgnorableParagraphShapeBlockElement(\DOMElement $element): bool
    {
        return in_array($element->localName, [
            'pPr',
            'bookmarkStart',
            'bookmarkEnd',
            'proofErr',
            'permStart',
            'permEnd',
        ], true);
    }

    /**
     * @return list<array{kind: string, node?: AstNode, blocks?: list<AstNode>}>|null
     */
    private function runFloatingShapeRecords(\DOMElement $run): ?array
    {
        $records = [];

        foreach ($this->transparentChildElements($run) as $child) {
            if ($child->localName === 'rPr') {
                continue;
            }

            if ($child->localName === 'drawing') {
                array_push($records, ...$this->drawingFloatingShapeRecords($child));
                continue;
            }
            if ($child->localName === 'pict') {
                array_push($records, ...$this->vmlFloatingShapeRecords($child, 'vml-pict'));
                continue;
            }
            if ($child->localName === 'object') {
                array_push($records, ...$this->vmlFloatingShapeRecords($child, 'vml-object'));
                continue;
            }

            return null;
        }

        return $records;
    }

    /**
     * @return list<array{kind: string, node?: AstNode, blocks?: list<AstNode>}>
     */
    private function drawingFloatingShapeRecords(\DOMElement $drawing): array
    {
        $records = [];
        $image = $this->drawingImage($drawing);
        if ($image instanceof AstNode) {
            $records[] = ['kind' => 'image', 'node' => $image];
        }

        array_push($records, ...$this->textBoxBlockRecords($drawing, 'drawing'));

        return $records;
    }

    /**
     * @return list<array{kind: string, node?: AstNode, blocks?: list<AstNode>}>
     */
    private function vmlFloatingShapeRecords(\DOMElement $container, string $source): array
    {
        $records = [];
        foreach ($this->descendantElementsByLocalName($container, 'imagedata') as $imageData) {
            $image = $this->vmlImage($imageData, $container, $source);
            if ($image instanceof AstNode) {
                $records[] = ['kind' => 'image', 'node' => $image];
            }
        }

        array_push($records, ...$this->textBoxBlockRecords($container, $source));

        return $records;
    }

    /**
     * @return list<array{kind: string, blocks: list<AstNode>}>
     */
    private function textBoxBlockRecords(\DOMElement $container, string $source): array
    {
        $records = [];
        foreach ($this->descendantElementsByLocalName($container, 'txbxContent') as $textBox) {
            if ($source !== 'drawing') {
                $shape = $this->nearestAncestorByLocalName($textBox, 'shape');
                if ($shape instanceof \DOMElement && !$this->vmlShapeIsVisible($shape)) {
                    continue;
                }
            }

            $blocks = $this->stripRawBookmarkInlines($this->bodyBlocks($textBox));
            if ($blocks === []) {
                continue;
            }

            $records[] = ['kind' => 'textbox', 'blocks' => $blocks];
        }

        return $records;
    }

    private function vmlShapeIsVisible(\DOMElement $shape): bool
    {
        $style = strtolower($shape->getAttribute('style'));
        if ($style === '') {
            return true;
        }

        return !preg_match('/(?:^|;)\s*(?:display\s*:\s*none|visibility\s*:\s*hidden)\s*(?:;|$)/', $style);
    }

    /**
     * @param list<AstNode> $captionBlocks
     */
    private function figureFromImageAndCaption(AstNode $image, array $captionBlocks): AstNode
    {
        $captionInlines = $this->captionInlinesFromBlocks($captionBlocks);
        $attrs = [
            'captionBlocks' => $captionBlocks,
            'caption' => $this->blocksPlainText($captionBlocks),
        ];
        if ($captionInlines !== []) {
            $attrs['captionInlines'] = $captionInlines;
        }

        return new AstNode('figure', $attrs, [$image]);
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function stripRawBookmarkInlines(array $blocks): array
    {
        $stripped = [];
        foreach ($blocks as $block) {
            $rebuilt = $this->stripRawBookmarkInlinesFromNode($block);
            if ($rebuilt instanceof AstNode) {
                $stripped[] = $rebuilt;
            }
        }

        return $stripped;
    }

    private function stripRawBookmarkInlinesFromNode(AstNode $node): ?AstNode
    {
        if ($this->isRawBookmarkInline($node)) {
            return null;
        }

        if ($node->children === []) {
            return $node;
        }

        $children = [];
        foreach ($node->children as $child) {
            $rebuilt = $this->stripRawBookmarkInlinesFromNode($child);
            if ($rebuilt instanceof AstNode) {
                $children[] = $rebuilt;
            }
        }

        $attrs = $node->attrs;
        if (array_key_exists('text', $attrs) && in_array($node->type, ['paragraph', 'plain', 'heading', 'line', 'term'], true)) {
            $attrs['text'] = $this->plainText($children);
        }

        return new AstNode($node->type, $attrs, $children);
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function captionInlinesFromBlocks(array $blocks): array
    {
        if (count($blocks) !== 1) {
            return [];
        }

        $block = $blocks[0];
        if (!in_array($block->type, ['paragraph', 'plain'], true)) {
            return [];
        }

        return $block->children;
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function blocksPlainText(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            $text = trim($this->nodeText($block));
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        $text = implode(' ', $parts);

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    /**
     * @param list<array{level: int, ordered: bool, attrs: array<string, mixed>, groupAttrs: array<string, mixed>, paragraph: AstNode, marker?: string, continuation?: bool, compact?: bool}> $records
     * @return list<AstNode>
     */
    private function listBlocksFromRecords(array $records): array
    {
        if ($records === []) {
            return [];
        }

        $index = 0;
        return $this->listBlocksAtLevel($records, $index, max(1, (int) $records[0]['level']));
    }

    /**
     * @param list<array{level: int, ordered: bool, attrs: array<string, mixed>, groupAttrs: array<string, mixed>, paragraph: AstNode, marker?: string, continuation?: bool, compact?: bool}> $records
     * @param array<string, int> $listNextStarts
     */
    private function updateOrderedListNextStarts(array $records, array &$listNextStarts): void
    {
        foreach ($records as $record) {
            if (($record['ordered'] ?? false) !== true || ($record['continuation'] ?? false) === true) {
                continue;
            }

            $groupAttrs = $record['groupAttrs'] ?? $record['attrs'];
            $numId = (string) ($groupAttrs['docxNumId'] ?? '');
            if ($numId === '') {
                continue;
            }

            $level = (int) ($groupAttrs['docxLevel'] ?? $record['level']);
            $key = $this->orderedListCounterKey($numId, $level);
            $start = max(1, (int) ($record['attrs']['start'] ?? ($listNextStarts[$key] ?? 1)));
            $listNextStarts[$key] = max($listNextStarts[$key] ?? $start, $start) + 1;
        }
    }

    private function orderedListCounterKey(string $numId, int $level): string
    {
        return $numId . ':' . $level;
    }

    /**
     * @param list<array{level: int, ordered: bool, attrs: array<string, mixed>, groupAttrs: array<string, mixed>, paragraph: AstNode, marker?: string, continuation?: bool, compact?: bool}> $records
     * @return list<AstNode>
     */
    private function listBlocksAtLevel(array $records, int &$index, int $level): array
    {
        $blocks = [];
        $count = count($records);
        while ($index < $count) {
            $record = $records[$index];
            $recordLevel = max(1, (int) $record['level']);
            if ($recordLevel < $level) {
                break;
            }
            if ($recordLevel > $level) {
                break;
            }

            $ordered = (bool) $record['ordered'];
            $attrs = $record['attrs'];
            $groupAttrs = $record['groupAttrs'] ?? $record['attrs'];
            $items = [];

            while ($index < $count) {
                $record = $records[$index];
                $recordLevel = max(1, (int) $record['level']);
                if ($recordLevel < $level) {
                    break;
                }
                if ($recordLevel > $level) {
                    break;
                }
                if ((bool) $record['ordered'] !== $ordered || ($record['groupAttrs'] ?? $record['attrs']) !== $groupAttrs) {
                    break;
                }

                $index++;
                if (($record['continuation'] ?? false) && $items !== []) {
                    $lastIndex = array_key_last($items);
                    $lastItem = $lastIndex === null ? null : $items[$lastIndex];
                    if ($lastItem instanceof AstNode) {
                        $items[$lastIndex] = new AstNode(
                            'list_item',
                            $lastItem->attrs,
                            [...$lastItem->children, $record['paragraph']]
                        );
                    }
                    continue;
                }

                $paragraph = $this->paragraphWithListMarker($record['paragraph'], (string) ($record['marker'] ?? ''));
                if (($record['compact'] ?? false) === true) {
                    $paragraph = $this->compactListParagraph($paragraph);
                }

                $children = [$paragraph];
                while ($index < $count && max(1, (int) $records[$index]['level']) > $level) {
                    $nestedLevel = max(1, (int) $records[$index]['level']);
                    array_push($children, ...$this->listBlocksAtLevel($records, $index, $nestedLevel));
                }
                $items[] = new AstNode('list_item', [], $children);
            }

            $blocks[] = new AstNode($ordered ? 'ordered_list' : 'bullet_list', $attrs, $items);
        }

        return $blocks;
    }

    private function paragraphWithListMarker(AstNode $paragraph, string $marker): AstNode
    {
        if ($marker === '' || !in_array($paragraph->type, ['paragraph', 'plain'], true)) {
            return $paragraph;
        }

        $children = [
            new AstNode('text', ['text' => $marker]),
            new AstNode('text', ['text' => ' ']),
            ...$paragraph->children,
        ];

        return new AstNode($paragraph->type, [
            ...$paragraph->attrs,
            'text' => trim($marker . ' ' . (string) $paragraph->attr('text', '')),
        ], $children);
    }

    private function compactListParagraph(AstNode $paragraph): AstNode
    {
        if ($paragraph->type !== 'paragraph') {
            return $paragraph;
        }

        return new AstNode('plain', $paragraph->attrs, $paragraph->children);
    }

    private function paragraph(\DOMElement $paragraph): ?AstNode
    {
        $paragraphEndRevision = $this->paragraphEndRevision($paragraph);
        $inlines = $this->inlineChildren($paragraph);
        $text = $this->plainText($inlines);
        $level = $this->headingLevel($paragraph);
        if ($text === '' && !$this->hasNonWhitespaceInlineContent($inlines)) {
            if ($level === null) {
                return null;
            }

            return new AstNode('heading', $this->headingAttrs($paragraph, $level, $text), $inlines);
        }

        $figure = $this->figureFromImageWithTextboxCaption($inlines);
        if ($figure instanceof AstNode) {
            return $this->withParagraphInternalMarkers($figure, $paragraph, $paragraphEndRevision);
        }

        if ($paragraphEndRevision !== null && $this->revisionMode === 'preserve') {
            $inlines[] = $this->paragraphEndRevisionSpan($paragraphEndRevision);
        }

        if ($level !== null) {
            return $this->withParagraphInternalMarkers(
                new AstNode('heading', $this->headingAttrs($paragraph, $level, $text), $inlines),
                $paragraph,
                $paragraphEndRevision
            );
        }

        $attrs = ['text' => $text];
        $alignment = $this->paragraphAlignment($paragraph);
        if ($alignment !== '') {
            $attrs['align'] = $alignment;
        }
        $backgroundColor = $this->paragraphBackgroundColor($paragraph);
        if ($backgroundColor !== '') {
            $attrs['backgroundColor'] = $backgroundColor;
        }
        $layoutStyle = $this->paragraphLayoutCssStyle($paragraph);
        if ($layoutStyle !== '') {
            $attrs['htmlAttributes'] = ['style' => $layoutStyle];
        }

        return $this->withParagraphInternalMarkers(
            new AstNode('paragraph', $attrs, $inlines),
            $paragraph,
            $paragraphEndRevision
        );
    }

    /**
     * @return array{kind:string, author:string, date:string}|null
     */
    private function paragraphEndRevision(\DOMElement $paragraph): ?array
    {
        $pPr = $this->directChild($paragraph, 'pPr');
        if (!$pPr instanceof \DOMElement) {
            return null;
        }

        $containers = [$pPr];
        $rPr = $this->directChild($pPr, 'rPr');
        if ($rPr instanceof \DOMElement) {
            $containers[] = $rPr;
        }

        foreach ($containers as $container) {
            foreach ($container->childNodes as $child) {
                if (!$child instanceof \DOMElement || !in_array($child->localName, ['ins', 'del', 'moveFrom', 'moveTo'], true)) {
                    continue;
                }

                return [
                    'kind' => $child->localName,
                    'author' => $this->attr($child, self::W_NS, 'author'),
                    'date' => $this->attr($child, self::W_NS, 'date'),
                ];
            }
        }

        return null;
    }

    /**
     * @param array{kind:string, author:string, date:string} $revision
     */
    private function paragraphEndRevisionSpan(array $revision): AstNode
    {
        $classes = match ($revision['kind']) {
            'del' => ['paragraph-deletion'],
            'moveFrom' => ['paragraph-deletion', 'move-from'],
            'moveTo' => ['paragraph-insertion', 'move-to'],
            default => ['paragraph-insertion'],
        };
        $attributes = array_filter([
            'author' => $revision['author'],
            'date' => $revision['date'],
        ], static fn (string $value): bool => $value !== '');

        return new AstNode('span', [
            'classes' => $classes,
            'attributes' => $attributes,
        ]);
    }

    /**
     * @param array{kind:string, author:string, date:string}|null $paragraphEndRevision
     */
    private function withParagraphInternalMarkers(
        AstNode $node,
        \DOMElement $paragraph,
        ?array $paragraphEndRevision
    ): AstNode {
        $attrs = $node->attrs;
        if ($paragraphEndRevision !== null) {
            $attrs[self::PARAGRAPH_END_REVISION_ATTR] = $paragraphEndRevision;
        }

        $style = $this->paragraphStyleForStylesExtension($paragraph);
        if ($style !== '') {
            $attrs[self::PARAGRAPH_STYLE_ATTR] = $style;
        }

        return $attrs === $node->attrs ? $node : new AstNode($node->type, $attrs, $node->children);
    }

    private function paragraphStyleForStylesExtension(\DOMElement $paragraph): string
    {
        if (!$this->stylesExtension) {
            return '';
        }

        $styleId = $this->paragraphStyleId($paragraph);
        if ($styleId === '') {
            return '';
        }

        $style = $this->styles[$styleId] ?? [];

        return trim((string) ($style['styleName'] ?? $styleId));
    }

    /**
     * @return array<string, mixed>
     */
    private function headingAttrs(\DOMElement $paragraph, int $level, string $text): array
    {
        $attrs = [
            'level' => $level,
            'id' => $this->uniqueHeadingIdentifier($text),
            'text' => $text,
        ];

        $alignment = $this->paragraphAlignment($paragraph);
        if ($alignment !== '') {
            $attrs['align'] = $alignment;
        }
        $backgroundColor = $this->paragraphBackgroundColor($paragraph);
        if ($backgroundColor !== '') {
            $attrs['backgroundColor'] = $backgroundColor;
        }
        $layoutStyle = $this->paragraphLayoutCssStyle($paragraph);
        if ($layoutStyle !== '') {
            $attrs['htmlAttributes'] = ['style' => $layoutStyle];
        }

        $styleId = $this->paragraphStyleId($paragraph);
        if ($styleId !== '') {
            $classes = $this->headingStyleClasses($styleId);
            if ($classes !== []) {
                $attrs['classes'] = $classes;
            }
        }

        return $attrs;
    }

    private function paragraphAlignment(\DOMElement $paragraph): string
    {
        $pPr = $this->directChild($paragraph, 'pPr');
        $jc = $pPr instanceof \DOMElement ? $this->directChild($pPr, 'jc') : null;
        if ($jc instanceof \DOMElement) {
            return $this->wordJustificationAlignment($this->attr($jc, self::W_NS, 'val'));
        }

        $styleId = $this->paragraphStyleId($paragraph);
        if ($styleId === '') {
            return '';
        }

        return (string) ($this->styles[$styleId]['paragraphAlignment'] ?? '');
    }

    private function paragraphBackgroundColor(\DOMElement $paragraph): string
    {
        $pPr = $this->directChild($paragraph, 'pPr');
        $direct = $pPr instanceof \DOMElement ? $this->paragraphPropertiesBackgroundColor($pPr) : '';
        if ($direct !== '') {
            return $direct;
        }

        $styleId = $this->paragraphStyleId($paragraph);
        if ($styleId === '') {
            return '';
        }

        return (string) ($this->styles[$styleId]['paragraphBackgroundColor'] ?? '');
    }

    private function paragraphPropertiesBackgroundColor(\DOMElement $pPr): string
    {
        $shd = $this->directChild($pPr, 'shd');
        if (!$shd instanceof \DOMElement) {
            return '';
        }

        return $this->wordHexColor($this->attr($shd, self::W_NS, 'fill'));
    }

    private function wordHexColor(string $value): string
    {
        $value = strtoupper(ltrim(trim($value), '#'));
        if ($value === '' || $value === 'AUTO') {
            return '';
        }

        return preg_match('/^[0-9A-F]{6}$/', $value) === 1 ? '#' . $value : '';
    }

    private function paragraphLayoutCssStyle(\DOMElement $paragraph): string
    {
        if (!$this->preserveImportStyles) {
            return '';
        }

        $declarations = [];
        $styleId = $this->paragraphStyleId($paragraph);
        if ($styleId !== '') {
            $styleCss = (string) ($this->styles[$styleId]['paragraphCssStyle'] ?? '');
            $declarations = $this->cssDeclarationMap($styleCss);
        }

        $pPr = $this->directChild($paragraph, 'pPr');
        if ($pPr instanceof \DOMElement) {
            $declarations = array_replace($declarations, $this->paragraphPropertiesCssDeclarations($pPr));
        }

        return $this->cssStyleFromDeclarations($declarations);
    }

    /**
     * @return array<string, string>
     */
    private function paragraphPropertiesCssDeclarations(\DOMElement $pPr): array
    {
        $declarations = [];

        $ind = $this->directChild($pPr, 'ind');
        if ($ind instanceof \DOMElement) {
            $left = $this->docxFirstAttr($ind, ['left', 'start']);
            if ($left !== '') {
                $length = $this->docxTwipsCssLength($left);
                if ($length !== '') {
                    $declarations['margin-left'] = $length;
                }
            }

            $right = $this->docxFirstAttr($ind, ['right', 'end']);
            if ($right !== '') {
                $length = $this->docxTwipsCssLength($right);
                if ($length !== '') {
                    $declarations['margin-right'] = $length;
                }
            }

            $firstLine = $this->attr($ind, self::W_NS, 'firstLine');
            $hanging = $this->attr($ind, self::W_NS, 'hanging');
            if ($firstLine !== '') {
                $length = $this->docxTwipsCssLength($firstLine);
                if ($length !== '') {
                    $declarations['text-indent'] = $length;
                }
            } elseif ($hanging !== '') {
                $length = $this->docxTwipsCssLength($hanging, true);
                if ($length !== '') {
                    $declarations['text-indent'] = '-' . $length;
                }
            }
        }

        $spacing = $this->directChild($pPr, 'spacing');
        if ($spacing instanceof \DOMElement) {
            $before = $this->attr($spacing, self::W_NS, 'before');
            if ($before !== '') {
                $length = $this->docxTwipsCssLength($before);
                if ($length !== '') {
                    $declarations['margin-top'] = $length;
                }
            }

            $after = $this->attr($spacing, self::W_NS, 'after');
            if ($after !== '') {
                $length = $this->docxTwipsCssLength($after);
                if ($length !== '') {
                    $declarations['margin-bottom'] = $length;
                }
            }

            $lineHeight = $this->docxLineHeightCssValue(
                $this->attr($spacing, self::W_NS, 'line'),
                strtolower($this->attr($spacing, self::W_NS, 'lineRule'))
            );
            if ($lineHeight !== '') {
                $declarations['line-height'] = $lineHeight;
            }
        }

        $pageBreakBefore = $this->directChild($pPr, 'pageBreakBefore');
        if ($pageBreakBefore instanceof \DOMElement && $this->truthyOnOff($pageBreakBefore)) {
            $declarations['break-before'] = 'page';
            $declarations['page-break-before'] = 'always';
        }

        return $declarations;
    }

    private function docxLineHeightCssValue(string $value, string $rule): string
    {
        $value = trim($value);
        if ($value === '' || !is_numeric($value)) {
            return '';
        }

        $number = (float) $value;
        if ($number <= 0.0 || $number > 10000.0) {
            return '';
        }

        if ($rule === '' || $rule === 'auto') {
            $ratio = $number / 240.0;
            if ($ratio <= 0.0 || $ratio > 20.0) {
                return '';
            }

            return $this->formatDecimal($ratio, 4);
        }

        if (!in_array($rule, ['exact', 'atleast', 'at_least'], true)) {
            return '';
        }

        return $this->docxTwipsCssLength($value);
    }

    private function wordJustificationAlignment(string $value): string
    {
        return match (strtolower(trim($value))) {
            'center' => 'center',
            'right', 'end' => 'right',
            'both', 'distribute', 'mediumkashida', 'highkashida', 'lowkashida', 'thai_distribute' => 'justify',
            'left', 'start' => 'left',
            default => '',
        };
    }

    /**
     * @return list<string>
     */
    private function headingStyleClasses(string $styleId): array
    {
        if ($this->paragraphUsesHeadingZeroStyle($styleId)) {
            return ['Heading-0'];
        }

        $style = $this->styles[$styleId] ?? [];
        $styleName = is_array($style) ? (string) ($style['styleName'] ?? '') : '';
        if ($this->isBuiltInHeadingStyle($styleId, $styleName)) {
            return [];
        }

        $class = $this->headingStyleClass($styleName !== '' ? $styleName : $styleId);

        return $class === '' ? [] : [$class];
    }

    private function isBuiltInHeadingStyle(string $styleId, string $styleName): bool
    {
        foreach ([$styleId, $styleName] as $candidate) {
            $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', '', $candidate) ?? '');
            if (preg_match('/^heading[1-6]$/', $normalized) === 1) {
                return true;
            }
        }

        return false;
    }

    private function headingStyleClass(string $style): string
    {
        $class = preg_replace('/\s+/u', '-', trim($style)) ?? trim($style);
        $class = preg_replace('/[^\pL\pN_.-]+/u', '', $class) ?? $class;
        $class = trim($class, '-');

        return $class;
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function figureFromImageWithTextboxCaption(array $inlines): ?AstNode
    {
        $image = null;
        $caption = null;

        foreach ($inlines as $inline) {
            if ($inline->type === 'image') {
                if ($image instanceof AstNode) {
                    return null;
                }
                $image = $inline;
                continue;
            }

            if ($this->isTextboxCaptionSpan($inline)) {
                if ($caption instanceof AstNode) {
                    return null;
                }
                $caption = $inline;
                continue;
            }

            if (!$this->isWhitespaceInlineNode($inline)) {
                return null;
            }
        }

        if (!$image instanceof AstNode || !$caption instanceof AstNode) {
            return null;
        }

        $captionInlines = $caption->children;
        $captionText = $this->plainText($captionInlines);
        if ($captionText === '') {
            return null;
        }

        $attributes = [
            'data-docx-figure-caption-source' => 'textbox',
        ];
        $captionAttributes = $caption->attr('attributes', []);
        if (is_array($captionAttributes)) {
            foreach ($captionAttributes as $name => $value) {
                $attributes[(string) $name] = $value;
            }
        }

        return new AstNode('figure', [
            'caption' => $captionText,
            'captionInlines' => $captionInlines,
            'captionBlocks' => [
                new AstNode('paragraph', ['text' => $captionText], $captionInlines),
            ],
            'attributes' => $attributes,
        ], [
            $image,
        ]);
    }

    private function isTextboxCaptionSpan(AstNode $node): bool
    {
        if ($node->type !== 'span') {
            return false;
        }

        $classes = $node->attr('classes', []);
        if (!is_array($classes) || !in_array('docx-textbox', $classes, true)) {
            return false;
        }

        return $this->plainText($node->children) !== '';
    }

    private function isWhitespaceInlineNode(AstNode $node): bool
    {
        if (in_array($node->type, ['space', 'softbreak', 'linebreak'], true)) {
            return true;
        }

        if ($node->type === 'text') {
            return trim((string) $node->attr('text', '')) === '';
        }

        return false;
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function hasNonWhitespaceInlineContent(array $inlines): bool
    {
        foreach ($inlines as $inline) {
            if (
                $inline->type === 'linebreak'
                || $inline->type === 'softbreak'
                || $inline->type === 'raw_inline'
                || $inline->type === 'raw_block'
            ) {
                continue;
            }
            if ($inline->children !== []) {
                if ($this->hasNonWhitespaceInlineContent($inline->children)) {
                    return true;
                }
                continue;
            }

            $text = (string) $inline->attr('text', '');
            if ($text !== '') {
                return trim($text) !== '';
            }
            if ($inline->type !== 'text') {
                return true;
            }
        }

        return false;
    }

    private function paragraphStyleBlockKind(\DOMElement $paragraph, string $styleId): ?string
    {
        if ($styleId === '') {
            return null;
        }

        foreach ($this->styleChainIds($styleId) as $candidateStyleId) {
            $style = $this->styles[$candidateStyleId] ?? [];
            $candidates = [
                $candidateStyleId,
                (string) ($style['styleName'] ?? ''),
            ];

            foreach ($candidates as $candidate) {
                $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', '', $candidate) ?? '');
                if ($normalized === 'sourcecode') {
                    return 'code';
                }
                if ($normalized === 'definitionterm') {
                    return 'definition-term';
                }
                if ($normalized === 'definition') {
                    return 'definition';
                }
                if (in_array($normalized, ['quote', 'intensequote', 'blockquote', 'blocktext'], true)) {
                    return 'blockquote';
                }
            }
        }

        return null;
    }

    private function paragraphUsesCompactListStyle(string $styleId): bool
    {
        if ($styleId === '') {
            return false;
        }

        foreach ($this->styleChainIds($styleId) as $candidateStyleId) {
            $style = $this->styles[$candidateStyleId] ?? [];
            foreach ([$candidateStyleId, (string) ($style['styleName'] ?? '')] as $candidate) {
                $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', '', $candidate) ?? '');
                if ($normalized === 'compact') {
                    return true;
                }
            }
        }

        return false;
    }

    private function paragraphMetadataField(string $styleId): ?string
    {
        if ($styleId === '') {
            return null;
        }

        foreach ($this->styleChainIds($styleId) as $candidateStyleId) {
            $style = $this->styles[$candidateStyleId] ?? [];
            foreach ([$candidateStyleId, (string) ($style['styleName'] ?? '')] as $candidate) {
                $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', '', $candidate) ?? '');
                $field = match ($normalized) {
                    'title' => 'title',
                    'subtitle' => 'subtitle',
                    'author' => 'author',
                    'date' => 'date',
                    'abstract' => 'abstract',
                    default => null,
                };
                if ($field !== null) {
                    return $field;
                }
            }
        }

        return null;
    }

    private function addBodyMetadataParagraph(string $field, AstNode $paragraph): void
    {
        $inlines = $paragraph->children;
        $text = $this->plainText($inlines);
        if ($text === '' && !$this->hasNonWhitespaceInlineContent($inlines)) {
            return;
        }

        if ($field === 'author') {
            $authors = $this->bodyMetadata['author'] ?? [];
            if (!is_array($authors) || isset($authors['type'])) {
                $authors = [];
            }
            $authors[] = $text;
            $this->bodyMetadata['author'] = $authors;

            $authorInlines = $this->bodyMetadata['authorInlines'] ?? [];
            if (!is_array($authorInlines) || !array_is_list($authorInlines)) {
                $authorInlines = [];
            }
            $authorInlines[] = $inlines;
            $this->bodyMetadata['authorInlines'] = $authorInlines;

            return;
        }

        if ($field === 'title') {
            if (!isset($this->bodyMetadata['titleInlines'])) {
                $this->bodyMetadata['title'] = $text;
                $this->bodyMetadata['titleInlines'] = $inlines;
            }

            return;
        }

        if ($field === 'date') {
            if (!isset($this->bodyMetadata['dateInlines'])) {
                $this->bodyMetadata['date'] = $text;
                $this->bodyMetadata['dateInlines'] = $inlines;
            }

            return;
        }

        if (!isset($this->bodyMetadata[$field])) {
            $this->bodyMetadata[$field] = ['type' => 'MetaInlines', 'value' => $inlines];
        }
    }

    private function paragraphIsDropCapFrame(\DOMElement $paragraph): bool
    {
        $pPr = $this->directChild($paragraph, 'pPr');
        $framePr = $pPr instanceof \DOMElement ? $this->directChild($pPr, 'framePr') : null;
        if (!$framePr instanceof \DOMElement) {
            return false;
        }

        return in_array(strtolower($this->attr($framePr, self::W_NS, 'dropCap')), ['drop', 'margin'], true);
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function prependParagraphInlines(AstNode $paragraph, array $inlines): AstNode
    {
        $children = $this->mergeAdjacentText([...$inlines, ...$paragraph->children]);
        $attrs = $paragraph->attrs;
        $attrs['text'] = $this->nodeText(new AstNode($paragraph->type, [], $children));

        return new AstNode($paragraph->type, $attrs, $children);
    }

    private function isIndentedBlockQuoteParagraph(\DOMElement $paragraph, string $styleId, bool $listOrQuoteContext = false): bool
    {
        if ($this->preserveImportStyles) {
            return false;
        }

        $pPr = $this->directChild($paragraph, 'pPr');
        $ind = $pPr instanceof \DOMElement ? $this->directChild($pPr, 'ind') : null;
        if (!$ind instanceof \DOMElement) {
            return false;
        }

        $left = $this->attr($ind, self::W_NS, 'left');
        if ($left === '' || !is_numeric($left)) {
            return false;
        }

        $leftTwips = (int) $left;
        if ($styleId === '') {
            return $leftTwips >= ($listOrQuoteContext ? 360 : 1000);
        }

        $styleLeft = $this->styles[$styleId]['paragraphLeftIndent'] ?? null;
        if (!is_int($styleLeft)) {
            return $leftTwips >= ($listOrQuoteContext ? 360 : 1000);
        }

        return ($leftTwips - $styleLeft) >= 360 || ($listOrQuoteContext && $leftTwips >= 360);
    }

    private function codeTextFromParagraph(\DOMElement $paragraph): string
    {
        $text = '';
        $this->appendCodeText($paragraph, $text);

        return $text;
    }

    private function appendCodeText(\DOMNode $node, string &$text): void
    {
        if ($node instanceof \DOMElement) {
            if ($node->localName === 't' || $node->localName === 'delText') {
                $text .= $node->textContent;
                return;
            }
            if ($node->localName === 'tab') {
                $text .= "\t";
                return;
            }
            if ($node->localName === 'br' || $node->localName === 'cr') {
                $text .= "\n";
                return;
            }
        }

        foreach ($node->childNodes as $child) {
            $this->appendCodeText($child, $text);
        }
    }

    /**
     * @return list<\DOMElement>
     */
    private function transparentChildElements(\DOMElement $container): array
    {
        $children = [];
        foreach ($container->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'smartTag') {
                array_push($children, ...$this->transparentChildElements($child));
                continue;
            }
            if ($child->localName === 'AlternateContent') {
                $fallback = $this->alternateContentFallback($child);
                if ($fallback instanceof \DOMElement) {
                    array_push($children, ...$this->transparentChildElements($fallback));
                }
                continue;
            }
            $children[] = $child;
        }

        return $children;
    }

    private function alternateContentFallback(\DOMElement $alternateContent): ?\DOMElement
    {
        foreach ($alternateContent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'Fallback') {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return list<AstNode>
     */
    private function inlineChildren(\DOMElement $container, bool $trimBoundaries = true): array
    {
        $inlines = [];
        $complexFieldStack = [];
        foreach ($this->transparentChildElements($container) as $child) {
            if ($child->localName === 'r') {
                $events = $this->runFieldEvents($child);
                if ($events !== []) {
                    $this->applyRunFieldEvents($events, $complexFieldStack, $inlines);
                    continue;
                }
                $this->appendFieldAwareInlines($inlines, $complexFieldStack, $this->run($child));
                continue;
            }
            if ($child->localName === 'hyperlink') {
                $link = $this->hyperlink($child);
                if ($link instanceof AstNode) {
                    $this->appendFieldAwareInlines($inlines, $complexFieldStack, [$link]);
                }
                continue;
            }
            if ($child->localName === 'fldSimple') {
                $field = $this->simpleField($child);
                if ($field instanceof AstNode) {
                    $this->appendFieldAwareInlines($inlines, $complexFieldStack, [$field]);
                } else {
                    $this->appendFieldAwareInlines($inlines, $complexFieldStack, $this->inlineChildren($child, false));
                }
                continue;
            }
            if ($child->localName === 'sdt') {
                $this->appendFieldAwareInlines($inlines, $complexFieldStack, $this->contentControlInlines($child));
                continue;
            }
            if ($child->localName === 'bookmarkStart' || $child->localName === 'bookmarkEnd') {
                $bookmark = $this->bookmarkRawInline($child);
                if ($bookmark instanceof AstNode) {
                    $this->appendFieldAwareInlines($inlines, $complexFieldStack, [$bookmark]);
                }
                continue;
            }
            if ($child->localName === 'commentRangeStart' || $child->localName === 'commentRangeEnd') {
                if ($this->commentsMode === 'omit') {
                    continue;
                }
                $commentRange = $this->commentRangeSpan($child);
                if ($commentRange instanceof AstNode) {
                    $this->appendFieldAwareInlines($inlines, $complexFieldStack, [$commentRange]);
                }
                continue;
            }
            if ($child->localName === 'oMath' || $child->localName === 'oMathPara') {
                $math = $this->ommlMath($child, $child->localName === 'oMathPara');
                if ($math instanceof AstNode) {
                    $this->appendFieldAwareInlines($inlines, $complexFieldStack, [$math]);
                }
                continue;
            }
            if (in_array($child->localName, ['ins', 'del', 'moveFrom', 'moveTo'], true)) {
                $this->appendFieldAwareInlines($inlines, $complexFieldStack, $this->trackedChangeInlines($child));
            }
        }

        $this->flushOpenComplexFields($complexFieldStack, $inlines);

        $inlines = $this->normalizeInlineSiblings($inlines);

        return $trimBoundaries ? $this->normalizeInlineWhitespace($inlines) : $inlines;
    }

    /**
     * Word can put the outer field end marker in a later empty paragraph while
     * visible generated field rows live in the current paragraph.
     *
     * @param list<array{instruction: string, result: list<AstNode>, separated: bool}> $complexFieldStack
     * @param list<AstNode> $inlines
     */
    private function flushOpenComplexFields(array &$complexFieldStack, array &$inlines): void
    {
        while ($complexFieldStack !== []) {
            $complexField = array_pop($complexFieldStack);
            if (!is_array($complexField) || !$complexField['separated'] || $complexField['result'] === []) {
                continue;
            }

            $field = $this->fieldNode($complexField['instruction'], $complexField['result']);
            if ($field instanceof AstNode) {
                $this->appendFieldAwareInlines($inlines, $complexFieldStack, [$field]);
            }
        }
    }

    /**
     * @param list<array{type: string, value: string}> $events
     * @param list<array{instruction: string, result: list<AstNode>, separated: bool}> $complexFieldStack
     * @param list<AstNode> $inlines
     */
    private function applyRunFieldEvents(array $events, array &$complexFieldStack, array &$inlines): void
    {
        foreach ($events as $event) {
            if ($event['type'] === 'begin') {
                $complexFieldStack[] = [
                    'instruction' => '',
                    'result' => [],
                    'separated' => false,
                ];
                continue;
            }

            $topIndex = array_key_last($complexFieldStack);
            if ($topIndex === null) {
                continue;
            }

            if ($event['type'] === 'instr') {
                if (!$complexFieldStack[$topIndex]['separated']) {
                    $complexFieldStack[$topIndex]['instruction'] .= $event['value'];
                }
                continue;
            }

            if ($event['type'] === 'separate') {
                $complexFieldStack[$topIndex]['separated'] = true;
                continue;
            }

            if ($event['type'] !== 'end') {
                continue;
            }

            $complexField = array_pop($complexFieldStack);
            if (!is_array($complexField)) {
                continue;
            }

            $field = $this->fieldNode($complexField['instruction'], $complexField['result']);
            if ($field instanceof AstNode) {
                $this->appendFieldAwareInlines($inlines, $complexFieldStack, [$field]);
            }
        }
    }

    /**
     * @param list<AstNode> $inlines
     * @param list<array{instruction: string, result: list<AstNode>, separated: bool}> $complexFieldStack
     * @param list<AstNode> $nodes
     */
    private function appendFieldAwareInlines(array &$inlines, array &$complexFieldStack, array $nodes): void
    {
        if ($nodes === []) {
            return;
        }

        $topIndex = array_key_last($complexFieldStack);
        if ($topIndex === null) {
            array_push($inlines, ...$nodes);
            return;
        }

        if ($complexFieldStack[$topIndex]['separated']) {
            array_push($complexFieldStack[$topIndex]['result'], ...$nodes);
        }
    }

    /**
     * @return list<array{type: string, value: string}>
     */
    private function runFieldEvents(\DOMElement $run): array
    {
        $events = [];
        foreach ($this->transparentChildElements($run) as $child) {
            if ($child->localName === 'fldChar') {
                $type = strtolower($this->attr($child, self::W_NS, 'fldCharType'));
                if (in_array($type, ['begin', 'separate', 'end'], true)) {
                    $events[] = ['type' => $type, 'value' => ''];
                }
                continue;
            }
            if ($child->localName === 'instrText') {
                $events[] = ['type' => 'instr', 'value' => $child->textContent];
            }
        }

        return $events;
    }

    /**
     * @return list<AstNode>
     */
    private function run(\DOMElement $run): array
    {
        $nodes = [];
        $usesSymbolFont = $this->runUsesSymbolFont($run);
        foreach ($this->transparentChildElements($run) as $child) {
            if ($child->localName === 't' || $child->localName === 'delText') {
                $text = $usesSymbolFont ? $this->symbolFontText($child->textContent) : $child->textContent;
                if ($text !== '') {
                    $nodes[] = new AstNode('text', ['text' => $text]);
                }
                continue;
            }
            if ($child->localName === 'sym') {
                $text = $this->symbolElementText($child);
                if ($text !== '') {
                    $nodes[] = new AstNode('text', ['text' => $text]);
                }
                continue;
            }
            if ($child->localName === 'tab') {
                $nodes[] = new AstNode('text', ['text' => "\t"]);
                continue;
            }
            if ($child->localName === 'softHyphen') {
                $nodes[] = new AstNode('text', ['text' => "\u{00AD}"]);
                continue;
            }
            if ($child->localName === 'noBreakHyphen') {
                $nodes[] = new AstNode('text', ['text' => "\u{2011}"]);
                continue;
            }
            if ($child->localName === 'br' || $child->localName === 'cr') {
                $nodes[] = new AstNode('linebreak');
                continue;
            }
            if ($child->localName === 'drawing') {
                array_push($nodes, ...$this->drawingNodes($child));
                continue;
            }
            if ($child->localName === 'pict') {
                array_push($nodes, ...$this->vmlNodes($child, 'vml-pict'));
                continue;
            }
            if ($child->localName === 'object') {
                array_push($nodes, ...$this->vmlNodes($child, 'vml-object'));
                continue;
            }
            if ($child->localName === 'oMath' || $child->localName === 'oMathPara') {
                $math = $this->ommlMath($child, $child->localName === 'oMathPara');
                if ($math instanceof AstNode) {
                    $nodes[] = $math;
                }
                continue;
            }
            if ($child->localName === 'footnoteReference') {
                $note = $this->noteReference($child, 'footnote');
                if ($note instanceof AstNode) {
                    $nodes[] = $note;
                }
                continue;
            }
            if ($child->localName === 'endnoteReference') {
                $note = $this->noteReference($child, 'endnote');
                if ($note instanceof AstNode) {
                    $nodes[] = $note;
                }
                continue;
            }
            if ($child->localName === 'commentReference') {
                if ($this->commentsMode === 'omit') {
                    continue;
                }
                if (isset($this->commentRangeIds[$this->attr($child, self::W_NS, 'id')])) {
                    continue;
                }
                $note = $this->noteReference($child, 'comment');
                if ($note instanceof AstNode) {
                    $nodes[] = $note;
                }
            }
        }

        if ($nodes !== [] && $this->allNodesHaveType($nodes, 'note')) {
            return $nodes;
        }

        $style = $this->runStyle($run);
        return $this->styledRunNodes($nodes, $style);
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function allNodesHaveType(array $nodes, string $type): bool
    {
        foreach ($nodes as $node) {
            if ($node->type !== $type) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<AstNode>
     */
    private function trackedChangeInlines(\DOMElement $change): array
    {
        $accepted = $change->localName === 'ins' || $change->localName === 'moveTo';

        if ($this->revisionMode === 'accept') {
            return $accepted ? $this->inlineChildren($change, false) : [];
        }

        if ($this->revisionMode === 'reject') {
            return $accepted ? [] : $this->inlineChildren($change, false);
        }

        $span = $this->trackedChangeSpan($change);

        return $span instanceof AstNode ? [$span] : [];
    }

    /**
     * @return list<AstNode>
     */
    private function contentControlBlocks(\DOMElement $control): array
    {
        $content = $this->directChild($control, 'sdtContent');
        if (!$content instanceof \DOMElement) {
            return [];
        }

        $blocks = $this->bodyBlocks($content);
        if ($blocks === []) {
            $inlines = $this->inlineChildren($content);
            if ($inlines !== []) {
                $blocks[] = new AstNode('paragraph', ['text' => $this->plainText($inlines)], $inlines);
            }
        }
        if ($blocks === []) {
            return [];
        }

        $attributes = $this->contentControlAttributes($control, 'block');
        $isGeneratedToc = $this->isGeneratedTocContentControl($control, $attributes);
        if ($this->shouldUnwrapContentControl($attributes) || $isGeneratedToc) {
            return $isGeneratedToc ? $this->generatedTocContentControlBlocks($blocks) : $blocks;
        }

        return [new AstNode('div', [
            'classes' => ['docx-content-control', 'docx-content-control-block'],
            'attributes' => $attributes,
        ], $blocks)];
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function generatedTocContentControlBlocks(array $blocks): array
    {
        $rebuilt = [];
        foreach ($blocks as $block) {
            if ($block->type !== 'paragraph' || $this->paragraphContainsGeneratedTocSpan($block)) {
                $rebuilt[] = $block;
                continue;
            }

            $target = $this->firstInternalLinkTarget($block->children);
            if ($target === null) {
                $rebuilt[] = $block;
                continue;
            }

            $rebuilt[] = new AstNode('paragraph', $block->attrs, [
                new AstNode('span', [
                    'classes' => [
                        'docx-field',
                        'docx-field-toc',
                        'docx-generated-field',
                        'docx-generated-field-toc',
                        'docx-field-hyperlink',
                    ],
                    'attributes' => [
                        'data-docx-field' => 'toc',
                        'data-docx-generated-field-type' => 'table-of-contents',
                        'data-docx-field-hyperlink' => 'true',
                        'data-docx-field-target' => $target,
                    ],
                ], [
                    new AstNode('link', [
                        'url' => $target,
                        'title' => '',
                    ], $this->stripLinkWrappers($block->children)),
                ]),
            ]);
        }

        return $rebuilt;
    }

    private function paragraphContainsGeneratedTocSpan(AstNode $paragraph): bool
    {
        foreach ($paragraph->children as $child) {
            if ($child->type !== 'span') {
                continue;
            }
            $classes = $child->attr('classes', []);
            if (is_array($classes) && in_array('docx-field-toc', $classes, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<AstNode>
     */
    private function contentControlInlines(\DOMElement $control): array
    {
        $content = $this->directChild($control, 'sdtContent');
        if (!$content instanceof \DOMElement) {
            return [];
        }

        $inlines = $this->inlineChildren($content, false);
        if ($inlines === []) {
            $inlines = $this->blocksAsInlineNodes($this->bodyBlocks($content));
        }
        if ($inlines === []) {
            return [];
        }

        $attributes = $this->contentControlAttributes($control, 'inline');
        if ($this->shouldUnwrapContentControl($attributes)) {
            return $inlines;
        }

        return [new AstNode('span', [
            'classes' => ['docx-content-control', 'docx-content-control-inline'],
            'attributes' => $attributes,
        ], $inlines)];
    }

    /**
     * @param array<string, string> $attributes
     */
    private function shouldUnwrapContentControl(array $attributes): bool
    {
        $metadata = $attributes;
        unset(
            $metadata['data-docx-content-control-display'],
            $metadata['data-docx-content-control-id'],
            $metadata['data-docx-content-control-metadata']
        );

        return $metadata === [];
    }

    /**
     * @param array<string, string> $attributes
     */
    private function isGeneratedTocContentControl(\DOMElement $control, array $attributes): bool
    {
        if (($attributes['data-docx-content-control-type'] ?? '') !== 'docPartObj') {
            return false;
        }

        $metadata = $attributes;
        unset(
            $metadata['data-docx-content-control-display'],
            $metadata['data-docx-content-control-id'],
            $metadata['data-docx-content-control-metadata'],
            $metadata['data-docx-content-control-type']
        );
        if ($metadata !== []) {
            return false;
        }

        if ($this->contentControlDocPartGalleryIsToc($control)) {
            return true;
        }

        $content = $this->directChild($control, 'sdtContent');
        return $content instanceof \DOMElement && $this->elementContainsFieldCommand($content, 'TOC');
    }

    private function contentControlDocPartGalleryIsToc(\DOMElement $control): bool
    {
        $properties = $this->directChild($control, 'sdtPr');
        if (!$properties instanceof \DOMElement) {
            return false;
        }

        foreach ($this->descendantElementsByLocalName($properties, 'docPartGallery') as $gallery) {
            $value = strtolower(preg_replace('/[^a-z0-9]+/i', '', $this->attr($gallery, self::W_NS, 'val')) ?? '');
            if ($value === 'tableofcontents' || $value === 'toc') {
                return true;
            }
        }

        return false;
    }

    private function elementContainsFieldCommand(\DOMElement $element, string $command): bool
    {
        foreach ($this->descendantElementsByLocalName($element, 'fldSimple') as $field) {
            if ($this->fieldInstructionCommand($this->attr($field, self::W_NS, 'instr')) === $command) {
                return true;
            }
        }

        $instruction = '';
        foreach ($element->getElementsByTagNameNS(self::W_NS, '*') as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'fldChar') {
                $type = strtolower($this->attr($child, self::W_NS, 'fldCharType'));
                if ($type === 'begin') {
                    $instruction = '';
                }
                if ($type === 'separate' || $type === 'end') {
                    if ($this->fieldInstructionCommand($instruction) === $command) {
                        return true;
                    }
                    $instruction = '';
                }
                continue;
            }
            if ($child->localName !== 'instrText') {
                continue;
            }

            $instruction .= $child->textContent;
            if ($this->fieldInstructionCommand($instruction) === $command) {
                return true;
            }
        }

        return $this->fieldInstructionCommand($instruction) === $command;
    }

    private function fieldInstructionCommand(string $instruction): string
    {
        $tokens = $this->fieldInstructionTokens($this->normalizeFieldInstruction($instruction));

        return strtoupper($tokens[0] ?? '');
    }

    /**
     * @return array<string, string>
     */
    private function contentControlAttributes(\DOMElement $control, string $display): array
    {
        $attrs = ['data-docx-content-control-display' => $display];
        $properties = $this->directChild($control, 'sdtPr');
        if (!$properties instanceof \DOMElement) {
            $attrs['data-docx-content-control-metadata'] = 'missing';

            return $attrs;
        }

        foreach ($properties->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->localName === 'alias') {
                $value = $this->attr($child, self::W_NS, 'val');
                if ($value !== '') {
                    $attrs['data-docx-content-control-alias'] = $value;
                }
                continue;
            }
            if ($child->localName === 'tag') {
                $value = $this->attr($child, self::W_NS, 'val');
                if ($value !== '') {
                    $attrs['data-docx-content-control-tag'] = $value;
                }
                continue;
            }
            if ($child->localName === 'id') {
                $value = $this->attr($child, self::W_NS, 'val');
                if ($value !== '') {
                    $attrs['data-docx-content-control-id'] = $value;
                }
                continue;
            }
            if ($child->localName === 'lock') {
                $value = $this->attr($child, self::W_NS, 'val');
                if ($value !== '') {
                    $attrs['data-docx-content-control-lock'] = $value;
                }
                continue;
            }
            if ($child->localName === 'temporary') {
                $attrs['data-docx-content-control-temporary'] = 'true';
                continue;
            }
            if ($child->localName === 'showingPlcHdr') {
                $attrs['data-docx-content-control-showing-placeholder'] = 'true';
                continue;
            }
            if ($child->localName === 'placeholder') {
                foreach ($this->descendantElementsByLocalName($child, 'docPart') as $docPart) {
                    $value = $this->attr($docPart, self::W_NS, 'val');
                    if ($value !== '') {
                        $attrs['data-docx-content-control-placeholder-doc-part'] = $value;
                    }
                    break;
                }
                continue;
            }
            if ($child->localName === 'dataBinding') {
                foreach ([
                    'xpath' => 'data-docx-content-control-binding-xpath',
                    'storeItemID' => 'data-docx-content-control-binding-store-item-id',
                    'prefixMappings' => 'data-docx-content-control-binding-prefix-mappings',
                ] as $source => $target) {
                    $value = $this->attr($child, self::W_NS, $source);
                    if ($value !== '') {
                        $attrs[$target] = $value;
                    }
                }
            }
        }

        $type = $this->contentControlTypeElement($properties);
        if ($type instanceof \DOMElement) {
            $attrs['data-docx-content-control-type'] = $type->localName;
            $attrs = array_replace($attrs, $this->contentControlTypeAttributes($type));
        }

        return $attrs;
    }

    private function contentControlTypeElement(\DOMElement $properties): ?\DOMElement
    {
        $typeNames = [
            'bibliography',
            'citation',
            'comboBox',
            'checkBox',
            'checkbox',
            'date',
            'docPartList',
            'docPartObj',
            'dropDownList',
            'equation',
            'group',
            'picture',
            'repeatingSection',
            'repeatingSectionItem',
            'richText',
            'text',
        ];

        foreach ($properties->childNodes as $child) {
            if ($child instanceof \DOMElement && in_array($child->localName, $typeNames, true)) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function contentControlTypeAttributes(\DOMElement $type): array
    {
        $attrs = [];
        if ($type->localName === 'text') {
            $multiLine = $this->attr($type, self::W_NS, 'multiLine');
            if ($multiLine !== '') {
                $attrs['data-docx-content-control-text-multiline'] = $this->onOffMetadataValue($multiLine);
            }
        } elseif ($type->localName === 'date') {
            foreach ([
                'fullDate' => 'data-docx-content-control-date-full',
                'calendar' => 'data-docx-content-control-date-calendar',
            ] as $source => $target) {
                $value = $this->attr($type, self::W_NS, $source);
                if ($value !== '') {
                    $attrs[$target] = $value;
                }
            }
            foreach ([
                'dateFormat' => 'data-docx-content-control-date-format',
                'lid' => 'data-docx-content-control-date-language-id',
            ] as $localName => $target) {
                foreach ($this->descendantElementsByLocalName($type, $localName) as $dateProperty) {
                    $value = $this->attr($dateProperty, self::W_NS, 'val');
                    if ($value !== '') {
                        $attrs[$target] = $value;
                    }
                    break;
                }
            }
        } elseif ($type->localName === 'dropDownList' || $type->localName === 'comboBox') {
            $displayTexts = [];
            $values = [];
            foreach ($this->descendantElementsByLocalName($type, 'listItem') as $item) {
                $displayText = $this->attr($item, self::W_NS, 'displayText');
                $value = $this->attr($item, self::W_NS, 'value');
                if ($displayText !== '') {
                    $displayTexts[] = $displayText;
                }
                if ($value !== '') {
                    $values[] = $value;
                }
            }
            $attrs['data-docx-content-control-list-item-count'] = (string) count($this->descendantElementsByLocalName($type, 'listItem'));
            if ($displayTexts !== []) {
                $attrs['data-docx-content-control-list-display-texts'] = implode(' ', $displayTexts);
            }
            if ($values !== []) {
                $attrs['data-docx-content-control-list-values'] = implode(' ', $values);
            }
        } elseif ($type->localName === 'checkBox' || $type->localName === 'checkbox') {
            foreach ([
                'checked' => 'data-docx-content-control-checkbox-checked',
                'default' => 'data-docx-content-control-checkbox-default',
            ] as $localName => $target) {
                foreach ($this->descendantElementsByLocalName($type, $localName) as $checkboxProperty) {
                    $value = $this->attr($checkboxProperty, self::W_NS, 'val');
                    if ($value !== '') {
                        $attrs[$target] = $this->onOffMetadataValue($value);
                    }
                    break;
                }
            }
        }

        return $attrs;
    }

    private function onOffMetadataValue(string $value): string
    {
        return in_array(strtolower(trim($value)), ['0', 'false', 'off', 'no'], true) ? 'false' : 'true';
    }

    private function trackedChangeSpan(\DOMElement $change): ?AstNode
    {
        $children = $this->inlineChildren($change, false);
        if ($children === []) {
            return null;
        }

        $attributes = array_filter([
            'author' => $this->attr($change, self::W_NS, 'author'),
            'date' => $this->attr($change, self::W_NS, 'date'),
        ], static fn (string $value): bool => $value !== '');

        $classes = match ($change->localName) {
            'del' => ['deletion'],
            'moveFrom' => ['deletion', 'move-from'],
            'moveTo' => ['insertion', 'move-to'],
            default => ['insertion'],
        };

        return new AstNode('span', [
            'classes' => $classes,
            'attributes' => $attributes,
        ], $children);
    }

    private function commentRangeSpan(\DOMElement $range): ?AstNode
    {
        if ($this->commentsMode === 'omit') {
            return null;
        }

        $id = $this->attr($range, self::W_NS, 'id');
        if ($id === '') {
            return null;
        }

        $comment = $this->comments[$id] ?? [];
        $attributes = ['id' => $id];
        if ($range->localName === 'commentRangeStart' && is_array($comment)) {
            foreach (['author', 'date'] as $key) {
                $value = (string) ($comment[$key] ?? '');
                if ($value !== '') {
                    $attributes[$key] = $value;
                }
            }
        }

        $children = [];
        if ($range->localName === 'commentRangeStart' && is_array($comment)) {
            $children = $comment['inlines'] ?? [];
            if (!is_array($children) || !$this->allAstNodes($children)) {
                $children = [];
            }
            $commentBlocks = $comment['children'] ?? [];
            if (
                $this->revisionMode === 'preserve'
                && is_array($commentBlocks)
                && !$this->commentBlocksRetainInlineShape($commentBlocks)
            ) {
                $this->addWarning("Docx comment {$id} will not retain formatting");
            }
        }

        return new AstNode('span', [
            'classes' => [$range->localName === 'commentRangeStart' ? 'comment-start' : 'comment-end'],
            'attributes' => $attributes,
        ], $children);
    }

    private function noteReference(\DOMElement $reference, string $kind): ?AstNode
    {
        if ($kind === 'comment' && $this->commentsMode === 'omit') {
            return null;
        }

        $id = $this->attr($reference, self::W_NS, 'id');
        if ($id === '') {
            return null;
        }

        if ($kind === 'footnote') {
            $children = $this->footnotes[$id] ?? [];
        } elseif ($kind === 'endnote') {
            $children = $this->endnotes[$id] ?? [];
        } else {
            $comment = $this->comments[$id] ?? null;
            if (!is_array($comment)) {
                return null;
            }
            $children = $comment['children'];
        }

        if ($children === []) {
            return null;
        }

        return new AstNode('note', [], $children);
    }

    private function hyperlink(\DOMElement $hyperlink): ?AstNode
    {
        $inlines = $this->inlineChildren($hyperlink, false);
        if ($inlines === []) {
            return null;
        }
        $rid = $this->attr($hyperlink, self::R_NS, 'id');
        $anchor = $this->attr($hyperlink, self::W_NS, 'anchor');
        $url = $rid !== '' ? ($this->relationships[$rid]['target'] ?? '') : '';
        if ($url === '') {
            $url = $anchor !== '' ? '#' . $anchor : '';
        } elseif ($anchor !== '') {
            $url .= '#' . $anchor;
        }

        return new AstNode('link', ['url' => $url, 'title' => ''], $inlines);
    }

    private function simpleField(\DOMElement $field): ?AstNode
    {
        $inlines = $this->inlineChildren($field, false);

        return $this->fieldNode($this->attr($field, self::W_NS, 'instr'), $inlines);
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function fieldNode(string $instruction, array $inlines): ?AstNode
    {
        $instruction = $this->normalizeFieldInstruction($instruction);
        if ($instruction === '') {
            return $inlines === [] ? null : new AstNode('span', ['classes' => ['docx-field']], $inlines);
        }

        $target = $this->fieldLinkTarget($instruction);
        if ($target === null) {
            return $this->metadataFieldNode($instruction, $inlines);
        }
        if ($inlines === []) {
            return null;
        }

        return new AstNode('link', [
            'url' => $target['url'],
            'title' => $target['title'],
        ], $inlines);
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function metadataFieldNode(string $instruction, array $inlines): ?AstNode
    {
        $tokens = $this->fieldInstructionTokens($instruction);
        $command = strtoupper($tokens[0] ?? '');
        if ($command === '') {
            return $inlines === [] ? null : $this->genericFieldSpan($instruction, $inlines);
        }

        return match ($command) {
            'TOC' => $this->tocFieldSpan($instruction, $tokens, $inlines),
            'INDEX' => $this->generatedFieldSpan($instruction, $tokens, $inlines, 'index', 'document-index'),
            'CITATION' => $this->generatedFieldSpan($instruction, $tokens, $inlines, 'citation', 'citation'),
            'BIBLIOGRAPHY' => $this->generatedFieldSpan($instruction, $tokens, $inlines, 'bibliography', 'bibliography'),
            'XE' => $this->indexEntryFieldSpan($instruction, $tokens),
            'ADDIN' => $this->addinFieldSpan($instruction, $tokens, $inlines),
            'TOA' => $this->toaFieldSpan($instruction, $tokens, $inlines),
            default => $inlines === [] ? null : $this->genericFieldSpan($instruction, $inlines),
        };
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function genericFieldSpan(string $instruction, array $inlines): AstNode
    {
        return new AstNode('span', [
            'classes' => ['docx-field'],
            'attributes' => ['data-docx-field-instruction' => $instruction],
        ], $inlines);
    }

    /**
     * @param list<string> $tokens
     * @param list<AstNode> $inlines
     */
    private function tocFieldSpan(string $instruction, array $tokens, array $inlines): AstNode
    {
        $switches = $this->fieldSwitches($tokens);
        $classes = [
            'docx-field',
            'docx-field-toc',
            'docx-generated-field',
            'docx-generated-field-toc',
        ];
        $attrs = [
            'data-docx-field' => 'toc',
            'data-docx-field-instruction' => $instruction,
            'data-docx-generated-field-type' => 'table-of-contents',
        ];

        if (isset($switches['n'])) {
            $classes[] = 'docx-field-omit-page-numbers';
            $attrs['data-docx-field-omit-page-numbers'] = 'true';
            if ($switches['n'] !== '') {
                $attrs['data-docx-field-omit-page-number-levels'] = $switches['n'];
            }
        }
        if (isset($switches['h'])) {
            $classes[] = 'docx-field-hyperlink';
            $attrs['data-docx-field-hyperlink'] = 'true';
        }
        if (isset($switches['o'])) {
            $classes[] = 'docx-field-outline-levels';
            $attrs['data-docx-field-outline-levels'] = $switches['o'];
        }
        if (isset($switches['z'])) {
            $classes[] = 'docx-field-hide-web-layout';
            $attrs['data-docx-field-hide-web-layout'] = 'true';
        }
        if (isset($switches['u'])) {
            $attrs['data-docx-field-use-outline-levels'] = 'true';
        }
        if (isset($switches['t'])) {
            $attrs['data-docx-field-style-levels'] = $switches['t'];
        }
        if (isset($switches['c'])) {
            $attrs['data-docx-field-sequence'] = $switches['c'];
        }
        if (isset($switches['p'])) {
            $attrs['data-docx-field-page-number-separator'] = $switches['p'];
        }

        if (isset($switches['h'])) {
            $tocTarget = $this->firstInternalLinkTarget($inlines);
            if ($tocTarget !== null) {
                $attrs['data-docx-field-target'] = $tocTarget;
                $inlines = [
                    new AstNode('link', [
                        'url' => $tocTarget,
                        'title' => '',
                    ], $this->stripLinkWrappers($inlines)),
                ];
            }
        }

        return new AstNode('span', [
            'classes' => array_values(array_unique($classes)),
            'attributes' => $attrs,
        ], $inlines);
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function firstInternalLinkTarget(array $inlines): ?string
    {
        foreach ($inlines as $inline) {
            if ($inline->type === 'link') {
                $url = (string) $inline->attr('url', '');
                if (str_starts_with($url, '#') && strlen($url) > 1) {
                    return $url;
                }
            }

            if ($inline->children !== []) {
                $target = $this->firstInternalLinkTarget($inline->children);
                if ($target !== null) {
                    return $target;
                }
            }
        }

        return null;
    }

    /**
     * @param list<AstNode> $inlines
     * @return list<AstNode>
     */
    private function stripLinkWrappers(array $inlines): array
    {
        $stripped = [];
        foreach ($inlines as $inline) {
            $children = $inline->children === [] ? [] : $this->stripLinkWrappers($inline->children);
            if ($inline->type === 'link') {
                array_push($stripped, ...$children);
                continue;
            }

            $stripped[] = new AstNode($inline->type, $inline->attrs, $children);
        }

        return $stripped;
    }

    /**
     * @param list<string> $tokens
     * @param list<AstNode> $inlines
     */
    private function generatedFieldSpan(string $instruction, array $tokens, array $inlines, string $field, string $generatedType): AstNode
    {
        $switches = $this->fieldSwitches($tokens);
        $attrs = [
            'data-docx-field' => $field,
            'data-docx-field-instruction' => $instruction,
            'data-docx-generated-field-type' => $generatedType,
        ];

        $target = $this->fieldTarget($tokens);
        if ($target !== '' && in_array($field, ['citation'], true)) {
            $attrs['data-docx-field-target'] = $target;
        }
        if (isset($switches['b'])) {
            $attrs['data-docx-field-bookmark'] = $switches['b'];
        }
        if (isset($switches['c'])) {
            $attrs[$field === 'index' ? 'data-docx-field-columns' : 'data-docx-field-category'] = $switches['c'];
        }
        if (isset($switches['e'])) {
            $attrs['data-docx-field-entry-separator'] = $switches['e'];
        }
        if (isset($switches['l'])) {
            $attrs['data-docx-field-locale-id'] = $switches['l'];
        }
        if (isset($switches['f'])) {
            $attrs[$field === 'bibliography' ? 'data-docx-field-entry-type' : 'data-docx-field-entry-type'] = $switches['f'];
        }
        if (isset($switches['v'])) {
            $attrs['data-docx-field-citation-volume'] = $switches['v'];
        }
        if (isset($switches['*'])) {
            $attrs['data-docx-field-format'] = $switches['*'];
        }

        return new AstNode('span', [
            'classes' => [
                'docx-field',
                'docx-field-' . $field,
                'docx-generated-field',
                'docx-generated-field-' . $field,
            ],
            'attributes' => $attrs,
        ], $inlines);
    }

    /**
     * @param list<string> $tokens
     */
    private function indexEntryFieldSpan(string $instruction, array $tokens): AstNode
    {
        $switches = $this->fieldSwitches($tokens);
        $entry = $this->fieldTarget($tokens);
        $preserveDocxProvenance = $switches !== [];
        $classes = $preserveDocxProvenance
            ? [
                'indexref',
                'docx-field',
                'docx-field-xe',
                'docx-index-entry',
            ]
            : ['indexref'];
        $attrs = $preserveDocxProvenance
            ? [
                'data-docx-field' => 'xe',
                'data-docx-field-instruction' => $instruction,
                'entry' => $entry,
                'data-docx-index-entry' => $entry,
                'data-docx-field-entry' => $entry,
            ]
            : ['entry' => $entry];
        if (isset($switches['t'])) {
            if ($preserveDocxProvenance) {
                $classes[] = 'docx-index-entry-cross-reference';
            }
            $attrs['crossref'] = $switches['t'];
            if ($preserveDocxProvenance) {
                $attrs['data-docx-field-cross-reference'] = $switches['t'];
            }
        }
        if (isset($switches['y'])) {
            if ($preserveDocxProvenance) {
                $classes[] = 'docx-index-entry-yomi';
            }
            $attrs['yomi'] = $switches['y'];
            if ($preserveDocxProvenance) {
                $attrs['data-docx-field-yomi'] = $switches['y'];
            }
        }
        if (isset($switches['b'])) {
            if ($preserveDocxProvenance) {
                $classes[] = 'docx-index-entry-bold';
            }
            $attrs['bold'] = 'true';
            if ($preserveDocxProvenance) {
                $attrs['data-docx-field-bold'] = 'true';
            }
        }
        if (isset($switches['i'])) {
            if ($preserveDocxProvenance) {
                $classes[] = 'docx-index-entry-italic';
            }
            $attrs['italic'] = 'true';
            if ($preserveDocxProvenance) {
                $attrs['data-docx-field-italic'] = 'true';
            }
        }
        if ($preserveDocxProvenance && isset($switches['f'])) {
            $attrs['data-docx-field-entry-type'] = $switches['f'];
        }
        if ($preserveDocxProvenance && isset($switches['r'])) {
            $attrs['data-docx-field-bookmark'] = $switches['r'];
        }

        return new AstNode('span', [
            'classes' => $classes,
            'attributes' => $attrs,
        ]);
    }

    /**
     * @param list<string> $tokens
     * @param list<AstNode> $inlines
     */
    private function addinFieldSpan(string $instruction, array $tokens, array $inlines): AstNode
    {
        $rawTail = trim(substr($instruction, 5));
        $upperTail = strtoupper($rawTail);
        $provider = 'unknown';
        $type = 'addin';
        $payload = '';

        if (str_starts_with($upperTail, 'ZOTERO_ITEM CSL_CITATION')) {
            $provider = 'zotero';
            $type = 'csl-citation';
            $payload = trim(substr($rawTail, strlen('ZOTERO_ITEM CSL_CITATION')));
        } elseif (str_starts_with($upperTail, 'CSL_CITATION')) {
            $type = 'csl-citation';
            $payload = trim(substr($rawTail, strlen('CSL_CITATION')));
        } elseif (str_starts_with($upperTail, 'ZOTERO_BIBL')) {
            $provider = 'zotero';
            $type = 'csl-bibliography';
        } elseif (str_contains($upperTail, 'MENDELEY') && str_contains($upperTail, 'BIBLIOGRAPHY')) {
            $provider = 'mendeley';
            $type = 'csl-bibliography';
        } elseif (str_starts_with($upperTail, 'EN.CITE')) {
            $provider = 'endnote';
            $type = 'endnote-citation';
            $payload = trim(substr($rawTail, strlen('EN.CITE')));
        } elseif (str_starts_with($upperTail, 'EN.REFLIST')) {
            $provider = 'endnote';
            $type = 'endnote-reference-list';
        }

        $payloadAttributes = [];

        if ($payload !== '') {
            $payloadKind = str_starts_with($payload, '{') ? 'json' : (str_starts_with($payload, '<') ? 'xml' : 'text');
            $payloadAttributes['data-docx-addin-payload-kind'] = $payloadKind;
            $payloadAttributes['data-docx-addin-payload-bytes'] = (string) strlen($payload);
            $payloadAttributes['data-docx-addin-payload-sha256'] = hash('sha256', $payload);
            if ($payloadKind === 'json') {
                $decoded = json_decode($payload, true);
                $payloadAttributes['data-docx-addin-csl-json-valid'] = is_array($decoded) ? 'true' : 'false';
                if (is_array($decoded)) {
                    if ($provider === 'unknown' && $type === 'csl-citation' && isset($decoded['mendeley'])) {
                        $provider = 'mendeley';
                    }
                    $citationId = (string) ($decoded['citationID'] ?? '');
                    if ($citationId !== '') {
                        $payloadAttributes['data-docx-addin-citation-id'] = $citationId;
                    }
                    $items = is_array($decoded['citationItems'] ?? null) ? $decoded['citationItems'] : [];
                    $ids = [];
                    foreach ($items as $item) {
                        if (is_array($item) && isset($item['id'])) {
                            $ids[] = (string) $item['id'];
                        }
                    }
                    $payloadAttributes['data-docx-addin-citation-item-count'] = (string) count($items);
                    if ($ids !== []) {
                        $payloadAttributes['data-docx-addin-citation-item-ids'] = implode(',', $ids);
                    }
                }
            }
        }

        $attrs = array_replace([
            'data-docx-field' => 'addin',
            'data-docx-field-instruction' => $instruction,
            'data-docx-addin-type' => $type,
            'data-docx-addin-provider' => $provider,
        ], $payloadAttributes);

        return new AstNode('span', [
            'classes' => [
                'docx-field',
                'docx-field-addin',
                'docx-addin-field',
                'docx-addin-' . $type,
                'docx-addin-provider-' . $provider,
            ],
            'attributes' => $attrs,
        ], $inlines);
    }

    /**
     * @param list<string> $tokens
     * @param list<AstNode> $inlines
     */
    private function toaFieldSpan(string $instruction, array $tokens, array $inlines): AstNode
    {
        $switches = $this->fieldSwitches($tokens);
        $attrs = [
            'data-docx-field' => 'toa',
            'data-docx-field-instruction' => $instruction,
            'data-docx-generated-field-type' => 'table-of-authorities',
        ];
        if (isset($switches['c'])) {
            $attrs['data-docx-field-category'] = $switches['c'];
        }
        if (isset($switches['b'])) {
            $attrs['data-docx-field-bookmark'] = $switches['b'];
        }
        if (isset($switches['e'])) {
            $attrs['data-docx-field-entry-separator'] = $switches['e'];
        }
        if (isset($switches['p'])) {
            $attrs['data-docx-field-page-number-separator'] = $switches['p'];
        }
        if (isset($switches['h'])) {
            $attrs['data-docx-field-hyperlink'] = 'true';
        }

        return new AstNode('span', [
            'classes' => [
                'docx-field',
                'docx-field-toa',
                'docx-generated-field',
                'docx-generated-field-toa',
                ...(isset($switches['h']) ? ['docx-field-hyperlink'] : []),
            ],
            'attributes' => $attrs,
        ], $inlines);
    }

    private function normalizeFieldInstruction(string $instruction): string
    {
        return trim(preg_replace('/\s+/u', ' ', $instruction) ?? $instruction);
    }

    /**
     * @return list<string>
     */
    private function fieldInstructionTokens(string $instruction): array
    {
        preg_match_all('/"([^"]*)"|(\\S+)/u', $instruction, $matches, PREG_SET_ORDER);
        $tokens = [];
        foreach ($matches as $match) {
            $tokens[] = array_key_exists(1, $match) && $match[1] !== '' ? $match[1] : (string) ($match[2] ?? '');
        }

        return $tokens;
    }

    /**
     * @param list<string> $tokens
     * @return array<string, string>
     */
    private function fieldSwitches(array $tokens): array
    {
        $switches = [];
        $count = count($tokens);
        for ($i = 1; $i < $count; $i++) {
            $token = $tokens[$i];
            if (!str_starts_with($token, '\\')) {
                continue;
            }
            $key = strtolower(ltrim($token, '\\'));
            $value = '';
            if ($i + 1 < $count && !str_starts_with($tokens[$i + 1], '\\')) {
                $value = $tokens[++$i];
            }
            $switches[$key] = $value;
        }

        return $switches;
    }

    /**
     * @param list<string> $tokens
     */
    private function fieldTarget(array $tokens): string
    {
        for ($i = 1, $count = count($tokens); $i < $count; $i++) {
            if (!str_starts_with($tokens[$i], '\\')) {
                return $tokens[$i];
            }
        }

        return '';
    }

    /**
     * @return ?array{url:string,title:string}
     */
    private function fieldLinkTarget(string $instruction): ?array
    {
        $tokens = $this->fieldInstructionTokens($instruction);
        $command = strtoupper($tokens[0] ?? '');
        if ($command === '') {
            return null;
        }

        if ($command === 'HYPERLINK') {
            $switches = $this->fieldSwitches($tokens);
            $url = $this->hyperlinkFieldTarget($tokens);
            if (isset($switches['l']) && $switches['l'] !== '') {
                $url = $url === '' ? '#' . $switches['l'] : $url . '#' . $switches['l'];
            }
            if ($url === '') {
                return null;
            }

            return [
                'url' => $url,
                'title' => $switches['o'] ?? '',
            ];
        }

        if (in_array($command, ['REF', 'PAGEREF', 'NOTEREF'], true)) {
            $anchor = $this->fieldTarget($tokens);
            if ($anchor === '') {
                return null;
            }

            return [
                'url' => '#' . $anchor,
                'title' => '',
            ];
        }

        return null;
    }

    /**
     * @param list<string> $tokens
     */
    private function hyperlinkFieldTarget(array $tokens): string
    {
        for ($i = 1, $count = count($tokens); $i < $count; $i++) {
            if (str_starts_with($tokens[$i], '\\')) {
                if ($i + 1 < $count && !str_starts_with($tokens[$i + 1], '\\')) {
                    $i++;
                }
                continue;
            }

            return $tokens[$i];
        }

        return '';
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function canonicalizeHeadingBookmarkLinks(array $nodes): array
    {
        $bookmarkTargets = [];
        $nodes = $this->collectHeadingBookmarkTargets($nodes, $bookmarkTargets);

        return $bookmarkTargets === [] ? $nodes : $this->rewriteBookmarkTargetLinks($nodes, $bookmarkTargets);
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function canonicalizeReferencedBookmarkAnchors(array $nodes): array
    {
        $linkTargets = [];
        $this->collectInternalLinkFragments($nodes, $linkTargets);
        if ($linkTargets === []) {
            return $nodes;
        }

        $bookmarkAliases = [];
        $this->collectOverlappingBookmarkAliases($nodes, $linkTargets, $bookmarkAliases);
        if ($bookmarkAliases !== []) {
            $nodes = $this->rewriteBookmarkTargetLinks($nodes, $bookmarkAliases);
            foreach ($bookmarkAliases as $alias => $canonical) {
                unset($linkTargets[$alias]);
                $linkTargets[$canonical] = true;
            }
        }

        $promotedBookmarkIds = [];

        return $this->promoteReferencedBookmarkAnchors($nodes, $linkTargets, $promotedBookmarkIds, $bookmarkAliases);
    }

    /**
     * @param list<AstNode> $nodes
     * @param array<string, true> $linkTargets
     */
    private function collectInternalLinkFragments(array $nodes, array &$linkTargets): void
    {
        foreach ($nodes as $node) {
            if ($node->type === 'link') {
                $url = (string) ($node->attrs['url'] ?? '');
                if (str_starts_with($url, '#') && strlen($url) > 1) {
                    $linkTargets[substr($url, 1)] = true;
                }
            }

            if ($node->children !== []) {
                $this->collectInternalLinkFragments($node->children, $linkTargets);
            }
        }
    }

    /**
     * @param list<AstNode> $nodes
     * @param array<string, true> $linkTargets
     * @param array<string, string> $bookmarkAliases
     */
    private function collectOverlappingBookmarkAliases(array $nodes, array $linkTargets, array &$bookmarkAliases): void
    {
        $count = count($nodes);
        for ($i = 0; $i < $count; $i++) {
            $node = $nodes[$i];
            $starts = [];
            while ($i < $count) {
                $bookmark = $this->rawBookmarkStart($nodes[$i]);
                if ($bookmark === null) {
                    break;
                }

                $starts[] = $bookmark;
                $i++;
            }

            if (count($starts) > 1) {
                $canonical = $starts[0]['name'];
                foreach ($starts as $bookmark) {
                    if ($bookmark['name'] !== $canonical && isset($linkTargets[$bookmark['name']])) {
                        $bookmarkAliases[$bookmark['name']] = $canonical;
                    }
                }
            }

            if ($i >= $count) {
                break;
            }

            $node = $nodes[$i];
            if ($node->children !== []) {
                $this->collectOverlappingBookmarkAliases($node->children, $linkTargets, $bookmarkAliases);
            }
        }
    }

    /**
     * @param list<AstNode> $nodes
     * @param array<string, true> $linkTargets
     * @param array<string, true> $promotedBookmarkIds
     * @param array<string, string> $bookmarkAliases
     * @return list<AstNode>
     */
    private function promoteReferencedBookmarkAnchors(array $nodes, array $linkTargets, array &$promotedBookmarkIds, array $bookmarkAliases = []): array
    {
        $rebuilt = [];
        foreach ($nodes as $node) {
            $bookmark = $this->rawBookmarkStart($node);
            if ($bookmark !== null) {
                if (isset($bookmarkAliases[$bookmark['name']])) {
                    $promotedBookmarkIds[$bookmark['id']] = true;
                    continue;
                }

                if (isset($linkTargets[$bookmark['name']])) {
                    $promotedBookmarkIds[$bookmark['id']] = true;
                    $rebuilt[] = new AstNode('span', [
                        'id' => $bookmark['name'],
                        'classes' => ['anchor'],
                    ]);
                    continue;
                }

                $rebuilt[] = $node;
                continue;
            }

            $bookmarkEndId = $this->rawBookmarkEndId($node);
            if ($bookmarkEndId !== null && isset($promotedBookmarkIds[$bookmarkEndId])) {
                unset($promotedBookmarkIds[$bookmarkEndId]);
                continue;
            }

            $children = $node->children === []
                ? []
                : $this->promoteReferencedBookmarkAnchors($node->children, $linkTargets, $promotedBookmarkIds, $bookmarkAliases);
            $rebuilt[] = new AstNode($node->type, $node->attrs, $children);
        }

        return $rebuilt;
    }

    /**
     * @param list<AstNode> $nodes
     * @param array<string, string> $bookmarkTargets
     * @return list<AstNode>
     */
    private function collectHeadingBookmarkTargets(array $nodes, array &$bookmarkTargets): array
    {
        $rebuilt = [];
        foreach ($nodes as $node) {
            $children = $node->children === [] ? [] : $this->collectHeadingBookmarkTargets($node->children, $bookmarkTargets);
            $attrs = $node->attrs;

            if ($node->type === 'heading') {
                $bookmarkNames = $this->rawBookmarkStartNames($children);
                if ($bookmarkNames !== []) {
                    $id = (string) ($attrs['id'] ?? '');
                    if ($id === '') {
                        $id = $this->headingIdentifierBase((string) ($attrs['text'] ?? $this->plainText($children)));
                        $attrs['id'] = $id;
                    }
                    foreach ($bookmarkNames as $bookmarkName) {
                        $bookmarkTargets[$bookmarkName] = $id;
                    }
                    $children = $this->removeRawBookmarkInlines($children);
                }
            }

            $rebuilt[] = new AstNode($node->type, $attrs, $children);
        }

        return $rebuilt;
    }

    /**
     * @param list<AstNode> $nodes
     * @param array<string, string> $bookmarkTargets
     * @return list<AstNode>
     */
    private function rewriteBookmarkTargetLinks(array $nodes, array $bookmarkTargets): array
    {
        $rebuilt = [];
        foreach ($nodes as $node) {
            $children = $node->children === [] ? [] : $this->rewriteBookmarkTargetLinks($node->children, $bookmarkTargets);
            $attrs = $node->attrs;
            if ($node->type === 'link') {
                $url = (string) ($attrs['url'] ?? '');
                if (str_starts_with($url, '#')) {
                    $fragment = substr($url, 1);
                    if (isset($bookmarkTargets[$fragment])) {
                        $attrs['url'] = '#' . $bookmarkTargets[$fragment];
                    }
                }
            }
            $attributes = $attrs['attributes'] ?? null;
            if (is_array($attributes)) {
                $fieldTarget = (string) ($attributes['data-docx-field-target'] ?? '');
                if (str_starts_with($fieldTarget, '#')) {
                    $fragment = substr($fieldTarget, 1);
                    if (isset($bookmarkTargets[$fragment])) {
                        $attributes['data-docx-field-target'] = '#' . $bookmarkTargets[$fragment];
                        $attrs['attributes'] = $attributes;
                    }
                }
            }

            $rebuilt[] = new AstNode($node->type, $attrs, $children);
        }

        return $rebuilt;
    }

    /**
     * @param list<AstNode> $inlines
     * @return list<string>
     */
    private function rawBookmarkStartNames(array $inlines): array
    {
        $names = [];
        foreach ($inlines as $inline) {
            $name = $this->rawBookmarkStartName($inline);
            if ($name !== null && $name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @param list<AstNode> $inlines
     * @return list<AstNode>
     */
    private function removeRawBookmarkInlines(array $inlines): array
    {
        $filtered = [];
        foreach ($inlines as $inline) {
            if (!$this->isRawBookmarkInline($inline)) {
                $filtered[] = $inline;
            }
        }

        return $filtered;
    }

    private function rawBookmarkStartName(AstNode $node): ?string
    {
        $bookmark = $this->rawBookmarkStart($node);

        return $bookmark['name'] ?? null;
    }

    /**
     * @return array{id: string, name: string}|null
     */
    private function rawBookmarkStart(AstNode $node): ?array
    {
        if ($node->type !== 'raw_inline' || $node->attr('format') !== 'openxml') {
            return null;
        }

        $text = (string) $node->attr('text', '');
        if (preg_match('/^<w:bookmarkStart\b(?=[^>]*\bw:id="([^"]*)")(?=[^>]*\bw:name="([^"]*)")/', $text, $match) !== 1) {
            return null;
        }

        return [
            'id' => html_entity_decode($match[1], ENT_QUOTES | ENT_XML1, 'UTF-8'),
            'name' => html_entity_decode($match[2], ENT_QUOTES | ENT_XML1, 'UTF-8'),
        ];
    }

    private function rawBookmarkEndId(AstNode $node): ?string
    {
        if ($node->type !== 'raw_inline' || $node->attr('format') !== 'openxml') {
            return null;
        }

        $text = (string) $node->attr('text', '');
        if (preg_match('/^<w:bookmarkEnd\b[^>]*\bw:id="([^"]*)"/', $text, $match) !== 1) {
            return null;
        }

        return html_entity_decode($match[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function isRawBookmarkInline(AstNode $node): bool
    {
        if ($node->type !== 'raw_inline' || $node->attr('format') !== 'openxml') {
            return false;
        }

        return preg_match('/^<w:bookmark(?:Start|End)\b/', (string) $node->attr('text', '')) === 1;
    }

    private function bookmarkRawInline(\DOMElement $bookmark): ?AstNode
    {
        $id = $this->attr($bookmark, self::W_NS, 'id');
        if ($id === '') {
            return null;
        }

        if ($bookmark->localName === 'bookmarkStart') {
            $name = $this->attr($bookmark, self::W_NS, 'name');
            if ($name === '') {
                return null;
            }
            if ($name === '_GoBack' || preg_match('/^OLE_LINK\d*$/', $name) === 1) {
                $this->suppressedBookmarkIds[$id] = true;

                return null;
            }

            $this->openRawBookmarkIds[$id] = true;

            return new AstNode('raw_inline', [
                'format' => 'openxml',
                'text' => '<w:bookmarkStart w:id="' . $this->xmlAttr($id) . '" w:name="' . $this->xmlAttr($name) . '"/>',
            ]);
        }

        if (isset($this->suppressedBookmarkIds[$id])) {
            unset($this->suppressedBookmarkIds[$id]);

            return null;
        }

        if (!isset($this->openRawBookmarkIds[$id])) {
            return null;
        }
        unset($this->openRawBookmarkIds[$id]);

        return new AstNode('raw_inline', [
            'format' => 'openxml',
            'text' => '<w:bookmarkEnd w:id="' . $this->xmlAttr($id) . '"/>',
        ]);
    }

    private function ommlMath(\DOMElement $math, bool $display): ?AstNode
    {
        $text = trim($this->ommlTex($math));
        if ($text === '') {
            $text = trim(preg_replace('/\s+/u', ' ', $math->textContent) ?? $math->textContent);
        }
        if ($text === '') {
            return null;
        }

        return new AstNode('math', [
            'display' => $display,
            'text' => $text,
        ]);
    }

    private function ommlTex(\DOMNode $node): string
    {
        if ($node instanceof \DOMText || $node instanceof \DOMCdataSection) {
            return $node->nodeValue;
        }
        if (!$node instanceof \DOMElement) {
            return '';
        }

        return match ($node->localName) {
            'oMath', 'oMathPara', 'r', 'e', 'num', 'den', 'sup', 'sub', 'deg', 'fName', 'lim' => $this->ommlChildrenTex($node),
            't' => $node->textContent,
            'f' => '\\frac{' . $this->ommlFirstChildTex($node, 'num') . '}{' . $this->ommlFirstChildTex($node, 'den') . '}',
            'sSup' => $this->ommlFirstChildTex($node, 'e') . '^{' . $this->ommlFirstChildTex($node, 'sup') . '}',
            'sSub' => $this->ommlFirstChildTex($node, 'e') . '_{' . $this->ommlFirstChildTex($node, 'sub') . '}',
            'sSubSup' => $this->ommlFirstChildTex($node, 'e') . '_{' . $this->ommlFirstChildTex($node, 'sub') . '}^{' . $this->ommlFirstChildTex($node, 'sup') . '}',
            'rad' => $this->ommlRadicalTex($node),
            'nary' => $this->ommlNaryTex($node),
            'd' => $this->ommlDelimiterTex($node),
            'func' => $this->ommlChildrenTex($node),
            'bar', 'box', 'groupChr', 'limLow', 'limUpp', 'phant', 'borderBox' => $this->ommlChildrenTex($node),
            'brk' => ' ',
            default => str_ends_with($node->localName, 'Pr') || in_array($node->localName, ['ctrlPr', 'argPr', 'rPr'], true)
                ? ''
                : $this->ommlChildrenTex($node),
        };
    }

    private function ommlChildrenTex(\DOMNode $node): string
    {
        $text = '';
        foreach ($node->childNodes as $child) {
            $text .= $this->ommlTex($child);
        }

        return $text;
    }

    private function ommlFirstChildTex(\DOMElement $node, string $localName): string
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                return $this->ommlChildrenTex($child);
            }
        }

        return '';
    }

    private function ommlRadicalTex(\DOMElement $node): string
    {
        $base = $this->ommlFirstChildTex($node, 'e');
        $degree = $this->ommlFirstChildTex($node, 'deg');

        return $degree === '' ? '\\sqrt{' . $base . '}' : '\\sqrt[' . $degree . ']{' . $base . '}';
    }

    private function ommlNaryTex(\DOMElement $node): string
    {
        $operator = '';
        foreach ($node->getElementsByTagNameNS(self::M_NS, 'chr') as $chr) {
            if ($chr instanceof \DOMElement) {
                $operator = $this->attr($chr, self::M_NS, 'val');
                break;
            }
        }
        $operator = match ($operator) {
            "\u{2211}" => '\\sum',
            "\u{222B}" => '\\int',
            "\u{220F}" => '\\prod',
            '' => '\\sum',
            default => $operator,
        };

        $sub = $this->ommlFirstChildTex($node, 'sub');
        $sup = $this->ommlFirstChildTex($node, 'sup');
        $expr = $this->ommlFirstChildTex($node, 'e');

        return $operator
            . ($sub === '' ? '' : '_{' . $sub . '}')
            . ($sup === '' ? '' : '^{' . $sup . '}')
            . ($expr === '' ? '' : ' ' . $expr);
    }

    private function ommlDelimiterTex(\DOMElement $node): string
    {
        $open = '(';
        $close = ')';
        foreach ($node->getElementsByTagNameNS(self::M_NS, 'begChr') as $begChr) {
            if ($begChr instanceof \DOMElement && $this->attr($begChr, self::M_NS, 'val') !== '') {
                $open = $this->attr($begChr, self::M_NS, 'val');
                break;
            }
        }
        foreach ($node->getElementsByTagNameNS(self::M_NS, 'endChr') as $endChr) {
            if ($endChr instanceof \DOMElement && $this->attr($endChr, self::M_NS, 'val') !== '') {
                $close = $this->attr($endChr, self::M_NS, 'val');
                break;
            }
        }

        return $open . $this->ommlFirstChildTex($node, 'e') . $close;
    }

    /**
     * @return list<AstNode>
     */
    private function drawingNodes(\DOMElement $drawing): array
    {
        $nodes = [];
        $image = $this->drawingImage($drawing);
        if ($image instanceof AstNode) {
            $nodes[] = $image;
        } else {
            $placeholder = $this->drawingPlaceholder($drawing);
            if ($placeholder instanceof AstNode) {
                $nodes[] = $placeholder;
            }
        }
        array_push($nodes, ...$this->textBoxSpans($drawing, 'drawing'));

        return $nodes;
    }

    private function drawingPlaceholder(\DOMElement $drawing): ?AstNode
    {
        foreach ($drawing->getElementsByTagNameNS(self::A_NS, 'graphicData') as $graphicData) {
            if (!$graphicData instanceof \DOMElement) {
                continue;
            }

            $uri = $graphicData->getAttribute('uri');
            if ($uri === '') {
                continue;
            }

            if ($uri === self::DGM_NS || str_contains($uri, '/diagram')) {
                return $this->drawingPlaceholderSpan(
                    'diagram',
                    '[DIAGRAM]',
                    $this->drawingDiagramAttributes($drawing, $graphicData, $uri)
                );
            }
            if (str_contains($uri, '/chart')) {
                return $this->drawingPlaceholderSpan('chart', '[CHART]');
            }
        }

        foreach ($this->descendantElementsByLocalName($drawing, 'relIds') as $relIds) {
            if (str_contains((string) $relIds->namespaceURI, '/diagram')) {
                return $this->drawingPlaceholderSpan('diagram', '[DIAGRAM]', $this->drawingDiagramAttributes($drawing, $relIds, (string) $relIds->namespaceURI));
            }
        }
        foreach ($this->descendantElementsByLocalName($drawing, 'chart') as $chart) {
            if (str_contains((string) $chart->namespaceURI, '/chart')) {
                return $this->drawingPlaceholderSpan('chart', '[CHART]');
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function drawingDiagramAttributes(\DOMElement $drawing, \DOMElement $diagramElement, string $uri): array
    {
        $attributes = ['data-docx-diagram-uri' => $uri];

        foreach ($drawing->getElementsByTagNameNS(self::WP_NS, 'docPr') as $docPr) {
            if (!$docPr instanceof \DOMElement) {
                continue;
            }
            foreach ([
                'id' => 'data-docx-diagram-id',
                'name' => 'data-docx-diagram-name',
                'title' => 'data-docx-diagram-title',
                'descr' => 'data-docx-diagram-description',
            ] as $source => $target) {
                $value = $docPr->getAttribute($source);
                if ($value !== '') {
                    $attributes[$target] = $value;
                }
            }
            break;
        }

        foreach ($drawing->getElementsByTagNameNS(self::WP_NS, 'extent') as $extent) {
            if (!$extent instanceof \DOMElement) {
                continue;
            }
            $width = $this->emuCssDimension($extent->getAttribute('cx'));
            if ($width !== '') {
                $attributes['data-docx-diagram-width'] = $width;
            }
            $height = $this->emuCssDimension($extent->getAttribute('cy'));
            if ($height !== '') {
                $attributes['data-docx-diagram-height'] = $height;
            }
            break;
        }

        $relIdsElement = $diagramElement->localName === 'relIds'
            ? $diagramElement
            : null;
        if (!$relIdsElement instanceof \DOMElement) {
            foreach ($this->descendantElementsByLocalName($diagramElement, 'relIds') as $relIds) {
                $relIdsElement = $relIds;
                break;
            }
        }
        if ($relIdsElement instanceof \DOMElement) {
            $attributes = array_replace($attributes, $this->diagramRelationshipAttributes($relIdsElement));
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    private function diagramRelationshipAttributes(\DOMElement $relIds): array
    {
        $attributes = [];
        foreach ([
            'dm' => ['data-docx-diagram-data-relationship-id', 'data-docx-diagram-data-target'],
            'lo' => ['data-docx-diagram-layout-relationship-id', 'data-docx-diagram-layout-target'],
            'qs' => ['data-docx-diagram-quick-style-relationship-id', 'data-docx-diagram-quick-style-target'],
            'cs' => ['data-docx-diagram-colors-relationship-id', 'data-docx-diagram-colors-target'],
        ] as $source => [$relationshipAttribute, $targetAttribute]) {
            $rid = $this->attr($relIds, self::R_NS, $source);
            if ($rid === '') {
                continue;
            }

            $attributes[$relationshipAttribute] = $rid;
            $relationship = $this->relationships[$rid] ?? null;
            if (!is_array($relationship)) {
                continue;
            }

            $target = (string) ($relationship['target'] ?? '');
            if ($target === '') {
                continue;
            }

            $attributes[$targetAttribute] = (string) ($relationship['mode'] ?? '') === 'External'
                ? $target
                : $this->normalizeWordTarget($target);
        }

        return $attributes;
    }

    /**
     * @param array<string, string> $attributes
     */
    private function drawingPlaceholderSpan(string $class, string $text, array $attributes = []): AstNode
    {
        $attrs = ['classes' => [$class]];
        if ($attributes !== []) {
            $attrs['attributes'] = $attributes;
        }

        return new AstNode('span', $attrs, [
            new AstNode('text', ['text' => $text]),
        ]);
    }

    /**
     * @return list<AstNode>
     */
    private function vmlNodes(\DOMElement $container, string $source): array
    {
        $nodes = [];
        foreach ($this->descendantElementsByLocalName($container, 'imagedata') as $imageData) {
            $image = $this->vmlImage($imageData, $container, $source);
            if ($image instanceof AstNode) {
                $nodes[] = $image;
            }
        }
        array_push($nodes, ...$this->textBoxSpans($container, $source));

        return $nodes;
    }

    private function vmlImage(\DOMElement $imageData, \DOMElement $container, string $source): ?AstNode
    {
        $rid = $this->attr($imageData, self::R_NS, 'id');
        if ($rid === '') {
            $rid = $this->attr($imageData, self::OFFICE_NS, 'relid');
        }

        $target = $rid !== '' ? ($this->relationships[$rid]['target'] ?? '') : '';
        if ($target === '') {
            $target = $imageData->getAttribute('src');
        }
        if ($target === '') {
            return null;
        }

        $mode = $rid !== '' ? (string) ($this->relationships[$rid]['mode'] ?? '') : '';
        $url = $mode === 'External' ? $target : $this->normalizeImageTarget($target);
        $shape = $this->nearestAncestorByLocalName($imageData, 'shape');

        $title = $this->attr($imageData, self::OFFICE_NS, 'title');
        $alt = $title;
        if ($shape instanceof \DOMElement) {
            $shapeAlt = $shape->getAttribute('alt');
            if ($shapeAlt !== '') {
                $alt = $shapeAlt;
            }
            $shapeTitle = $shape->getAttribute('title');
            if ($shapeTitle !== '') {
                $title = $shapeTitle;
            }
        }

        $attrs = [
            'url' => $url,
            'title' => $title,
            'alt' => $alt,
        ];
        $sourceAttributes = ['data-docx-image-source' => $source];
        if ($rid !== '') {
            $sourceAttributes['data-docx-image-relationship-id'] = $rid;
        }
        if ($shape instanceof \DOMElement) {
            $sourceAttributes = array_replace($sourceAttributes, $this->vmlShapeSourceAttributes($shape));
            $style = $shape->getAttribute('style');
            $width = $this->cssStyleDimension($style, 'width');
            if ($width !== '') {
                if ($source === 'vml-object') {
                    $sourceAttributes['data-docx-vml-image-width'] = $width;
                } else {
                    $attrs['width'] = $width;
                    $sourceAttributes['width'] = $width;
                }
            }
            $height = $this->cssStyleDimension($style, 'height');
            if ($height !== '') {
                if ($source === 'vml-object') {
                    $sourceAttributes['data-docx-vml-image-height'] = $height;
                } else {
                    $attrs['height'] = $height;
                    $sourceAttributes['height'] = $height;
                }
            }
        }
        if ($source === 'vml-object') {
            foreach (['dxaOrig' => 'data-docx-object-dxa-orig', 'dyaOrig' => 'data-docx-object-dya-orig'] as $localName => $attrName) {
                foreach ($this->descendantElementsByLocalName($container, $localName) as $dimension) {
                    $value = $this->attr($dimension, self::W_NS, 'val');
                    if ($value !== '') {
                        $sourceAttributes[$attrName] = $value;
                    }
                    break;
                }
            }
        }

        $attrs['attributes'] = $sourceAttributes;

        return new AstNode('image', $attrs, $this->imageAltChildren($alt));
    }

    /**
     * @return list<AstNode>
     */
    private function imageAltChildren(string $alt): array
    {
        return $alt === '' ? [] : [new AstNode('text', ['text' => $alt])];
    }

    /**
     * @return list<AstNode>
     */
    private function textBoxSpans(\DOMElement $container, string $source): array
    {
        $spans = [];
        foreach ($this->descendantElementsByLocalName($container, 'txbxContent') as $textBox) {
            $blocks = $this->bodyBlocks($textBox);
            $inlines = $this->blocksAsInlineNodes($blocks);
            if ($inlines === []) {
                continue;
            }

            $attributes = ['data-docx-textbox-source' => $source];
            $shape = $this->nearestAncestorByLocalName($textBox, 'shape');
            if ($shape instanceof \DOMElement) {
                $attributes = array_replace($attributes, $this->vmlShapeSourceAttributes($shape));
            }

            $spans[] = new AstNode('span', [
                'classes' => ['docx-textbox'],
                'attributes' => $attributes,
            ], $inlines);
        }

        return $spans;
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function blocksAsInlineNodes(array $blocks): array
    {
        $inlines = [];
        foreach ($blocks as $index => $block) {
            if ($index > 0) {
                $inlines[] = new AstNode('linebreak');
            }

            if (in_array($block->type, ['paragraph', 'plain', 'heading'], true)) {
                array_push($inlines, ...$block->children);
                continue;
            }

            $text = trim($this->nodeText($block));
            if ($text !== '') {
                $inlines[] = new AstNode('text', ['text' => $text]);
            }
        }

        return $inlines;
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function commentBlocksRetainInlineShape(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if (!$block instanceof AstNode) {
                return false;
            }
            if (in_array($block->type, ['paragraph', 'plain'], true)) {
                continue;
            }
            if ($block->type === 'div' && $this->commentBlocksRetainInlineShape($block->children)) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    private function vmlShapeSourceAttributes(\DOMElement $shape): array
    {
        $attrs = [];
        foreach ([
            'id' => 'data-docx-vml-shape-id',
            'type' => 'data-docx-vml-shape-type',
            'style' => 'data-docx-vml-shape-style',
        ] as $sourceName => $attrName) {
            $value = $shape->getAttribute($sourceName);
            if ($value !== '') {
                $attrs[$attrName] = $value;
            }
        }

        $spid = $this->attr($shape, self::OFFICE_NS, 'spid');
        if ($spid !== '') {
            $attrs['data-docx-vml-shape-spid'] = $spid;
        }

        return $attrs;
    }

    private function drawingImage(\DOMElement $drawing): ?AstNode
    {
        foreach ($drawing->getElementsByTagNameNS(self::A_NS, 'blip') as $blip) {
            if (!$blip instanceof \DOMElement) {
                continue;
            }
            $rid = $this->attr($blip, self::R_NS, 'embed');
            if ($rid === '') {
                $rid = $this->attr($blip, self::R_NS, 'link');
            }
            $target = $rid !== '' ? ($this->relationships[$rid]['target'] ?? '') : '';
            if ($target === '') {
                continue;
            }
            $url = ($this->relationships[$rid]['mode'] ?? '') === 'External' ? $target : $this->normalizeImageTarget($target);

            $attrs = ['url' => $url, 'title' => '', 'alt' => ''];
            $sourceAttributes = [
                'data-docx-image-relationship-id' => $rid,
            ];

            foreach ($drawing->getElementsByTagNameNS(self::WP_NS, 'docPr') as $docPr) {
                if (!$docPr instanceof \DOMElement) {
                    continue;
                }
                $description = $docPr->getAttribute('descr');
                if ($description !== '') {
                    $attrs['alt'] = $description;
                }
                $title = $docPr->getAttribute('title');
                if ($title !== '') {
                    $attrs['title'] = $title;
                }
                $name = $docPr->getAttribute('name');
                if ($name !== '') {
                    $sourceAttributes['data-docx-image-name'] = $name;
                }
                $id = $docPr->getAttribute('id');
                if ($id !== '') {
                    $sourceAttributes['data-docx-image-id'] = $id;
                }
                break;
            }

            foreach ($drawing->getElementsByTagNameNS(self::WP_NS, 'extent') as $extent) {
                if (!$extent instanceof \DOMElement) {
                    continue;
                }
                $width = $this->emuCssDimension($extent->getAttribute('cx'));
                if ($width !== '') {
                    $attrs['width'] = $width;
                    $sourceAttributes['width'] = $width;
                }
                $height = $this->emuCssDimension($extent->getAttribute('cy'));
                if ($height !== '') {
                    $attrs['height'] = $height;
                    $sourceAttributes['height'] = $height;
                }
                break;
            }

            if ($sourceAttributes !== []) {
                $attrs['attributes'] = $sourceAttributes;
            }

            return new AstNode('image', $attrs, $this->imageAltChildren((string) $attrs['alt']));
        }

        return null;
    }

    private function table(\DOMElement $table): AstNode
    {
        $rowSpecs = [];
        $rowHeaderStates = [];
        $firstRowHeaderLook = $this->tableUsesFirstRowHeaderLook($table);
        foreach ($table->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'tr') {
                $cells = [];
                $column = 0;
                $gridBefore = $this->tableRowGridBefore($child);
                for ($omitted = 0; $omitted < $gridBefore; ++$omitted) {
                    $cells[] = [
                        'element' => null,
                        'column' => $column,
                        'colspan' => 1,
                        'vMerge' => '',
                        'omitted' => 'gridBefore',
                    ];
                    ++$column;
                }
                foreach ($this->tableRowCellElements($child) as $cell) {
                    $colspan = $this->gridSpan($cell);
                    $cells[] = [
                        'element' => $cell,
                        'column' => $column,
                        'colspan' => $colspan,
                        'vMerge' => $this->verticalMerge($cell),
                        'omitted' => '',
                    ];
                    $column += $colspan;
                }
                $gridAfter = $this->tableRowGridAfter($child);
                for ($omitted = 0; $omitted < $gridAfter; ++$omitted) {
                    $cells[] = [
                        'element' => null,
                        'column' => $column,
                        'colspan' => 1,
                        'vMerge' => '',
                        'omitted' => 'gridAfter',
                    ];
                    ++$column;
                }
                $rowHeaderStates[] = $this->tableRowHeaderState($child);
                $rowSpecs[] = $cells;
            }
        }

        $rowHeaderFlags = $this->tableRowHeaderFlags($rowSpecs, $rowHeaderStates, $firstRowHeaderLook);
        $rowspans = [];
        $skip = [];
        $active = [];
        foreach ($rowSpecs as $rowIndex => $cells) {
            foreach ($cells as $cellIndex => $cell) {
                $key = $rowIndex . ':' . $cellIndex;
                $rowspans[$key] = 1;
                $coveredColumns = $this->tableCellCoveredColumns($cell);

                if ($cell['vMerge'] === 'continue') {
                    $owners = [];
                    foreach ($coveredColumns as $column) {
                        if (isset($active[$column])) {
                            $owners[$active[$column]] = true;
                        }
                    }
                    if ($owners !== []) {
                        foreach (array_keys($owners) as $owner) {
                            $rowspans[$owner] = ($rowspans[$owner] ?? 1) + 1;
                        }
                        $skip[$key] = true;
                        continue;
                    }
                }

                foreach ($coveredColumns as $column) {
                    unset($active[$column]);
                }

                if ($cell['vMerge'] === 'restart') {
                    foreach ($coveredColumns as $column) {
                        $active[$column] = $key;
                    }
                }
            }
        }

        $headRows = [];
        $rows = [];
        foreach ($rowSpecs as $rowIndex => $cells) {
            $rowCells = [];
            foreach ($cells as $cellIndex => $cell) {
                $key = $rowIndex . ':' . $cellIndex;
                if (isset($skip[$key])) {
                    continue;
                }
                if (($cell['omitted'] ?? '') !== '') {
                    $rowCells[] = $this->omittedTableCell((string) $cell['omitted']);
                    continue;
                }
                $element = $cell['element'] ?? null;
                if (!$element instanceof \DOMElement) {
                    continue;
                }
                $rowCells[] = $this->tableCell($element, (int) $cell['colspan'], (int) ($rowspans[$key] ?? 1), (string) $cell['vMerge']);
            }
            $row = new AstNode('table_row', [], $rowCells);
            if ($rowHeaderFlags[$rowIndex] ?? false) {
                $headRows[] = $row;
            } else {
                $rows[] = $row;
            }
        }

        $columnCount = $this->tableColumnCountFromRowSpecs($rowSpecs);

        return new AstNode('table', $this->tableAttributes($table, $columnCount, $rowSpecs), [
            new AstNode('table_head', [], $headRows),
            new AstNode('table_body', [], $rows),
            new AstNode('table_foot'),
        ]);
    }

    /**
     * @param list<list<array{element: ?\DOMElement, column: int, colspan: int, vMerge: string, omitted: string}>> $rowSpecs
     * @param list<?bool> $rowHeaderStates
     * @return list<bool>
     */
    private function tableRowHeaderFlags(array $rowSpecs, array $rowHeaderStates, bool $firstRowHeaderLook): array
    {
        $flags = [];
        $activeHeaderColumns = [];
        $headerGroupOpen = false;

        foreach ($rowSpecs as $rowIndex => $cells) {
            $continuesHeaderMerge = false;
            foreach ($cells as $cell) {
                if ($cell['vMerge'] !== 'continue') {
                    continue;
                }
                foreach ($this->tableCellCoveredColumns($cell) as $column) {
                    if (($activeHeaderColumns[$column] ?? false) === true) {
                        $continuesHeaderMerge = true;
                        break 2;
                    }
                }
            }

            $explicitHeaderState = $rowHeaderStates[$rowIndex] ?? null;
            $isHeader = $explicitHeaderState ?? (
                ($firstRowHeaderLook && $rowIndex === 0)
                || ($headerGroupOpen && $continuesHeaderMerge)
            );
            $flags[] = $isHeader;

            foreach ($cells as $cell) {
                if ($cell['vMerge'] === 'continue') {
                    continue;
                }

                foreach ($this->tableCellCoveredColumns($cell) as $column) {
                    unset($activeHeaderColumns[$column]);
                    if ($cell['vMerge'] === 'restart' && $isHeader) {
                        $activeHeaderColumns[$column] = true;
                    }
                }
            }

            if ($isHeader) {
                $headerGroupOpen = true;
            } elseif ($explicitHeaderState === false || !$continuesHeaderMerge) {
                $headerGroupOpen = false;
            }
        }

        return $flags;
    }

    /**
     * @param array{column: int, colspan: int} $cell
     * @return list<int>
     */
    private function tableCellCoveredColumns(array $cell): array
    {
        $start = (int) $cell['column'];

        return range($start, $start + max(1, (int) $cell['colspan']) - 1);
    }

    /**
     * @param list<list<array{column: int, colspan: int}>> $rowSpecs
     */
    private function tableColumnCountFromRowSpecs(array $rowSpecs): int
    {
        $columnCount = 0;
        foreach ($rowSpecs as $cells) {
            foreach ($cells as $cell) {
                $columnCount = max($columnCount, (int) $cell['column'] + (int) $cell['colspan']);
            }
        }

        return $columnCount;
    }

    /**
     * @param list<list<array{element: ?\DOMElement, column: int, colspan: int, vMerge: string, omitted: string}>> $rowSpecs
     * @return list<string>
     */
    private function tableColumnAlignmentsFromRowSpecs(array $rowSpecs, int $columnCount): array
    {
        $alignments = array_fill(0, $columnCount, 'default');
        foreach ($rowSpecs as $cells) {
            foreach ($cells as $cell) {
                if ((int) $cell['colspan'] !== 1) {
                    continue;
                }

                $column = (int) $cell['column'];
                if (!array_key_exists($column, $alignments) || $alignments[$column] !== 'default') {
                    continue;
                }

                $element = $cell['element'] ?? null;
                if (!$element instanceof \DOMElement) {
                    continue;
                }

                if ($this->tableCellAlignment($element) === 'left') {
                    $alignments[$column] = 'left';
                }
            }
        }

        return $alignments;
    }

    private function tableRowGridBefore(\DOMElement $row): int
    {
        return $this->tableRowGridOmission($row, 'gridBefore');
    }

    private function tableRowGridAfter(\DOMElement $row): int
    {
        return $this->tableRowGridOmission($row, 'gridAfter');
    }

    private function tableRowGridOmission(\DOMElement $row, string $localName): int
    {
        $trPr = $this->directChild($row, 'trPr');
        if (!$trPr instanceof \DOMElement) {
            return 0;
        }

        $gridOmission = $this->directChild($trPr, $localName);
        if (!$gridOmission instanceof \DOMElement) {
            return 0;
        }

        return max(0, (int) ($this->attr($gridOmission, self::W_NS, 'val') ?: '0'));
    }

    private function tableUsesFirstRowHeaderLook(\DOMElement $table): bool
    {
        $tblPr = $this->directChild($table, 'tblPr');
        if (!$tblPr instanceof \DOMElement) {
            return false;
        }

        $tblLook = $this->directChild($tblPr, 'tblLook');
        if (!$tblLook instanceof \DOMElement) {
            return false;
        }

        return $this->onOffMetadataValue($this->attr($tblLook, self::W_NS, 'firstRow')) === 'true';
    }

    private function tableRowHeaderState(\DOMElement $row): ?bool
    {
        $trPr = $this->directChild($row, 'trPr');
        if (!$trPr instanceof \DOMElement) {
            return null;
        }

        $tblHeader = $this->directChild($trPr, 'tblHeader');
        if (!$tblHeader instanceof \DOMElement) {
            return null;
        }

        return $this->onOffMetadataValue($this->attr($tblHeader, self::W_NS, 'val')) !== 'false';
    }

    private function tableRow(\DOMElement $row): AstNode
    {
        $cells = [];
        foreach ($this->tableRowCellElements($row) as $cell) {
            $cells[] = $this->tableCell($cell);
        }

        return new AstNode('table_row', [], $cells);
    }

    /**
     * @return list<\DOMElement>
     */
    private function tableRowCellElements(\DOMElement $row): array
    {
        $cells = [];
        foreach ($row->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'tc') {
                $cells[] = $child;
                continue;
            }
            if ($child->localName !== 'sdt') {
                continue;
            }
            $content = $this->directChild($child, 'sdtContent');
            if (!$content instanceof \DOMElement) {
                continue;
            }
            foreach ($content->childNodes as $contentChild) {
                if ($contentChild instanceof \DOMElement && $contentChild->localName === 'tc') {
                    $cells[] = $contentChild;
                }
            }
        }

        return $cells;
    }

    private function omittedTableCell(string $source): AstNode
    {
        return new AstNode('table_cell', [
            'text' => '',
            'colspan' => 1,
            'rowspan' => 1,
            'htmlAttributes' => ['data-docx-omitted-cell' => $source],
        ]);
    }

    private function tableCell(\DOMElement $cell, ?int $colspan = null, int $rowspan = 1, string $verticalMerge = ''): AstNode
    {
        $blocks = $this->bodyBlocks($cell);
        if (count($blocks) === 1 && $blocks[0]->type === 'paragraph') {
            $blocks = [new AstNode('plain', [], $blocks[0]->children)];
        }
        $text = trim(implode(' ', array_map(fn (AstNode $block): string => $this->nodeText($block), $blocks)));

        $attrs = [
            'text' => $text,
            'colspan' => $colspan ?? $this->gridSpan($cell),
            'rowspan' => max(1, $rowspan),
        ];
        $align = $this->tableCellAlignment($cell);
        if ($align !== '') {
            $attrs['align'] = $align;
        }
        $htmlAttributes = $this->tableCellHtmlAttributes($cell, $verticalMerge);
        if ($htmlAttributes !== []) {
            $attrs['htmlAttributes'] = $htmlAttributes;
        }

        return new AstNode('table_cell', $attrs, $blocks);
    }

    private function tableCellAlignment(\DOMElement $cell): string
    {
        foreach ($cell->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'p') {
                continue;
            }

            $pPr = $this->directChild($child, 'pPr');
            $jc = $pPr instanceof \DOMElement ? $this->directChild($pPr, 'jc') : null;
            if (!$jc instanceof \DOMElement) {
                continue;
            }

            return match (strtolower($this->attr($jc, self::W_NS, 'val'))) {
                'center' => 'center',
                'right', 'end' => 'right',
                'left', 'start' => 'left',
                default => '',
            };
        }

        return '';
    }

    private function gridSpan(\DOMElement $cell): int
    {
        foreach ($cell->getElementsByTagNameNS(self::W_NS, 'gridSpan') as $gridSpan) {
            if ($gridSpan instanceof \DOMElement) {
                return max(1, (int) ($this->attr($gridSpan, self::W_NS, 'val') ?: '1'));
            }
        }

        return 1;
    }

    private function verticalMerge(\DOMElement $cell): string
    {
        $tcPr = $this->directChild($cell, 'tcPr');
        if (!$tcPr instanceof \DOMElement) {
            return '';
        }

        foreach ($tcPr->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'vMerge') {
                continue;
            }
            $value = strtolower($this->attr($child, self::W_NS, 'val'));

            return $value === '' || $value === 'continue' ? 'continue' : 'restart';
        }

        return '';
    }

    /**
     * @param list<list<array{element: ?\DOMElement, column: int, colspan: int, vMerge: string, omitted: string}>> $rowSpecs
     * @return array<string, mixed>
     */
    private function tableAttributes(\DOMElement $table, int $columnCount, array $rowSpecs): array
    {
        $attrs = [];
        if ($columnCount > 0) {
            $attrs['alignments'] = $this->tableColumnAlignmentsFromRowSpecs($rowSpecs, $columnCount);
            $attrs['widths'] = $this->tableGridWidths($table, $columnCount);
        }

        $tblPr = $this->directChild($table, 'tblPr');
        if (!$tblPr instanceof \DOMElement) {
            return $attrs;
        }

        $htmlAttributes = [];
        if ($this->preserveImportStyles) {
            $htmlAttributes = array_replace($htmlAttributes, $this->tableLayoutHtmlAttributes($tblPr));
        }
        foreach ($tblPr->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'tblStyle') {
                $style = $this->attr($child, self::W_NS, 'val');
                if ($style !== '') {
                    $htmlAttributes['data-docx-table-style'] = $style;
                    $styleEntry = $this->styles[$style] ?? [];
                    foreach ([
                        'styleName' => 'data-docx-table-style-name',
                        'basedOn' => 'data-docx-table-style-based-on',
                        'tableFill' => 'data-docx-table-style-fill',
                        'tableAlignment' => 'data-docx-table-style-align',
                    ] as $styleKey => $attrName) {
                        $value = (string) ($styleEntry[$styleKey] ?? '');
                        if ($value !== '') {
                            $htmlAttributes[$attrName] = $value;
                        }
                    }
                    $chain = $styleEntry['styleChain'] ?? [];
                    if (is_array($chain) && $chain !== []) {
                        $htmlAttributes['data-docx-table-style-chain'] = implode(' ', array_map(static fn (mixed $value): string => (string) $value, $chain));
                    }
                }
            }
        }

        if ($htmlAttributes !== []) {
            $attrs['htmlAttributes'] = $htmlAttributes;
        }

        return $attrs;
    }

    /**
     * @return array<string, string>
     */
    private function tableLayoutHtmlAttributes(\DOMElement $tblPr): array
    {
        $attrs = [];
        $styleDeclarations = [];

        $tableStyle = $this->directChild($tblPr, 'tblStyle');
        if ($tableStyle instanceof \DOMElement) {
            $styleId = $this->attr($tableStyle, self::W_NS, 'val');
            $styleEntry = $styleId !== '' ? ($this->styles[$styleId] ?? []) : [];
            if (is_array($styleEntry)) {
                $fill = $this->docxHexCssColor((string) ($styleEntry['tableFill'] ?? ''));
                if ($fill !== '') {
                    $styleDeclarations['background-color'] = $fill;
                }
                $alignment = $this->docxTableAlignment((string) ($styleEntry['tableAlignment'] ?? ''));
                if ($alignment !== '') {
                    $attrs['align'] = $alignment;
                }
            }
        }

        foreach ($tblPr->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'tblW') {
                $width = $this->docxTableWidth($this->attr($child, self::W_NS, 'w'), strtolower($this->attr($child, self::W_NS, 'type')));
                if ($width !== '') {
                    if (str_ends_with($width, '%')) {
                        $attrs['width'] = $width;
                    } else {
                        $styleDeclarations['width'] = $width;
                    }
                }
            } elseif ($child->localName === 'jc') {
                $alignment = $this->docxTableAlignment($this->attr($child, self::W_NS, 'val'));
                if ($alignment !== '') {
                    $attrs['align'] = $alignment;
                }
            } elseif ($child->localName === 'shd') {
                $fill = $this->docxHexCssColor($this->attr($child, self::W_NS, 'fill'));
                if ($fill !== '') {
                    $styleDeclarations['background-color'] = $fill;
                }
            } elseif ($child->localName === 'tblBorders') {
                $border = $this->docxTableBorderCssDeclarations($child);
                if ($border !== []) {
                    $styleDeclarations = array_replace($styleDeclarations, $border);
                }
            }
        }

        if ($styleDeclarations !== []) {
            $attrs['style'] = $this->cssStyleFromDeclarations($styleDeclarations);
        }

        return $attrs;
    }

    private function docxTableWidth(string $value, string $type): string
    {
        $value = trim($value);
        if ($value === '' || !is_numeric($value)) {
            return '';
        }

        $number = (float) $value;
        if ($number <= 0.0) {
            return '';
        }

        if ($type === 'pct') {
            $percent = $number / 50.0;
            if ($percent <= 0.0 || $percent > 100.0) {
                return '';
            }

            return $this->formatDecimal($percent, 4) . '%';
        }

        if ($type === '' || $type === 'dxa') {
            return $this->docxTwipsCssLength($value);
        }

        return '';
    }

    private function docxTableAlignment(string $value): string
    {
        return match (strtolower(trim($value))) {
            'center' => 'center',
            'right', 'end' => 'right',
            'left', 'start' => 'left',
            default => '',
        };
    }

    /**
     * @return array<string, string>
     */
    private function docxTableBorderCssDeclarations(\DOMElement $borders): array
    {
        foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $edge) {
            $border = $this->directChild($borders, $edge);
            if (!$border instanceof \DOMElement) {
                continue;
            }
            $style = strtolower($this->attr($border, self::W_NS, 'val'));
            if ($style === '' || in_array($style, ['nil', 'none'], true)) {
                continue;
            }

            $declarations = [
                'border-style' => $this->docxBorderStyle($style),
            ];
            $width = $this->docxBorderWidth($this->attr($border, self::W_NS, 'sz'));
            if ($width !== '') {
                $declarations['border-width'] = $width;
            }
            $color = $this->docxHexCssColor($this->attr($border, self::W_NS, 'color'));
            if ($color !== '') {
                $declarations['border-color'] = $color;
            }

            return $declarations;
        }

        return [];
    }

    private function docxBorderStyle(string $style): string
    {
        return match ($style) {
            'dashed', 'dashsmallgap', 'dashdotstroked', 'dashdot', 'dashdotdot' => 'dashed',
            'dotted' => 'dotted',
            'double', 'thickthinlargegap', 'thickthinmediumgap', 'thickthinsmallgap',
            'thinthicklargegap', 'thinthickmediumgap', 'thinthicksmallgap' => 'double',
            default => 'solid',
        };
    }

    private function docxBorderWidth(string $value): string
    {
        $value = trim($value);
        if ($value === '' || !is_numeric($value)) {
            return '';
        }

        $eighthPoints = (float) $value;
        if ($eighthPoints <= 0.0 || $eighthPoints > 768.0) {
            return '';
        }

        return $this->formatDecimal($eighthPoints / 8.0, 3) . 'pt';
    }

    /**
     * @return list<float>
     */
    private function tableGridWidths(\DOMElement $table, int $columnCount): array
    {
        $defaultWidths = array_fill(0, $columnCount, 0.0);
        $tblGrid = $this->directChild($table, 'tblGrid');
        if (!$tblGrid instanceof \DOMElement) {
            return $defaultWidths;
        }

        $widths = [];
        foreach ($tblGrid->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'gridCol') {
                continue;
            }
            $width = (int) ($this->attr($child, self::W_NS, 'w') ?: '0');
            if ($width <= 0) {
                $widths[] = 0.0;
                continue;
            }
            $widths[] = (float) $width;
        }

        if ($widths === []) {
            return $defaultWidths;
        }

        $denominator = $this->textWidthTwips - (10 * (count($widths) - 1));
        if ($denominator <= 0) {
            return $defaultWidths;
        }

        $fractions = array_map(
            static fn (float $width): float => $width > 0.0 ? $width / $denominator : 0.0,
            $widths
        );
        $total = array_sum($fractions);
        if ($total > 1.0) {
            $fractions = array_map(static fn (float $width): float => $width / $total, $fractions);
        }

        while (count($fractions) < $columnCount) {
            $fractions[] = 0.0;
        }

        return array_slice($fractions, 0, $columnCount);
    }

    /**
     * @return array<string, string>
     */
    private function tableCellHtmlAttributes(\DOMElement $cell, string $verticalMerge): array
    {
        $attrs = [];
        if ($verticalMerge !== '') {
            $attrs['data-docx-vmerge'] = $verticalMerge;
        }

        $tcPr = $this->directChild($cell, 'tcPr');
        if (!$tcPr instanceof \DOMElement) {
            return $attrs;
        }

        $styles = [];
        foreach ($tcPr->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'shd') {
                $fill = strtoupper($this->attr($child, self::W_NS, 'fill'));
                if ($fill !== '' && $fill !== 'AUTO') {
                    $styles[] = 'background-color:#' . ltrim($fill, '#');
                }
            } elseif ($child->localName === 'vAlign') {
                $align = strtolower($this->attr($child, self::W_NS, 'val'));
                if ($align !== '') {
                    $styles[] = 'vertical-align:' . ($align === 'center' ? 'middle' : $align);
                }
            } elseif ($child->localName === 'tcW') {
                $width = $this->attr($child, self::W_NS, 'w');
                if ($width !== '') {
                    $attrs['data-docx-cell-width'] = $width;
                }
                $type = $this->attr($child, self::W_NS, 'type');
                if ($type !== '') {
                    $attrs['data-docx-cell-width-type'] = $type;
                }
            }
        }

        if ($styles !== []) {
            $attrs['style'] = implode('; ', $styles);
        }

        return $attrs;
    }

    private function headingLevel(\DOMElement $paragraph): ?int
    {
        $directOutlineLevel = $this->paragraphDirectOutlineHeadingLevel($paragraph);
        if ($directOutlineLevel !== null) {
            return $directOutlineLevel;
        }

        foreach ($paragraph->getElementsByTagNameNS(self::W_NS, 'pStyle') as $style) {
            if (!$style instanceof \DOMElement) {
                continue;
            }
            $styleId = $this->attr($style, self::W_NS, 'val');
            if ($this->headingZeroStyleMatches($styleId, '')) {
                return 1;
            }
            if (isset($this->styles[$styleId]['headingLevel'])) {
                return max(1, min(6, (int) $this->styles[$styleId]['headingLevel']));
            }
            if (preg_match('/heading\s*([1-6])|Heading([1-6])/i', $styleId, $m)) {
                return (int) ($m[1] !== '' ? $m[1] : $m[2]);
            }
        }
        return null;
    }

    private function paragraphDirectOutlineHeadingLevel(\DOMElement $paragraph): ?int
    {
        $pPr = $this->directChild($paragraph, 'pPr');
        if (!$pPr instanceof \DOMElement) {
            return null;
        }

        $outlineLevel = $this->directChild($pPr, 'outlineLvl');
        if (!$outlineLevel instanceof \DOMElement) {
            return null;
        }

        $value = $this->attr($outlineLevel, self::W_NS, 'val');
        if (!preg_match('/^\d+$/', $value)) {
            return null;
        }

        return $value === '0' ? 1 : null;
    }

    private function paragraphStyleId(\DOMElement $paragraph): string
    {
        $pPr = $this->directChild($paragraph, 'pPr');
        $pStyle = $pPr instanceof \DOMElement ? $this->directChild($pPr, 'pStyle') : null;

        return $pStyle instanceof \DOMElement ? $this->attr($pStyle, self::W_NS, 'val') : '';
    }

    private function uniqueHeadingIdentifier(string $text): string
    {
        $base = $this->headingIdentifierBase($text);
        $count = $this->headingIds[$base] ?? 0;
        $this->headingIds[$base] = $count + 1;

        return $count === 0 ? $base : $base . '-' . $count;
    }

    private function headingIdentifierBase(string $text): string
    {
        $identifier = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        $identifier = preg_replace('/\s+/u', '-', $identifier) ?? $identifier;
        $identifier = preg_replace('/[^\pL\pN_.-]+/u', '', $identifier) ?? $identifier;
        $identifier = preg_replace('/^[^\pL]+/u', '', $identifier) ?? $identifier;
        $identifier = trim($identifier, '-');

        return $identifier === '' ? 'section' : $identifier;
    }

    /**
     * @return array{numId: string, level: int}|null
     * @param array{numId: string, level: int}|null $activeStyleNumbering
     */
    private function paragraphNumbering(\DOMElement $paragraph, ?array $activeStyleNumbering = null): ?array
    {
        $pPr = $this->directChild($paragraph, 'pPr');
        $numPr = $pPr instanceof \DOMElement ? $this->directChild($pPr, 'numPr') : null;
        if ($numPr instanceof \DOMElement) {
            $numId = '';
            $level = 1;
            foreach ($numPr->childNodes as $child) {
                if ($child instanceof \DOMElement && $child->localName === 'numId') {
                    $numId = $this->attr($child, self::W_NS, 'val');
                } elseif ($child instanceof \DOMElement && $child->localName === 'ilvl') {
                    $level = max(1, (int) ($this->attr($child, self::W_NS, 'val') ?: '0') + 1);
                }
            }
            if ($numId !== '') {
                if ($numId === '0') {
                    return null;
                }

                return ['numId' => $numId, 'level' => $level];
            }
        }

        if ($activeStyleNumbering !== null) {
            return $activeStyleNumbering;
        }

        $styleId = $this->paragraphStyleId($paragraph);
        if ($styleId === '') {
            return null;
        }

        return $this->paragraphStyleNumbering($styleId);
    }

    private function paragraphExplicitlySuppressesNumbering(\DOMElement $paragraph): bool
    {
        $pPr = $this->directChild($paragraph, 'pPr');
        $numPr = $pPr instanceof \DOMElement ? $this->directChild($pPr, 'numPr') : null;
        $numId = $numPr instanceof \DOMElement ? $this->directChild($numPr, 'numId') : null;

        return $numId instanceof \DOMElement && $this->attr($numId, self::W_NS, 'val') === '0';
    }

    private function paragraphDirectLeftIndent(\DOMElement $paragraph): int
    {
        $pPr = $this->directChild($paragraph, 'pPr');
        $ind = $pPr instanceof \DOMElement ? $this->directChild($pPr, 'ind') : null;
        if (!$ind instanceof \DOMElement) {
            return 0;
        }

        $left = $this->attr($ind, self::W_NS, 'left');

        return $left !== '' && is_numeric($left) ? (int) $left : 0;
    }

    private function paragraphHasDirectConcreteNumbering(\DOMElement $paragraph): bool
    {
        $pPr = $this->directChild($paragraph, 'pPr');
        $numPr = $pPr instanceof \DOMElement ? $this->directChild($pPr, 'numPr') : null;
        $numId = $numPr instanceof \DOMElement ? $this->directChild($numPr, 'numId') : null;
        if (!$numId instanceof \DOMElement) {
            return false;
        }

        $value = $this->attr($numId, self::W_NS, 'val');

        return $value !== '' && $value !== '0';
    }

    /**
     * @param array{numId: string, level: int} $numbering
     */
    private function paragraphNumberingSeedsStyleContinuation(\DOMElement $paragraph, string $styleId, array $numbering): bool
    {
        if (!$this->paragraphHasDirectConcreteNumbering($paragraph)) {
            return true;
        }

        $definition = $this->numbering[$numbering['numId']][$numbering['level']] ?? null;
        $definitionStyleId = is_array($definition) ? (string) ($definition['styleId'] ?? '') : '';
        if ($definitionStyleId !== '' && in_array($definitionStyleId, $this->styleChainIds($styleId), true)) {
            return true;
        }

        $styleNumbering = $this->paragraphStyleNumbering($styleId);

        return $styleNumbering !== null
            && $styleNumbering['numId'] === $numbering['numId']
            && $styleNumbering['level'] === $numbering['level'];
    }

    /**
     * @return array{numId: string, level: int}|null
     */
    private function paragraphStyleNumbering(string $styleId): ?array
    {
        foreach ($this->styleChainIds($styleId) as $candidateStyleId) {
            $style = $this->styles[$candidateStyleId] ?? [];
            $numId = (string) ($style['numId'] ?? '');
            if ($numId !== '' && $numId !== '0') {
                return [
                    'numId' => $numId,
                    'level' => max(1, (int) ($style['numLevel'] ?? 1)),
                ];
            }
        }

        foreach ($this->styleChainIds($styleId) as $candidateStyleId) {
            $numbering = $this->numberingForParagraphStyle($candidateStyleId);
            if ($numbering !== null) {
                return $numbering;
            }
        }

        return null;
    }

    /**
     * @return array{ordered: bool, attrs: array<string, mixed>, groupAttrs?: array<string, mixed>, marker?: string, continuation?: bool, explicitStart?: bool}
     */
    private function numberingListAttributes(string $numId, int $level): array
    {
        $levels = $this->numbering[$numId] ?? [];
        $style = $levels[$level] ?? $levels[1] ?? null;
        if (!is_array($style) || !($style['ordered'] ?? false)) {
            $result = ['ordered' => false, 'attrs' => []];
            if (($style['continuation'] ?? false) === true) {
                $result['continuation'] = true;
            }
            $taskChecked = $style['taskChecked'] ?? null;
            if (is_bool($taskChecked)) {
                $result['marker'] = $taskChecked ? "\u{2612}" : "\u{2610}";
                $result['groupAttrs'] = ['docxTaskBullet' => true];
            }

            return $result;
        }

        return [
            'ordered' => true,
            'explicitStart' => (bool) ($style['startOverride'] ?? false),
            'attrs' => [
                'start' => max(1, (int) ($style['start'] ?? 1)),
                'style' => $style['style'] ?? 'decimal',
                'delimiter' => $style['delimiter'] ?? 'period',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runStyle(\DOMElement $run): array
    {
        $style = [];
        $styleLayers = [];
        $directStyle = [];
        $customStyleNames = [];
        foreach ($run->getElementsByTagNameNS(self::W_NS, 'rPr') as $rPr) {
            if (!$rPr instanceof \DOMElement || $rPr->parentNode !== $run) {
                continue;
            }
            foreach ($rPr->childNodes as $prop) {
                if (!$prop instanceof \DOMElement) {
                    continue;
                }
                if ($prop->localName === 'rStyle') {
                    $styleId = $this->attr($prop, self::W_NS, 'val');
                    $customStyleName = $this->runStyleForStylesExtension($styleId);
                    if ($customStyleName !== '') {
                        $customStyleNames[] = $customStyleName;
                        continue;
                    }
                    $style = array_replace($style, $this->styles[$styleId] ?? []);
                    $styleLayer = $this->trueRunStyleFlags($style);
                    if ($this->runStyleIsInlineCode($styleId)) {
                        $style['code'] = true;
                        $styleLayer['code'] = true;
                    }
                    if ($this->runStyleIsHyperlink($styleId) && !isset($style['underlineFromDirectFormatting'])) {
                        unset($style['underline']);
                        unset($styleLayer['underline']);
                    }
                    if ($styleLayer !== []) {
                        $styleLayers[] = $styleLayer;
                    }
                }
            }
            $rPrDirectStyle = $this->runPropertyStyle($rPr);
            if (array_key_exists('underline', $rPrDirectStyle)) {
                $rPrDirectStyle['underlineFromDirectFormatting'] = true;
            }
            $directStyle = array_replace($directStyle, $rPrDirectStyle);
            $style = array_replace($style, $rPrDirectStyle);
        }

        unset($style['underlineFromDirectFormatting']);
        unset($directStyle['underlineFromDirectFormatting']);

        $directFalseFlags = array_keys(array_filter(
            $directStyle,
            static fn (mixed $value): bool => $value === false
        ));
        if ($directFalseFlags !== []) {
            foreach ($styleLayers as $index => $layer) {
                foreach ($directFalseFlags as $flag) {
                    unset($layer[$flag]);
                }
                $styleLayers[$index] = $layer;
            }
            $styleLayers = array_values(array_filter($styleLayers, static fn (array $layer): bool => $layer !== []));
        }

        $layers = [];
        $directLayer = $this->trueRunStyleFlags($directStyle);
        if ($directLayer !== []) {
            $layers[] = ['origin' => 'direct', 'style' => $directLayer];
        }
        foreach ($styleLayers as $styleLayer) {
            $layers[] = ['origin' => 'style', 'style' => $styleLayer];
        }

        $flags = $this->runStyleFlags($style);
        foreach (['textColor', 'fontSize'] as $inlineStyleKey) {
            if (is_string($style[$inlineStyleKey] ?? null) && $style[$inlineStyleKey] !== '') {
                $flags[$inlineStyleKey] = $style[$inlineStyleKey];
            }
        }
        if ($layers !== []) {
            $flags['__layers'] = $layers;
        }
        if ($customStyleNames !== []) {
            $flags['__customStyles'] = array_values(array_unique($customStyleNames));
        }

        return $flags;
    }

    private function runStyleForStylesExtension(string $styleId): string
    {
        if (!$this->stylesExtension || $styleId === '') {
            return '';
        }

        $style = $this->styles[$styleId] ?? [];
        $name = trim((string) ($style['styleName'] ?? $styleId));
        if ($name === '' || strcasecmp($name, 'Hyperlink') === 0 || strcasecmp($styleId, 'Hyperlink') === 0) {
            return '';
        }

        return $name;
    }

    private function runStyleIsInlineCode(string $styleId): bool
    {
        foreach ($this->styleChainIds($styleId) as $candidateStyleId) {
            $style = $this->styles[$candidateStyleId] ?? [];
            foreach ([$candidateStyleId, (string) ($style['styleName'] ?? '')] as $candidate) {
                $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', '', $candidate) ?? '');
                if (in_array($normalized, ['verbatimchar', 'sourcecode'], true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function runStyleIsHyperlink(string $styleId): bool
    {
        foreach ($this->styleChainIds($styleId) as $candidateStyleId) {
            $style = $this->styles[$candidateStyleId] ?? [];
            foreach ([$candidateStyleId, (string) ($style['styleName'] ?? '')] as $candidate) {
                $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', '', $candidate) ?? '');
                if (in_array($normalized, ['hyperlink', 'lienhypertexte', 'link'], true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function runUsesSymbolFont(\DOMElement $run): bool
    {
        $rPr = $this->directChild($run, 'rPr');
        if (!$rPr instanceof \DOMElement) {
            return false;
        }

        $fonts = $this->directChild($rPr, 'rFonts');
        if (!$fonts instanceof \DOMElement) {
            return false;
        }

        foreach (['ascii', 'hAnsi', 'cs'] as $name) {
            if (strcasecmp($this->attr($fonts, self::W_NS, $name), 'Symbol') === 0) {
                return true;
            }
        }

        return false;
    }

    private function symbolElementText(\DOMElement $symbol): string
    {
        if (strcasecmp($this->attr($symbol, self::W_NS, 'font'), 'Symbol') !== 0) {
            return '';
        }

        $hex = $this->attr($symbol, self::W_NS, 'char');
        if ($hex === '' || preg_match('/^[0-9a-f]+$/i', $hex) !== 1) {
            return '';
        }

        return $this->macSymbolByteText(hexdec(substr($hex, -2)));
    }

    private function symbolFontText(string $text): string
    {
        return preg_replace_callback('/[\x{F000}-\x{F0FF}]/u', function (array $match): string {
            return $this->macSymbolByteText($this->utf8Codepoint($match[0]) & 0xff);
        }, $text) ?? $text;
    }

    private function macSymbolByteText(int $byte): string
    {
        $decoded = UnicodeText::decodeBytes(chr($byte & 0xff), 'mac-symbol');

        return (string) ($decoded['text'] ?? '');
    }

    private function utf8Codepoint(string $char): int
    {
        if (function_exists('mb_ord')) {
            return mb_ord($char, 'UTF-8');
        }

        $bytes = array_map('ord', str_split($char));
        $first = $bytes[0] ?? 0;
        if ($first < 0x80) {
            return $first;
        }
        if (($first & 0xe0) === 0xc0) {
            return (($first & 0x1f) << 6) | (($bytes[1] ?? 0) & 0x3f);
        }
        if (($first & 0xf0) === 0xe0) {
            return (($first & 0x0f) << 12) | ((($bytes[1] ?? 0) & 0x3f) << 6) | (($bytes[2] ?? 0) & 0x3f);
        }
        if (($first & 0xf8) === 0xf0) {
            return (($first & 0x07) << 18) | ((($bytes[1] ?? 0) & 0x3f) << 12) | ((($bytes[2] ?? 0) & 0x3f) << 6) | (($bytes[3] ?? 0) & 0x3f);
        }

        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function runPropertyStyle(\DOMElement $rPr): array
    {
        $style = [];
        foreach ($rPr->childNodes as $prop) {
            if (!$prop instanceof \DOMElement) {
                continue;
            }
            if ($prop->localName === 'b') {
                $style['strong'] = $this->truthyOnOff($prop);
            } elseif ($prop->localName === 'i') {
                $style['emph'] = $this->truthyOnOff($prop);
            } elseif ($prop->localName === 'u') {
                $style['underline'] = $this->truthyUnderline($prop);
            } elseif ($prop->localName === 'strike' || $prop->localName === 'dstrike') {
                $style['strikeout'] = $this->truthyOnOff($prop);
            } elseif ($prop->localName === 'smallCaps') {
                $style['small_caps'] = $this->truthyOnOff($prop);
            } elseif ($prop->localName === 'highlight') {
                $value = strtolower($this->attr($prop, self::W_NS, 'val'));
                if ($this->preserveRunStyles) {
                    $highlightColor = $this->docxHighlightCssColor($value);
                    $style['mark'] = $highlightColor !== '';
                    if ($highlightColor !== '') {
                        $style['markBackgroundColor'] = $highlightColor;
                    } elseif (in_array($value, ['none', ''], true)) {
                        unset($style['markBackgroundColor']);
                    }
                } else {
                    $style['mark'] = !in_array($value, ['', 'none'], true);
                }
            } elseif ($prop->localName === 'color') {
                if ($this->preserveRunStyles) {
                    $color = $this->docxHexCssColor($this->attr($prop, self::W_NS, 'val'));
                    if ($color !== '') {
                        $style['textColor'] = $color;
                    }
                }
            } elseif ($prop->localName === 'sz') {
                if ($this->preserveRunStyles) {
                    $fontSize = $this->docxHalfPointFontSize($this->attr($prop, self::W_NS, 'val'));
                    if ($fontSize !== '') {
                        $style['fontSize'] = $fontSize;
                    }
                }
            } elseif ($prop->localName === 'vertAlign') {
                $value = strtolower($this->attr($prop, self::W_NS, 'val'));
                if ($value === 'superscript') {
                    $style['superscript'] = true;
                    $style['subscript'] = false;
                } elseif ($value === 'subscript') {
                    $style['subscript'] = true;
                    $style['superscript'] = false;
                } elseif ($value === 'baseline') {
                    $style['superscript'] = false;
                    $style['subscript'] = false;
                }
            }
        }

        return $style;
    }

    private function docxHexCssColor(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^[0-9a-f]{6}$/i', $value) !== 1) {
            return '';
        }

        return '#' . strtoupper($value);
    }

    private function docxHighlightCssColor(string $value): string
    {
        return match (strtolower(trim($value))) {
            'black' => '#000000',
            'blue' => '#0000FF',
            'cyan' => '#00FFFF',
            'green' => '#008000',
            'magenta' => '#FF00FF',
            'red' => '#FF0000',
            'yellow' => '#FFFF00',
            'white' => '#FFFFFF',
            'darkblue' => '#00008B',
            'darkcyan' => '#008B8B',
            'darkgreen' => '#006400',
            'darkmagenta' => '#8B008B',
            'darkred' => '#8B0000',
            'darkyellow' => '#808000',
            'darkgray', 'darkgrey' => '#A9A9A9',
            'lightgray', 'lightgrey' => '#D3D3D3',
            default => '',
        };
    }

    private function docxHalfPointFontSize(string $value): string
    {
        $value = trim($value);
        if (!is_numeric($value)) {
            return '';
        }

        $halfPoints = (float) $value;
        if ($halfPoints <= 0.0 || $halfPoints > 400.0) {
            return '';
        }

        $points = $halfPoints / 2.0;
        $formatted = rtrim(rtrim(number_format($points, 2, '.', ''), '0'), '.');

        return ($formatted === '' ? '0' : $formatted) . 'pt';
    }

    /**
     * @param list<string> $names
     */
    private function docxFirstAttr(\DOMElement $element, array $names): string
    {
        foreach ($names as $name) {
            $value = $this->attr($element, self::W_NS, $name);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function docxTwipsCssLength(string $value, bool $positiveOnly = false): string
    {
        $value = trim($value);
        if ($value === '' || !is_numeric($value)) {
            return '';
        }

        $twips = (float) $value;
        if ($positiveOnly && $twips < 0.0) {
            return '';
        }
        if (abs($twips) > 63360.0) {
            return '';
        }

        $points = $twips / 20.0;

        return $this->formatDecimal($points, 3) . 'pt';
    }

    /**
     * @return array<string, string>
     */
    private function cssDeclarationMap(string $style): array
    {
        $declarations = [];
        foreach (explode(';', $style) as $declaration) {
            [$name, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            $name = strtolower(trim($name));
            $value = trim($value);
            if ($name !== '' && $value !== '') {
                $declarations[$name] = $value;
            }
        }

        return $declarations;
    }

    /**
     * @param array<string, string> $declarations
     */
    private function cssStyleFromDeclarations(array $declarations): string
    {
        $parts = [];
        foreach ($declarations as $property => $value) {
            $property = strtolower(trim((string) $property));
            $value = trim((string) $value);
            if ($property === '' || $value === '') {
                continue;
            }
            $parts[] = $property . ':' . $value;
        }

        return implode('; ', $parts);
    }

    private function formatDecimal(float $value, int $precision): string
    {
        $formatted = rtrim(rtrim(number_format($value, $precision, '.', ''), '0'), '.');

        return $formatted === '-0' || $formatted === '' ? '0' : $formatted;
    }

    /**
     * @param array<string, mixed> $style
     * @return array<string, bool>
     */
    private function runStyleFlags(array $style): array
    {
        $flags = [];
        foreach ($this->runStyleFlagOrder() as $flag) {
            if (array_key_exists($flag, $style) && is_bool($style[$flag])) {
                $flags[$flag] = $style[$flag];
            }
        }
        if (($flags['mark'] ?? false) === true && is_string($style['markBackgroundColor'] ?? null)) {
            $flags['markBackgroundColor'] = $style['markBackgroundColor'];
        }

        return $flags;
    }

    /**
     * @param array<string, mixed> $style
     * @return array<string, true>
     */
    private function trueRunStyleFlags(array $style): array
    {
        $flags = [];
        foreach ($this->runStyleFlagOrder() as $flag) {
            if (($style[$flag] ?? false) === true) {
                $flags[$flag] = true;
            }
        }
        if (($flags['mark'] ?? false) === true && is_string($style['markBackgroundColor'] ?? null)) {
            $flags['markBackgroundColor'] = $style['markBackgroundColor'];
        }

        return $flags;
    }

    /**
     * Ordered from inner wrappers to outer wrappers.
     *
     * @return list<string>
     */
    private function runStyleFlagOrder(): array
    {
        return [
            'code',
            'underline',
            'strikeout',
            'small_caps',
            'mark',
            'strong',
            'emph',
            'subscript',
            'superscript',
        ];
    }

    /**
     * @param list<AstNode> $nodes
     * @param array<string, mixed> $style
     * @return list<AstNode>
     */
    private function styledRunNodes(array $nodes, array $style): array
    {
        if (($style['code'] ?? false) && $nodes !== [] && $this->allNodesHaveType($nodes, 'text')) {
            $nodes = [new AstNode('code', ['text' => $this->nodeText(new AstNode('span', [], $nodes))])];
            $style['code'] = false;
            $layers = $style['__layers'] ?? [];
            if (is_array($layers)) {
                $filteredLayers = [];
                foreach ($layers as $layer) {
                    if (!is_array($layer)) {
                        continue;
                    }
                    $layerStyle = $layer['style'] ?? $layer;
                    if (!is_array($layerStyle)) {
                        continue;
                    }
                    unset($layerStyle['code']);
                    if ($layerStyle === []) {
                        continue;
                    }
                    $layer['style'] = $layerStyle;
                    $filteredLayers[] = $layer;
                }
                $style['__layers'] = $filteredLayers;
            }
        }

        $layers = $style['__layers'] ?? [];
        if (is_array($layers) && $layers !== []) {
            foreach ($layers as $layer) {
                if (!is_array($layer)) {
                    continue;
                }
                $origin = (string) ($layer['origin'] ?? 'direct');
                $layerStyle = $layer['style'] ?? $layer;
                if (!is_array($layerStyle)) {
                    continue;
                }
                $nodes = $this->styledRunNodesForLayer($nodes, $layerStyle, $origin);
            }

            return $this->withRunCustomStyleNodes($this->styledRunInlineStyleNodes($nodes, $style), $style);
        }

        $nodes = $this->styledRunNodesForLayer($nodes, $style, 'direct');

        return $this->withRunCustomStyleNodes($this->styledRunInlineStyleNodes($nodes, $style), $style);
    }

    /**
     * @param list<AstNode> $nodes
     * @param array<string, mixed> $style
     * @return list<AstNode>
     */
    private function withRunCustomStyleNodes(array $nodes, array $style): array
    {
        $styles = $style['__customStyles'] ?? [];
        if (!is_array($styles) || $nodes === []) {
            return $nodes;
        }

        foreach (array_reverse($styles) as $customStyle) {
            $customStyle = trim((string) $customStyle);
            if ($customStyle !== '') {
                $nodes = [new AstNode('span', ['attributes' => ['custom-style' => $customStyle]], $nodes)];
            }
        }

        return $nodes;
    }

    /**
     * @param list<AstNode> $nodes
     * @param array<string, mixed> $style
     * @return list<AstNode>
     */
    private function styledRunNodesForLayer(array $nodes, array $style, string $origin): array
    {
        foreach ($this->runStyleNodeTypes() as $styleKey => $nodeType) {
            if (($style[$styleKey] ?? false) && $nodes !== []) {
                $nodes = [new AstNode($nodeType, $this->runStyleNodeAttrs($styleKey, $origin, $style), $nodes)];
            }
        }

        return $nodes;
    }

    /**
     * @param list<AstNode> $nodes
     * @param array<string, mixed> $style
     * @return list<AstNode>
     */
    private function styledRunInlineStyleNodes(array $nodes, array $style): array
    {
        $css = $this->runInlineCssStyle($style);
        if ($css === '' || $nodes === []) {
            return $nodes;
        }

        return [new AstNode('span', ['attributes' => ['style' => $css]], $nodes)];
    }

    /**
     * @param array<string, mixed> $style
     */
    private function runInlineCssStyle(array $style): string
    {
        $declarations = [];
        $textColor = is_string($style['textColor'] ?? null) ? $style['textColor'] : '';
        if ($textColor !== '') {
            $declarations[] = 'color:' . $textColor;
        }
        $fontSize = is_string($style['fontSize'] ?? null) ? $style['fontSize'] : '';
        if ($fontSize !== '') {
            $declarations[] = 'font-size:' . $fontSize;
        }

        return implode('; ', $declarations);
    }

    /**
     * @return array<string, mixed>
     */
    private function runStyleNodeAttrs(string $styleKey, string $origin, array $style = []): array
    {
        $attrs = [self::INTERNAL_STYLE_ORIGIN_ATTR => $origin];
        if ($styleKey === 'mark') {
            $attrs['classes'] = ['mark'];
            $background = is_string($style['markBackgroundColor'] ?? null) ? $style['markBackgroundColor'] : '';
            if ($background !== '') {
                $attrs['attributes'] = ['style' => 'background-color:' . $background];
            }
        }

        return $attrs;
    }

    /**
     * @return array<string, string>
     */
    private function runStyleNodeTypes(): array
    {
        return [
            'underline' => 'underline',
            'strikeout' => 'strikeout',
            'small_caps' => 'small_caps',
            'mark' => 'span',
            'strong' => 'strong',
            'emph' => 'emph',
            'subscript' => 'subscript',
            'superscript' => 'superscript',
        ];
    }

    private function truthyOnOff(\DOMElement $element): bool
    {
        $value = strtolower($this->attr($element, self::W_NS, 'val'));
        return !in_array($value, ['0', 'false', 'off', 'no'], true);
    }

    private function truthyUnderline(\DOMElement $element): bool
    {
        return strtolower($this->attr($element, self::W_NS, 'val')) !== 'none'
            && $this->truthyOnOff($element);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function styles(\DOMDocument $dom): array
    {
        $styles = [];
        foreach ($dom->getElementsByTagNameNS(self::W_NS, 'style') as $style) {
            if (!$style instanceof \DOMElement) {
                continue;
            }
            $styleId = $this->attr($style, self::W_NS, 'styleId');
            if ($styleId === '') {
                continue;
            }
            $entry = ['styleId' => $styleId];
            $type = $this->attr($style, self::W_NS, 'type');
            if ($type !== '') {
                $entry['styleType'] = $type;
            }
            foreach ($style->childNodes as $child) {
                if (!$child instanceof \DOMElement) {
                    continue;
                }
                if ($child->localName === 'name') {
                    $name = $this->attr($child, self::W_NS, 'val');
                    if ($name !== '') {
                        $entry['styleName'] = $name;
                    }
                } elseif ($child->localName === 'basedOn') {
                    $basedOn = $this->attr($child, self::W_NS, 'val');
                    if ($basedOn !== '') {
                        $entry['basedOn'] = $basedOn;
                    }
                } elseif ($child->localName === 'pPr') {
                    $jc = $this->directChild($child, 'jc');
                    if ($jc instanceof \DOMElement) {
                        $alignment = $this->wordJustificationAlignment($this->attr($jc, self::W_NS, 'val'));
                        if ($alignment !== '') {
                            $entry['paragraphAlignment'] = $alignment;
                        }
                    }
                    $paragraphBackgroundColor = $this->paragraphPropertiesBackgroundColor($child);
                    if ($paragraphBackgroundColor !== '') {
                        $entry['paragraphBackgroundColor'] = $paragraphBackgroundColor;
                    }
                    $paragraphCssStyle = $this->cssStyleFromDeclarations($this->paragraphPropertiesCssDeclarations($child));
                    if ($paragraphCssStyle !== '') {
                        $entry['paragraphCssStyle'] = $paragraphCssStyle;
                    }
                    $ind = $this->directChild($child, 'ind');
                    if ($ind instanceof \DOMElement) {
                        $left = $this->attr($ind, self::W_NS, 'left');
                        if ($left !== '' && is_numeric($left)) {
                            $entry['paragraphLeftIndent'] = (int) $left;
                        }
                    }
                    foreach ($child->getElementsByTagNameNS(self::W_NS, 'outlineLvl') as $outline) {
                        if ($outline instanceof \DOMElement) {
                            $outlineLevel = $this->attr($outline, self::W_NS, 'val');
                            if (preg_match('/^\d+$/', $outlineLevel) === 1 && (int) $outlineLevel < 9) {
                                $entry['headingLevel'] = max(1, min(6, (int) $outlineLevel + 1));
                            }
                        }
                    }
                    $styleNumbering = $this->styleParagraphNumbering($child);
                    if ($styleNumbering !== null) {
                        $entry['numId'] = $styleNumbering['numId'];
                        $entry['numLevel'] = $styleNumbering['level'];
                    }
                } elseif ($child->localName === 'rPr') {
                    $entry = array_replace($entry, $this->runPropertyStyle($child));
                } elseif ($child->localName === 'tblPr') {
                    $entry = array_replace($entry, $this->tableStyleMetadata($child));
                }
            }
            if (!isset($entry['headingLevel']) && preg_match('/heading\s*([1-6])|Heading([1-6])/i', $styleId, $m)) {
                $entry['headingLevel'] = (int) ($m[1] !== '' ? $m[1] : $m[2]);
            }
            if ($this->headingZeroStyleMatches($styleId, (string) ($entry['styleName'] ?? ''))) {
                $entry['headingZeroStyle'] = true;
                if (!isset($entry['headingLevel'])) {
                    $entry['headingLevel'] = 1;
                }
            }
            $styles[$styleId] = $entry;
        }

        foreach (array_keys($styles) as $styleId) {
            $styles[$styleId] = $this->resolveStyleEntry($styleId, $styles, []);
        }

        return $styles;
    }

    /**
     * @param array<string, array<string, mixed>> $styles
     * @param list<string> $seen
     * @return array<string, mixed>
     */
    private function resolveStyleEntry(string|int $styleId, array $styles, array $seen): array
    {
        $styleId = (string) $styleId;
        $entry = $styles[$styleId] ?? [];
        if ($entry === [] || in_array($styleId, $seen, true)) {
            return $entry;
        }

        $basedOn = (string) ($entry['basedOn'] ?? '');
        if ($basedOn === '' || !isset($styles[$basedOn])) {
            $entry['styleChain'] = [$styleId];

            return $entry;
        }

        $parent = $this->resolveStyleEntry($basedOn, $styles, [...$seen, $styleId]);
        $merged = array_replace($parent, $entry);
        $parentChain = $parent['styleChain'] ?? [$basedOn];
        $merged['styleChain'] = array_values(array_unique([
            ...array_map(static fn (mixed $value): string => (string) $value, is_array($parentChain) ? $parentChain : [$basedOn]),
            $styleId,
        ]));

        return $merged;
    }

    /**
     * @return array{numId: string, level: int}|null
     */
    private function styleParagraphNumbering(\DOMElement $pPr): ?array
    {
        $numPr = $this->directChild($pPr, 'numPr');
        if (!$numPr instanceof \DOMElement) {
            return null;
        }

        $numId = '';
        $level = 1;
        foreach ($numPr->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->localName === 'numId') {
                $numId = $this->attr($child, self::W_NS, 'val');
            } elseif ($child->localName === 'ilvl') {
                $level = max(1, (int) ($this->attr($child, self::W_NS, 'val') ?: '0') + 1);
            }
        }

        return $numId === '' ? null : ['numId' => $numId, 'level' => $level];
    }

    /**
     * @return list<string>
     */
    private function styleChainIds(string $styleId): array
    {
        $style = $this->styles[$styleId] ?? null;
        if (!is_array($style)) {
            return [$styleId];
        }

        $chain = $style['styleChain'] ?? [$styleId];
        if (!is_array($chain)) {
            return [$styleId];
        }

        $ids = array_values(array_unique(array_reverse(array_map(
            static fn (mixed $value): string => (string) $value,
            $chain
        ))));

        return $ids === [] ? [$styleId] : $ids;
    }

    private function paragraphUsesHeadingZeroStyle(string $styleId): bool
    {
        foreach ($this->styleChainIds($styleId) as $candidateStyleId) {
            $style = $this->styles[$candidateStyleId] ?? [];
            if (($style['headingZeroStyle'] ?? false) === true) {
                return true;
            }
            if ($this->headingZeroStyleMatches($candidateStyleId, (string) ($style['styleName'] ?? ''))) {
                return true;
            }
        }

        return false;
    }

    private function headingZeroStyleMatches(string $styleId, string $styleName): bool
    {
        foreach ([$styleId, $styleName] as $candidate) {
            $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', '', $candidate) ?? '');
            if ($normalized === 'heading0') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{numId: string, level: int}|null
     */
    private function numberingForParagraphStyle(string $styleId): ?array
    {
        foreach ($this->numbering as $numId => $levels) {
            foreach ($levels as $level => $definition) {
                if (($definition['styleId'] ?? null) === $styleId) {
                    return [
                        'numId' => (string) $numId,
                        'level' => max(1, (int) $level),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function tableStyleMetadata(\DOMElement $tblPr): array
    {
        $metadata = [];
        foreach ($tblPr->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'shd') {
                $fill = strtoupper($this->attr($child, self::W_NS, 'fill'));
                if ($fill !== '' && $fill !== 'AUTO') {
                    $metadata['tableFill'] = $fill;
                }
            } elseif ($child->localName === 'jc') {
                $alignment = strtolower($this->attr($child, self::W_NS, 'val'));
                if ($alignment !== '') {
                    $metadata['tableAlignment'] = $alignment;
                }
            }
        }

        return $metadata;
    }

    /**
     * @return array<string, array<int, array{ordered: bool, style?: string, delimiter?: string, start?: int, startOverride?: bool, styleId?: string, text?: string, continuation?: bool, taskChecked?: bool}>>
     */
    private function numbering(\DOMDocument $dom): array
    {
        $abstractLevels = [];
        foreach ($dom->getElementsByTagNameNS(self::W_NS, 'abstractNum') as $abstractNum) {
            if (!$abstractNum instanceof \DOMElement) {
                continue;
            }
            $abstractId = $this->attr($abstractNum, self::W_NS, 'abstractNumId');
            if ($abstractId === '') {
                continue;
            }

            foreach ($abstractNum->childNodes as $level) {
                if (!$level instanceof \DOMElement || $level->localName !== 'lvl') {
                    continue;
                }
                $levelIndex = max(1, (int) ($this->attr($level, self::W_NS, 'ilvl') ?: '0') + 1);
                $abstractLevels[$abstractId][$levelIndex] = $this->numberingLevel($level);
            }
        }

        $numbering = [];
        foreach ($dom->getElementsByTagNameNS(self::W_NS, 'num') as $num) {
            if (!$num instanceof \DOMElement) {
                continue;
            }
            $numId = $this->attr($num, self::W_NS, 'numId');
            $abstractId = '';
            $overrides = [];
            foreach ($num->childNodes as $child) {
                if (!$child instanceof \DOMElement) {
                    continue;
                }
                if ($child->localName === 'abstractNumId') {
                    $abstractId = $this->attr($child, self::W_NS, 'val');
                    continue;
                }
                if ($child->localName !== 'lvlOverride') {
                    continue;
                }
                $levelIndex = max(1, (int) ($this->attr($child, self::W_NS, 'ilvl') ?: '0') + 1);
                foreach ($child->childNodes as $override) {
                    if (!$override instanceof \DOMElement) {
                        continue;
                    }
                    if ($override->localName === 'startOverride') {
                        $start = $this->attr($override, self::W_NS, 'val');
                        if ($start !== '' && is_numeric($start)) {
                            $overrides[$levelIndex]['start'] = max(1, (int) $start);
                            $overrides[$levelIndex]['startOverride'] = true;
                        }
                    } elseif ($override->localName === 'lvl') {
                        $overrides[$levelIndex] = array_replace($overrides[$levelIndex] ?? [], $this->numberingLevel($override));
                    }
                }
            }
            if ($numId !== '') {
                $levels = $abstractLevels[$abstractId] ?? [1 => ['ordered' => false]];
                foreach ($overrides as $level => $override) {
                    $levels[$level] = array_replace($levels[$level] ?? ['ordered' => false], $override);
                }
                $numbering[$numId] = $levels;
            }
        }

        return $numbering;
    }

    /**
     * @return array{ordered: bool, style?: string, delimiter?: string, start?: int, startOverride?: bool, styleId?: string, text?: string, continuation?: bool, taskChecked?: bool}
     */
    private function numberingLevel(\DOMElement $level): array
    {
        $format = 'bullet';
        $text = '';
        $hasLevelText = false;
        $start = null;
        $styleId = '';
        foreach ($level->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'numFmt') {
                $format = $this->attr($child, self::W_NS, 'val') ?: 'bullet';
            } elseif ($child->localName === 'lvlText') {
                $text = $this->attr($child, self::W_NS, 'val');
                $hasLevelText = true;
            } elseif ($child->localName === 'start') {
                $value = $this->attr($child, self::W_NS, 'val');
                if ($value !== '' && is_numeric($value)) {
                    $start = max(1, (int) $value);
                }
            } elseif ($child->localName === 'pStyle') {
                $styleId = $this->attr($child, self::W_NS, 'val');
            }
        }

        if ($format === 'bullet' || $format === 'none') {
            $entry = ['ordered' => false];
            if ($text !== '') {
                $entry['text'] = $text;
            }
            if ($format === 'none' || ($hasLevelText && trim($text) === '')) {
                $entry['continuation'] = true;
            }
            $taskChecked = $this->docxTaskBulletChecked($text);
            if ($taskChecked !== null) {
                $entry['taskChecked'] = $taskChecked;
            }
            if ($styleId !== '') {
                $entry['styleId'] = $styleId;
            }

            return $entry;
        }

        $entry = [
            'ordered' => true,
            'style' => $this->docxOrderedListStyle($format),
            'delimiter' => $this->docxOrderedListDelimiter($text),
        ];
        if ($start !== null) {
            $entry['start'] = $start;
        }
        if ($styleId !== '') {
            $entry['styleId'] = $styleId;
        }

        return $entry;
    }

    private function docxTaskBulletChecked(string $levelText): ?bool
    {
        if (preg_match('/^\s*(\x{2610}|\x{2612})\s*$/u', $levelText, $matches) !== 1) {
            return null;
        }

        return $matches[1] === "\u{2612}";
    }

    private function docxOrderedListStyle(string $format): string
    {
        return match ($format) {
            'lowerLetter' => 'lower_alpha',
            'upperLetter' => 'upper_alpha',
            'lowerRoman' => 'lower_roman',
            'upperRoman' => 'upper_roman',
            default => 'decimal',
        };
    }

    private function docxOrderedListDelimiter(string $levelText): string
    {
        $levelText = trim($levelText);
        if (preg_match('/^\(%\d+\)$/', $levelText) === 1) {
            return 'two_parens';
        }
        if (preg_match('/%\d+\)$/', $levelText) === 1) {
            return 'one_paren';
        }
        if (preg_match('/%\d+\.$/', $levelText) === 1) {
            return 'period';
        }

        return 'default';
    }

    /**
     * @return list<array{section: int, headers: list<array{type: string, relationshipId: string, target: string, part: string}>, footers: list<array{type: string, relationshipId: string, target: string, part: string}>}>
     */
    private function sectionReferences(\DOMElement $body): array
    {
        $sections = [];
        foreach ($body->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $sectPr = null;
            if ($child->localName === 'sectPr') {
                $sectPr = $child;
            } elseif ($child->localName === 'p') {
                $pPr = $this->directChild($child, 'pPr');
                if ($pPr instanceof \DOMElement) {
                    $sectPr = $this->directChild($pPr, 'sectPr');
                }
            }

            if (!$sectPr instanceof \DOMElement) {
                continue;
            }

            $section = [
                'section' => count($sections) + 1,
                'headers' => [],
                'footers' => [],
            ];
            foreach ($sectPr->childNodes as $reference) {
                if (!$reference instanceof \DOMElement) {
                    continue;
                }
                if ($reference->localName !== 'headerReference' && $reference->localName !== 'footerReference') {
                    continue;
                }

                $rid = $this->attr($reference, self::R_NS, 'id');
                if ($rid === '') {
                    continue;
                }
                $relationship = $this->relationships[$rid] ?? null;
                if (!is_array($relationship)) {
                    continue;
                }
                $target = (string) ($relationship['target'] ?? '');
                if ($target === '') {
                    continue;
                }

                $record = [
                    'type' => $this->attr($reference, self::W_NS, 'type') ?: 'default',
                    'relationshipId' => $rid,
                    'target' => $target,
                    'part' => $this->normalizeWordTarget($target),
                ];
                if ($reference->localName === 'headerReference') {
                    $section['headers'][] = $record;
                } else {
                    $section['footers'][] = $record;
                }
            }

            if ($section['headers'] !== [] || $section['footers'] !== []) {
                $sections[] = $section;
            }
        }

        return $sections;
    }

    /**
     * @return array<string, array{target: string, type: string, mode: string}>
     */
    private function relationships(\DOMDocument $dom): array
    {
        $rels = [];
        foreach ($dom->getElementsByTagNameNS(self::REL_NS, 'Relationship') as $rel) {
            if (!$rel instanceof \DOMElement) {
                continue;
            }
            $id = $rel->getAttribute('Id');
            if ($id === '') {
                continue;
            }
            $rels[$id] = [
                'target' => $rel->getAttribute('Target'),
                'type' => $rel->getAttribute('Type'),
                'mode' => $rel->getAttribute('TargetMode'),
            ];
        }

        return $rels;
    }

    private function normalizePackageTarget(string $sourcePartName, string $target): string
    {
        $target = str_replace('\\', '/', $target);
        if (str_starts_with($target, '/')) {
            return ltrim($target, '/');
        }
        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $target)) {
            return $target;
        }

        $base = '';
        if ($sourcePartName !== '') {
            $slash = strrpos($sourcePartName, '/');
            $base = $slash === false ? '' : substr($sourcePartName, 0, $slash + 1);
        }

        $parts = [];
        foreach (explode('/', $base . $target) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    /**
     * @return array<string, list<AstNode>>
     */
    private function notes(\DOMDocument $dom, string $localName, array $relationships): array
    {
        $notes = [];

        $previousRelationships = $this->relationships;
        $this->relationships = $relationships;
        try {
            foreach ($dom->getElementsByTagNameNS(self::W_NS, $localName) as $note) {
                if (!$note instanceof \DOMElement) {
                    continue;
                }
                $id = $this->attr($note, self::W_NS, 'id');
                if ($id === '' || (int) $id < 1) {
                    continue;
                }
                $children = $this->bodyBlocks($note);
                if ($children !== []) {
                    $notes[$id] = $this->trimLeadingNoteWhitespace($children);
                }
            }
        } finally {
            $this->relationships = $previousRelationships;
        }

        return $notes;
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function trimLeadingNoteWhitespace(array $blocks): array
    {
        $done = false;
        foreach ($blocks as $index => $block) {
            $blocks[$index] = $this->trimLeadingWhitespaceNode($block, $done);
            if ($done) {
                break;
            }
        }

        return $blocks;
    }

    private function trimLeadingWhitespaceNode(AstNode $node, bool &$done): AstNode
    {
        if ($done) {
            return $node;
        }

        if ($node->type === 'text') {
            $text = (string) $node->attr('text', '');
            $trimmed = preg_replace('/^\s+/u', '', $text) ?? $text;
            if ($trimmed !== '') {
                $done = true;
            }
            if ($trimmed === $text) {
                return $node;
            }

            return new AstNode('text', ['text' => $trimmed]);
        }

        if ($node->children === []) {
            return $node;
        }

        $children = [];
        foreach ($node->children as $child) {
            $children[] = $this->trimLeadingWhitespaceNode($child, $done);
        }

        $attrs = $node->attrs;
        if (array_key_exists('text', $attrs)) {
            $attrs['text'] = trim(preg_replace('/\s+/', ' ', implode('', array_map(fn (AstNode $child): string => $this->nodeText($child), $children))) ?? '');
        }

        return new AstNode($node->type, $attrs, $children);
    }

    /**
     * @return array<string, array{author: string, date: string, children: list<AstNode>, inlines: list<AstNode>, text: string}>
     */
    private function comments(\DOMDocument $dom, array $relationships): array
    {
        $comments = [];

        $previousRelationships = $this->relationships;
        $this->relationships = $relationships;
        try {
            foreach ($dom->getElementsByTagNameNS(self::W_NS, 'comment') as $comment) {
                if (!$comment instanceof \DOMElement) {
                    continue;
                }
                $id = $this->attr($comment, self::W_NS, 'id');
                if ($id === '') {
                    continue;
                }
                $children = $this->bodyBlocks($comment);
                if ($children === []) {
                    continue;
                }
                if ($this->revisionMode === 'preserve' && $this->commentMayLoseFormatting($children)) {
                    $this->addWarning("Docx comment {$id} will not retain formatting");
                }
                $comments[$id] = [
                    'author' => $this->attr($comment, self::W_NS, 'author'),
                    'date' => $this->attr($comment, self::W_NS, 'date'),
                    'children' => $children,
                    'inlines' => $this->blocksAsInlineNodes($children),
                    'text' => trim(implode(' ', array_map(fn (AstNode $block): string => $this->nodeText($block), $children))),
                ];
            }
        } finally {
            $this->relationships = $previousRelationships;
        }

        return $comments;
    }

    /**
     * @param list<AstNode> $children
     */
    private function commentMayLoseFormatting(array $children): bool
    {
        return !$this->commentBlocksRetainInlineShape($children);
    }

    /**
     * @return array<string, true>
     */
    private function commentRangeIds(\DOMElement $body): array
    {
        $ids = [];
        foreach (['commentRangeStart', 'commentRangeEnd'] as $localName) {
            foreach ($this->descendantElementsByLocalName($body, $localName) as $range) {
                $id = $this->attr($range, self::W_NS, 'id');
                if ($id !== '') {
                    $ids[$id] = true;
                }
            }
        }

        return $ids;
    }

    /**
     * @return array<string, mixed>
     */
    private function coreProperties(\DOMDocument $dom): array
    {
        $map = [
            'title' => 'title',
            'creator' => 'author',
            'created' => 'date',
            'description' => 'description',
            'subject' => 'subject',
        ];
        $meta = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }
            $key = $map[$element->localName] ?? '';
            if ($key === '') {
                continue;
            }
            $text = trim(preg_replace('/\s+/', ' ', $element->textContent) ?? $element->textContent);
            if ($text === '') {
                continue;
            }
            $meta[$key] = $text;
            if ($key === 'title') {
                $meta['titleInlines'] = [new AstNode('text', ['text' => $text])];
            }
        }

        return $meta;
    }

    private function normalizeRevisionMode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        if (in_array($mode, ['preserve', 'accept', 'reject'], true)) {
            return $mode;
        }

        throw new \InvalidArgumentException("Unsupported DOCX revisionMode '{$mode}'. Expected preserve, accept, or reject.");
    }

    private function normalizeCommentsMode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        if (in_array($mode, ['preserve', 'all', 'all-changes'], true)) {
            return 'preserve';
        }
        if (in_array($mode, ['omit', 'accept', 'accept-changes', 'reject', 'reject-changes'], true)) {
            return 'omit';
        }

        throw new \InvalidArgumentException("Unsupported DOCX commentsMode '{$mode}'. Expected preserve/all, omit, accept, or reject.");
    }

    private function loadXml(string $xml, string $label): \DOMDocument
    {
        if (!class_exists(\DOMDocument::class)) {
            throw new \RuntimeException($label . ' needs DOMDocument, which is unavailable in this runtime.');
        }
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        foreach ($errors as $error) {
            if ($error->level === LIBXML_ERR_WARNING) {
                $this->addWarning($label . ': ' . $error->message);
            }
        }
        if (!$ok) {
            throw new \InvalidArgumentException($label . ' is not valid XML.');
        }

        return $dom;
    }

    private function addWarning(string $message): void
    {
        $normalized = preg_replace('/\s+/', ' ', trim($message));
        if (!is_string($normalized) || $normalized === '') {
            return;
        }
        if (strlen($normalized) > self::MAX_DOCX_WARNING_LENGTH) {
            $normalized = substr($normalized, 0, self::MAX_DOCX_WARNING_LENGTH - 3) . '...';
        }
        if (isset($this->warningKeys[$normalized])) {
            return;
        }
        $this->warningKeys[$normalized] = true;
        if (count($this->warnings) >= self::MAX_WARNING_COUNT) {
            ++$this->warningOverflowCount;
            return;
        }

        $this->warnings[] = $normalized;
    }

    private function firstElementByLocalName(\DOMDocument $dom, string $localName): ?\DOMElement
    {
        foreach ($dom->getElementsByTagName('*') as $element) {
            if ($element instanceof \DOMElement && $element->localName === $localName) {
                return $element;
            }
        }

        return null;
    }

    private function directChild(\DOMElement $element, string $localName): ?\DOMElement
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return list<\DOMElement>
     */
    private function descendantElementsByLocalName(\DOMElement $element, string $localName): array
    {
        $matches = [];
        foreach ($element->getElementsByTagName('*') as $descendant) {
            if ($descendant instanceof \DOMElement && $descendant->localName === $localName) {
                $matches[] = $descendant;
            }
        }

        return $matches;
    }

    private function nearestAncestorByLocalName(\DOMElement $element, string $localName): ?\DOMElement
    {
        $node = $element->parentNode;
        while ($node instanceof \DOMNode) {
            if ($node instanceof \DOMElement && $node->localName === $localName) {
                return $node;
            }
            $node = $node->parentNode;
        }

        return null;
    }

    private function attr(\DOMElement $element, string $namespace, string $name): string
    {
        $value = $element->getAttributeNS($namespace, $name);
        if ($value !== '') {
            return $value;
        }
        foreach ($element->attributes ?? [] as $attribute) {
            if ($attribute instanceof \DOMAttr && $attribute->localName === $name) {
                return $attribute->value;
            }
        }

        return '';
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function plainText(array $inlines): string
    {
        $text = '';
        foreach ($inlines as $inline) {
            $text .= $this->nodeText($inline);
        }

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private function nodeText(AstNode $node): string
    {
        if ($node->type === 'raw_inline' || $node->type === 'raw_block') {
            return '';
        }
        if (isset($node->attrs['text'])) {
            return (string) $node->attrs['text'];
        }
        if ($node->type === 'linebreak') {
            return ' ';
        }
        $text = '';
        foreach ($node->children as $child) {
            $text .= $this->nodeText($child);
        }

        return $text;
    }

    /**
     * @param array<mixed> $nodes
     */
    private function allAstNodes(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if (!$node instanceof AstNode) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function mergeAdjacentText(array $nodes): array
    {
        $merged = [];
        foreach ($nodes as $node) {
            if ($node->type === 'text') {
                $attrs = $node->attrs;
                $attrs['text'] = $this->normalizeInlineTextWhitespace((string) ($attrs['text'] ?? ''));
                $node = new AstNode('text', $attrs, $node->children);
            }

            $lastIndex = array_key_last($merged);
            $last = $lastIndex === null ? null : $merged[$lastIndex];
            if ($node->type === 'text' && $last instanceof AstNode && $last->type === 'text') {
                $merged[$lastIndex] = new AstNode('text', [
                    'text' => $this->normalizeInlineTextWhitespace((string) $last->attr('text', '') . (string) $node->attr('text', '')),
                ]);
                continue;
            }
            $merged[] = $node;
        }

        return $merged;
    }

    private function normalizeInlineTextWhitespace(string $text): string
    {
        return preg_replace('/[ \t\r\n]+/u', ' ', $text) ?? str_replace("\t", ' ', $text);
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function normalizeInlineSiblings(array $nodes): array
    {
        $normalized = [];
        foreach ($nodes as $node) {
            $children = $node->children === [] ? [] : $this->normalizeInlineSiblings($node->children);
            $node = new AstNode($node->type, $node->attrs, $children);

            $lastIndex = array_key_last($normalized);
            $last = $lastIndex === null ? null : $normalized[$lastIndex];
            if ($this->canMergeInlineSiblings($last, $node)) {
                /** @var AstNode $last */
                $normalized[$lastIndex] = new AstNode(
                    $last->type,
                    $last->attrs,
                    $this->normalizeInlineSiblings([...$last->children, ...$node->children])
                );
                continue;
            }

            $absorbed = $this->absorbSharedInlineWrapper($last, $node);
            if ($absorbed instanceof AstNode) {
                /** @var int $lastIndex */
                $normalized[$lastIndex] = $absorbed;
                continue;
            }

            $normalized[] = $node;
        }

        $normalized = $this->mergeInlineWrapperSandwiches($normalized);
        $normalized = $this->collapseAdjacentCommentEndSpans($normalized);
        $withBoundaryWhitespace = [];
        foreach ($normalized as $node) {
            array_push($withBoundaryWhitespace, ...$this->splitInlineBoundaryWhitespace($node));
        }

        return array_map(
            fn (AstNode $node): AstNode => $this->stripInternalInlineAttrs($node),
            $this->mergeAdjacentText($withBoundaryWhitespace)
        );
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function normalizeInlineWhitespace(array $nodes): array
    {
        $nodes = $this->externalizeFormattingBoundaryWhitespace($nodes);
        $nodes = $this->collapseInlineBoundaryWhitespace($nodes);
        $nodes = $this->removeLeadingInlineWhitespace($nodes);
        $nodes = $this->removeTrailingInlineWhitespace($nodes);

        return $this->pruneEmptyInlineNodes($nodes);
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function externalizeFormattingBoundaryWhitespace(array $nodes): array
    {
        $normalized = array_values($nodes);
        $count = count($normalized);
        for ($index = 0; $index < $count; ++$index) {
            $node = $normalized[$index] ?? null;
            if (!$node instanceof AstNode || !$this->isInlineFormattingContainer($node)) {
                continue;
            }

            if ($index > 0 && $this->inlineNodeStartsWithSpace($node)) {
                $previous = $normalized[$index - 1] ?? null;
                if ($previous instanceof AstNode && $this->inlineNodeContainsText($previous)) {
                    $node = $this->trimLeadingInlineNode($node);
                    if (!$node instanceof AstNode) {
                        unset($normalized[$index]);
                        continue;
                    }
                    $normalized[$index] = $node;
                    if (!$this->inlineNodeEndsWithSpace($previous)) {
                        $normalized[$index - 1] = $this->appendTrailingSpaceToInlineNode($previous);
                    }
                }
            }

            if ($index < $count - 1 && $this->inlineNodeEndsWithSpace($node)) {
                $next = $normalized[$index + 1] ?? null;
                if ($next instanceof AstNode && $this->inlineNodeContainsText($next)) {
                    $node = $this->trimTrailingInlineNode($node);
                    if (!$node instanceof AstNode) {
                        unset($normalized[$index]);
                        continue;
                    }
                    $normalized[$index] = $node;
                    if (!$this->inlineNodeStartsWithSpace($next)) {
                        $normalized[$index + 1] = $this->prependLeadingSpaceToInlineNode($next);
                    }
                }
            }
        }

        return array_values($normalized);
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function collapseInlineBoundaryWhitespace(array $nodes): array
    {
        $collapsed = [];
        foreach ($nodes as $node) {
            while ($collapsed !== [] && $this->inlineNodeEndsWithSpace($collapsed[array_key_last($collapsed)])) {
                $trimmed = $this->trimLeadingInlineNode($node);
                if ($trimmed instanceof AstNode) {
                    $node = $trimmed;
                    break;
                }
                continue 2;
            }

            $collapsed[] = $node;
        }

        return $collapsed;
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function removeLeadingInlineWhitespace(array $nodes): array
    {
        $trimmed = [];
        $count = count($nodes);
        for ($index = 0; $index < $count; ++$index) {
            $node = $this->trimLeadingInlineNode($nodes[$index]);
            if (!$node instanceof AstNode) {
                continue;
            }

            $trimmed[] = $node;
            for ($tail = $index + 1; $tail < $count; ++$tail) {
                $trimmed[] = $nodes[$tail];
            }

            return $trimmed;
        }

        return [];
    }

    private function trimLeadingInlineNode(AstNode $node): ?AstNode
    {
        if ($node->type === 'text') {
            $attrs = $node->attrs;
            $attrs['text'] = ltrim((string) ($attrs['text'] ?? ''), ' ');

            return $attrs['text'] === '' ? null : new AstNode('text', $attrs, $node->children);
        }

        if ($node->children === []) {
            return $node;
        }

        $children = $this->removeLeadingInlineWhitespace($node->children);
        if ($children === [] && $this->isPrunableInlineContainer($node)) {
            return null;
        }

        return new AstNode($node->type, $node->attrs, $children);
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function removeTrailingInlineWhitespace(array $nodes): array
    {
        $trimmed = [];
        for ($index = count($nodes) - 1; $index >= 0; --$index) {
            $node = $this->trimTrailingInlineNode($nodes[$index]);
            if (!$node instanceof AstNode) {
                continue;
            }

            $trimmed[] = $node;
            for ($head = $index - 1; $head >= 0; --$head) {
                $trimmed[] = $nodes[$head];
            }

            return array_reverse($trimmed);
        }

        return [];
    }

    private function trimTrailingInlineNode(AstNode $node): ?AstNode
    {
        if ($node->type === 'text') {
            $attrs = $node->attrs;
            $attrs['text'] = rtrim((string) ($attrs['text'] ?? ''), ' ');

            return $attrs['text'] === '' ? null : new AstNode('text', $attrs, $node->children);
        }

        if ($node->children === []) {
            return $node;
        }

        $children = $this->removeTrailingInlineWhitespace($node->children);
        if ($children === [] && $this->isPrunableInlineContainer($node)) {
            return null;
        }

        return new AstNode($node->type, $node->attrs, $children);
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function pruneEmptyInlineNodes(array $nodes): array
    {
        $pruned = [];
        foreach ($nodes as $node) {
            if ($node->type === 'text' && (string) $node->attr('text', '') === '') {
                continue;
            }

            if ($node->children !== []) {
                $children = $this->pruneEmptyInlineNodes($node->children);
                if ($children === [] && $this->isPrunableInlineContainer($node)) {
                    continue;
                }
                $node = new AstNode($node->type, $node->attrs, $children);
            }

            $pruned[] = $node;
        }

        return $pruned;
    }

    private function inlineNodeStartsWithSpace(AstNode $node): bool
    {
        if ($node->type === 'text') {
            return str_starts_with((string) $node->attr('text', ''), ' ');
        }

        foreach ($node->children as $child) {
            if ($this->inlineNodeStartsWithSpace($child)) {
                return true;
            }
            if ($this->inlineNodeContainsText($child)) {
                return false;
            }
        }

        return false;
    }

    private function inlineNodeEndsWithSpace(AstNode $node): bool
    {
        if ($node->type === 'text') {
            return str_ends_with((string) $node->attr('text', ''), ' ');
        }

        for ($index = count($node->children) - 1; $index >= 0; --$index) {
            $child = $node->children[$index];
            if ($this->inlineNodeEndsWithSpace($child)) {
                return true;
            }
            if ($this->inlineNodeContainsText($child)) {
                return false;
            }
        }

        return false;
    }

    private function inlineNodeContainsText(AstNode $node): bool
    {
        if ($node->type === 'text') {
            return true;
        }

        foreach ($node->children as $child) {
            if ($this->inlineNodeContainsText($child)) {
                return true;
            }
        }

        return false;
    }

    private function prependLeadingSpaceToInlineNode(AstNode $node): AstNode
    {
        if ($node->type === 'text') {
            $attrs = $node->attrs;
            $attrs['text'] = ' ' . (string) ($attrs['text'] ?? '');

            return new AstNode('text', $attrs, $node->children);
        }

        if ($node->children === []) {
            return $node;
        }

        $children = $node->children;
        foreach ($children as $index => $child) {
            if (!$this->inlineNodeContainsText($child)) {
                continue;
            }
            $children[$index] = $this->prependLeadingSpaceToInlineNode($child);
            break;
        }

        return new AstNode($node->type, $node->attrs, $children);
    }

    private function appendTrailingSpaceToInlineNode(AstNode $node): AstNode
    {
        if ($node->type === 'text') {
            $attrs = $node->attrs;
            $attrs['text'] = (string) ($attrs['text'] ?? '') . ' ';

            return new AstNode('text', $attrs, $node->children);
        }

        if ($node->children === []) {
            return $node;
        }

        $children = $node->children;
        for ($index = count($children) - 1; $index >= 0; --$index) {
            if (!$this->inlineNodeContainsText($children[$index])) {
                continue;
            }
            $children[$index] = $this->appendTrailingSpaceToInlineNode($children[$index]);
            break;
        }

        return new AstNode($node->type, $node->attrs, $children);
    }

    private function isInlineFormattingContainer(AstNode $node): bool
    {
        return in_array($node->type, [
            'emph',
            'strong',
            'underline',
            'strikeout',
            'small_caps',
            'superscript',
            'subscript',
        ], true);
    }

    private function isPrunableInlineContainer(AstNode $node): bool
    {
        return in_array($node->type, [
            'emph',
            'strong',
            'underline',
            'strikeout',
            'small_caps',
            'superscript',
            'subscript',
            'span',
            'link',
        ], true);
    }

    private function canMergeInlineSiblings(?AstNode $left, AstNode $right): bool
    {
        if (!$left instanceof AstNode || $left->type !== $right->type || $left->attrs !== $right->attrs) {
            return false;
        }

        return in_array($right->type, [
            'emph',
            'strong',
            'underline',
            'strikeout',
            'small_caps',
            'superscript',
            'subscript',
            'span',
            'link',
        ], true);
    }

    private function absorbSharedInlineWrapper(?AstNode $left, AstNode $right): ?AstNode
    {
        if (!$left instanceof AstNode || count($right->children) !== 1) {
            return null;
        }

        $inner = $right->children[0];
        if (
            $left->type !== $inner->type
            || $this->inlineComparableAttrs($left) !== $this->inlineComparableAttrs($inner)
        ) {
            return null;
        }
        if (!in_array($right->type, ['strong', 'emph', 'underline', 'strikeout', 'small_caps', 'superscript', 'subscript'], true)) {
            return null;
        }

        $children = $left->children;
        $replacement = new AstNode($right->type, $right->attrs, $inner->children);
        $lastIndex = array_key_last($children);
        $last = $lastIndex === null ? null : $children[$lastIndex];
        if ($this->canMergeInlineSiblings($last, $replacement)) {
            /** @var AstNode $last */
            $children[$lastIndex] = new AstNode(
                $last->type,
                $last->attrs,
                $this->mergeAdjacentText([...$last->children, ...$replacement->children])
            );
        } else {
            $children[] = $replacement;
        }

        return new AstNode($left->type, $left->attrs, $children);
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function mergeInlineWrapperSandwiches(array $nodes): array
    {
        $merged = [];
        $count = count($nodes);
        for ($index = 0; $index < $count; ++$index) {
            $left = $nodes[$index];
            $middle = $nodes[$index + 1] ?? null;
            $right = $nodes[$index + 2] ?? null;
            if (
                $middle instanceof AstNode
                && $right instanceof AstNode
                && $this->canMergeInlineSandwich($left, $middle, $right)
            ) {
                /** @var AstNode $inner */
                $inner = $middle->children[0];
                $merged[] = new AstNode($left->type, $left->attrs, [
                    ...$left->children,
                    new AstNode($middle->type, $middle->attrs, $inner->children),
                    ...$right->children,
                ]);
                $index += 2;
                continue;
            }

            $merged[] = $left;
        }

        return $merged;
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function collapseAdjacentCommentEndSpans(array $nodes): array
    {
        $collapsed = [];
        $count = count($nodes);
        for ($index = 0; $index < $count; ++$index) {
            $node = $nodes[$index];
            if (!$this->isEmptyCommentEndSpan($node)) {
                $collapsed[] = $node;
                continue;
            }

            $run = [$node];
            while (isset($nodes[$index + 1]) && $this->isEmptyCommentEndSpan($nodes[$index + 1])) {
                $run[] = $nodes[++$index];
            }

            if (count($run) === 1) {
                $collapsed[] = $node;
                continue;
            }

            $nested = array_pop($run);
            while ($run !== []) {
                $parent = array_pop($run);
                $nested = new AstNode($parent->type, $parent->attrs, [$nested]);
            }

            $collapsed[] = $nested;
        }

        return $collapsed;
    }

    private function isEmptyCommentEndSpan(AstNode $node): bool
    {
        if ($node->type !== 'span' || $node->children !== []) {
            return false;
        }

        $classes = $node->attr('classes', []);

        return is_array($classes) && $classes === ['comment-end'];
    }

    private function canMergeInlineSandwich(AstNode $left, AstNode $middle, AstNode $right): bool
    {
        if (!$this->canMergeInlineSiblings($left, $right)) {
            return false;
        }
        if (!in_array($middle->type, ['strong', 'emph', 'underline', 'strikeout', 'small_caps', 'superscript', 'subscript'], true)) {
            return false;
        }
        if (count($middle->children) !== 1) {
            return false;
        }

        $inner = $middle->children[0];

        return $inner->type === $left->type
            && $this->inlineComparableAttrs($inner) === $this->inlineComparableAttrs($left);
    }

    /**
     * @return array<string, mixed>
     */
    private function inlineComparableAttrs(AstNode $node): array
    {
        $attrs = $node->attrs;
        unset($attrs[self::INTERNAL_STYLE_ORIGIN_ATTR]);

        return $attrs;
    }

    /**
     * @return list<AstNode>
     */
    private function splitInlineBoundaryWhitespace(AstNode $node): array
    {
        if (
            !$this->canSplitInlineBoundaryWhitespace($node)
            || $this->inlineStyleOrigin($node) !== 'style'
            || $node->children === []
            || trim($this->nodeText($node)) === ''
        ) {
            return [$node];
        }

        $children = $node->children;
        $before = [];
        $after = [];

        $first = $children[0] ?? null;
        if ($first instanceof AstNode && $first->type === 'text') {
            [$leading, $remaining] = $this->splitLeadingWhitespace((string) $first->attr('text', ''));
            if ($leading !== '') {
                $before[] = new AstNode('text', ['text' => $leading]);
                if ($remaining === '') {
                    array_shift($children);
                } else {
                    $children[0] = new AstNode('text', ['text' => $remaining]);
                }
            }
        }

        $lastIndex = count($children) - 1;
        $last = $lastIndex >= 0 ? $children[$lastIndex] : null;
        if ($last instanceof AstNode && $last->type === 'text') {
            [$remaining, $trailing] = $this->splitTrailingWhitespace((string) $last->attr('text', ''));
            if ($trailing !== '') {
                $after[] = new AstNode('text', ['text' => $trailing]);
                if ($remaining === '') {
                    array_pop($children);
                } else {
                    $children[$lastIndex] = new AstNode('text', ['text' => $remaining]);
                }
            }
        }

        if ($node->type === 'emph') {
            $segments = [];
            $current = [];
            $count = count($children);
            foreach ($children as $index => $child) {
                $next = $children[$index + 1] ?? null;
                $previous = $index > 0 ? ($children[$index - 1] ?? null) : null;
                if ($child->type === 'text') {
                    $text = (string) $child->attr('text', '');
                    if ($previous instanceof AstNode && $previous->type !== 'text') {
                        [$leading, $remaining] = $this->splitLeadingWhitespace($text);
                        if ($leading !== '' && $remaining !== '') {
                            if ($current !== []) {
                                $segments[] = ['style' => $current];
                                $current = [];
                            }
                            $segments[] = ['text' => $leading];
                            $current[] = new AstNode('text', ['text' => $remaining]);
                            continue;
                        }
                    }
                    if ($next instanceof AstNode && $next->type !== 'text') {
                        [$remaining, $trailing] = $this->splitTrailingWhitespace($text);
                        if ($trailing !== '' && $remaining !== '') {
                            $current[] = new AstNode('text', ['text' => $remaining]);
                            $segments[] = ['style' => $current];
                            $segments[] = ['text' => $trailing];
                            $current = [];
                            continue;
                        }
                    }
                }

                $current[] = $child;
            }

            if ($current !== [] && $count > 0) {
                $segments[] = ['style' => $current];
            }

            if (count($segments) > 1) {
                foreach ($segments as $segment) {
                    if (isset($segment['text'])) {
                        $before[] = new AstNode('text', ['text' => (string) $segment['text']]);
                        continue;
                    }
                    $segmentChildren = $segment['style'] ?? [];
                    if (is_array($segmentChildren) && $segmentChildren !== []) {
                        $before[] = new AstNode($node->type, $node->attrs, array_values($segmentChildren));
                    }
                }

                return [...$before, ...$after];
            }
        }

        if ($children !== []) {
            $before[] = new AstNode($node->type, $node->attrs, array_values($children));
        }

        return [...$before, ...$after];
    }

    private function canSplitInlineBoundaryWhitespace(AstNode $node): bool
    {
        return in_array($node->type, [
            'emph',
            'strong',
            'underline',
            'strikeout',
            'small_caps',
            'superscript',
            'subscript',
        ], true);
    }

    private function inlineStyleOrigin(AstNode $node): string
    {
        $origin = $node->attr(self::INTERNAL_STYLE_ORIGIN_ATTR, '');

        return is_string($origin) ? $origin : '';
    }

    private function stripInternalInlineAttrs(AstNode $node): AstNode
    {
        $attrs = $node->attrs;
        unset($attrs[self::INTERNAL_STYLE_ORIGIN_ATTR]);

        $children = array_map(
            fn (AstNode $child): AstNode => $this->stripInternalInlineAttrs($child),
            $node->children
        );

        return new AstNode($node->type, $attrs, $children);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitLeadingWhitespace(string $text): array
    {
        if (preg_match('/^(\s+)(.*)$/us', $text, $match) !== 1) {
            return ['', $text];
        }

        return [(string) $match[1], (string) $match[2]];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitTrailingWhitespace(string $text): array
    {
        if (preg_match('/^(.*?)(\s+)$/us', $text, $match) !== 1) {
            return [$text, ''];
        }

        return [(string) $match[1], (string) $match[2]];
    }

    private function normalizeWordTarget(string $target): string
    {
        $target = str_replace('\\', '/', $target);
        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $target)) {
            return $target;
        }
        if (str_starts_with($target, '/') || str_starts_with($target, 'word/')) {
            return ltrim($target, '/');
        }

        return $this->normalizePackageTarget($this->documentPartName, $target);
    }

    private function normalizeImageTarget(string $target): string
    {
        $target = $this->normalizeWordTarget($target);
        if (str_starts_with($target, 'word/media/')) {
            return substr($target, strlen('word/'));
        }

        return $target;
    }

    private function emuCssDimension(string $value): string
    {
        if ($value === '' || !is_numeric($value) || (float) $value <= 0.0) {
            return '';
        }

        $inches = (float) $value / 914400.0;
        $formatted = json_encode($inches);
        if (!is_string($formatted)) {
            $formatted = rtrim(rtrim(sprintf('%.16G', $inches), '0'), '.');
        }

        return ($formatted === '' ? '0' : $formatted) . 'in';
    }

    private function cssStyleDimension(string $style, string $name): string
    {
        if ($style === '') {
            return '';
        }

        foreach (explode(';', $style) as $declaration) {
            [$property, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            if (strtolower(trim($property)) !== strtolower($name)) {
                continue;
            }
            $value = trim($value);
            if (preg_match('/^(?:\d+(?:\.\d+)?|\.\d+)(?:cm|mm|in|pt|pc|px|em|rem|%)$/i', $value) === 1) {
                return $value;
            }
        }

        return '';
    }

    private function xmlAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
