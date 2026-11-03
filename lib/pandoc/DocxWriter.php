<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DocxWriter
{
    private const EMU_PER_INCH = 914400;
    private const DEFAULT_IMAGE_DPI = 96.0;
    private const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const NS_CP = 'http://schemas.openxmlformats.org/package/2006/metadata/core-properties';
    private const NS_DC = 'http://purl.org/dc/elements/1.1/';
    private const NS_DCTERMS = 'http://purl.org/dc/terms/';
    private const NS_DCMITYPE = 'http://purl.org/dc/dcmitype/';
    private const NS_EP = 'http://schemas.openxmlformats.org/officeDocument/2006/extended-properties';
    private const NS_VT = 'http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes';
    private const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';
    private const NS_A = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const NS_PIC = 'http://schemas.openxmlformats.org/drawingml/2006/picture';
    private const NS_WP = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';
    private const REL_OFFICE_DOCUMENT = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument';
    private const REL_CORE_PROPERTIES = 'http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties';
    private const REL_EXTENDED_PROPERTIES = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties';
    private const REL_CUSTOM_PROPERTIES = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/custom-properties';
    private const REL_COMMENTS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments';
    private const REL_FOOTNOTES = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes';
    private const REL_THEME = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme';
    private const REL_FONT_TABLE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/fontTable';
    private const REL_WEB_SETTINGS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/webSettings';
    private const REL_STYLES = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles';
    private const REL_NUMBERING = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering';
    private const REL_SETTINGS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings';
    private const REL_HYPERLINK = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink';
    private const REL_IMAGE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';
    private const CT_CORE_PROPERTIES = 'application/vnd.openxmlformats-package.core-properties+xml';
    private const CT_EXTENDED_PROPERTIES = 'application/vnd.openxmlformats-officedocument.extended-properties+xml';
    private const CT_CUSTOM_PROPERTIES = 'application/vnd.openxmlformats-officedocument.custom-properties+xml';
    private const CT_COMMENTS = 'application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml';
    private const CT_FOOTNOTES = 'application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml';
    private const CT_FONT_TABLE = 'application/vnd.openxmlformats-officedocument.wordprocessingml.fontTable+xml';
    private const CT_THEME = 'application/vnd.openxmlformats-officedocument.theme+xml';
    private const CT_WEB_SETTINGS = 'application/vnd.openxmlformats-officedocument.wordprocessingml.webSettings+xml';
    private const CT_MAIN_DOCUMENT = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml';
    private const CT_STYLES = 'application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml';
    private const CT_NUMBERING = 'application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml';
    private const CT_SETTINGS = 'application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml';
    private const CT_RELATIONSHIPS = 'application/vnd.openxmlformats-package.relationships+xml';
    private const CT_OBFUSCATED_FONT = 'application/vnd.openxmlformats-officedocument.obfuscatedFont';
    private const CT_XML = 'application/xml';
    private const GENERATED_TIMESTAMP = '1980-01-01T00:00:00Z';
    private const GENERATED_DOS_TIME = 0;
    private const GENERATED_DOS_DATE = 33; // 1980-01-01 00:00:00, the earliest valid DOS ZIP date.
    private const CONTINUATION_NUM_ID = 1000;
    private const CONTINUATION_ABSTRACT_NUM_ID = 990;
    private const BULLET_ABSTRACT_NUM_ID = 991;
    private const TASK_UNCHECKED_ABSTRACT_NUM_ID = 992;
    private const TASK_CHECKED_ABSTRACT_NUM_ID = 993;
    private const TABLE_GRID_WIDTH_DXA = 7920;

    /** @var array<string, int> */
    private const CORE_PART_ORDER = [
        '[Content_Types].xml' => 0,
        '_rels/.rels' => 1,
        'docProps/core.xml' => 2,
        'docProps/app.xml' => 3,
        'docProps/custom.xml' => 4,
        'word/document.xml' => 5,
        'word/_rels/document.xml.rels' => 6,
        'word/_rels/footnotes.xml.rels' => 7,
        'word/comments.xml' => 8,
        'word/footnotes.xml' => 9,
        'word/fontTable.xml' => 10,
        'word/numbering.xml' => 11,
        'word/settings.xml' => 12,
        'word/styles.xml' => 13,
        'word/theme/theme1.xml' => 14,
        'word/webSettings.xml' => 15,
    ];

    /** @var list<OpcRelationship> */
    private array $documentRelationships = [];

    /** @var list<OpcRelationship> */
    private array $footnoteRelationships = [];

    /** @var array<string, string> */
    private array $hyperlinkRelationshipIds = [];

    /** @var list<array{name:string, data:string, contentType:string, source:string, relationshipId:string}> */
    private array $mediaParts = [];

    /** @var array<string, array{name:string, data:string, contentType:string, source:string, relationshipId:string}> */
    private array $mediaPartsBySource = [];

    /** @var list<array{id:int, blocks:list<AstNode>}> */
    private array $footnotes = [];

    /** @var list<array{id:string, author:string, date:string, children:list<AstNode>}> */
    private array $comments = [];

    /** @var array<string, true> */
    private array $commentIds = [];

    /** @var array<string, string> */
    private array $customParagraphStyles = [];

    /** @var array<string, string> */
    private array $customCharacterStyles = [];

    /** @var array<string, array{abstractNumId:int, style:string, delimiter:string, start:int}> */
    private array $orderedListDefinitions = [];

    /** @var list<array{numId:int, abstractNumId:int, start:int|null}> */
    private array $numberingInstances = [];

    /** @var list<int> */
    private array $numberingAbstractOrder = [];

    /** @var array<int, true> */
    private array $numberingAbstractsSeen = [];

    private ?ZipPackage $referencePackage = null;
    private bool $referencePackageLoaded = false;

    private int $nextDocumentRelationshipId = 9;
    private int $nextFootnoteId = 9;
    private int $nextBookmarkId = 9;
    private int $nextNumberingId = 1001;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function write(AstNode $document): string
    {
        return ZipPackage::build($this->packageParts($document));
    }

    /**
     * @return list<array{name:string, data:string, modifiedDosTime:int, modifiedDosDate:int}>
     */
    public function packageParts(AstNode $document): array
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('DOCX writer expects a document node');
        }

        $this->resetState();
        $this->seedBookmarkIds($document);
        $documentXml = $this->documentXml($document);
        $commentsXml = $this->commentsXml();
        $footnotesXml = $this->footnotesXml();

        $parts = [
            ['name' => '[Content_Types].xml', 'data' => $this->contentTypesXml()],
            ['name' => '_rels/.rels', 'data' => $this->rootRelationshipsXml()],
            ['name' => 'docProps/core.xml', 'data' => $this->corePropertiesXml($document)],
            ['name' => 'docProps/app.xml', 'data' => $this->referencePackagePart('docProps/app.xml') ?? $this->extendedPropertiesXml()],
            ['name' => 'docProps/custom.xml', 'data' => $this->customPropertiesXml($document)],
            ['name' => 'word/document.xml', 'data' => $documentXml],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $this->documentRelationshipsXml()],
            ['name' => 'word/_rels/footnotes.xml.rels', 'data' => $this->footnotesRelationshipsXml()],
            ['name' => 'word/comments.xml', 'data' => $commentsXml],
            ['name' => 'word/footnotes.xml', 'data' => $footnotesXml],
            ['name' => 'word/fontTable.xml', 'data' => $this->referencePackagePart('word/fontTable.xml') ?? $this->fontTableXml()],
            ['name' => 'word/styles.xml', 'data' => $this->stylesXml($document)],
            ['name' => 'word/numbering.xml', 'data' => $this->numberingXml()],
            ['name' => 'word/settings.xml', 'data' => $this->settingsXml()],
            ['name' => 'word/theme/theme1.xml', 'data' => $this->referencePackagePart('word/theme/theme1.xml') ?? $this->themeXml()],
            ['name' => 'word/webSettings.xml', 'data' => $this->referencePackagePart('word/webSettings.xml') ?? $this->webSettingsXml()],
        ];

        foreach ($this->mediaParts as $mediaPart) {
            $parts[] = ['name' => $mediaPart['name'], 'data' => $mediaPart['data']];
        }

        return $this->normalizePackageParts($parts);
    }

    public static function normalizePackagePartName(string $partName): string
    {
        $canonical = OpcPackagePath::canonicalPartNameFromUri($partName);
        if (strtolower($canonical) === '/[content_types].xml') {
            return '[Content_Types].xml';
        }

        return ltrim($canonical, '/');
    }

    /**
     * @param list<array<string, mixed>> $parts
     * @return list<array{name:string, data:string, modifiedDosTime:int, modifiedDosDate:int}>
     */
    private function normalizePackageParts(array $parts): array
    {
        $normalized = [];
        $seen = [];
        foreach ($parts as $index => $part) {
            if (!is_array($part) || !isset($part['name']) || !is_string($part['name'])) {
                throw new \InvalidArgumentException("DOCX package part {$index} is missing a string name");
            }
            if (!array_key_exists('data', $part) || !is_string($part['data'])) {
                throw new \InvalidArgumentException("DOCX package part {$part['name']} is missing string data");
            }

            $name = self::normalizePackagePartName($part['name']);
            $key = strtolower($name);
            if (isset($seen[$key])) {
                throw new \InvalidArgumentException('Duplicate DOCX package part after normalization: ' . $name);
            }
            $seen[$key] = true;

            $normalized[] = [
                'name' => $name,
                'data' => $part['data'],
                'modifiedDosTime' => self::GENERATED_DOS_TIME,
                'modifiedDosDate' => self::GENERATED_DOS_DATE,
            ];
        }

        usort($normalized, static function (array $left, array $right): int {
            $leftOrder = self::CORE_PART_ORDER[$left['name']] ?? 1000;
            $rightOrder = self::CORE_PART_ORDER[$right['name']] ?? 1000;
            if ($leftOrder !== $rightOrder) {
                return $leftOrder <=> $rightOrder;
            }

            return strcmp($left['name'], $right['name']);
        });

        return $normalized;
    }

    private function resetState(): void
    {
        $this->documentRelationships = [];
        $this->footnoteRelationships = [];
        $this->hyperlinkRelationshipIds = [];
        $this->mediaParts = [];
        $this->mediaPartsBySource = [];
        $this->footnotes = [];
        $this->comments = [];
        $this->commentIds = [];
        $this->customParagraphStyles = [];
        $this->customCharacterStyles = [];
        $this->orderedListDefinitions = [];
        $this->numberingInstances = [];
        $this->numberingAbstractOrder = [];
        $this->numberingAbstractsSeen = [];
        $this->nextDocumentRelationshipId = 9;
        $this->nextFootnoteId = 9;
        $this->nextBookmarkId = 9;
        $this->nextNumberingId = 1001;
    }

    private function referenceDocxPath(): ?string
    {
        foreach (['referenceDocxPath', 'referenceDocPath', 'referenceDoc'] as $key) {
            $value = $this->options[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function usesChapterTopLevelDivision(): bool
    {
        $value = $this->options['topLevelDivision'] ?? $this->options['writerTopLevelDivision'] ?? null;
        if (!is_string($value)) {
            return false;
        }

        return in_array(strtolower(str_replace(['_', '-'], '', $value)), [
            'chapter',
            'toplevelchapter',
        ], true);
    }

    private function referencePackage(): ?ZipPackage
    {
        if ($this->referencePackageLoaded) {
            return $this->referencePackage;
        }

        $this->referencePackageLoaded = true;
        $path = $this->referenceDocxPath();
        if ($path === null) {
            return null;
        }
        if (!is_file($path)) {
            throw new \RuntimeException("DOCX reference package does not exist: {$path}");
        }

        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new \RuntimeException("Unable to read DOCX reference package: {$path}");
        }

        $this->referencePackage = ZipPackage::fromString($bytes);

        return $this->referencePackage;
    }

    private function referencePackagePart(string $partName, int $maxUncompressedBytes = 2097152): ?string
    {
        $package = $this->referencePackage();
        if ($package === null) {
            return null;
        }

        $name = self::normalizePackagePartName($partName);
        if (!in_array($name, $package->names(), true)) {
            return null;
        }

        return $package->read($name, $maxUncompressedBytes);
    }

    private function seedBookmarkIds(AstNode $document): void
    {
        $nextId = $this->nextDocumentRelationshipId;
        $hyperlinkRelationshipKeys = [];
        $mediaRelationshipKeys = [];
        foreach ($document->children as $child) {
            if ($child instanceof AstNode) {
                $this->countDynamicIdsBeforeBookmarks($child, 'document', $nextId, $hyperlinkRelationshipKeys, $mediaRelationshipKeys);
            }
        }

        $this->nextBookmarkId = max($this->nextBookmarkId, $nextId);
    }

    /**
     * @param array<string, true> $hyperlinkRelationshipKeys
     * @param array<string, true> $mediaRelationshipKeys
     */
    private function countDynamicIdsBeforeBookmarks(
        AstNode $node,
        string $context,
        int &$nextId,
        array &$hyperlinkRelationshipKeys,
        array &$mediaRelationshipKeys
    ): void {
        if ($node->type === 'note') {
            ++$nextId;
            foreach ($node->children as $child) {
                if ($child instanceof AstNode) {
                    $this->countDynamicIdsBeforeBookmarks($child, 'footnote', $nextId, $hyperlinkRelationshipKeys, $mediaRelationshipKeys);
                }
            }

            return;
        }

        if ($node->type === 'link') {
            $target = (string) $node->attr('url', $node->attr('href', ''));
            if ($target !== '' && !str_starts_with($target, '#')) {
                $key = OpcRelationship::TARGET_MODE_EXTERNAL . "\0" . $target;
                if (!isset($hyperlinkRelationshipKeys[$key])) {
                    $hyperlinkRelationshipKeys[$key] = true;
                    ++$nextId;
                }
            }
        }

        if ($node->type === 'image') {
            if ($context === 'document') {
                $source = (string) $node->attr('url', $node->attr('src', ''));
                $media = $source === '' ? null : $this->resolveImageMedia($source);
                if ($media !== null) {
                    $key = $this->mediaSourceKey($source);
                    if (!isset($mediaRelationshipKeys[$key])) {
                        $mediaRelationshipKeys[$key] = true;
                        ++$nextId;
                    }
                    $nextId += 2;
                    if ((string) $node->attr('id', '') !== '') {
                        ++$nextId;
                    }
                }
            }

            return;
        }

        if ($node->type === 'span' && $this->hasClass($node, 'comment-start')) {
            foreach ($node->children as $child) {
                if ($child instanceof AstNode) {
                    $this->countDynamicIdsBeforeBookmarks($child, 'comment', $nextId, $hyperlinkRelationshipKeys, $mediaRelationshipKeys);
                }
            }

            return;
        }

        foreach ($node->children as $child) {
            if ($child instanceof AstNode) {
                $this->countDynamicIdsBeforeBookmarks($child, $context, $nextId, $hyperlinkRelationshipKeys, $mediaRelationshipKeys);
            }
        }
    }

    private function contentTypesXml(): string
    {
        $types = new OpcContentTypes();
        $types->addDefault('rels', self::CT_RELATIONSHIPS);
        $types->addDefault('xml', self::CT_XML);
        $types->addDefault('odttf', self::CT_OBFUSCATED_FONT);
        $types->addOverride('/docProps/core.xml', self::CT_CORE_PROPERTIES);
        $types->addOverride('/docProps/app.xml', self::CT_EXTENDED_PROPERTIES);
        $types->addOverride('/docProps/custom.xml', self::CT_CUSTOM_PROPERTIES);
        $types->addOverride('/word/document.xml', self::CT_MAIN_DOCUMENT);
        $types->addOverride('/word/comments.xml', self::CT_COMMENTS);
        $types->addOverride('/word/footnotes.xml', self::CT_FOOTNOTES);
        $types->addOverride('/word/fontTable.xml', self::CT_FONT_TABLE);
        $types->addOverride('/word/styles.xml', self::CT_STYLES);
        $types->addOverride('/word/numbering.xml', self::CT_NUMBERING);
        $types->addOverride('/word/settings.xml', self::CT_SETTINGS);
        $types->addOverride('/word/theme/theme1.xml', self::CT_THEME);
        $types->addOverride('/word/webSettings.xml', self::CT_WEB_SETTINGS);
        foreach ($this->mediaParts as $mediaPart) {
            $types->addOverride('/' . $mediaPart['name'], $mediaPart['contentType']);
        }

        return self::xmlDeclaration() . $types->toXml() . "\n";
    }

    private function rootRelationshipsXml(): string
    {
        $relationships = new OpcRelationships('/');
        $relationships->add(new OpcRelationship('rId1', self::REL_OFFICE_DOCUMENT, 'word/document.xml'));
        $relationships->add(new OpcRelationship('rId3', self::REL_CORE_PROPERTIES, 'docProps/core.xml'));
        $relationships->add(new OpcRelationship('rId4', self::REL_EXTENDED_PROPERTIES, 'docProps/app.xml'));
        $relationships->add(new OpcRelationship('rId5', self::REL_CUSTOM_PROPERTIES, 'docProps/custom.xml'));

        return self::xmlDeclaration() . $relationships->toXml() . "\n";
    }

    private function corePropertiesXml(AstNode $document): string
    {
        $meta = $this->documentMetadata($document);
        $title = $this->metadataText($this->documentMetadataValue($document, $meta, 'title', $this->options['title'] ?? ''));
        $creator = $this->metadataText($this->documentMetadataValue(
            $document,
            $meta,
            'creator',
            $this->documentMetadataValue($document, $meta, 'author', $this->options['creator'] ?? $this->options['author'] ?? ''),
            'author'
        ), '; ');
        $category = $this->metadataText($this->documentMetadataValue($document, $meta, 'category', $this->options['category'] ?? ''));
        $description = $this->metadataText($this->documentMetadataValue($document, $meta, 'description', $this->options['description'] ?? ''));
        $language = $this->metadataText($this->documentMetadataValue($document, $meta, 'language', $this->options['language'] ?? $this->options['lang'] ?? '', 'lang'));
        $subject = $this->metadataText($this->documentMetadataValue($document, $meta, 'subject', $this->options['subject'] ?? ''));
        $keywords = $this->metadataText($this->documentMetadataValue($document, $meta, 'keywords', $this->options['keywords'] ?? ''), ', ');
        $created = $this->metadataText($this->documentMetadataValue($document, $meta, 'created', $this->options['created'] ?? self::GENERATED_TIMESTAMP, 'date'));
        $modified = $this->metadataText($this->documentMetadataValue($document, $meta, 'modified', $this->options['modified'] ?? $created));

        $xml = self::xmlDeclaration()
            . '<cp:coreProperties xmlns:cp="' . self::NS_CP . '" xmlns:dc="' . self::NS_DC . '" xmlns:dcterms="' . self::NS_DCTERMS . '" xmlns:dcmitype="' . self::NS_DCMITYPE . '" xmlns:xsi="' . self::NS_XSI . '">'
            . '<dc:title>' . self::escText($title) . '</dc:title>'
            . '<dc:creator>' . self::escText($creator) . '</dc:creator>';
        if ($category !== '') {
            $xml .= '<cp:category>' . self::escText($category) . '</cp:category>';
        }
        if ($description !== '') {
            $xml .= '<dc:description>' . self::escText($description) . '</dc:description>';
        }
        if ($language !== '') {
            $xml .= '<dc:language>' . self::escText($language) . '</dc:language>';
        }
        if ($subject !== '') {
            $xml .= '<dc:subject>' . self::escText($subject) . '</dc:subject>';
        }

        return $xml
            . '<cp:keywords>' . self::escText($keywords) . '</cp:keywords>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . self::escText($created) . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . self::escText($modified) . '</dcterms:modified>'
            . '</cp:coreProperties>'
            . "\n";
    }

    private function extendedPropertiesXml(): string
    {
        $application = $this->metadataText($this->options['application'] ?? 'Microsoft Word 12.0.0');
        $appVersion = $this->metadataText($this->options['appVersion'] ?? '12.0000');

        return self::xmlDeclaration()
            . '<Properties xmlns="' . self::NS_EP . '" xmlns:vt="' . self::NS_VT . '">'
            . '<Words>83</Words>'
            . '<SharedDoc>false</SharedDoc>'
            . '<HyperlinksChanged>false</HyperlinksChanged>'
            . '<Lines>12</Lines>'
            . '<AppVersion>' . self::escText($appVersion) . '</AppVersion>'
            . '<LinksUpToDate>false</LinksUpToDate>'
            . '<Application>' . self::escText($application) . '</Application>'
            . '<CharactersWithSpaces>583</CharactersWithSpaces>'
            . '<Template>Normal.dotm</Template>'
            . '<DocSecurity>0</DocSecurity>'
            . '<TotalTime>6</TotalTime>'
            . '<ScaleCrop>false</ScaleCrop>'
            . '<Characters>475</Characters>'
            . '<Paragraphs>8</Paragraphs>'
            . '<Pages>1</Pages>'
            . '</Properties>'
            . "\n";
    }

    private function customPropertiesXml(AstNode $document): string
    {
        $properties = '';
        $pid = 2;
        foreach ($this->customDocumentProperties($document) as $name => $value) {
            $properties .= '<property fmtid="{D5CDD505-2E9C-101B-9397-08002B2CF9AE}" pid="' . $pid++ . '" name="' . self::escAttr($name) . '">'
                . ($value === '' ? '<vt:lpwstr/>' : '<vt:lpwstr>' . self::escText($value) . '</vt:lpwstr>')
                . '</property>';
        }

        return self::xmlDeclaration()
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/custom-properties" xmlns:vt="' . self::NS_VT . '">'
            . $properties
            . '</Properties>'
            . "\n";
    }

    /**
     * @return array<string, string>
     */
    private function customDocumentProperties(AstNode $document): array
    {
        $meta = $this->documentMetadata($document);
        if ($meta === []) {
            return [];
        }

        $coreKeys = array_fill_keys([
            'author',
            'authorInlines',
            'category',
            'created',
            'creator',
            'date',
            'dateInlines',
            'description',
            'keywords',
            'lang',
            'language',
            'modified',
            'subject',
            'title',
            'titleInlines',
        ], true);

        $properties = [];
        foreach ($meta as $key => $value) {
            if (!is_string($key) || isset($coreKeys[$key])) {
                continue;
            }

            $properties[$key] = $this->metadataCustomPropertyText($value);
        }

        return $properties;
    }

    /**
     * @return array<string, mixed>
     */
    private function documentMetadata(AstNode $document): array
    {
        $meta = $document->attr('meta', []);

        return is_array($meta) && !array_is_list($meta) ? $meta : [];
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function documentMetadataValue(AstNode $document, array $meta, string $key, mixed $default = '', ?string $metaKey = null): mixed
    {
        if (array_key_exists($key, $document->attrs)) {
            return $document->attrs[$key];
        }
        if (array_key_exists($key, $meta)) {
            return $meta[$key];
        }
        if ($metaKey !== null && array_key_exists($metaKey, $meta)) {
            return $meta[$metaKey];
        }

        return $default;
    }

    private function metadataText(mixed $value, string $separator = '; '): string
    {
        if (is_array($value)) {
            if (isset($value['type']) && array_key_exists('value', $value)) {
                return match ($value['type']) {
                    'MetaInlines' => is_array($value['value']) ? $this->metadataInlineText($value['value']) : '',
                    'MetaBlocks' => is_array($value['value']) ? $this->metadataBlockText($value['value']) : '',
                    'MetaList' => is_array($value['value']) ? $this->metadataListText($value['value'], $separator) : '',
                    default => '',
                };
            }

            $parts = [];
            foreach ($value as $item) {
                $part = $this->metadataText($item, $separator);
                if ($part !== '') {
                    $parts[] = $part;
                }
            }

            return implode($separator, $parts);
        }

        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return '';
    }

    private function metadataCustomPropertyText(mixed $value, string $separator = '; '): string
    {
        if (is_array($value)) {
            if (isset($value['type']) && array_key_exists('value', $value)) {
                return match ($value['type']) {
                    'MetaInlines' => is_array($value['value']) ? $this->metadataInlineText($value['value']) : '',
                    'MetaBlocks' => is_array($value['value']) ? $this->metadataBlockText($value['value']) : '',
                    'MetaList' => is_array($value['value']) ? $this->metadataCustomPropertyListText($value['value'], $separator) : '',
                    default => '',
                };
            }

            return '';
        }

        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return '';
    }

    /**
     * @param list<mixed> $items
     */
    private function metadataCustomPropertyListText(array $items, string $separator): string
    {
        $parts = [];
        foreach ($items as $item) {
            $part = $this->metadataCustomPropertyText($item, $separator);
            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return implode($separator, $parts);
    }

    /**
     * @param list<mixed> $items
     */
    private function metadataListText(array $items, string $separator): string
    {
        $parts = [];
        foreach ($items as $item) {
            $part = $this->metadataText($item, $separator);
            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return implode($separator, $parts);
    }

    /**
     * @param list<mixed> $blocks
     */
    private function metadataBlockText(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            if (!$block instanceof AstNode) {
                continue;
            }

            $part = $block->children === []
                ? $this->metadataText($block->attr('text', ''))
                : $this->metadataInlineText($block->children);
            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return implode("_x000d_\n", $parts);
    }

    /**
     * @param list<mixed> $nodes
     */
    private function metadataInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if (!$node instanceof AstNode) {
                continue;
            }

            $text .= match ($node->type) {
                'text', 'str', 'code' => (string) $node->attr('text', $node->attr('value', $node->attr('code', ''))),
                'space', 'softbreak', 'linebreak' => ' ',
                'raw_inline', 'raw_html', 'raw_html_inline', 'raw_tex', 'raw_tex_inline', 'raw_markdown' => '',
                'math' => (string) $node->attr('text', $node->attr('math', '')),
                default => $this->metadataInlineText($node->children),
            };
        }

        return trim(preg_replace('/[ \t\r\n]+/u', ' ', $text) ?? $text);
    }

    private function documentRelationshipsXml(): string
    {
        $relationships = new OpcRelationships('/word/document.xml');
        $relationships->add(new OpcRelationship('rId1', self::REL_COMMENTS, 'comments.xml'));
        $relationships->add(new OpcRelationship('rId2', self::REL_FOOTNOTES, 'footnotes.xml'));
        $relationships->add(new OpcRelationship('rId3', self::REL_THEME, 'theme/theme1.xml'));
        $relationships->add(new OpcRelationship('rId4', self::REL_FONT_TABLE, 'fontTable.xml'));
        $relationships->add(new OpcRelationship('rId5', self::REL_WEB_SETTINGS, 'webSettings.xml'));
        $relationships->add(new OpcRelationship('rId6', self::REL_SETTINGS, 'settings.xml'));
        $relationships->add(new OpcRelationship('rId7', self::REL_STYLES, 'styles.xml'));
        $relationships->add(new OpcRelationship('rId8', self::REL_NUMBERING, 'numbering.xml'));
        foreach ($this->documentRelationships as $relationship) {
            $relationships->add($relationship);
        }

        return self::xmlDeclaration() . $relationships->toXml() . "\n";
    }

    private function footnotesRelationshipsXml(): string
    {
        $relationships = new OpcRelationships('/word/footnotes.xml');
        foreach ($this->footnoteRelationships as $relationship) {
            $relationships->add($relationship);
        }

        return self::xmlDeclaration() . $relationships->toXml() . "\n";
    }

    private function commentsXml(): string
    {
        $comments = '';
        foreach ($this->comments as $comment) {
            $attrs = ' w:id="' . self::escAttr($comment['id']) . '"';
            if ($comment['author'] !== '') {
                $attrs .= ' w:author="' . self::escAttr($comment['author']) . '"';
            }
            if ($comment['date'] !== '') {
                $attrs .= ' w:date="' . self::escAttr($comment['date']) . '"';
            }

            $comments .= '<w:comment' . $attrs . '>'
                . $this->commentParagraphXml($comment['children'])
                . '</w:comment>';
        }

        return self::xmlDeclaration()
            . '<w:comments xmlns:w="' . self::NS_W . '" xmlns:r="' . self::NS_R . '">'
            . $comments
            . '</w:comments>'
            . "\n";
    }

    private function footnotesXml(): string
    {
        $footnotes = '';
        foreach ($this->footnotes as $footnote) {
            $footnotes .= '<w:footnote w:id="' . $footnote['id'] . '">'
                . $this->footnoteBlocksXml($footnote['blocks'])
                . '</w:footnote>';
        }

        return self::xmlDeclaration()
            . '<w:footnotes xmlns:w="' . self::NS_W . '" xmlns:r="' . self::NS_R . '">'
            . '<w:footnote w:type="continuationSeparator" w:id="0"><w:p><w:r><w:continuationSeparator/></w:r></w:p></w:footnote>'
            . '<w:footnote w:type="separator" w:id="-1"><w:p><w:r><w:separator/></w:r></w:p></w:footnote>'
            . $footnotes
            . '</w:footnotes>'
            . "\n";
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function commentParagraphXml(array $inlines): string
    {
        return $this->paragraphWrapperXml(
            $this->annotationReferenceRun() . $this->renderInlines($inlines, [], 'comment'),
            'CommentText',
            null
        );
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function footnoteBlocksXml(array $blocks): string
    {
        if ($blocks === []) {
            return $this->paragraphWrapperXml($this->footnoteReferenceMarkerRun(), 'FootnoteText', null);
        }

        $xml = '';
        $markerPending = true;
        foreach ($blocks as $block) {
            if (!$block instanceof AstNode) {
                continue;
            }

            $runs = $this->runsForFootnoteBlock($block);
            if ($markerPending) {
                $runs = $this->footnoteReferenceMarkerRun() . $this->textRun(' ', []) . $runs;
                $markerPending = false;
            }
            $xml .= $this->paragraphWrapperXml($runs, 'FootnoteText', null);
        }

        return $xml === '' ? $this->paragraphWrapperXml($this->footnoteReferenceMarkerRun(), 'FootnoteText', null) : $xml;
    }

    private function runsForFootnoteBlock(AstNode $block): string
    {
        if ($block->type === 'paragraph' || $block->type === 'plain') {
            return $block->children === []
                ? $this->textRun((string) $block->attr('text', ''), [])
                : $this->renderInlines($block->children, [], 'footnote');
        }

        if ($this->isInlineNode($block)) {
            return $this->renderInline($block, [], 'footnote');
        }

        $text = $this->plainText($block);

        return $text === '' ? '' : $this->textRun($text, []);
    }

    private function fontTableXml(): string
    {
        return self::xmlDeclaration()
            . '<w:fonts xmlns:r="' . self::NS_R . '" xmlns:w="' . self::NS_W . '">'
            . '<w:font w:name="Aptos"><w:panose1 w:val="020B0004020202020204"/><w:charset w:val="00"/><w:family w:val="swiss"/><w:pitch w:val="variable"/><w:sig w:usb0="20000287" w:usb1="00000003" w:usb2="00000000" w:usb3="00000000" w:csb0="0000019F" w:csb1="00000000"/></w:font>'
            . '<w:font w:name="Times New Roman"><w:panose1 w:val="02020603050405020304"/><w:charset w:val="00"/><w:family w:val="roman"/><w:pitch w:val="variable"/><w:sig w:usb0="E0002EFF" w:usb1="C000785B" w:usb2="00000009" w:usb3="00000000" w:csb0="000001FF" w:csb1="00000000"/></w:font>'
            . '<w:font w:name="Aptos Display"><w:panose1 w:val="020B0004020202020204"/><w:charset w:val="00"/><w:family w:val="swiss"/><w:pitch w:val="variable"/><w:sig w:usb0="20000287" w:usb1="00000003" w:usb2="00000000" w:usb3="00000000" w:csb0="0000019F" w:csb1="00000000"/></w:font>'
            . '<w:font w:name="Cambria Math"><w:panose1 w:val="02040503050406030204"/><w:charset w:val="00"/><w:family w:val="roman"/><w:pitch w:val="variable"/><w:sig w:usb0="E00002FF" w:usb1="420024FF" w:usb2="00000000" w:usb3="00000000" w:csb0="0000019F" w:csb1="00000000"/></w:font>'
            . '<w:font w:name="Courier New"><w:panose1 w:val="02070309020205020404"/><w:charset w:val="00"/><w:family w:val="modern"/><w:pitch w:val="fixed"/><w:sig w:usb0="E0002AFF" w:usb1="C0007843" w:usb2="00000009" w:usb3="00000000" w:csb0="000001FF" w:csb1="00000000"/></w:font>'
            . '<w:font w:name="Cambria"><w:panose1 w:val="02040503050406030204"/><w:charset w:val="00"/><w:family w:val="roman"/><w:pitch w:val="variable"/><w:sig w:usb0="E00002FF" w:usb1="400004FF" w:usb2="00000000" w:usb3="00000000" w:csb0="0000019F" w:csb1="00000000"/></w:font>'
            . '<w:font w:name="Calibri"><w:panose1 w:val="020F0502020204030204"/><w:charset w:val="00"/><w:family w:val="swiss"/><w:pitch w:val="variable"/><w:sig w:usb0="E0002AFF" w:usb1="C000247B" w:usb2="00000009" w:usb3="00000000" w:csb0="000001FF" w:csb1="00000000"/></w:font>'
            . '</w:fonts>'
            . "\n";
    }

    private function themeXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Office Theme">
  <a:themeElements>
    <a:clrScheme name="Office">
      <a:dk1>
        <a:sysClr val="windowText" lastClr="000000"/>
      </a:dk1>
      <a:lt1>
        <a:sysClr val="window" lastClr="FFFFFF"/>
      </a:lt1>
      <a:dk2>
        <a:srgbClr val="0E2841"/>
      </a:dk2>
      <a:lt2>
        <a:srgbClr val="E8E8E8"/>
      </a:lt2>
      <a:accent1>
        <a:srgbClr val="156082"/>
      </a:accent1>
      <a:accent2>
        <a:srgbClr val="E97132"/>
      </a:accent2>
      <a:accent3>
        <a:srgbClr val="196B24"/>
      </a:accent3>
      <a:accent4>
        <a:srgbClr val="0F9ED5"/>
      </a:accent4>
      <a:accent5>
        <a:srgbClr val="A02B93"/>
      </a:accent5>
      <a:accent6>
        <a:srgbClr val="4EA72E"/>
      </a:accent6>
      <a:hlink>
        <a:srgbClr val="467886"/>
      </a:hlink>
      <a:folHlink>
        <a:srgbClr val="96607D"/>
      </a:folHlink>
    </a:clrScheme>
    <a:fontScheme name="Office">
      <a:majorFont>
        <a:latin typeface="Aptos Display" panose="02110004020202020204"/>
        <a:ea typeface=""/>
        <a:cs typeface=""/>
        <a:font script="Jpan" typeface="&#x6E38;&#x30B4;&#x30B7;&#x30C3;&#x30AF; Light"/>
        <a:font script="Hang" typeface="&#xB9D1;&#xC740; &#xACE0;&#xB515;"/>
        <a:font script="Hans" typeface="&#x7B49;&#x7EBF; Light"/>
        <a:font script="Hant" typeface="&#x65B0;&#x7D30;&#x660E;&#x9AD4;"/>
        <a:font script="Arab" typeface="Times New Roman"/>
        <a:font script="Hebr" typeface="Times New Roman"/>
        <a:font script="Thai" typeface="Angsana New"/>
        <a:font script="Ethi" typeface="Nyala"/>
        <a:font script="Beng" typeface="Vrinda"/>
        <a:font script="Gujr" typeface="Shruti"/>
        <a:font script="Khmr" typeface="MoolBoran"/>
        <a:font script="Knda" typeface="Tunga"/>
        <a:font script="Guru" typeface="Raavi"/>
        <a:font script="Cans" typeface="Euphemia"/>
        <a:font script="Cher" typeface="Plantagenet Cherokee"/>
        <a:font script="Yiii" typeface="Microsoft Yi Baiti"/>
        <a:font script="Tibt" typeface="Microsoft Himalaya"/>
        <a:font script="Thaa" typeface="MV Boli"/>
        <a:font script="Deva" typeface="Mangal"/>
        <a:font script="Telu" typeface="Gautami"/>
        <a:font script="Taml" typeface="Latha"/>
        <a:font script="Syrc" typeface="Estrangelo Edessa"/>
        <a:font script="Orya" typeface="Kalinga"/>
        <a:font script="Mlym" typeface="Kartika"/>
        <a:font script="Laoo" typeface="DokChampa"/>
        <a:font script="Sinh" typeface="Iskoola Pota"/>
        <a:font script="Mong" typeface="Mongolian Baiti"/>
        <a:font script="Viet" typeface="Times New Roman"/>
        <a:font script="Uigh" typeface="Microsoft Uighur"/>
        <a:font script="Geor" typeface="Sylfaen"/>
        <a:font script="Armn" typeface="Arial"/>
        <a:font script="Bugi" typeface="Leelawadee UI"/>
        <a:font script="Bopo" typeface="Microsoft JhengHei"/>
        <a:font script="Java" typeface="Javanese Text"/>
        <a:font script="Lisu" typeface="Segoe UI"/>
        <a:font script="Mymr" typeface="Myanmar Text"/>
        <a:font script="Nkoo" typeface="Ebrima"/>
        <a:font script="Olck" typeface="Nirmala UI"/>
        <a:font script="Osma" typeface="Ebrima"/>
        <a:font script="Phag" typeface="Phagspa"/>
        <a:font script="Syrn" typeface="Estrangelo Edessa"/>
        <a:font script="Syrj" typeface="Estrangelo Edessa"/>
        <a:font script="Syre" typeface="Estrangelo Edessa"/>
        <a:font script="Sora" typeface="Nirmala UI"/>
        <a:font script="Tale" typeface="Microsoft Tai Le"/>
        <a:font script="Talu" typeface="Microsoft New Tai Lue"/>
        <a:font script="Tfng" typeface="Ebrima"/>
      </a:majorFont>
      <a:minorFont>
        <a:latin typeface="Aptos" panose="02110004020202020204"/>
        <a:ea typeface=""/>
        <a:cs typeface=""/>
        <a:font script="Jpan" typeface="&#x6E38;&#x660E;&#x671D;"/>
        <a:font script="Hang" typeface="&#xB9D1;&#xC740; &#xACE0;&#xB515;"/>
        <a:font script="Hans" typeface="&#x7B49;&#x7EBF;"/>
        <a:font script="Hant" typeface="&#x65B0;&#x7D30;&#x660E;&#x9AD4;"/>
        <a:font script="Arab" typeface="Arial"/>
        <a:font script="Hebr" typeface="Arial"/>
        <a:font script="Thai" typeface="Cordia New"/>
        <a:font script="Ethi" typeface="Nyala"/>
        <a:font script="Beng" typeface="Vrinda"/>
        <a:font script="Gujr" typeface="Shruti"/>
        <a:font script="Khmr" typeface="DaunPenh"/>
        <a:font script="Knda" typeface="Tunga"/>
        <a:font script="Guru" typeface="Raavi"/>
        <a:font script="Cans" typeface="Euphemia"/>
        <a:font script="Cher" typeface="Plantagenet Cherokee"/>
        <a:font script="Yiii" typeface="Microsoft Yi Baiti"/>
        <a:font script="Tibt" typeface="Microsoft Himalaya"/>
        <a:font script="Thaa" typeface="MV Boli"/>
        <a:font script="Deva" typeface="Mangal"/>
        <a:font script="Telu" typeface="Gautami"/>
        <a:font script="Taml" typeface="Latha"/>
        <a:font script="Syrc" typeface="Estrangelo Edessa"/>
        <a:font script="Orya" typeface="Kalinga"/>
        <a:font script="Mlym" typeface="Kartika"/>
        <a:font script="Laoo" typeface="DokChampa"/>
        <a:font script="Sinh" typeface="Iskoola Pota"/>
        <a:font script="Mong" typeface="Mongolian Baiti"/>
        <a:font script="Viet" typeface="Arial"/>
        <a:font script="Uigh" typeface="Microsoft Uighur"/>
        <a:font script="Geor" typeface="Sylfaen"/>
        <a:font script="Armn" typeface="Arial"/>
        <a:font script="Bugi" typeface="Leelawadee UI"/>
        <a:font script="Bopo" typeface="Microsoft JhengHei"/>
        <a:font script="Java" typeface="Javanese Text"/>
        <a:font script="Lisu" typeface="Segoe UI"/>
        <a:font script="Mymr" typeface="Myanmar Text"/>
        <a:font script="Nkoo" typeface="Ebrima"/>
        <a:font script="Olck" typeface="Nirmala UI"/>
        <a:font script="Osma" typeface="Ebrima"/>
        <a:font script="Phag" typeface="Phagspa"/>
        <a:font script="Syrn" typeface="Estrangelo Edessa"/>
        <a:font script="Syrj" typeface="Estrangelo Edessa"/>
        <a:font script="Syre" typeface="Estrangelo Edessa"/>
        <a:font script="Sora" typeface="Nirmala UI"/>
        <a:font script="Tale" typeface="Microsoft Tai Le"/>
        <a:font script="Talu" typeface="Microsoft New Tai Lue"/>
        <a:font script="Tfng" typeface="Ebrima"/>
      </a:minorFont>
    </a:fontScheme>
    <a:fmtScheme name="Office">
      <a:fillStyleLst>
        <a:solidFill>
          <a:schemeClr val="phClr"/>
        </a:solidFill>
        <a:gradFill rotWithShape="1">
          <a:gsLst>
            <a:gs pos="0">
              <a:schemeClr val="phClr">
                <a:lumMod val="110000"/>
                <a:satMod val="105000"/>
                <a:tint val="67000"/>
              </a:schemeClr>
            </a:gs>
            <a:gs pos="50000">
              <a:schemeClr val="phClr">
                <a:lumMod val="105000"/>
                <a:satMod val="103000"/>
                <a:tint val="73000"/>
              </a:schemeClr>
            </a:gs>
            <a:gs pos="100000">
              <a:schemeClr val="phClr">
                <a:lumMod val="105000"/>
                <a:satMod val="109000"/>
                <a:tint val="81000"/>
              </a:schemeClr>
            </a:gs>
          </a:gsLst>
          <a:lin ang="5400000" scaled="0"/>
        </a:gradFill>
        <a:gradFill rotWithShape="1">
          <a:gsLst>
            <a:gs pos="0">
              <a:schemeClr val="phClr">
                <a:satMod val="103000"/>
                <a:lumMod val="102000"/>
                <a:tint val="94000"/>
              </a:schemeClr>
            </a:gs>
            <a:gs pos="50000">
              <a:schemeClr val="phClr">
                <a:satMod val="110000"/>
                <a:lumMod val="100000"/>
                <a:shade val="100000"/>
              </a:schemeClr>
            </a:gs>
            <a:gs pos="100000">
              <a:schemeClr val="phClr">
                <a:lumMod val="99000"/>
                <a:satMod val="120000"/>
                <a:shade val="78000"/>
              </a:schemeClr>
            </a:gs>
          </a:gsLst>
          <a:lin ang="5400000" scaled="0"/>
        </a:gradFill>
      </a:fillStyleLst>
      <a:lnStyleLst>
        <a:ln w="6350" cap="flat" cmpd="sng" algn="ctr">
          <a:solidFill>
            <a:schemeClr val="phClr"/>
          </a:solidFill>
          <a:prstDash val="solid"/>
          <a:miter lim="800000"/>
        </a:ln>
        <a:ln w="12700" cap="flat" cmpd="sng" algn="ctr">
          <a:solidFill>
            <a:schemeClr val="phClr"/>
          </a:solidFill>
          <a:prstDash val="solid"/>
          <a:miter lim="800000"/>
        </a:ln>
        <a:ln w="19050" cap="flat" cmpd="sng" algn="ctr">
          <a:solidFill>
            <a:schemeClr val="phClr"/>
          </a:solidFill>
          <a:prstDash val="solid"/>
          <a:miter lim="800000"/>
        </a:ln>
      </a:lnStyleLst>
      <a:effectStyleLst>
        <a:effectStyle>
          <a:effectLst/>
        </a:effectStyle>
        <a:effectStyle>
          <a:effectLst/>
        </a:effectStyle>
        <a:effectStyle>
          <a:effectLst>
            <a:outerShdw blurRad="57150" dist="19050" dir="5400000" algn="ctr" rotWithShape="0">
              <a:srgbClr val="000000">
                <a:alpha val="63000"/>
              </a:srgbClr>
            </a:outerShdw>
          </a:effectLst>
        </a:effectStyle>
      </a:effectStyleLst>
      <a:bgFillStyleLst>
        <a:solidFill>
          <a:schemeClr val="phClr"/>
        </a:solidFill>
        <a:solidFill>
          <a:schemeClr val="phClr">
            <a:tint val="95000"/>
            <a:satMod val="170000"/>
          </a:schemeClr>
        </a:solidFill>
        <a:gradFill rotWithShape="1">
          <a:gsLst>
            <a:gs pos="0">
              <a:schemeClr val="phClr">
                <a:tint val="93000"/>
                <a:satMod val="150000"/>
                <a:shade val="98000"/>
                <a:lumMod val="102000"/>
              </a:schemeClr>
            </a:gs>
            <a:gs pos="50000">
              <a:schemeClr val="phClr">
                <a:tint val="98000"/>
                <a:satMod val="130000"/>
                <a:shade val="90000"/>
                <a:lumMod val="103000"/>
              </a:schemeClr>
            </a:gs>
            <a:gs pos="100000">
              <a:schemeClr val="phClr">
                <a:shade val="63000"/>
                <a:satMod val="120000"/>
              </a:schemeClr>
            </a:gs>
          </a:gsLst>
          <a:lin ang="5400000" scaled="0"/>
        </a:gradFill>
      </a:bgFillStyleLst>
    </a:fmtScheme>
  </a:themeElements>
  <a:objectDefaults/>
  <a:extraClrSchemeLst/>
  <a:extLst>
    <a:ext uri="{05A4C25C-085E-4340-85A3-A5531E510DB2}">
      <thm15:themeFamily xmlns:thm15="http://schemas.microsoft.com/office/thememl/2012/main" name="Office Theme" id="{2E142A2C-CD16-42D6-873A-C26D2A0506FA}" vid="{1BDDFF52-6CD6-40A5-AB3C-68EB2F1E4D0A}"/>
    </a:ext>
  </a:extLst>
</a:theme>
XML;
    }

    private function webSettingsXml(): string
    {
        return self::xmlDeclaration()
            . '<w:webSettings xmlns:w="' . self::NS_W . '"><w:allowPNG/><w:doNotSaveAsSingleFile/></w:webSettings>'
            . "\n";
    }

    private static function xmlDeclaration(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    }

    private function documentXml(AstNode $document): string
    {
        $blocks = $this->metadataFrontMatterParagraphsXml($document);
        $previousTopLevelTable = false;
        $bodyParagraphStyle = 'FirstParagraph';
        $openHeadingBookmarks = [];
        $chapterTopLevelDivision = $this->usesChapterTopLevelDivision();
        $seenTopLevelChapter = false;
        foreach ($document->children as $child) {
            if (!$child instanceof AstNode) {
                continue;
            }
            if ($previousTopLevelTable && $child->type === 'table') {
                $blocks[] = '<w:p/>';
            }

            if ($child->type === 'heading') {
                $level = max(1, min(9, (int) $child->attr('level', 1)));
                while ($openHeadingBookmarks !== [] && $openHeadingBookmarks[count($openHeadingBookmarks) - 1]['level'] >= $level) {
                    $this->closePendingHeadingBookmark($blocks, $openHeadingBookmarks);
                }

                if ($chapterTopLevelDivision && $level === 1) {
                    if ($seenTopLevelChapter) {
                        $blocks[] = $this->sectionBreakParagraphXml();
                    }
                    $seenTopLevelChapter = true;
                }

                $bookmarkName = $this->headingBookmarkName($child);
                if ($bookmarkName !== null) {
                    $startIndex = count($blocks);
                    $blocks[] = '';
                }
                $blocks[] = $this->headingParagraphXml($child);
                if ($bookmarkName !== null) {
                    $openHeadingBookmarks[] = ['level' => $level, 'name' => $bookmarkName, 'startIndex' => $startIndex];
                }
                $bodyParagraphStyle = 'FirstParagraph';
                $previousTopLevelTable = false;
                continue;
            }

            if ($child->type === 'paragraph' || $child->type === 'plain') {
                $blocks[] = $this->paragraphXml($child, $bodyParagraphStyle, null);
                $bodyParagraphStyle = 'BodyText';
                $previousTopLevelTable = false;
                continue;
            }

            foreach ($this->renderBlock($child, 0, null) as $blockXml) {
                if ($blockXml !== '') {
                    $blocks[] = $blockXml;
                }
            }
            if (in_array($child->type, ['blockquote', 'code_block', 'definition_list'], true)) {
                $bodyParagraphStyle = 'FirstParagraph';
            }
            $previousTopLevelTable = $child->type === 'table';
        }
        while ($openHeadingBookmarks !== []) {
            $this->closePendingHeadingBookmark($blocks, $openHeadingBookmarks);
        }
        if ($blocks === []) {
            $blocks[] = '<w:p/>';
        }

        $namespaces = 'xmlns:w="' . self::NS_W . '" xmlns:r="' . self::NS_R . '"';
        if ($this->mediaParts !== []) {
            $namespaces .= ' xmlns:a="' . self::NS_A . '" xmlns:pic="' . self::NS_PIC . '" xmlns:wp="' . self::NS_WP . '"';
        }

        return self::xmlDeclaration()
            . '<w:document ' . $namespaces . '>'
            . '<w:body>'
            . implode('', $blocks)
            . $this->sectionPropertiesXml()
            . '</w:body></w:document>'
            . "\n";
    }

    private function sectionBreakParagraphXml(): string
    {
        return '<w:p><w:pPr>' . $this->sectionPropertiesXml() . '</w:pPr></w:p>';
    }

    /**
     * @return list<string>
     */
    private function metadataFrontMatterParagraphsXml(AstNode $document): array
    {
        $meta = $this->documentMetadata($document);
        if ($meta === []) {
            return [];
        }

        $paragraphs = [];
        foreach ([
            'title' => 'Title',
            'subtitle' => 'Subtitle',
            'author' => 'Author',
        ] as $key => $styleId) {
            if (!array_key_exists($key, $meta)) {
                continue;
            }

            $text = $this->metadataText($meta[$key]);
            if ($text !== '') {
                $paragraphs[] = $this->textParagraphXml($text, $styleId, []);
            }
        }

        if (array_key_exists('abstract', $meta)) {
            $abstractParagraphs = $this->metadataBlockParagraphsXml($meta['abstract'], 'Abstract');
            if ($abstractParagraphs !== []) {
                $paragraphs[] = $this->textParagraphXml('Abstract', 'AbstractTitle', []);
                array_push($paragraphs, ...$abstractParagraphs);
            }
        }

        return $paragraphs;
    }

    /**
     * @return list<string>
     */
    private function metadataBlockParagraphsXml(mixed $value, string $styleId): array
    {
        if (is_array($value) && isset($value['type']) && ($value['type'] === 'MetaBlocks') && is_array($value['value'])) {
            $paragraphs = [];
            foreach ($value['value'] as $block) {
                if (!$block instanceof AstNode) {
                    continue;
                }

                if ($block->children !== []) {
                    $paragraphs[] = $this->paragraphWrapperXml($this->renderInlines($block->children, []), $styleId, null);
                    continue;
                }

                $text = $this->metadataText($block->attr('text', ''));
                if ($text !== '') {
                    $paragraphs[] = $this->textParagraphXml($text, $styleId, []);
                }
            }

            return $paragraphs;
        }

        $text = $this->metadataText($value);

        return $text === '' ? [] : [$this->textParagraphXml($text, $styleId, [])];
    }

    /**
     * @return list<string>
     */
    private function renderBlock(AstNode $node, int $listLevel, ?string $paragraphStyle): array
    {
        return match ($node->type) {
            'paragraph', 'plain' => [$this->paragraphXml($node, $paragraphStyle, null)],
            'heading' => $this->headingBlockXml($node),
            'bullet_list' => $this->listXml($node, false, $listLevel, $paragraphStyle),
            'ordered_list' => $this->listXml($node, true, $listLevel, $paragraphStyle),
            'blockquote' => $this->blockCollectionXml($node->children, $listLevel, 'BlockText'),
            'div' => $this->divXml($node, $listLevel, $paragraphStyle),
            'code_block' => [$this->codeBlockXml($node)],
            'definition_list' => $this->definitionListXml($node, $listLevel),
            'line_block' => $this->lineBlockXml($node),
            'horizontal_rule' => [$this->horizontalRuleXml()],
            'table' => $this->tableXml($node),
            'raw_block', 'raw_html', 'raw_tex' => [$this->rawBlockXml($node, $paragraphStyle)],
            default => $this->fallbackBlockXml($node, $listLevel, $paragraphStyle),
        };
    }

    /**
     * @return list<string>
     */
    private function headingBlockXml(AstNode $node): array
    {
        $xml = $this->headingParagraphXml($node);
        $bookmark = $this->headingBookmark($node);
        if ($bookmark === null) {
            return [$xml];
        }

        return [
            '<w:bookmarkStart w:id="' . $bookmark['id'] . '" w:name="' . self::escAttr($bookmark['name']) . '"/>',
            $xml,
            '<w:bookmarkEnd w:id="' . $bookmark['id'] . '"/>',
        ];
    }

    private function headingParagraphXml(AstNode $node): string
    {
        return $this->paragraphXml($node, 'Heading' . max(1, min(6, (int) $node->attr('level', 1))), null);
    }

    /**
     * @param list<string> $blocks
     * @param list<array{level:int, name:string, startIndex:int}> $openHeadingBookmarks
     */
    private function closePendingHeadingBookmark(array &$blocks, array &$openHeadingBookmarks): void
    {
        $bookmark = array_pop($openHeadingBookmarks);
        if ($bookmark === null) {
            return;
        }

        $bookmarkId = $this->nextBookmarkId++;
        $blocks[$bookmark['startIndex']] = '<w:bookmarkStart w:id="' . $bookmarkId . '" w:name="' . self::escAttr($bookmark['name']) . '"/>';
        $blocks[] = '<w:bookmarkEnd w:id="' . $bookmarkId . '"/>';
    }

    private function headingBookmarkName(AstNode $node): ?string
    {
        $id = (string) $node->attr('id', '');
        if ($id === '') {
            return null;
        }

        return $this->bookmarkName($id);
    }

    /**
     * @return array{id:int, name:string}|null
     */
    private function headingBookmark(AstNode $node): ?array
    {
        $name = $this->headingBookmarkName($node);
        if ($name === null) {
            return null;
        }

        return [
            'id' => $this->nextBookmarkId++,
            'name' => $name,
        ];
    }

    /**
     * @param list<AstNode> $children
     * @return list<string>
     */
    private function blockCollectionXml(array $children, int $listLevel, ?string $paragraphStyle): array
    {
        $blocks = [];
        $inlineBuffer = [];
        $openHeadingBookmarks = [];
        foreach ($children as $child) {
            if (!$child instanceof AstNode) {
                continue;
            }
            if ($this->isInlineNode($child)) {
                $inlineBuffer[] = $child;
                continue;
            }
            if ($inlineBuffer !== []) {
                $blocks[] = $this->paragraphFromInlinesXml($inlineBuffer, $paragraphStyle, null);
                $inlineBuffer = [];
            }
            if ($child->type === 'heading') {
                $level = max(1, min(9, (int) $child->attr('level', 1)));
                while ($openHeadingBookmarks !== [] && $openHeadingBookmarks[count($openHeadingBookmarks) - 1]['level'] >= $level) {
                    $bookmark = array_pop($openHeadingBookmarks);
                    $blocks[] = '<w:bookmarkEnd w:id="' . $bookmark['id'] . '"/>';
                }

                $bookmark = $this->headingBookmark($child);
                if ($bookmark !== null) {
                    $blocks[] = '<w:bookmarkStart w:id="' . $bookmark['id'] . '" w:name="' . self::escAttr($bookmark['name']) . '"/>';
                }
                $blocks[] = $this->headingParagraphXml($child);
                if ($bookmark !== null) {
                    $openHeadingBookmarks[] = ['id' => $bookmark['id'], 'level' => $level];
                }
                continue;
            }
            foreach ($this->renderBlock($child, $listLevel, $paragraphStyle) as $blockXml) {
                $blocks[] = $blockXml;
            }
        }
        if ($inlineBuffer !== []) {
            $blocks[] = $this->paragraphFromInlinesXml($inlineBuffer, $paragraphStyle, null);
        }
        while ($openHeadingBookmarks !== []) {
            $bookmark = array_pop($openHeadingBookmarks);
            $blocks[] = '<w:bookmarkEnd w:id="' . $bookmark['id'] . '"/>';
        }

        return $blocks;
    }

    private function codeBlockXml(AstNode $node): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", (string) $node->attr('text', ''));
        $lines = explode("\n", $text);
        $runs = '';
        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $runs .= '<w:r><w:br/></w:r>';
            }
            if ($line !== '') {
                $runs .= $this->textRun($line, ['code' => true]);
            }
        }

        return $this->paragraphWrapperXml($runs === '' ? $this->textRun('', ['code' => true]) : $runs, 'SourceCode', null);
    }

    /**
     * @return list<string>
     */
    private function definitionListXml(AstNode $list, int $listLevel): array
    {
        $blocks = [];
        foreach ($list->children as $item) {
            if (!$item instanceof AstNode || $item->type !== 'definition_item') {
                continue;
            }

            $term = null;
            foreach ($item->children as $child) {
                if ($child instanceof AstNode && in_array($child->type, ['term', 'definition_term'], true)) {
                    $term = $child;
                    break;
                }
            }

            if ($term instanceof AstNode) {
                $blocks[] = $term->children === []
                    ? $this->textParagraphXml((string) $term->attr('text', $item->attr('term', '')), 'DefinitionTerm', [])
                    : $this->paragraphFromInlinesXml($term->children, 'DefinitionTerm', null);
            } elseif ((string) $item->attr('term', '') !== '') {
                $blocks[] = $this->textParagraphXml((string) $item->attr('term', ''), 'DefinitionTerm', []);
            }

            foreach ($item->children as $child) {
                if (!$child instanceof AstNode || $child->type !== 'definition') {
                    continue;
                }
                foreach ($this->definitionBlocksXml($child->children, $listLevel) as $blockXml) {
                    $blocks[] = $blockXml;
                }
            }
        }

        return $blocks;
    }

    /**
     * @param list<AstNode> $children
     * @return list<string>
     */
    private function definitionBlocksXml(array $children, int $listLevel): array
    {
        $blocks = [];
        $inlineBuffer = [];
        $flushInlines = function () use (&$blocks, &$inlineBuffer): void {
            if ($inlineBuffer === []) {
                return;
            }

            $blocks[] = $this->paragraphFromInlinesXml($inlineBuffer, 'Definition', null);
            $inlineBuffer = [];
        };

        foreach ($children as $child) {
            if (!$child instanceof AstNode) {
                continue;
            }
            if ($this->isInlineNode($child)) {
                $inlineBuffer[] = $child;
                continue;
            }

            $flushInlines();
            if ($child->type === 'paragraph' || $child->type === 'plain') {
                $blocks[] = $this->paragraphXml($child, 'Definition', null);
                continue;
            }

            foreach ($this->renderBlock($child, $listLevel, 'Definition') as $blockXml) {
                $blocks[] = $blockXml;
            }
        }

        $flushInlines();

        return $blocks;
    }

    /**
     * @return list<string>
     */
    private function fallbackBlockXml(AstNode $node, int $listLevel, ?string $paragraphStyle): array
    {
        if ($node->children !== []) {
            return $this->blockCollectionXml($node->children, $listLevel, $paragraphStyle);
        }

        $text = (string) $node->attr('text', $node->attr('markdown', $node->attr('html', $node->attr('tex', ''))));

        return $text === '' ? [] : [$this->textParagraphXml($text, $paragraphStyle, [])];
    }

    /**
     * @return list<string>
     */
    private function divXml(AstNode $node, int $listLevel, ?string $paragraphStyle): array
    {
        $customStyle = $this->customStyleId($node);
        if ($customStyle !== null) {
            $this->customParagraphStyles[$customStyle] = $this->customStyleName($node);
            $paragraphStyle = $customStyle;
        }

        $id = $this->nodeAttribute($node, 'id');
        if ($id === 'refs') {
            $blocks = [];
            $bibliographyStarted = false;
            foreach ($node->children as $child) {
                if (!$child instanceof AstNode) {
                    continue;
                }

                if (!$bibliographyStarted && $child->type === 'heading') {
                    foreach ($this->renderBlock($child, $listLevel, $paragraphStyle) as $blockXml) {
                        $blocks[] = $blockXml;
                    }
                    continue;
                }

                $bibliographyStarted = true;
                foreach ($this->renderBlock($child, $listLevel, 'Bibliography') as $blockXml) {
                    $blocks[] = $blockXml;
                }
            }

            return $this->wrapBlockBookmarkXml($id, $blocks);
        }

        return $this->wrapBlockBookmarkXml($id, $this->blockCollectionXml($node->children, $listLevel, $paragraphStyle));
    }

    /**
     * @param list<string> $blocks
     * @return list<string>
     */
    private function wrapBlockBookmarkXml(string $id, array $blocks): array
    {
        if ($id === '') {
            return $blocks;
        }

        $bookmarkId = $this->nextBookmarkId++;
        array_unshift($blocks, '<w:bookmarkStart w:id="' . $bookmarkId . '" w:name="' . self::escAttr($this->bookmarkName($id)) . '"/>');
        $blocks[] = '<w:bookmarkEnd w:id="' . $bookmarkId . '"/>';

        return $blocks;
    }

    private function isBookmarkBoundaryXml(string $xml): bool
    {
        return str_starts_with($xml, '<w:bookmarkStart ') || str_starts_with($xml, '<w:bookmarkEnd ');
    }

    /**
     * @return list<string>
     */
    private function lineBlockXml(AstNode $node): array
    {
        $runs = [];
        foreach ($node->children as $index => $line) {
            if (!$line instanceof AstNode || $line->type !== 'line') {
                continue;
            }
            if ($index > 0) {
                $runs[] = '<w:r><w:br/></w:r>';
            }
            $runs[] = $line->children === []
                ? $this->textRun((string) $line->attr('text', ''), [])
                : $this->renderInlines($line->children, []);
        }

        return ['<w:p>' . implode('', $runs) . '</w:p>'];
    }

    private function horizontalRuleXml(): string
    {
        return '<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="6" w:space="1" w:color="auto"/></w:pBdr></w:pPr></w:p>';
    }

    private function rawBlockXml(AstNode $node, ?string $paragraphStyle): string
    {
        $text = (string) $node->attr('text', $node->attr('html', $node->attr('tex', '')));
        if ($text === '') {
            return '<w:p/>';
        }
        if (strtolower((string) $node->attr('format', '')) === 'openxml' && self::isSafeRawOpenXmlFragment($text)) {
            return $text;
        }

        return $this->textParagraphXml($text, $paragraphStyle, []);
    }

    /**
     * @return list<string>
     */
    private function tableXml(AstNode $table): array
    {
        $blocks = [];
        $caption = $this->tableCaptionInlines($table);
        if ($caption !== []) {
            $blocks[] = $this->paragraphFromInlinesXml($caption, 'TableCaption', null);
        }

        $columnCount = TableGeometry::columnCount($table);
        if ($columnCount === 0) {
            return $blocks;
        }

        $rowsXml = '';
        $hasHeaderRows = false;
        $firstParagraphInTable = true;
        foreach (TableGeometry::sectionRowEntryGroups($table, $columnCount) as $group) {
            foreach (TableGeometry::layoutRows($group['rows'], $columnCount) as $rowIndex => $layoutRow) {
                $rowEntry = $group['rowEntries'][$rowIndex] ?? [];
                $header = (bool) ($rowEntry['header'] ?? false);
                $hasHeaderRows = $hasHeaderRows || $header;
                $rowsXml .= $this->tableRowXml($layoutRow, $columnCount, $header, $firstParagraphInTable);
            }
        }

        if ($rowsXml === '') {
            return $blocks;
        }

        $grid = '';
        foreach ($this->tableColumnWidths($table, $columnCount) as $width) {
            $grid .= '<w:gridCol w:w="' . $width . '"/>';
        }

        $blocks[] = '<w:tbl>'
            . $this->tablePropertiesXml($table, $columnCount, $hasHeaderRows)
            . '<w:tblGrid>' . $grid . '</w:tblGrid>'
            . $rowsXml
            . '</w:tbl>';

        return $blocks;
    }

    /**
     * @param array{row:AstNode,cells:list<array{node:AstNode,column:int,colspan:int,rowspan:int}>} $layoutRow
     */
    private function tableRowXml(array $layoutRow, int $columnCount, bool $header, bool &$firstParagraphInTable): string
    {
        $cells = '';
        $visualColumn = 0;
        foreach ($layoutRow['cells'] as $layoutCell) {
            $column = (int) $layoutCell['column'];
            while ($visualColumn < $column && $visualColumn < $columnCount) {
                $cells .= $this->tableCellXml(null, 1, $firstParagraphInTable);
                $visualColumn++;
            }

            $colspan = max(1, (int) $layoutCell['colspan']);
            $cells .= $this->tableCellXml($layoutCell['node'], $colspan, $firstParagraphInTable);
            $visualColumn += $colspan;
        }

        while ($visualColumn < $columnCount) {
            $cells .= $this->tableCellXml(null, 1, $firstParagraphInTable);
            $visualColumn++;
        }

        $properties = $header ? '<w:trPr><w:tblHeader w:val="on"/></w:trPr>' : '';

        return '<w:tr>' . $properties . $cells . '</w:tr>';
    }

    private function tableCellXml(?AstNode $cell, int $colspan, bool &$firstParagraphInTable): string
    {
        $cellProperties = [];
        if ($colspan > 1) {
            $cellProperties[] = '<w:gridSpan w:val="' . $colspan . '"/>';
        }

        $properties = $cellProperties === [] ? '<w:tcPr/>' : '<w:tcPr>' . implode('', $cellProperties) . '</w:tcPr>';
        if (!$cell instanceof AstNode) {
            return '<w:tc>' . $properties . '<w:p/></w:tc>';
        }

        $content = '';
        if ($cell->children === []) {
            $text = (string) $cell->attr('text', '');
            $content = $this->textParagraphXml($text, 'Compact', []);
        } else {
            foreach ($this->tableCellBlockCollectionXml($cell->children, $firstParagraphInTable) as $blockXml) {
                $content .= $blockXml;
            }
        }
        if ($content === '') {
            $content = '<w:p/>';
        }

        return '<w:tc>' . $properties . $content . '</w:tc>';
    }

    /**
     * @param list<AstNode> $children
     * @return list<string>
     */
    private function tableCellBlockCollectionXml(array $children, bool &$firstParagraphInTable): array
    {
        $blocks = [];
        $inlineBuffer = [];
        $flushInlines = function () use (&$blocks, &$inlineBuffer): void {
            if ($inlineBuffer === []) {
                return;
            }

            $blocks[] = $this->paragraphFromInlinesXml($inlineBuffer, 'Compact', null);
            $inlineBuffer = [];
        };

        foreach ($children as $child) {
            if (!$child instanceof AstNode) {
                continue;
            }
            if ($this->isInlineNode($child)) {
                $inlineBuffer[] = $child;
                continue;
            }

            $flushInlines();
            if ($child->type === 'plain') {
                $blocks[] = $this->paragraphXml($child, 'Compact', null);
                continue;
            }
            if ($child->type === 'paragraph') {
                $style = $firstParagraphInTable ? 'FirstParagraph' : 'BodyText';
                $firstParagraphInTable = false;
                $blocks[] = $this->paragraphXml($child, $style, null);
                continue;
            }

            foreach ($this->renderBlock($child, 0, null) as $blockXml) {
                $blocks[] = $blockXml;
            }
        }

        $flushInlines();

        return $blocks;
    }

    /**
     * @return list<AstNode>
     */
    private function tableCaptionInlines(AstNode $table): array
    {
        $captionInlines = $table->attr('captionInlines', []);
        if (is_array($captionInlines) && $captionInlines !== []) {
            $inlines = [];
            foreach ($captionInlines as $inline) {
                if (!$inline instanceof AstNode) {
                    return [];
                }
                $inlines[] = $inline;
            }

            return $inlines;
        }

        $captionBlocks = $table->attr('captionBlocks', []);
        if (is_array($captionBlocks) && $captionBlocks !== []) {
            $texts = [];
            foreach ($captionBlocks as $block) {
                if ($block instanceof AstNode) {
                    $text = trim($this->plainText($block));
                    if ($text !== '') {
                        $texts[] = $text;
                    }
                }
            }

            return $texts === [] ? [] : [new AstNode('text', ['text' => implode(' ', $texts)])];
        }

        $caption = trim((string) $table->attr('caption', ''));

        return $caption === '' ? [] : [new AstNode('text', ['text' => $caption])];
    }

    /**
     * @return list<int>
     */
    private function tableColumnWidths(AstNode $table, int $columnCount): array
    {
        $weights = $this->tableColumnWidthWeights($table, $columnCount);
        $resolved = [];
        $remaining = self::TABLE_GRID_WIDTH_DXA;
        for ($column = 0; $column < $columnCount; $column++) {
            if ($column === $columnCount - 1) {
                $resolved[] = max(1, $remaining);
                break;
            }

            $width = max(1, (int) round(self::TABLE_GRID_WIDTH_DXA * $weights[$column]));
            $resolved[] = $width;
            $remaining -= $width;
        }

        return $resolved;
    }

    /**
     * @return list<float>
     */
    private function tableColumnWidthWeights(AstNode $table, int $columnCount): array
    {
        $widths = $table->attr('widths', []);
        $positive = [];
        if (is_array($widths)) {
            foreach (array_values($widths) as $width) {
                if (count($positive) >= $columnCount) {
                    break;
                }
                $positive[] = (is_int($width) || is_float($width)) && (float) $width > 0.0 ? (float) $width : null;
            }
        }

        $allExplicit = count($positive) >= $columnCount
            && count(array_filter($positive, static fn (?float $width): bool => $width !== null && $width > 0.0)) === $columnCount;
        if ($allExplicit) {
            $total = array_sum(array_map(static fn (?float $width): float => (float) $width, $positive));
            if ($total > 0.0) {
                return array_map(static fn (?float $width): float => ((float) $width) / $total, array_slice($positive, 0, $columnCount));
            }
        }

        return array_fill(0, $columnCount, 1.0 / $columnCount);
    }

    private function tablePropertiesXml(AstNode $table, int $columnCount, bool $hasHeaderRows): string
    {
        $fixed = $this->tableHasExplicitColumnWidths($table, $columnCount);
        $width = $fixed
            ? '<w:tblW w:type="pct" w:w="5000"/><w:tblLayout w:type="fixed"/>'
            : '<w:tblW w:type="auto" w:w="0"/>';
        $firstRow = $hasHeaderRows ? '1' : '0';
        $lookValue = $hasHeaderRows ? '0020' : '0000';

        return '<w:tblPr>'
            . '<w:tblStyle w:val="Table"/>'
            . $width
            . '<w:tblLook w:firstRow="' . $firstRow . '" w:lastRow="0" w:firstColumn="0" w:lastColumn="0" w:noHBand="0" w:noVBand="0" w:val="' . $lookValue . '"/>'
            . '</w:tblPr>';
    }

    private function tableHasExplicitColumnWidths(AstNode $table, int $columnCount): bool
    {
        $widths = $table->attr('widths', []);
        if (!is_array($widths) || count($widths) < $columnCount) {
            return false;
        }

        for ($column = 0; $column < $columnCount; $column++) {
            $width = $widths[$column] ?? null;
            if (!(is_int($width) || is_float($width)) || (float) $width <= 0.0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function listXml(AstNode $list, bool $ordered, int $listLevel, ?string $paragraphStyle): array
    {
        $numId = $ordered ? $this->orderedListNumId($list) : null;
        $blocks = [];
        foreach ($list->children as $item) {
            if (!$item instanceof AstNode || $item->type !== 'list_item') {
                continue;
            }

            $itemBlocks = $item->children;
            if ($itemBlocks === [] && (string) $item->attr('text', '') !== '') {
                $itemBlocks = [new AstNode('plain', [], [new AstNode('text', ['text' => (string) $item->attr('text')])])];
            }

            $taskChecked = $ordered ? null : $this->taskListItemChecked($itemBlocks);
            $itemNumId = match ($taskChecked) {
                true => $this->taskListNumId(true),
                false => $this->taskListNumId(false),
                default => $numId ??= $this->bulletListNumId(),
            };
            $primaryNumbering = ['numId' => $itemNumId, 'level' => $listLevel];
            $continuationNumbering = ['numId' => self::CONTINUATION_NUM_ID, 'level' => $listLevel];
            $numberedParagraphEmitted = false;
            $stripTaskMarker = $taskChecked !== null;
            foreach ($itemBlocks as $child) {
                if (!$child instanceof AstNode) {
                    continue;
                }
                if ($child->type === 'bullet_list') {
                    if (!$numberedParagraphEmitted) {
                        $blocks[] = $this->paragraphFromInlinesXml([], $paragraphStyle ?? 'Compact', $primaryNumbering);
                        $numberedParagraphEmitted = true;
                        $stripTaskMarker = false;
                    }
                    foreach ($this->listXml($child, false, $listLevel + 1, $paragraphStyle) as $nested) {
                        $blocks[] = $nested;
                    }
                    continue;
                }
                if ($child->type === 'ordered_list') {
                    if (!$numberedParagraphEmitted) {
                        $blocks[] = $this->paragraphFromInlinesXml([], $paragraphStyle ?? 'Compact', $primaryNumbering);
                        $numberedParagraphEmitted = true;
                        $stripTaskMarker = false;
                    }
                    foreach ($this->listXml($child, true, $listLevel + 1, $paragraphStyle) as $nested) {
                        $blocks[] = $nested;
                    }
                    continue;
                }

                if ($child->type === 'paragraph' || $child->type === 'plain') {
                    $paragraph = $stripTaskMarker ? $this->stripTaskListMarker($child) : $child;
                    $stripTaskMarker = false;
                    $style = $child->type === 'plain' ? ($paragraphStyle ?? 'Compact') : $paragraphStyle;
                    $blocks[] = $this->paragraphXml(
                        $paragraph,
                        $style,
                        $numberedParagraphEmitted ? $continuationNumbering : $primaryNumbering
                    );
                    $numberedParagraphEmitted = true;
                    continue;
                }

                if ($this->isInlineNode($child)) {
                    $inlines = $stripTaskMarker ? $this->stripTaskListMarkerFromInlines([$child]) : [$child];
                    $stripTaskMarker = false;
                    $blocks[] = $this->paragraphFromInlinesXml(
                        $inlines,
                        $paragraphStyle,
                        $numberedParagraphEmitted ? $continuationNumbering : $primaryNumbering
                    );
                    $numberedParagraphEmitted = true;
                    continue;
                }

                foreach ($this->renderBlock($child, $listLevel + 1, $paragraphStyle) as $blockXml) {
                    if ($this->isBookmarkBoundaryXml($blockXml)) {
                        $blocks[] = $blockXml;
                        continue;
                    }
                    if (!$numberedParagraphEmitted && str_starts_with($blockXml, '<w:p>')) {
                        $blocks[] = $this->numberedParagraphBlockXml($blockXml, $primaryNumbering);
                        $numberedParagraphEmitted = true;
                        $stripTaskMarker = false;
                        continue;
                    }
                    if ($numberedParagraphEmitted && str_starts_with($blockXml, '<w:p>')) {
                        $blocks[] = $this->numberedParagraphBlockXml($blockXml, $continuationNumbering);
                        continue;
                    }
                    if (!$numberedParagraphEmitted) {
                        $blocks[] = $this->paragraphFromInlinesXml([], $paragraphStyle, $primaryNumbering);
                        $numberedParagraphEmitted = true;
                        $stripTaskMarker = false;
                    }
                    $blocks[] = $blockXml;
                }
            }

            if (!$numberedParagraphEmitted) {
                $blocks[] = $this->paragraphFromInlinesXml([], $paragraphStyle ?? 'Compact', $primaryNumbering);
            }
        }

        return $blocks;
    }

    private function bulletListNumId(): int
    {
        return $this->numberingNumId(self::BULLET_ABSTRACT_NUM_ID, null);
    }

    private function taskListNumId(bool $checked): int
    {
        return $this->numberingNumId($checked ? self::TASK_CHECKED_ABSTRACT_NUM_ID : self::TASK_UNCHECKED_ABSTRACT_NUM_ID, null);
    }

    private function orderedListNumId(AstNode $list): int
    {
        $start = max(1, (int) $list->attr('start', 1));
        $style = $this->orderedListStyle($list);
        $delimiter = $this->orderedListDelimiter($list);
        $abstractNumId = $this->orderedListAbstractNumId($style, $delimiter, $start);

        return $this->numberingNumId($abstractNumId, $start);
    }

    private function numberingNumId(int $abstractNumId, ?int $start): int
    {
        $this->markNumberingAbstractUsed($abstractNumId);
        $numId = $this->nextNumberingId++;
        $this->numberingInstances[] = [
            'numId' => $numId,
            'abstractNumId' => $abstractNumId,
            'start' => $start,
        ];

        return $numId;
    }

    private function markNumberingAbstractUsed(int $abstractNumId): void
    {
        if (isset($this->numberingAbstractsSeen[$abstractNumId])) {
            return;
        }

        $this->numberingAbstractsSeen[$abstractNumId] = true;
        $this->numberingAbstractOrder[] = $abstractNumId;
    }

    private function orderedListAbstractNumId(string $style, string $delimiter, int $start): int
    {
        $key = $style . ':' . $delimiter . ':' . $start;
        if (!isset($this->orderedListDefinitions[$key])) {
            $this->orderedListDefinitions[$key] = [
                'abstractNumId' => $this->orderedListAbstractNumIdValue($style, $delimiter, $start),
                'style' => $style,
                'delimiter' => $delimiter,
                'start' => $start,
            ];
        }

        return $this->orderedListDefinitions[$key]['abstractNumId'];
    }

    private function orderedListAbstractNumIdValue(string $style, string $delimiter, int $start): int
    {
        $styleCode = match ($style) {
            'default' => '2',
            'example' => '3',
            'lower_roman' => '5',
            'upper_roman' => '6',
            'lower_alpha' => '7',
            'upper_alpha' => '8',
            default => '4',
        };
        $delimiterCode = match ($delimiter) {
            'default' => '0',
            'one_paren' => '2',
            'two_parens' => '3',
            default => '1',
        };

        return (int) ('99' . $styleCode . $delimiterCode . (string) max(1, $start));
    }

    private function orderedListStyle(AstNode $list): string
    {
        $style = $list->attr('style', 'decimal');

        return match (is_string($style) ? $style : '') {
            'default',
            'lower_alpha',
            'upper_alpha',
            'lower_roman',
            'upper_roman',
            'example' => $style,
            default => 'decimal',
        };
    }

    private function orderedListDelimiter(AstNode $list): string
    {
        $delimiter = $list->attr('delimiter', 'period');

        return match (is_string($delimiter) ? $delimiter : '') {
            'one_paren',
            'two_parens',
            'default' => $delimiter,
            default => 'period',
        };
    }

    /**
     * @param list<AstNode> $itemBlocks
     */
    private function taskListItemChecked(array $itemBlocks): ?bool
    {
        foreach ($itemBlocks as $block) {
            if (!$block instanceof AstNode) {
                continue;
            }
            if ($block->type === 'paragraph' || $block->type === 'plain') {
                return $this->taskMarkerCheckedFromText($block->children === []
                    ? (string) $block->attr('text', '')
                    : $this->plainInlineText($block->children));
            }
            if ($this->isInlineNode($block)) {
                return $this->taskMarkerCheckedFromText($this->plainInlineText([$block]));
            }
            if ($block->type === 'div') {
                $checked = $this->taskListItemChecked($block->children);
                if ($checked !== null) {
                    return $checked;
                }
            }

            return null;
        }

        return null;
    }

    private function taskMarkerCheckedFromText(string $text): ?bool
    {
        if (preg_match('/^\s*(\x{2610}|\x{2612})(?:\s|$)/u', $text, $matches) !== 1) {
            return null;
        }

        return $matches[1] === "\u{2612}";
    }

    private function stripTaskListMarker(AstNode $node): AstNode
    {
        if ($node->children === []) {
            $text = preg_replace('/^\s*(?:\x{2610}|\x{2612})\s*/u', '', (string) $node->attr('text', '')) ?? '';

            return new AstNode($node->type, array_replace($node->attrs, ['text' => $text]), []);
        }

        return new AstNode($node->type, $node->attrs, $this->stripTaskListMarkerFromInlines($node->children));
    }

    /**
     * @param list<AstNode> $inlines
     * @return list<AstNode>
     */
    private function stripTaskListMarkerFromInlines(array $inlines): array
    {
        $stripped = [];
        $markerSeen = false;
        $dropFollowingSpace = false;
        foreach ($inlines as $inline) {
            if (!$inline instanceof AstNode) {
                continue;
            }

            if (!$markerSeen && ($inline->type === 'text' || $inline->type === 'str')) {
                $key = $inline->type === 'str' ? 'value' : 'text';
                $text = (string) $inline->attr($key, $inline->attr('text', ''));
                $withoutMarker = preg_replace('/^\s*(?:\x{2610}|\x{2612})\s*/u', '', $text, 1, $count);
                if ($count > 0) {
                    $markerSeen = true;
                    if ($withoutMarker !== '') {
                        $stripped[] = new AstNode($inline->type, array_replace($inline->attrs, [$key => $withoutMarker]), $inline->children);
                    } else {
                        $dropFollowingSpace = true;
                    }
                    continue;
                }
            }

            if ($dropFollowingSpace) {
                if ($inline->type === 'space' || $inline->type === 'softbreak') {
                    $dropFollowingSpace = false;
                    continue;
                }
                if ($inline->type === 'text' && trim((string) $inline->attr('text', '')) === '') {
                    $dropFollowingSpace = false;
                    continue;
                }
            }

            $dropFollowingSpace = false;
            $stripped[] = $inline;
        }

        return $stripped;
    }

    /**
     * @param array{numId:int, level:int}|null $numbering
     */
    private function paragraphXml(AstNode $node, ?string $styleId, ?array $numbering, string $context = 'document'): string
    {
        $runs = $node->children === []
            ? $this->textRun((string) $node->attr('text', ''), [])
            : $this->renderInlines($node->children, [], $context);

        return $this->paragraphWrapperXml($runs, $styleId, $numbering);
    }

    /**
     * @param list<AstNode> $inlines
     * @param array{numId:int, level:int}|null $numbering
     */
    private function paragraphFromInlinesXml(array $inlines, ?string $styleId, ?array $numbering, string $context = 'document'): string
    {
        return $this->paragraphWrapperXml($this->renderInlines($inlines, [], $context), $styleId, $numbering);
    }

    /**
     * @param array<string, bool|string> $format
     */
    private function textParagraphXml(string $text, ?string $styleId, array $format): string
    {
        return $this->paragraphWrapperXml($this->textRun($text, $format), $styleId, null);
    }

    /**
     * @param array{numId:int, level:int}|null $numbering
     */
    private function paragraphWrapperXml(string $runs, ?string $styleId, ?array $numbering): string
    {
        $properties = [];
        if ($styleId !== null && $styleId !== '') {
            $properties[] = '<w:pStyle w:val="' . self::escAttr($styleId) . '"/>';
        }
        if ($numbering !== null) {
            $properties[] = $this->numberingPropertiesXml($numbering);
        }

        $pPr = $properties === [] ? '' : '<w:pPr>' . implode('', $properties) . '</w:pPr>';

        return '<w:p>' . $pPr . $runs . '</w:p>';
    }

    /**
     * @param array{numId:int, level:int} $numbering
     */
    private function numberedParagraphBlockXml(string $paragraphXml, array $numbering): string
    {
        $numberingXml = $this->numberingPropertiesXml($numbering);
        if (str_starts_with($paragraphXml, '<w:p><w:pPr>')) {
            $withNumberingAfterStyle = preg_replace(
                '/^<w:p><w:pPr>(<w:pStyle\b[^>]*\/>)/',
                '<w:p><w:pPr>$1' . $numberingXml,
                $paragraphXml,
                1
            );
            if ($withNumberingAfterStyle !== null && $withNumberingAfterStyle !== $paragraphXml) {
                return $withNumberingAfterStyle;
            }

            return preg_replace('/^<w:p><w:pPr>/', '<w:p><w:pPr>' . $numberingXml, $paragraphXml, 1) ?? $paragraphXml;
        }
        if (str_starts_with($paragraphXml, '<w:p>')) {
            return '<w:p><w:pPr>' . $numberingXml . '</w:pPr>' . substr($paragraphXml, strlen('<w:p>'));
        }

        return $paragraphXml;
    }

    /**
     * @param array{numId:int, level:int} $numbering
     */
    private function numberingPropertiesXml(array $numbering): string
    {
        return '<w:numPr><w:ilvl w:val="' . max(0, min(8, $numbering['level'])) . '"/><w:numId w:val="' . (int) $numbering['numId'] . '"/></w:numPr>';
    }

    /**
     * @param list<AstNode> $nodes
     * @param array<string, bool|string> $format
     */
    private function renderInlines(array $nodes, array $format, string $context = 'document'): string
    {
        $xml = '';
        $baseFormat = in_array($context, ['comment', 'footnote'], true)
            ? array_replace($format, ['preserveSpace' => true])
            : $format;
        $buffer = '';
        $bufferPreserveSpace = false;
        $buffered = false;
        $flushBuffer = function () use (&$xml, &$buffer, &$bufferPreserveSpace, &$buffered, $baseFormat): void {
            if (!$buffered) {
                return;
            }

            $runFormat = $bufferPreserveSpace
                ? array_replace($baseFormat, ['preserveSpace' => true])
                : $baseFormat;
            $xml .= $this->textRun($buffer, $runFormat);
            $buffer = '';
            $bufferPreserveSpace = false;
            $buffered = false;
        };

        foreach ($nodes as $index => $node) {
            if (!$node instanceof AstNode) {
                continue;
            }

            if ($node->type === 'softbreak') {
                $flushBuffer();
                $xml .= $this->textRun(' ', array_replace($baseFormat, ['preserveSpace' => true]));
                continue;
            }

            $plain = $this->plainInlineRunText($node);
            if ($plain !== null) {
                if ($this->isInlineBoundarySpace($nodes, $index, $node)) {
                    $flushBuffer();
                    $xml .= $this->textRun($plain['text'], array_replace($baseFormat, ['preserveSpace' => true]));
                    continue;
                }

                if ($node->type === 'text' || $node->type === 'str') {
                    $boundaryText = $this->splitPlainInlineBoundaryText($nodes, $index, $plain['text']);
                    if ($boundaryText['leading'] !== '' || $boundaryText['trailing'] !== '') {
                        if ($boundaryText['leading'] !== '') {
                            $flushBuffer();
                            $xml .= $this->textRun($boundaryText['leading'], array_replace($baseFormat, ['preserveSpace' => true]));
                        }

                        if ($boundaryText['text'] !== '') {
                            $buffer .= $boundaryText['text'];
                            $bufferPreserveSpace = $bufferPreserveSpace || $plain['preserveSpace'] || $buffered;
                            $buffered = true;
                        }

                        if ($boundaryText['trailing'] !== '') {
                            $flushBuffer();
                            $xml .= $this->textRun($boundaryText['trailing'], array_replace($baseFormat, ['preserveSpace' => true]));
                        }
                        continue;
                    }
                }

                $buffer .= $plain['text'];
                $bufferPreserveSpace = $bufferPreserveSpace || $plain['preserveSpace'] || $buffered;
                $buffered = true;
                continue;
            }

            $flushBuffer();
            $xml .= $this->renderInline($node, $baseFormat, $context);
        }
        $flushBuffer();

        return $xml;
    }

    /**
     * @param list<AstNode> $nodes
     * @return array{leading:string,text:string,trailing:string}
     */
    private function splitPlainInlineBoundaryText(array $nodes, int $index, string $text): array
    {
        $leading = '';
        $trailing = '';

        if ($text !== '' && $this->adjacentInlineUsesSeparateTextBoundarySpace($nodes, $index, -1) && preg_match('/^ +/', $text, $matches) === 1) {
            $leading = $matches[0];
            $text = substr($text, strlen($leading));
        }
        if ($text !== '' && $this->adjacentInlineUsesSeparateTextBoundarySpace($nodes, $index, 1) && preg_match('/ +$/', $text, $matches) === 1) {
            $trailing = $matches[0];
            $text = substr($text, 0, -strlen($trailing));
        }

        return [
            'leading' => $leading,
            'text' => $text,
            'trailing' => $trailing,
        ];
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function adjacentInlineUsesSeparateTextBoundarySpace(array $nodes, int $index, int $step): bool
    {
        for ($i = $index + $step, $count = count($nodes); $i >= 0 && $i < $count; $i += $step) {
            $node = $nodes[$i] ?? null;
            if (!$node instanceof AstNode) {
                continue;
            }

            return $node->type === 'image' || $node->type === 'link';
        }

        return false;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function isInlineBoundarySpace(array $nodes, int $index, AstNode $node): bool
    {
        $isSpace = $node->type === 'space' || $node->type === 'softbreak';
        if (!$isSpace && ($node->type === 'text' || $node->type === 'str')) {
            $isSpace = (string) $node->attr('text', $node->attr('value', '')) === ' ';
        }
        if (!$isSpace) {
            return false;
        }

        return $this->adjacentInlineIsNonPlain($nodes, $index, -1)
            || $this->adjacentInlineIsNonPlain($nodes, $index, 1);
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function adjacentInlineIsNonPlain(array $nodes, int $index, int $step): bool
    {
        for ($i = $index + $step, $count = count($nodes); $i >= 0 && $i < $count; $i += $step) {
            $node = $nodes[$i] ?? null;
            if (!$node instanceof AstNode) {
                continue;
            }

            return $this->plainInlineRunText($node) === null;
        }

        return false;
    }

    /**
     * @return array{text:string, preserveSpace:bool}|null
     */
    private function plainInlineRunText(AstNode $node): ?array
    {
        if ($node->type === 'text' || $node->type === 'str') {
            $text = (string) $node->attr('text', $node->attr('value', ''));

            return [
                'text' => $text,
                'preserveSpace' => $text === ' ',
            ];
        }
        if ($node->type === 'space' || $node->type === 'softbreak') {
            return [
                'text' => ' ',
                'preserveSpace' => true,
            ];
        }

        return null;
    }

    /**
     * @param array<string, bool|string> $format
     */
    private function renderInline(AstNode $node, array $format, string $context = 'document'): string
    {
        return match ($node->type) {
            'text', 'str' => $this->textRun((string) $node->attr('text', $node->attr('value', '')), $format),
            'space' => $this->textRun(' ', $format),
            'softbreak' => $this->textRun(' ', $format),
            'linebreak' => $this->breakRun($format),
            'emph' => $this->renderInlines($node->children, $format + ['italic' => true], $context),
            'strong' => $this->renderInlines($node->children, $format + ['bold' => true], $context),
            'underline' => $this->renderInlines($node->children, $format + ['underline' => true], $context),
            'strikeout', 'strike' => $this->renderInlines($node->children, $format + ['strike' => true], $context),
            'superscript' => $this->renderInlines($node->children, $format + ['verticalAlign' => 'superscript'], $context),
            'subscript' => $this->renderInlines($node->children, $format + ['verticalAlign' => 'subscript'], $context),
            'smallcaps', 'small_caps' => $this->renderInlines($node->children, $format + ['smallCaps' => true], $context),
            'code' => $this->textRun((string) $node->attr('text', $node->attr('code', '')), $format + ['code' => true]),
            'link' => $this->hyperlinkXml($node, $format, $context),
            'image' => $context === 'document'
                ? $this->imageXml($node, $format)
                : $this->textRun($this->imageAltText($node), $format),
            'math' => $this->textRun((string) $node->attr('text', $node->attr('math', '')), $format),
            'raw_inline' => $this->rawOpenXmlInlineXml($node) ?? $this->textRun(
                (string) $node->attr('text', $node->attr('math', $node->attr('html', $node->attr('tex', '')))),
                $format
            ),
            'raw_html', 'raw_html_inline', 'raw_tex', 'raw_tex_inline', 'raw_markdown' => $this->textRun(
                (string) $node->attr('text', $node->attr('html', $node->attr('tex', $node->attr('markdown', '')))),
                $format
            ),
            'note' => $this->footnoteReferenceXml($node),
            'span' => $this->spanXml($node, $format, $context),
            default => $node->children === []
                ? $this->textRun((string) $node->attr('text', ''), $format)
                : $this->renderInlines($node->children, $format, $context),
        };
    }

    /**
     * @param array<string, bool|string> $format
     */
    private function hyperlinkXml(AstNode $node, array $format, string $context = 'document'): string
    {
        $target = (string) $node->attr('url', $node->attr('href', ''));
        if ($target === '') {
            return $this->renderInlines($node->children, $format, $context);
        }

        $content = $this->renderInlines(
            $node->children === [] ? [new AstNode('text', ['text' => $target])] : $node->children,
            array_replace($format, ['runStyle' => 'Hyperlink']),
            $context
        );
        if (str_starts_with($target, '#')) {
            $anchor = $this->bookmarkName(substr($target, 1));

            return '<w:hyperlink w:anchor="' . self::escAttr($anchor) . '">' . $content . '</w:hyperlink>';
        }

        $relationshipId = $this->addHyperlinkRelationship($target);

        return '<w:hyperlink r:id="' . self::escAttr($relationshipId) . '">' . $content . '</w:hyperlink>';
    }

    /**
     * @param array<string, bool|string> $format
     */
    private function imageXml(AstNode $node, array $format): string
    {
        $source = (string) $node->attr('url', $node->attr('src', ''));
        $mediaPart = $source === '' ? null : $this->ensureImageMediaPart($source);
        if ($mediaPart === null) {
            return $this->textRun($this->imageAltText($node), $format);
        }

        $dimensions = $this->imageDimensionsEmu($node, $mediaPart['data']);
        $docPrId = $this->nextDocumentDynamicId();
        $picturePrId = $this->nextDocumentDynamicId();
        $alt = $this->imageAltText($node);
        $title = (string) $node->attr('title', '');
        $sourceName = $this->imageSourceName($source);
        $relationshipId = $mediaPart['relationshipId'];
        $bookmarkName = (string) $node->attr('id', '');
        $bookmarkStart = '';
        $bookmarkEnd = '';
        if ($bookmarkName !== '') {
            $bookmarkId = $this->nextDocumentDynamicId();
            if ($this->nextBookmarkId <= $bookmarkId) {
                $this->nextBookmarkId = $bookmarkId + 1;
            }
            $bookmarkStart = '<w:bookmarkStart w:id="' . $bookmarkId . '" w:name="' . self::escAttr($bookmarkName) . '"/>';
            $bookmarkEnd = '<w:bookmarkEnd w:id="' . $bookmarkId . '"/>';
        }

        $drawing = '<w:r><w:drawing><wp:inline>'
            . '<wp:extent cx="' . $dimensions['cx'] . '" cy="' . $dimensions['cy'] . '"/>'
            . '<wp:effectExtent b="0" l="0" r="0" t="0"/>'
            . '<wp:docPr descr="' . self::escAttr($alt) . '" title="' . self::escAttr($title) . '" id="' . $docPrId . '" name="Picture"/>'
            . '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:pic><pic:nvPicPr>'
            . '<pic:cNvPr descr="' . self::escAttr($sourceName) . '" id="' . $picturePrId . '" name="Picture"/>'
            . '<pic:cNvPicPr><a:picLocks noChangeArrowheads="1" noChangeAspect="1"/></pic:cNvPicPr>'
            . '</pic:nvPicPr><pic:blipFill>'
            . '<a:blip r:embed="' . self::escAttr($relationshipId) . '"/>'
            . '<a:stretch><a:fillRect/></a:stretch>'
            . '</pic:blipFill><pic:spPr bwMode="auto">'
            . '<a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $dimensions['cx'] . '" cy="' . $dimensions['cy'] . '"/></a:xfrm>'
            . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom>'
            . '<a:noFill/><a:ln w="9525"><a:noFill/><a:headEnd/><a:tailEnd/></a:ln>'
            . '</pic:spPr></pic:pic>'
            . '</a:graphicData></a:graphic>'
            . '</wp:inline></w:drawing></w:r>';

        return $bookmarkStart . $drawing . $bookmarkEnd;
    }

    private function footnoteReferenceXml(AstNode $node): string
    {
        $id = $this->nextDocumentDynamicId();
        $this->nextFootnoteId = max($this->nextFootnoteId, $id + 1);

        $this->footnotes[] = [
            'id' => $id,
            'blocks' => $node->children,
        ];

        return '<w:r><w:rPr><w:rStyle w:val="FootnoteReference"/></w:rPr><w:footnoteReference w:id="' . $id . '"/></w:r>';
    }

    /**
     * @param array<string, bool|string> $format
     */
    private function spanXml(AstNode $node, array $format, string $context): string
    {
        if ($this->hasClass($node, 'comment-start')) {
            return $this->commentStartXml($node);
        }
        if ($this->hasClass($node, 'comment-end')) {
            return $this->commentEndXml($node, $format, $context);
        }
        if ($this->hasClass($node, 'insertion')) {
            return $this->trackedChangeXml('ins', $node, $format, $context);
        }
        if ($this->hasClass($node, 'deletion')) {
            return $this->trackedChangeXml('del', $node, $format, $context);
        }
        if ($this->hasClass($node, 'indexref')) {
            return $this->indexReferenceFieldXml($node, $format, $context);
        }

        $customStyle = $this->customStyleId($node);
        if ($customStyle !== null) {
            $this->customCharacterStyles[$customStyle] = $this->customStyleName($node);
            $format = array_replace($format, ['runStyle' => $customStyle]);
        }

        $content = $this->renderInlines($node->children, $format, $context);
        $id = (string) $node->attr('id', '');
        if ($id === '') {
            return $content;
        }

        $bookmarkId = $this->nextBookmarkId++;
        $name = $this->bookmarkName($id);

        return '<w:bookmarkStart w:id="' . $bookmarkId . '" w:name="' . self::escAttr($name) . '"/>'
            . $content
            . '<w:bookmarkEnd w:id="' . $bookmarkId . '"/>';
    }

    private function indexReferenceFieldXml(AstNode $node, array $format, string $context): string
    {
        $entry = $this->nodeAttribute($node, 'entry');
        if ($entry === '') {
            return $this->renderInlines($node->children, $format, $context);
        }

        return '<w:r><w:fldChar w:fldCharType="begin"/></w:r>'
            . '<w:r><w:instrText xml:space="preserve"> XE ' . self::escText(self::fieldInstructionQuotedString($entry)) . ' </w:instrText></w:r>'
            . '<w:r><w:fldChar w:fldCharType="end"/></w:r>'
            . $this->renderInlines($node->children, $format, $context);
    }

    private static function fieldInstructionQuotedString(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }

    private function commentStartXml(AstNode $node): string
    {
        $id = $this->nodeAttribute($node, 'id');
        if ($id === '') {
            $id = (string) count($this->comments);
        }
        if (!isset($this->commentIds[$id])) {
            $this->commentIds[$id] = true;
            $this->comments[] = [
                'id' => $id,
                'author' => $this->nodeAttribute($node, 'author'),
                'date' => $this->nodeAttribute($node, 'date'),
                'children' => $node->children,
            ];
        }

        return '<w:commentRangeStart w:id="' . self::escAttr($id) . '"/>';
    }

    /**
     * @param array<string, bool|string> $format
     */
    private function commentEndXml(AstNode $node, array $format, string $context): string
    {
        $id = $this->nodeAttribute($node, 'id');
        if ($id === '') {
            return $this->renderInlines($node->children, $format, $context);
        }

        return '<w:commentRangeEnd w:id="' . self::escAttr($id) . '"/>'
            . '<w:r><w:rPr><w:rStyle w:val="CommentReference"/></w:rPr><w:commentReference w:id="' . self::escAttr($id) . '"/></w:r>'
            . $this->renderInlines($node->children, $format, $context);
    }

    /**
     * @param array<string, bool|string> $format
     */
    private function trackedChangeXml(string $tag, AstNode $node, array $format, string $context): string
    {
        $id = $this->nodeAttribute($node, 'id');
        if ($id === '') {
            $id = '1';
        }

        $attributes = ' w:id="' . self::escAttr($id) . '"';
        $author = $this->nodeAttribute($node, 'author');
        if ($author !== '') {
            $attributes .= ' w:author="' . self::escAttr($author) . '"';
        }
        $date = $this->nodeAttribute($node, 'date');
        if ($date !== '') {
            $attributes .= ' w:date="' . self::escAttr($date) . '"';
        }

        $changeFormat = $tag === 'del' ? $format + ['deleted' => true] : $format;

        return '<w:' . $tag . $attributes . '>'
            . $this->renderInlines($node->children, $changeFormat, $context)
            . '</w:' . $tag . '>';
    }

    private function rawOpenXmlInlineXml(AstNode $node): ?string
    {
        if (strtolower((string) $node->attr('format', '')) !== 'openxml') {
            return null;
        }

        $xml = trim((string) $node->attr('text', ''));
        if ($xml === '') {
            return '';
        }
        if (preg_match('/^<w:bookmarkStart\s+w:id="([0-9]+)"\s+w:name="([^"]+)"\s*\/>$/', $xml, $matches) === 1) {
            return '<w:bookmarkStart w:id="' . self::escAttr($matches[1]) . '" w:name="' . self::escAttr($matches[2]) . '"/>';
        }
        if (preg_match('/^<w:bookmarkEnd\s+w:id="([0-9]+)"\s*\/>$/', $xml, $matches) === 1) {
            return '<w:bookmarkEnd w:id="' . self::escAttr($matches[1]) . '"/>';
        }
        if (preg_match('/^<w:fldSimple\s+w:instr="([^"]+)"\s*\/>$/', $xml, $matches) === 1) {
            return '<w:fldSimple w:instr="' . self::escAttr($matches[1]) . '"/>';
        }
        if (self::isSafeRawOpenXmlInlineFragment($xml)) {
            return $xml;
        }

        return null;
    }

    private function hasClass(AstNode $node, string $class): bool
    {
        $classes = $node->attr('classes', []);
        if (!is_array($classes)) {
            return false;
        }

        return in_array($class, array_map('strval', $classes), true);
    }

    private function nodeAttribute(AstNode $node, string $name): string
    {
        if (array_key_exists($name, $node->attrs)) {
            $value = $node->attrs[$name];

            return is_scalar($value) || $value === null ? (string) $value : '';
        }

        $attributes = $node->attr('attributes', []);
        if (is_array($attributes) && array_key_exists($name, $attributes)) {
            $value = $attributes[$name];

            return is_scalar($value) || $value === null ? (string) $value : '';
        }

        return '';
    }

    private function customStyleName(AstNode $node): string
    {
        return trim($this->nodeAttribute($node, 'custom-style'));
    }

    private function customStyleId(AstNode $node): ?string
    {
        $name = $this->customStyleName($node);
        if ($name === '') {
            return null;
        }

        $styleId = preg_replace('/\s+/u', '', $name);
        if (!is_string($styleId) || $styleId === '') {
            return null;
        }

        return $styleId;
    }

    private function addHyperlinkRelationship(string $target): string
    {
        $key = OpcRelationship::TARGET_MODE_EXTERNAL . "\0" . $target;
        if (isset($this->hyperlinkRelationshipIds[$key])) {
            return $this->hyperlinkRelationshipIds[$key];
        }

        $id = $this->addDocumentRelationship(
            self::REL_HYPERLINK,
            $target,
            OpcRelationship::TARGET_MODE_EXTERNAL
        );
        $this->footnoteRelationships[] = new OpcRelationship(
            $id,
            self::REL_HYPERLINK,
            $target,
            OpcRelationship::TARGET_MODE_EXTERNAL
        );
        $this->hyperlinkRelationshipIds[$key] = $id;

        return $id;
    }

    private function addDocumentRelationship(string $type, string $target, string $targetMode): string
    {
        $id = 'rId' . $this->nextDocumentRelationshipId++;
        $this->documentRelationships[] = new OpcRelationship($id, $type, $target, $targetMode);

        return $id;
    }

    private function nextDocumentDynamicId(): int
    {
        return $this->nextDocumentRelationshipId++;
    }

    /**
     * @return array{name:string, data:string, contentType:string, source:string, relationshipId:string}|null
     */
    private function ensureImageMediaPart(string $source): ?array
    {
        $key = $this->mediaSourceKey($source);
        if (isset($this->mediaPartsBySource[$key])) {
            return $this->mediaPartsBySource[$key];
        }

        $media = $this->resolveImageMedia($source);
        if ($media === null) {
            return null;
        }

        $relationshipId = 'rId' . $this->nextDocumentDynamicId();
        $target = 'media/' . $relationshipId . '.' . $media['extension'];
        $partName = 'word/' . $target;
        $this->documentRelationships[] = new OpcRelationship(
            $relationshipId,
            self::REL_IMAGE,
            $target,
            OpcRelationship::TARGET_MODE_INTERNAL
        );

        $part = [
            'name' => $partName,
            'data' => $media['data'],
            'contentType' => $media['contentType'],
            'source' => $source,
            'relationshipId' => $relationshipId,
        ];
        $this->mediaParts[] = $part;
        $this->mediaPartsBySource[$key] = $part;

        return $part;
    }

    private function mediaSourceKey(string $source): string
    {
        $resolved = $this->resolveLocalMediaPath($source);

        return $resolved === null ? $source : $resolved;
    }

    /**
     * @return array{data:string, extension:string, contentType:string}|null
     */
    private function resolveImageMedia(string $source): ?array
    {
        $path = $this->resolveLocalMediaPath($source);
        if ($path === null || !is_file($path) || !is_readable($path)) {
            return null;
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $contentType = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            default => null,
        };
        if ($contentType === null) {
            return null;
        }

        $data = file_get_contents($path);
        if (!is_string($data)) {
            return null;
        }

        return [
            'data' => $data,
            'extension' => $extension === 'jpeg' ? 'jpg' : $extension,
            'contentType' => $contentType,
        ];
    }

    private function resolveLocalMediaPath(string $source): ?string
    {
        if ($source === '' || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $source) === 1) {
            return null;
        }

        $candidates = [];
        if ($source[0] === '/') {
            $candidates[] = $source;
        } else {
            $normalizedSource = str_replace('/', DIRECTORY_SEPARATOR, $source);
            foreach ($this->mediaBasePaths() as $basePath) {
                $candidates[] = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $normalizedSource;
            }
            $cwd = getcwd();
            if (is_string($cwd)) {
                $candidates[] = $cwd . DIRECTORY_SEPARATOR . $normalizedSource;
            }
        }

        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if (is_string($real) && is_file($real)) {
                return $real;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function mediaBasePaths(): array
    {
        $paths = [];
        foreach (['mediaBasePath', 'resourcePath'] as $key) {
            if (isset($this->options[$key]) && is_string($this->options[$key]) && $this->options[$key] !== '') {
                $paths[] = $this->options[$key];
            }
        }
        foreach (['mediaBasePaths', 'resourcePaths'] as $key) {
            $value = $this->options[$key] ?? [];
            if (is_string($value) && $value !== '') {
                $paths[] = $value;
                continue;
            }
            if (!is_array($value)) {
                continue;
            }
            foreach ($value as $path) {
                if (is_string($path) && $path !== '') {
                    $paths[] = $path;
                }
            }
        }

        return array_values(array_unique($paths));
    }

    /** @return array{cx:int, cy:int} */
    private function imageDimensionsEmu(AstNode $node, string $bytes): array
    {
        $imageSize = @getimagesizefromstring($bytes);
        $naturalWidth = is_array($imageSize) && isset($imageSize[0]) ? max(1, (int) $imageSize[0]) : 200;
        $naturalHeight = is_array($imageSize) && isset($imageSize[1]) ? max(1, (int) $imageSize[1]) : 200;
        $dpi = $this->imageDpiFromBytes($bytes);
        $naturalDpiX = $dpi['dpiX'] ?? self::DEFAULT_IMAGE_DPI;
        $naturalDpiY = $dpi['dpiY'] ?? self::DEFAULT_IMAGE_DPI;
        $naturalCx = max(1, (int) round($naturalWidth * self::EMU_PER_INCH / $naturalDpiX));
        $naturalCy = max(1, (int) round($naturalHeight * self::EMU_PER_INCH / $naturalDpiY));
        $width = $this->imageDimensionAttribute($node, 'width');
        $height = $this->imageDimensionAttribute($node, 'height');
        $cx = $width === null ? null : $this->dimensionToEmu($width);
        $cy = $height === null ? null : $this->dimensionToEmu($height);

        if ($cx === null && $cy === null) {
            $cx = $naturalCx;
            $cy = $naturalCy;
        } elseif ($cx !== null && $cy === null) {
            $cy = (int) round($cx * ($naturalCy / $naturalCx));
        } elseif ($cx === null && $cy !== null) {
            $cx = (int) round($cy * ($naturalCx / $naturalCy));
        }

        $cx = max(1, (int) $cx);
        $cy = max(1, (int) $cy);
        $maxCx = 5334000;
        if ($cx > $maxCx) {
            $scale = $maxCx / $cx;
            $cx = $maxCx;
            $cy = max(1, (int) round($cy * $scale));
        }

        return ['cx' => $cx, 'cy' => $cy];
    }

    /**
     * @return array{dpiX:float, dpiY:float}|null
     */
    private function imageDpiFromBytes(string $bytes): ?array
    {
        if (substr($bytes, 0, 2) === "\xFF\xD8") {
            return $this->jpegJfifDpi($bytes);
        }

        return null;
    }

    /**
     * @return array{dpiX:float, dpiY:float}|null
     */
    private function jpegJfifDpi(string $bytes): ?array
    {
        $length = strlen($bytes);
        $offset = 2;
        while ($offset + 4 <= $length) {
            if ($bytes[$offset] !== "\xFF") {
                break;
            }
            while ($offset < $length && $bytes[$offset] === "\xFF") {
                ++$offset;
            }
            if ($offset >= $length) {
                break;
            }

            $marker = ord($bytes[$offset]);
            ++$offset;
            if ($marker === 0xDA || $marker === 0xD9) {
                break;
            }
            if ($marker >= 0xD0 && $marker <= 0xD7) {
                continue;
            }
            if ($offset + 2 > $length) {
                break;
            }

            $segmentLength = self::readUint16($bytes, $offset);
            if ($segmentLength < 2 || $offset + $segmentLength > $length) {
                break;
            }

            $segmentStart = $offset + 2;
            $segmentDataLength = $segmentLength - 2;
            if (
                $marker === 0xE0
                && $segmentDataLength >= 14
                && substr($bytes, $segmentStart, 5) === "JFIF\0"
            ) {
                $units = ord($bytes[$segmentStart + 7]);
                $densityX = self::readUint16($bytes, $segmentStart + 8);
                $densityY = self::readUint16($bytes, $segmentStart + 10);
                if ($densityX <= 0 || $densityY <= 0) {
                    return null;
                }

                return match ($units) {
                    1 => ['dpiX' => (float) $densityX, 'dpiY' => (float) $densityY],
                    2 => ['dpiX' => $densityX * 2.54, 'dpiY' => $densityY * 2.54],
                    default => null,
                };
            }

            $offset += $segmentLength;
        }

        return null;
    }

    private static function readUint16(string $bytes, int $offset): int
    {
        return (ord($bytes[$offset]) << 8) | ord($bytes[$offset + 1]);
    }

    private function imageDimensionAttribute(AstNode $node, string $name): ?string
    {
        $value = $node->attr($name);
        if (is_scalar($value) && (string) $value !== '') {
            return (string) $value;
        }

        $attributes = $node->attr('attributes', []);
        if (is_array($attributes) && isset($attributes[$name]) && is_scalar($attributes[$name]) && (string) $attributes[$name] !== '') {
            return (string) $attributes[$name];
        }

        return null;
    }

    private function dimensionToEmu(string $dimension): ?int
    {
        $dimension = trim($dimension);
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?|\.[0-9]+)\s*(in|cm|mm|pt|px)?$/i', $dimension, $match) !== 1) {
            return null;
        }

        $value = (float) $match[1];
        $unit = strtolower($match[2] ?? 'px');

        return match ($unit) {
            'in' => (int) round($value * 914400),
            'cm' => max(1, (int) floor($value * 359999.99999999994)),
            'mm' => max(1, (int) floor($value * 35999.99999999999)),
            'pt' => (int) round($value * 12700),
            default => (int) round($value * 9525),
        };
    }

    private function imageSourceName(string $source): string
    {
        $basename = basename(str_replace('\\', '/', $source));

        return $basename === '' ? $source : $basename;
    }

    private function bookmarkName(string $target): string
    {
        $name = preg_replace('/[^\p{L}\p{N}_:-]/u', '_', $target);
        if (!is_string($name)) {
            $name = preg_replace('/[^A-Za-z0-9_:-]/', '_', $target) ?? '';
        }
        $name = trim($name, '_');
        if ($name === '') {
            return '_';
        }
        if (preg_match('/^[\p{L}_]/u', $name) !== 1) {
            $name = '_' . $name;
        }
        if ($this->utf8Length($name) > 40) {
            return 'X' . substr(sha1($target), 1);
        }

        return $name;
    }

    private function utf8Length(string $value): int
    {
        if (preg_match_all('/./us', $value, $matches) === false) {
            return strlen($value);
        }

        return count($matches[0]);
    }

    /**
     * @param array<string, bool|string> $format
     */
    private function textRun(string $text, array $format): string
    {
        if ($text === '') {
            $properties = $this->runPropertiesXml($format);

            return $properties === '' ? '<w:r/>' : '<w:r>' . $properties . '</w:r>';
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $parts = preg_split('/(\n|\t)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            $parts = [$text];
        }

        $runs = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if ($part === "\n") {
                $runs .= $this->breakRun($format);
                continue;
            }
            if ($part === "\t") {
                $runs .= '<w:r>' . $this->runPropertiesXml($format) . '<w:tab/></w:r>';
                continue;
            }
            $tag = (($format['deleted'] ?? false) === true) ? 'w:delText' : 'w:t';
            foreach (self::textScriptChunks($part) as $chunk) {
                $chunkFormat = $chunk['eastAsia']
                    ? array_replace($format, ['eastAsia' => true])
                    : $format;
                $runs .= '<w:r>' . $this->runPropertiesXml($chunkFormat) . '<' . $tag . ' xml:space="preserve">' . self::escText($chunk['text']) . '</' . $tag . '></w:r>';
            }
        }

        return $runs;
    }

    /**
     * @return list<array{text:string, eastAsia:bool}>
     */
    private static function textScriptChunks(string $text): array
    {
        if (!self::containsCjk($text)) {
            return [['text' => $text, 'eastAsia' => false]];
        }

        $tokens = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($tokens === false || $tokens === []) {
            return [['text' => $text, 'eastAsia' => false]];
        }

        $groups = [];
        foreach ($tokens as $token) {
            $lastIndex = count($groups) - 1;
            if ($lastIndex >= 0 && self::shouldJoinScriptChunk($groups[$lastIndex], $token)) {
                $groups[$lastIndex] .= $token;
                continue;
            }

            $groups[] = $token;
        }

        return array_map(
            static fn (string $group): array => ['text' => $group, 'eastAsia' => self::containsCjk($group)],
            $groups
        );
    }

    private static function shouldJoinScriptChunk(string $left, string $right): bool
    {
        $leftHasCjk = self::containsCjk($left);
        $rightHasCjk = self::containsCjk($right);

        return (!$leftHasCjk && !$rightHasCjk)
            || (self::isUnicodeSpace($left) && !$rightHasCjk)
            || (self::isUnicodeSpace($right) && !$leftHasCjk);
    }

    private static function containsCjk(string $text): bool
    {
        return preg_match('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u', $text) === 1;
    }

    private static function isUnicodeSpace(string $text): bool
    {
        return preg_match('/^\s+$/u', $text) === 1;
    }

    /**
     * @param array<string, bool|string> $format
     */
    private function breakRun(array $format): string
    {
        return '<w:r>' . $this->runPropertiesXml($format) . '<w:br/></w:r>';
    }

    private function annotationReferenceRun(): string
    {
        return '<w:r><w:rPr><w:rStyle w:val="CommentReference"/></w:rPr><w:annotationRef/></w:r>';
    }

    private function footnoteReferenceMarkerRun(): string
    {
        return '<w:r><w:rPr><w:rStyle w:val="FootnoteReference"/></w:rPr><w:footnoteRef/></w:r>';
    }

    /**
     * @param array<string, bool|string> $format
     */
    private function runPropertiesXml(array $format): string
    {
        $props = [];
        $runStyle = (string) ($format['runStyle'] ?? '');
        if (($format['code'] ?? false) === true) {
            $runStyle = 'VerbatimChar';
        }
        if ($runStyle !== '') {
            $props[] = '<w:rStyle w:val="' . self::escAttr($runStyle) . '"/>';
        }
        if (($format['bold'] ?? false) === true) {
            $props[] = '<w:b/>';
            $props[] = '<w:bCs/>';
        }
        if (($format['italic'] ?? false) === true) {
            $props[] = '<w:i/>';
            $props[] = '<w:iCs/>';
        }
        if (($format['underline'] ?? false) === true) {
            $props[] = '<w:u w:val="single"/>';
        }
        if (($format['strike'] ?? false) === true) {
            $props[] = '<w:strike/>';
        }
        if (($format['smallCaps'] ?? false) === true) {
            $props[] = '<w:smallCaps/>';
        }
        if (isset($format['verticalAlign']) && is_string($format['verticalAlign'])) {
            $props[] = '<w:vertAlign w:val="' . self::escAttr($format['verticalAlign']) . '"/>';
        }
        if (($format['eastAsia'] ?? false) === true) {
            array_unshift($props, '<w:rFonts w:hint="eastAsia"/>');
        }

        return $props === [] ? '' : '<w:rPr>' . implode('', $props) . '</w:rPr>';
    }

    private function imageAltText(AstNode $node): string
    {
        if ($node->children !== []) {
            return $this->plainInlineText($node->children);
        }

        return (string) $node->attr('alt', $node->attr('title', $node->attr('url', $node->attr('src', ''))));
    }

    private function isInlineNode(AstNode $node): bool
    {
        return in_array($node->type, [
            'text',
            'str',
            'space',
            'softbreak',
            'linebreak',
            'emph',
            'strong',
            'underline',
            'strikeout',
            'strike',
            'superscript',
            'subscript',
            'smallcaps',
            'small_caps',
            'code',
            'link',
            'image',
            'math',
            'raw_inline',
            'raw_html',
            'raw_html_inline',
            'raw_tex',
            'raw_tex_inline',
            'raw_markdown',
            'note',
            'span',
        ], true);
    }

    private function plainText(AstNode $node): string
    {
        if ($this->isInlineNode($node)) {
            return $this->plainInlineText([$node]);
        }
        if ($node->children !== []) {
            $parts = [];
            foreach ($node->children as $child) {
                if ($child instanceof AstNode) {
                    $parts[] = $this->plainText($child);
                }
            }

            return trim(implode(' ', array_filter($parts, static fn (string $part): bool => $part !== '')));
        }

        return (string) $node->attr('text', '');
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if (!$node instanceof AstNode) {
                continue;
            }
            $text .= match ($node->type) {
                'text', 'str' => (string) $node->attr('text', $node->attr('value', '')),
                'space', 'softbreak' => ' ',
                'linebreak' => "\n",
                'code' => (string) $node->attr('text', $node->attr('code', '')),
                'image' => $this->imageAltText($node),
                'math', 'raw_inline', 'raw_html', 'raw_html_inline', 'raw_tex', 'raw_tex_inline', 'raw_markdown' => (string) $node->attr('text', $node->attr('math', $node->attr('html', $node->attr('tex', $node->attr('markdown', ''))))),
                default => $node->children === [] ? (string) $node->attr('text', '') : $this->plainInlineText($node->children),
            };
        }

        return $text;
    }

    private function sectionPropertiesXml(): string
    {
        $referenceDocument = $this->referencePackagePart('word/document.xml');
        if ($referenceDocument !== null) {
            $sectionProperties = $this->referenceSectionPropertiesXml($referenceDocument);
            if ($sectionProperties !== null) {
                return $sectionProperties;
            }
        }

        return '<w:sectPr><w:footnotePr><w:numRestart w:val="eachSect"/></w:footnotePr></w:sectPr>';
    }

    private function referenceSectionPropertiesXml(string $documentXml): ?string
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($documentXml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return null;
        }

        $sectionProperties = $dom->getElementsByTagNameNS(self::NS_W, 'sectPr');
        if ($sectionProperties->length === 0) {
            return null;
        }

        $last = $sectionProperties->item($sectionProperties->length - 1);
        if (!$last instanceof \DOMElement) {
            return null;
        }

        $xml = $last->prefix === 'w'
            ? $dom->saveXML($last)
            : $this->wordPrefixedElementXml($last);

        return is_string($xml) && $xml !== '' ? $xml : null;
    }

    private function wordPrefixedElementXml(\DOMElement $element): ?string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->appendChild($this->cloneElementWithWordPrefix($dom, $element));
        $xml = $dom->saveXML($dom->documentElement);

        return is_string($xml) && $xml !== '' ? $xml : null;
    }

    private function cloneElementWithWordPrefix(\DOMDocument $dom, \DOMElement $element): \DOMElement
    {
        $namespace = (string) ($element->namespaceURI ?? '');
        $prefix = (string) $element->prefix;
        $qualifiedName = $namespace === self::NS_W
            ? 'w:' . $element->localName
            : ($prefix === '' ? $element->localName : $prefix . ':' . $element->localName);
        $copy = $namespace === ''
            ? $dom->createElement($qualifiedName)
            : $dom->createElementNS($namespace, $qualifiedName);

        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            $attributeNamespace = (string) ($attribute->namespaceURI ?? '');
            if ($attributeNamespace === '') {
                $copy->setAttribute($attribute->name, $attribute->value);
                continue;
            }

            $attributeName = $attributeNamespace === self::NS_W
                ? 'w:' . $attribute->localName
                : $attribute->name;
            $copy->setAttributeNS($attributeNamespace, $attributeName, $attribute->value);
        }

        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $copy->appendChild($this->cloneElementWithWordPrefix($dom, $child));
                continue;
            }

            $copy->appendChild($dom->importNode($child, true));
        }

        return $copy;
    }

    private function customStylesXml(string $baseStylesXml): string
    {
        $defaultStyleNamesByType = self::styleNamesByType($baseStylesXml);
        $xml = '';
        ksort($this->customParagraphStyles, SORT_STRING);
        foreach ($this->customParagraphStyles as $styleId => $name) {
            if (isset($defaultStyleNamesByType['paragraph'][$name])) {
                continue;
            }
            $xml .= $this->paragraphStyleXml($styleId, $name, 'BodyText', true, true);
        }
        ksort($this->customCharacterStyles, SORT_STRING);
        foreach ($this->customCharacterStyles as $styleId => $name) {
            if (isset($defaultStyleNamesByType['character'][$name])) {
                continue;
            }
            $xml .= $this->characterStyleXml($styleId, $name, 'BodyTextChar', true);
        }

        return $xml;
    }

    private function paragraphStyleXml(string $styleId, string $name, ?string $basedOn, bool $custom, bool $qFormat = false): string
    {
        $customAttr = $custom ? ' w:customStyle="1"' : '';
        $basedOnXml = $basedOn === null ? '' : '<w:basedOn w:val="' . self::escAttr($basedOn) . '"/>';
        $qFormatXml = $qFormat ? '<w:qFormat/>' : '';

        return '<w:style w:type="paragraph"' . $customAttr . ' w:styleId="' . self::escAttr($styleId) . '">'
            . '<w:name w:val="' . self::escAttr($name) . '"/>'
            . $basedOnXml
            . $qFormatXml
            . '</w:style>';
    }

    private function characterStyleXml(string $styleId, string $name, ?string $basedOn, bool $custom): string
    {
        $customAttr = $custom ? ' w:customStyle="1"' : '';
        $basedOnXml = $basedOn === null ? '' : '<w:basedOn w:val="' . self::escAttr($basedOn) . '"/>';

        return '<w:style w:type="character"' . $customAttr . ' w:styleId="' . self::escAttr($styleId) . '">'
            . '<w:name w:val="' . self::escAttr($name) . '"/>'
            . $basedOnXml
            . '</w:style>';
    }

    private function stylesXml(AstNode $document): string
    {
        $stylesXml = rtrim($this->referencePackagePart('word/styles.xml') ?? self::defaultStylesXml(), "\r\n");
        $language = $this->documentLanguage($document);
        if ($language !== '') {
            $stylesXml = $this->stylesXmlWithLanguage($stylesXml, $language);
        }

        $customStyles = $this->customStylesXml($stylesXml);

        if ($customStyles !== '') {
            $stylesXml = self::insertCustomStylesXml($stylesXml, $customStyles);
        }

        return $stylesXml . "\n";
    }

    private function documentLanguage(AstNode $document): string
    {
        $meta = $this->documentMetadata($document);

        return $this->metadataText($this->documentMetadataValue(
            $document,
            $meta,
            'language',
            $this->options['language'] ?? $this->options['lang'] ?? '',
            'lang'
        ));
    }

    private function stylesXmlWithLanguage(string $stylesXml, string $language): string
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($stylesXml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded || !$dom->documentElement instanceof \DOMElement) {
            return $stylesXml;
        }

        $updated = false;
        foreach ($dom->getElementsByTagNameNS(self::NS_W, 'lang') as $lang) {
            if (!$lang instanceof \DOMElement) {
                continue;
            }
            $lang->setAttributeNS(self::NS_W, 'w:val', $language);
            $updated = true;
        }

        if (!$updated) {
            return $stylesXml;
        }

        $xml = $dom->saveXML($dom->documentElement);

        return is_string($xml) && $xml !== '' ? self::xmlDeclaration() . $xml : $stylesXml;
    }

    private static function insertCustomStylesXml(string $stylesXml, string $customStyles): string
    {
        if (preg_match('/<w:style\b(?=[^>]*\bw:styleId="SourceCode")/u', $stylesXml, $matches, PREG_OFFSET_CAPTURE) === 1) {
            $offset = $matches[0][1];

            return substr($stylesXml, 0, $offset) . $customStyles . substr($stylesXml, $offset);
        }

        $closingOffset = strrpos($stylesXml, '</w:styles>');
        if ($closingOffset === false) {
            return $stylesXml . $customStyles;
        }

        return substr($stylesXml, 0, $closingOffset) . $customStyles . substr($stylesXml, $closingOffset);
    }

    private static function defaultStylesXml(): string
    {
        static $stylesXml = null;
        if ($stylesXml !== null) {
            return $stylesXml;
        }

        $path = __DIR__ . '/resources/docx-default-styles.xml';
        $stylesXml = file_get_contents($path);
        if (!is_string($stylesXml)) {
            throw new \RuntimeException("Unable to read DOCX default styles resource: {$path}");
        }

        $stylesXml = rtrim($stylesXml, "\r\n");

        return $stylesXml;
    }

    /**
     * @return array{paragraph: array<string, true>, character: array<string, true>}
     */
    private static function styleNamesByType(string $stylesXml): array
    {
        $styleNamesByType = [
            'paragraph' => [],
            'character' => [],
        ];

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($stylesXml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return $styleNamesByType;
        }

        foreach ($dom->getElementsByTagNameNS(self::NS_W, 'style') as $style) {
            if (!$style instanceof \DOMElement) {
                continue;
            }

            $type = $style->getAttributeNS(self::NS_W, 'type');
            if ($type !== 'paragraph' && $type !== 'character') {
                continue;
            }

            foreach ($style->getElementsByTagNameNS(self::NS_W, 'name') as $name) {
                if (!$name instanceof \DOMElement) {
                    continue;
                }

                $value = $name->getAttributeNS(self::NS_W, 'val');
                if ($value !== '') {
                    $styleNamesByType[$type][$value] = true;
                }
                break;
            }
        }

        return $styleNamesByType;
    }

    private function numberingXml(): string
    {
        $fragments = $this->numberingFragments();
        $referenceNumbering = $this->referencePackagePart('word/numbering.xml');
        if ($referenceNumbering !== null) {
            return $this->mergeReferenceNumberingXml($referenceNumbering, $fragments['abstractNums'], $fragments['numbers']);
        }

        return self::xmlDeclaration()
            . '<w:numbering xmlns:w="' . self::NS_W . '">'
            . implode('', array_column($fragments['abstractNums'], 'xml'))
            . implode('', array_column($fragments['numbers'], 'xml'))
            . '</w:numbering>'
            . "\n";
    }

    /**
     * @return array{
     *     abstractNums:list<array{id:int, xml:string}>,
     *     numbers:list<array{id:int, xml:string}>
     * }
     */
    private function numberingFragments(): array
    {
        $abstractNums = [
            [
                'id' => self::CONTINUATION_ABSTRACT_NUM_ID,
                'xml' => $this->abstractNumberingXml(self::CONTINUATION_ABSTRACT_NUM_ID),
            ],
        ];
        foreach ($this->numberingAbstractOrder as $abstractNumId) {
            if ($abstractNumId === self::CONTINUATION_ABSTRACT_NUM_ID) {
                continue;
            }
            $abstractNums[] = [
                'id' => $abstractNumId,
                'xml' => $this->abstractNumberingXml($abstractNumId),
            ];
        }

        $numbers = [
            [
                'id' => self::CONTINUATION_NUM_ID,
                'xml' => '<w:num w:numId="' . self::CONTINUATION_NUM_ID . '"><w:abstractNumId w:val="' . self::CONTINUATION_ABSTRACT_NUM_ID . '"/></w:num>',
            ],
        ];
        foreach ($this->numberingInstances as $instance) {
            $numbers[] = [
                'id' => $instance['numId'],
                'xml' => '<w:num w:numId="' . $instance['numId'] . '"><w:abstractNumId w:val="' . $instance['abstractNumId'] . '"/>'
                    . $this->levelOverridesXml($instance['start'])
                    . '</w:num>',
            ];
        }

        return [
            'abstractNums' => $abstractNums,
            'numbers' => $numbers,
        ];
    }

    /**
     * @param list<array{id:int, xml:string}> $abstractNums
     * @param list<array{id:int, xml:string}> $numbers
     */
    private function mergeReferenceNumberingXml(string $referenceNumbering, array $abstractNums, array $numbers): string
    {
        $existingAbstractIds = $this->wordElementIntegerIds($referenceNumbering, 'abstractNum', 'abstractNumId');
        $existingNumIds = $this->wordElementIntegerIds($referenceNumbering, 'num', 'numId');
        $abstractXml = '';
        foreach ($abstractNums as $fragment) {
            if (!isset($existingAbstractIds[$fragment['id']])) {
                $abstractXml .= $fragment['xml'];
            }
        }

        $numberXml = '';
        foreach ($numbers as $fragment) {
            if (!isset($existingNumIds[$fragment['id']])) {
                $numberXml .= $fragment['xml'];
            }
        }

        $xml = rtrim($referenceNumbering, "\r\n");
        if ($abstractXml !== '') {
            if (preg_match('/<w:num\b/u', $xml, $matches, PREG_OFFSET_CAPTURE) === 1) {
                $offset = $matches[0][1];
                $xml = substr($xml, 0, $offset) . $abstractXml . substr($xml, $offset);
            } else {
                $xml = self::insertBeforeClosingTag($xml, 'w:numbering', $abstractXml);
            }
        }

        if ($numberXml !== '') {
            $xml = self::insertBeforeClosingTag($xml, 'w:numbering', $numberXml);
        }

        return $xml . "\n";
    }

    /**
     * @return array<int, true>
     */
    private function wordElementIntegerIds(string $xml, string $elementName, string $attributeName): array
    {
        $ids = [];
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return $ids;
        }

        foreach ($dom->getElementsByTagNameNS(self::NS_W, $elementName) as $element) {
            if (!$element instanceof \DOMElement || !$element->hasAttributeNS(self::NS_W, $attributeName)) {
                continue;
            }
            $ids[(int) $element->getAttributeNS(self::NS_W, $attributeName)] = true;
        }

        return $ids;
    }

    private static function insertBeforeClosingTag(string $xml, string $tagName, string $insert): string
    {
        $closingOffset = strrpos($xml, '</' . $tagName . '>');
        if ($closingOffset === false) {
            return $xml . $insert;
        }

        return substr($xml, 0, $closingOffset) . $insert . substr($xml, $closingOffset);
    }

    private function abstractNumberingXml(int $abstractNumId): string
    {
        if ($abstractNumId === self::CONTINUATION_ABSTRACT_NUM_ID) {
            return '<w:abstractNum w:abstractNumId="' . $abstractNumId . '"><w:nsid w:val="0000A990"/><w:multiLevelType w:val="multilevel"/>'
                . $this->plainBulletLevelsXml(' ')
                . '</w:abstractNum>';
        }
        if ($abstractNumId === self::BULLET_ABSTRACT_NUM_ID) {
            return '<w:abstractNum w:abstractNumId="' . $abstractNumId . '"><w:nsid w:val="0000A991"/><w:multiLevelType w:val="multilevel"/>'
                . $this->pandocBulletLevelsXml()
                . '</w:abstractNum>';
        }
        if ($abstractNumId === self::TASK_UNCHECKED_ABSTRACT_NUM_ID) {
            return '<w:abstractNum w:abstractNumId="' . $abstractNumId . '"><w:nsid w:val="0000A992"/><w:multiLevelType w:val="multilevel"/>'
                . $this->plainBulletLevelsXml("\u{2610}")
                . '</w:abstractNum>';
        }
        if ($abstractNumId === self::TASK_CHECKED_ABSTRACT_NUM_ID) {
            return '<w:abstractNum w:abstractNumId="' . $abstractNumId . '"><w:nsid w:val="0000A993"/><w:multiLevelType w:val="multilevel"/>'
                . $this->plainBulletLevelsXml("\u{2612}")
                . '</w:abstractNum>';
        }

        foreach ($this->orderedListDefinitions as $definition) {
            if ($definition['abstractNumId'] !== $abstractNumId) {
                continue;
            }

            return '<w:abstractNum w:abstractNumId="' . $abstractNumId . '"><w:nsid w:val="' . self::escAttr('00A' . (string) $abstractNumId) . '"/><w:multiLevelType w:val="multilevel"/>'
                . $this->orderedLevelsXml($definition['style'], $definition['delimiter'], $definition['start'])
                . '</w:abstractNum>';
        }

        return '';
    }

    private function levelOverridesXml(?int $start): string
    {
        if ($start === null) {
            return '';
        }

        $overrides = '';
        for ($level = 0; $level < 9; $level++) {
            $overrides .= '<w:lvlOverride w:ilvl="' . $level . '"><w:startOverride w:val="' . $start . '"/></w:lvlOverride>';
        }

        return $overrides;
    }

    private function plainBulletLevelsXml(string $levelText): string
    {
        $levels = '';
        for ($level = 0; $level < 9; $level++) {
            $left = 720 + ($level * 720);
            $levels .= '<w:lvl w:ilvl="' . $level . '"><w:numFmt w:val="bullet"/><w:lvlText w:val="' . self::escAttr($levelText) . '"/><w:lvlJc w:val="left"/><w:pPr><w:ind w:left="' . $left . '" w:hanging="360"/></w:pPr></w:lvl>';
        }

        return $levels;
    }

    private function pandocBulletLevelsXml(): string
    {
        $glyphs = [
            ['text' => "\u{F0B7}", 'font' => 'Symbol'],
            ['text' => 'o', 'font' => 'Courier New'],
            ['text' => "\u{F0A7}", 'font' => 'Wingdings'],
        ];
        $levels = '';
        for ($level = 0; $level < 9; $level++) {
            $left = 720 + ($level * 720);
            $glyph = $glyphs[$level % 3];
            $levels .= '<w:lvl w:ilvl="' . $level . '"><w:numFmt w:val="bullet"/><w:lvlText w:val="' . self::escAttr($glyph['text']) . '"/><w:lvlJc w:val="left"/><w:pPr><w:ind w:left="' . $left . '" w:hanging="360"/></w:pPr><w:rPr><w:rFonts w:ascii="' . self::escAttr($glyph['font']) . '" w:hAnsi="' . self::escAttr($glyph['font']) . '" w:cs="' . self::escAttr($glyph['font']) . '" w:hint="default"/></w:rPr></w:lvl>';
        }

        return $levels;
    }

    private function orderedLevelsXml(string $style, string $delimiter, int $start): string
    {
        $levels = '';
        for ($level = 0; $level < 9; $level++) {
            $left = 720 + ($level * 720);
            $format = $this->docxOrderedNumberFormat($style, $level);
            $text = $this->docxOrderedLevelText($level + 1, $delimiter);
            $levels .= '<w:lvl w:ilvl="' . $level . '"><w:start w:val="' . $start . '"/><w:numFmt w:val="' . $format . '"/><w:lvlText w:val="' . $text . '"/><w:lvlJc w:val="left"/><w:pPr><w:ind w:left="' . $left . '" w:hanging="360"/></w:pPr></w:lvl>';
        }

        return $levels;
    }

    private function docxOrderedNumberFormat(string $style, int $level): string
    {
        if ($style === 'default') {
            return match ($level % 3) {
                1 => 'lowerLetter',
                2 => 'lowerRoman',
                default => 'decimal',
            };
        }

        return match ($style) {
            'lower_alpha' => 'lowerLetter',
            'upper_alpha' => 'upperLetter',
            'lower_roman' => 'lowerRoman',
            'upper_roman' => 'upperRoman',
            default => 'decimal',
        };
    }

    private function docxOrderedLevelText(int $level, string $delimiter): string
    {
        $placeholder = '%' . $level;

        return match ($delimiter) {
            'one_paren' => $placeholder . ')',
            'two_parens' => '(' . $placeholder . ')',
            default => $placeholder . '.',
        };
    }

    private function settingsXml(): string
    {
        $referenceSettings = $this->referencePackagePart('word/settings.xml');
        if ($referenceSettings !== null) {
            return $this->referenceSettingsXml($referenceSettings);
        }

        return $this->defaultSettingsXml();
    }

    private function referenceSettingsXml(string $settingsXml): string
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($settingsXml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded || !$dom->documentElement instanceof \DOMElement) {
            return $this->defaultSettingsXml();
        }

        $root = $dom->documentElement;
        $this->removeChildElements($root, self::NS_W, ['footnotePr', 'endnotePr']);
        $this->removeChildElements($root, 'http://schemas.openxmlformats.org/officeDocument/2006/math', ['mathPr']);
        $this->removeChildElements($root, 'http://schemas.microsoft.com/office/word/2012/wordml', ['docId']);
        $this->insertWordEmptyChildAfter($dom, $root, 'proofState', ['grammar' => 'clean', 'spelling' => 'clean'], 'embedSystemFonts');
        $this->insertWordEmptyChildAfter($dom, $root, 'savePreviewPicture', [], 'characterSpacingControl');

        $xml = $dom->saveXML($root);
        if (!is_string($xml)) {
            return $this->defaultSettingsXml();
        }

        return self::xmlDeclaration() . $xml . "\n";
    }

    /**
     * @param list<string> $localNames
     */
    private function removeChildElements(\DOMElement $root, string $namespace, array $localNames): void
    {
        $remove = [];
        foreach ($root->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ((string) ($child->namespaceURI ?? '') === $namespace && in_array($child->localName, $localNames, true)) {
                $remove[] = $child;
            }
        }

        foreach ($remove as $child) {
            $root->removeChild($child);
        }
    }

    /**
     * @param array<string, string> $attributes
     */
    private function insertWordEmptyChildAfter(
        \DOMDocument $dom,
        \DOMElement $root,
        string $localName,
        array $attributes,
        string $afterLocalName
    ): void {
        if ($this->hasDirectChild($root, self::NS_W, $localName)) {
            return;
        }

        $element = $dom->createElementNS(self::NS_W, 'w:' . $localName);
        foreach ($attributes as $name => $value) {
            $element->setAttributeNS(self::NS_W, 'w:' . $name, $value);
        }

        $after = $this->firstDirectChild($root, self::NS_W, $afterLocalName);
        if (!$after instanceof \DOMElement || !$after->nextSibling instanceof \DOMNode) {
            $root->appendChild($element);
            return;
        }

        $root->insertBefore($element, $after->nextSibling);
    }

    private function hasDirectChild(\DOMElement $root, string $namespace, string $localName): bool
    {
        return $this->firstDirectChild($root, $namespace, $localName) instanceof \DOMElement;
    }

    private function firstDirectChild(\DOMElement $root, string $namespace, string $localName): ?\DOMElement
    {
        foreach ($root->childNodes as $child) {
            if (
                $child instanceof \DOMElement
                && (string) ($child->namespaceURI ?? '') === $namespace
                && $child->localName === $localName
            ) {
                return $child;
            }
        }

        return null;
    }

    private function defaultSettingsXml(): string
    {
        return self::xmlDeclaration()
            . '<w:settings xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:r="' . self::NS_R . '" xmlns:sl="http://schemas.openxmlformats.org/schemaLibrary/2006/main" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="' . self::NS_W . '" xmlns:w10="urn:schemas-microsoft-com:office:word">'
            . '<w:zoom w:percent="100"/>'
            . '<w:embedSystemFonts/>'
            . '<w:proofState w:grammar="clean" w:spelling="clean"/>'
            . '<w:stylePaneFormatFilter w:val="0004"/>'
            . '<w:doNotTrackMoves/>'
            . '<w:defaultTabStop w:val="720"/>'
            . '<w:drawingGridHorizontalSpacing w:val="360"/>'
            . '<w:drawingGridVerticalSpacing w:val="360"/>'
            . '<w:displayHorizontalDrawingGridEvery w:val="0"/>'
            . '<w:displayVerticalDrawingGridEvery w:val="0"/>'
            . '<w:characterSpacingControl w:val="doNotCompress"/>'
            . '<w:savePreviewPicture/>'
            . "<w:rsids>\n  </w:rsids>"
            . '<w:themeFontLang w:val="en-US"/>'
            . '<w:clrSchemeMapping w:bg1="light1" w:t1="dark1" w:bg2="light2" w:t2="dark2" w:accent1="accent1" w:accent2="accent2" w:accent3="accent3" w:accent4="accent4" w:accent5="accent5" w:accent6="accent6" w:hyperlink="hyperlink" w:followedHyperlink="followedHyperlink"/>'
            . '<w:decimalSymbol w:val="."/>'
            . '<w:listSeparator w:val=","/>'
            . '</w:settings>'
            . "\n";
    }

    private static function escText(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function escAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function isSafeRawOpenXmlFragment(string $xml): bool
    {
        $trimmed = trim($xml);
        if ($trimmed === '' || str_contains($trimmed, '<?') || stripos($trimmed, '<!DOCTYPE') !== false) {
            return false;
        }

        return preg_match('/^<\\/?w:[A-Za-z][A-Za-z0-9_.:-]*(\\s|>|\\/)/', $trimmed) === 1;
    }

    private static function isSafeRawOpenXmlInlineFragment(string $xml): bool
    {
        $trimmed = trim($xml);
        if ($trimmed === '' || str_contains($trimmed, '<?') || stripos($trimmed, '<!DOCTYPE') !== false) {
            return false;
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $loaded = $document->loadXML(
            '<w:root xmlns:w="' . self::NS_W . '" xmlns:r="' . self::NS_R . '">' . $trimmed . '</w:root>',
            LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded || !$document->documentElement instanceof \DOMElement) {
            return false;
        }

        $allowedRoots = [
            'bookmarkStart' => true,
            'bookmarkEnd' => true,
            'fldSimple' => true,
            'hyperlink' => true,
            'proofErr' => true,
            'r' => true,
        ];
        $hasElement = false;
        foreach ($document->documentElement->childNodes as $child) {
            if ($child instanceof \DOMText && trim($child->wholeText) === '') {
                continue;
            }
            if (!$child instanceof \DOMElement) {
                return false;
            }
            if ($child->namespaceURI !== self::NS_W || !isset($allowedRoots[$child->localName])) {
                return false;
            }
            $hasElement = true;
        }

        return $hasElement;
    }
}
