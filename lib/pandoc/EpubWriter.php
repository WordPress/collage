<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubWriter
{
    private const PACKAGE_PATH = 'EPUB/package.opf';
    private const NAV_PATH = 'EPUB/nav.xhtml';
    private const NCX_PATH = 'EPUB/toc.ncx';
    private const CHAPTER_PATH = 'EPUB/text/chapter.xhtml';
    private const TITLE_PAGE_PATH = 'EPUB/text/title_page.xhtml';
    private const STYLESHEET_PATH = 'EPUB/styles/stylesheet.css';

    /**
     * @param array{modified?: string, date?: string, title?: string, author?: string, lang?: string, identifier?: string, css?: string|list<string>, stylesheet?: string|list<string>, stylesheets?: string|list<string>, epubStylesheets?: string|list<string>, cssResources?: array<string, string|array{contents?:string, data?:string, mimeType?:string|null}>, stylesheetResources?: array<string, string|array{contents?:string, data?:string, mimeType?:string|null}>, pageProgressionDirection?: string, epubPageDirection?: string, pageDirection?: string, writerEpubTitlePage?: bool|string|int, epubTitlePage?: bool|string|int, titlePage?: bool|string|int, writerSplitLevel?: int|string|bool, splitLevel?: int|string|bool, epubSplitLevel?: int|string|bool, epubChapterLevel?: int|string|bool, mediaResources?: array<string, string|array{contents?:string, data?:string, mimeType?:string|null}>, resources?: array<string, string|array{contents?:string, data?:string, mimeType?:string|null}>, resourceMap?: array<string, string|array{contents?:string, data?:string, mimeType?:string|null}>, media?: array<string, string|array{contents?:string, data?:string, mimeType?:string|null}>, coverImage?: string, cover-image?: string, epubCoverImage?: string, epub-cover-image?: string, epubCoverImagePath?: string} $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    public function write(AstNode $document): string
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('EPUB writer expects a document node');
        }

        $media = $this->withMediaResources($document);
        $document = $media['document'];
        $metadata = $this->metadata($document);
        $stylesheets = $this->stylesheets($document);
        $chapterSet = $this->chapters($document, $metadata, $stylesheets);
        $coverPage = $this->coverPage($metadata, $media['entries'], $stylesheets);
        $titlePage = $this->titlePage($metadata, $stylesheets);

        $parts = [
            ['name' => 'mimetype', 'data' => EpubPackage::EPUB_MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/container.xml', 'data' => $this->containerXml()],
            ['name' => self::PACKAGE_PATH, 'data' => $this->packageOpf($metadata, $coverPage, $titlePage, $chapterSet['chapters'], $media['entries'], $stylesheets)],
            ['name' => self::NCX_PATH, 'data' => $this->tocNcx($document, $metadata, $titlePage, $chapterSet['headingHrefs'], $chapterSet['defaultHref'])],
            ['name' => self::NAV_PATH, 'data' => $this->navXhtml($document, $metadata, $coverPage, $titlePage, $chapterSet['headingHrefs'], $chapterSet['defaultHref'], $stylesheets)],
        ];
        if ($coverPage !== null) {
            $parts[] = ['name' => $coverPage['packagePath'], 'data' => $coverPage['contents']];
        }
        if ($titlePage !== null) {
            $parts[] = ['name' => $titlePage['packagePath'], 'data' => $titlePage['contents']];
        }
        foreach ($chapterSet['chapters'] as $chapter) {
            $parts[] = ['name' => $chapter['packagePath'], 'data' => $chapter['contents']];
        }
        foreach ($media['entries'] as $entry) {
            $parts[] = ['name' => $entry['packagePath'], 'data' => $entry['contents']];
        }
        foreach ($stylesheets as $stylesheet) {
            $parts[] = ['name' => $stylesheet['packagePath'], 'data' => $stylesheet['contents']];
        }

        return ZipPackage::build($parts);
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(AstNode $document): array
    {
        $meta = $document->attr('meta', []);
        $meta = is_array($meta) ? $meta : [];

        $titleRecords = [];
        $optionTitle = $this->optionString('title');
        if ($optionTitle !== null) {
            $titleRecords = [['text' => $optionTitle]];
        } else {
            $titleRecords = $this->metaRecordList($meta, 'title', ['file-as', 'type']);
        }
        $titleInlines = $this->metaInlinesText($meta, 'titleInlines');
        $explicitTitle = $this->firstRecordText($titleRecords) ?? $titleInlines;
        $title = $explicitTitle
            ?? $this->firstHeadingText($document)
            ?? 'Untitled';
        if ($titleRecords === []) {
            $titleRecords = [['text' => $title]];
        }

        $creatorRecords = [];
        $optionAuthor = $this->optionString('author');
        if ($optionAuthor !== null) {
            $creatorRecords = [['text' => $optionAuthor]];
        } else {
            $creatorRecords = $this->metaRecordList($meta, 'creator', ['file-as', 'role']);
            if ($creatorRecords === []) {
                $creatorRecords = $this->metaRecordList($meta, 'author', ['file-as', 'role']);
            }
        }
        $explicitAuthor = $this->firstRecordText($creatorRecords);
        $author = $explicitAuthor ?? 'Port Libs';
        if ($creatorRecords === []) {
            $creatorRecords = [['text' => $author]];
        }

        $lang = $this->optionString('lang')
            ?? $this->metaString($meta, ['lang', 'language'])
            ?? 'en';
        $modified = $this->optionString('modified')
            ?? $this->metaString($meta, ['modified', 'date'])
            ?? gmdate('Y-m-d\TH:i:s\Z');
        $identifierRecords = [];
        $optionIdentifier = $this->optionString('identifier');
        if ($optionIdentifier !== null) {
            $identifierRecords = [['text' => $optionIdentifier]];
        } else {
            $identifierRecords = $this->metaRecordList($meta, 'identifier', ['scheme', 'type']);
            if ($identifierRecords === []) {
                $identifierRecords = $this->metaRecordList($meta, 'id', ['scheme', 'type']);
            }
        }
        $identifier = $this->firstRecordText($identifierRecords)
            ?? $this->documentIdentifier($document, $title, $author, $lang);
        if ($identifierRecords === []) {
            $identifierRecords = [['text' => $identifier]];
        }

        return [
            'title' => $title,
            'titleRecords' => $this->withRecordText($titleRecords, $title),
            'titleExplicit' => $explicitTitle !== null,
            'author' => $author,
            'creatorRecords' => $this->withRecordText($creatorRecords, $author),
            'authorExplicit' => $explicitAuthor !== null,
            'lang' => $lang,
            'identifier' => $identifier,
            'identifierRecords' => $this->withRecordText($identifierRecords, $identifier),
            'modified' => $this->epubTimestamp($modified),
            'date' => $this->optionString('date') ?? $this->metaString($meta, ['date']),
            'contributorRecords' => $this->metaRecordList($meta, 'contributor', ['file-as', 'role']),
            'subjectRecords' => $this->metaRecordList($meta, 'subject', ['authority', 'term']),
            'description' => $this->metaString($meta, ['description']),
            'type' => $this->metaString($meta, ['type']),
            'format' => $this->metaString($meta, ['format']),
            'publisher' => $this->metaString($meta, ['publisher']),
            'source' => $this->metaString($meta, ['source']),
            'relation' => $this->metaString($meta, ['relation']),
            'coverage' => $this->metaString($meta, ['coverage']),
            'rights' => $this->metaString($meta, ['rights']),
            'belongsToCollection' => $this->metaString($meta, ['belongs-to-collection', 'belongsToCollection']),
            'groupPosition' => $this->metaString($meta, ['group-position', 'groupPosition']),
            'pageProgressionDirection' => $this->pageProgressionDirection($meta),
            'accessModes' => $this->metaStringList($meta, ['accessModes', 'accessMode']) ?: ['textual'],
            'accessModeSufficient' => $this->metaStringList($meta, ['accessModeSufficient']) ?: ['textual'],
            'accessibilityFeatures' => $this->metaStringList($meta, ['accessibilityFeatures']) ?: ['alternativeText', 'readingOrder', 'structuralNavigation', 'tableOfContents'],
            'accessibilityHazards' => $this->metaStringList($meta, ['accessibilityHazards']) ?: ['none'],
            'accessibilitySummary' => $this->metaString($meta, ['accessibilitySummary']),
        ];
    }

    /**
     * @param array<string, mixed> $meta
     * @param list<string> $keys
     */
    private function metaString(array $meta, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $meta)) {
                continue;
            }
            $text = $this->stringFromMetaValue($meta[$key]);
            if ($text !== null) {
                return $text;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $meta
     * @param list<string> $keys
     * @return list<string>
     */
    private function metaStringList(array $meta, array $keys): array
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $meta)) {
                continue;
            }

            $values = $this->stringListFromMetaValue($meta[$key]);
            if ($values !== []) {
                return $values;
            }
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function stringListFromMetaValue(mixed $value): array
    {
        if (is_array($value) && array_is_list($value)) {
            $values = [];
            foreach ($value as $item) {
                $text = $this->stringFromMetaValue($item);
                if ($text !== null) {
                    $values[] = $text;
                }
            }

            return $values;
        }

        $text = $this->stringFromMetaValue($value);

        return $text === null ? [] : [$text];
    }

    private function stringFromMetaValue(mixed $value): ?string
    {
        if (is_string($value) || is_int($value) || is_float($value)) {
            $text = trim((string) $value);

            return $text === '' ? null : $text;
        }
        if ($value instanceof AstNode) {
            $text = $this->plainBlockText($value);

            return $text === '' ? null : $text;
        }
        if (!is_array($value)) {
            return null;
        }
        if (array_is_list($value)) {
            $allAstNodes = $value !== [] && array_reduce(
                $value,
                static fn (bool $carry, mixed $item): bool => $carry && $item instanceof AstNode,
                true
            );
            if ($allAstNodes) {
                $text = trim($this->plainInlineText($value));

                return $text === '' ? null : $text;
            }

            foreach ($value as $item) {
                $text = $this->stringFromMetaValue($item);
                if ($text !== null) {
                    return $text;
                }
            }

            return null;
        }

        foreach (['text', 'value', 'content', 'title', 'name'] as $key) {
            $text = $this->stringFromMetaValue($value[$key] ?? null);
            if ($text !== null) {
                return $text;
            }
        }

        foreach ($value as $item) {
            $text = $this->stringFromMetaValue($item);
            if ($text !== null) {
                return $text;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $meta
     * @param list<string> $fields
     * @return list<array<string, string>>
     */
    private function metaRecordList(array $meta, string $key, array $fields = []): array
    {
        if (!array_key_exists($key, $meta)) {
            return [];
        }

        return $this->recordsFromMetaValue($meta[$key], $fields);
    }

    /**
     * @param list<string> $fields
     * @return list<array<string, string>>
     */
    private function recordsFromMetaValue(mixed $value, array $fields): array
    {
        if (is_array($value) && array_is_list($value)) {
            $records = [];
            foreach ($value as $item) {
                $record = $this->recordFromMetaValue($item, $fields);
                if ($record !== null) {
                    $records[] = $record;
                }
            }

            return $records;
        }

        $record = $this->recordFromMetaValue($value, $fields);

        return $record === null ? [] : [$record];
    }

    /**
     * @param list<string> $fields
     * @return array<string, string>|null
     */
    private function recordFromMetaValue(mixed $value, array $fields): ?array
    {
        $text = $this->stringFromMetaValue($value);
        if ($text === null) {
            return null;
        }

        $record = ['text' => $text];
        if (!is_array($value) || array_is_list($value)) {
            return $record;
        }

        foreach ($fields as $field) {
            $fieldValue = $this->recordFieldValue($value, $field);
            if ($fieldValue !== null) {
                $record[$field] = $fieldValue;
            }
        }

        return $record;
    }

    /**
     * @param array<string, mixed> $map
     */
    private function recordFieldValue(array $map, string $field): ?string
    {
        $camel = preg_replace_callback(
            '/-([a-z])/',
            static fn (array $match): string => strtoupper($match[1]),
            $field
        ) ?? $field;
        $snake = str_replace('-', '_', $field);

        foreach (array_unique([$field, $camel, $snake]) as $key) {
            if (!array_key_exists($key, $map)) {
                continue;
            }

            return $this->stringFromMetaValue($map[$key]);
        }

        return null;
    }

    /**
     * @param list<array<string, string>> $records
     */
    private function firstRecordText(array $records): ?string
    {
        foreach ($records as $record) {
            if (($record['text'] ?? '') !== '') {
                return $record['text'];
            }
        }

        return null;
    }

    /**
     * @param list<array<string, string>> $records
     * @return list<array<string, string>>
     */
    private function withRecordText(array $records, string $text): array
    {
        if ($records === []) {
            return [['text' => $text]];
        }

        $records[0]['text'] = $text;

        return $records;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function pageProgressionDirection(array $meta): ?string
    {
        $value = $this->optionStringFromKeys(['pageProgressionDirection', 'epubPageDirection', 'pageDirection'])
            ?? $this->metaString($meta, ['page-progression-direction', 'pageProgressionDirection']);
        if ($value === null) {
            return null;
        }

        $normalized = strtolower($value);

        return in_array($normalized, ['ltr', 'rtl', 'default'], true) ? $normalized : null;
    }

    /**
     * @param list<string> $keys
     */
    private function optionStringFromKeys(array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->optionString($key);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function metaInlinesText(array $meta, string $key): ?string
    {
        $value = $meta[$key] ?? null;
        if (!is_array($value)) {
            return null;
        }

        $text = $this->plainInlineText($value);

        return $text === '' ? null : $text;
    }

    private function optionString(string $key): ?string
    {
        $value = $this->options[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function firstHeadingText(AstNode $document): ?string
    {
        foreach ($document->children as $child) {
            if ($child->type !== 'heading') {
                continue;
            }
            $text = $this->plainBlockText($child);

            return $text === '' ? null : $text;
        }

        return null;
    }

    private function documentIdentifier(AstNode $document, string $title, string $author, string $lang): string
    {
        $seed = $title . "\n" . $author . "\n" . $lang . "\n" . $this->plainBlockText($document);
        $hex = substr(hash('sha256', $seed), 0, 32);

        return sprintf(
            'urn:uuid:%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    private function epubTimestamp(string $value): string
    {
        $trimmed = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $trimmed) === 1) {
            return $trimmed;
        }
        $timestamp = strtotime($trimmed);

        return $timestamp === false ? gmdate('Y-m-d\TH:i:s\Z') : gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }

    /**
     * @param array<string, mixed> $metadata
     * @param list<array{id:string, href:string, packagePath:string, contents:string, source:?string}> $stylesheets
     */
    private function chapterXhtml(AstNode $document, array $metadata, array $stylesheets): string
    {
        $body = trim((new HtmlWriter([
            'writerHTMLMathMethod' => 'mathml',
            'writerWrapText' => 'preserve',
        ]))->write($document));
        if ($body === '') {
            $body = '<p></p>';
        }
        $stylesheetLinks = $this->stylesheetLinks($stylesheets, true);

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<!DOCTYPE html>' . "\n"
            . '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="' . $this->esc($metadata['lang']) . '" lang="' . $this->esc($metadata['lang']) . '">' . "\n"
            . '<head>' . "\n"
            . '  <title>' . $this->esc($metadata['title']) . '</title>' . "\n"
            . $stylesheetLinks
            . '</head>' . "\n"
            . '<body>' . "\n"
            . $body . "\n"
            . '</body>' . "\n"
            . '</html>' . "\n";
    }

    /**
     * @param array<string, mixed> $metadata
     * @param list<array{id:string, href:string, packagePath:string, contents:string, source:?string}> $stylesheets
     * @return array{
     *     chapters:list<array{id:string, href:string, packagePath:string, contents:string}>,
     *     headingHrefs:array<int, string>,
     *     defaultHref:string
     * }
     */
    private function chapters(AstNode $document, array $metadata, array $stylesheets): array
    {
        $chunks = $this->chapterChunks($document);
        $split = count($chunks) > 1;
        $chapterSpecs = [];
        $headingHrefs = [];
        $idChapterFiles = [];

        foreach ($chunks as $index => $children) {
            $fileName = $split ? 'ch' . ($index + 1) . '.xhtml' : basename(self::CHAPTER_PATH);
            $href = 'text/' . $fileName;
            $chapterSpecs[] = [
                'id' => $split ? 'chapter' . ($index + 1) : 'chapter',
                'href' => $href,
                'packagePath' => 'EPUB/' . $href,
                'fileName' => $fileName,
                'children' => $children,
            ];
            foreach ($children as $child) {
                $this->collectHeadingHrefs($child, $href, $headingHrefs);
                $this->collectNodeIdChapterFiles($child, $fileName, $idChapterFiles);
            }
        }

        $chapters = [];
        foreach ($chapterSpecs as $chapter) {
            $children = [];
            foreach ($chapter['children'] as $child) {
                $children[] = $this->rewriteInternalChapterLinks($child, $chapter['fileName'], $idChapterFiles);
            }
            $chapterDocument = new AstNode('document', $document->attrs, $children);
            $chapters[] = [
                'id' => $chapter['id'],
                'href' => $chapter['href'],
                'packagePath' => $chapter['packagePath'],
                'contents' => $this->chapterXhtml($chapterDocument, $metadata, $stylesheets),
            ];
        }

        return [
            'chapters' => $chapters,
            'headingHrefs' => $headingHrefs,
            'defaultHref' => $chapters[0]['href'],
        ];
    }

    /**
     * @return list<list<AstNode>>
     */
    private function chapterChunks(AstNode $document): array
    {
        $splitLevel = $this->writerSplitLevel();
        if ($splitLevel < 1) {
            return [$document->children];
        }

        $chunks = [];
        $current = [];
        foreach ($document->children as $child) {
            if ($child->type === 'heading' && max(1, min(6, (int) $child->attr('level', 1))) <= $splitLevel) {
                if ($current !== []) {
                    $chunks[] = $current;
                }
                $current = [$child];
                continue;
            }
            $current[] = $child;
        }
        if ($current !== []) {
            $chunks[] = $current;
        }

        return $chunks === [] ? [[]] : $chunks;
    }

    private function writerSplitLevel(): int
    {
        foreach (['writerSplitLevel', 'splitLevel', 'epubSplitLevel', 'epubChapterLevel'] as $key) {
            if (!array_key_exists($key, $this->options)) {
                continue;
            }

            $value = $this->options[$key];
            if ($value === false || $value === null) {
                return 0;
            }
            if ($value === true) {
                return 1;
            }
            if (is_int($value) || is_float($value)) {
                return max(0, (int) $value);
            }
            if (is_string($value) && is_numeric(trim($value))) {
                return max(0, (int) trim($value));
            }
        }

        return 1;
    }

    /**
     * @param array<int, string> $headingHrefs
     */
    private function collectHeadingHrefs(AstNode $node, string $href, array &$headingHrefs): void
    {
        if ($node->type === 'heading') {
            $headingHrefs[spl_object_id($node)] = $href;
        }
        foreach ($node->children as $child) {
            $this->collectHeadingHrefs($child, $href, $headingHrefs);
        }
    }

    /**
     * @param array<string, string> $idChapterFiles
     */
    private function collectNodeIdChapterFiles(AstNode $node, string $fileName, array &$idChapterFiles): void
    {
        $id = trim((string) $node->attr('id', ''));
        if ($id !== '' && !array_key_exists($id, $idChapterFiles)) {
            $idChapterFiles[$id] = $fileName;
        }
        foreach ($node->children as $child) {
            $this->collectNodeIdChapterFiles($child, $fileName, $idChapterFiles);
        }
    }

    /**
     * @param array<string, string> $idChapterFiles
     */
    private function rewriteInternalChapterLinks(AstNode $node, string $currentFileName, array $idChapterFiles): AstNode
    {
        $changed = false;
        $children = [];
        foreach ($node->children as $child) {
            $rewritten = $this->rewriteInternalChapterLinks($child, $currentFileName, $idChapterFiles);
            $children[] = $rewritten;
            $changed = $changed || $rewritten !== $child;
        }

        $attrs = $node->attrs;
        if ($node->type === 'link') {
            $urlKey = array_key_exists('url', $attrs) || !array_key_exists('href', $attrs) ? 'url' : 'href';
            $url = (string) ($attrs[$urlKey] ?? '');
            if (preg_match('/^#(.+)$/', $url, $match) === 1) {
                $fragment = $match[1];
                $targetId = rawurldecode($fragment);
                $targetFileName = $idChapterFiles[$targetId] ?? null;
                if ($targetFileName !== null && $targetFileName !== $currentFileName) {
                    $attrs[$urlKey] = $targetFileName . '#' . $fragment;
                    $changed = true;
                }
            }
        }

        return $changed ? new AstNode($node->type, $attrs, $children) : $node;
    }

    /**
     * @param array<string, mixed> $metadata
     * @param list<array{id:string, href:string, packagePath:string, contents:string, source:?string}> $stylesheets
     * @return array{id:string, href:string, packagePath:string, contents:string, linear:bool}|null
     */
    private function titlePage(array $metadata, array $stylesheets): ?array
    {
        if (!$this->epubTitlePageEnabled()) {
            return null;
        }

        return [
            'id' => 'title_page_xhtml',
            'href' => 'text/' . basename(self::TITLE_PAGE_PATH),
            'packagePath' => self::TITLE_PAGE_PATH,
            'contents' => $this->titlePageXhtml($metadata, $stylesheets),
            'linear' => $metadata['titleExplicit'],
        ];
    }

    private function epubTitlePageEnabled(): bool
    {
        foreach (['writerEpubTitlePage', 'epubTitlePage', 'titlePage'] as $key) {
            if (array_key_exists($key, $this->options)) {
                return $this->optionBool($this->options[$key], true);
            }
        }

        return true;
    }

    private function optionBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value !== 0;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off', 'none'], true)) {
                return false;
            }
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $metadata
     * @param list<array{id:string, href:string, packagePath:string, mediaType:string, contents:string, properties:list<string>}> $mediaEntries
     * @param list<array{id:string, href:string, packagePath:string, contents:string, source:?string}> $stylesheets
     * @return array{id:string, href:string, packagePath:string, contents:string}|null
     */
    private function coverPage(array $metadata, array $mediaEntries, array $stylesheets): ?array
    {
        $coverEntry = null;
        foreach ($mediaEntries as $entry) {
            if (in_array('cover-image', $entry['properties'], true)) {
                $coverEntry = $entry;
                break;
            }
        }
        if ($coverEntry === null) {
            return null;
        }

        return [
            'id' => 'cover_xhtml',
            'href' => 'text/cover.xhtml',
            'packagePath' => 'EPUB/text/cover.xhtml',
            'contents' => $this->coverPageXhtml($metadata, $coverEntry, $stylesheets),
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array{id:string, href:string, packagePath:string, mediaType:string, contents:string, properties:list<string>} $coverEntry
     * @param list<array{id:string, href:string, packagePath:string, contents:string, source:?string}> $stylesheets
     */
    private function coverPageXhtml(array $metadata, array $coverEntry, array $stylesheets): string
    {
        $src = $this->coverPageImageHref($coverEntry['href']);
        $stylesheetLinks = $this->stylesheetLinks($stylesheets, true);

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<!DOCTYPE html>' . "\n"
            . '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" xml:lang="' . $this->esc($metadata['lang']) . '" lang="' . $this->esc($metadata['lang']) . '">' . "\n"
            . '<head>' . "\n"
            . '  <title>' . $this->esc($metadata['title']) . '</title>' . "\n"
            . $stylesheetLinks
            . '</head>' . "\n"
            . '<body epub:type="cover">' . "\n"
            . '  <section id="cover" epub:type="cover" role="doc-cover">' . "\n"
            . '    <img src="' . $this->esc($src) . '" alt="Cover" />' . "\n"
            . '  </section>' . "\n"
            . '</body>' . "\n"
            . '</html>' . "\n";
    }

    private function coverPageImageHref(string $href): string
    {
        return str_starts_with($href, 'text/') ? substr($href, 5) : $href;
    }

    /**
     * @param array<string, mixed> $metadata
     * @param list<array{id:string, href:string, packagePath:string, contents:string, source:?string}> $stylesheets
     */
    private function titlePageXhtml(array $metadata, array $stylesheets): string
    {
        $author = $metadata['authorExplicit']
            ? '    <p class="author">' . $this->esc($metadata['author']) . '</p>' . "\n"
            : '';
        $stylesheetLinks = $this->stylesheetLinks($stylesheets, true);

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<!DOCTYPE html>' . "\n"
            . '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" xml:lang="' . $this->esc($metadata['lang']) . '" lang="' . $this->esc($metadata['lang']) . '">' . "\n"
            . '<head>' . "\n"
            . '  <title>' . $this->esc($metadata['title']) . '</title>' . "\n"
            . $stylesheetLinks
            . '</head>' . "\n"
            . '<body epub:type="frontmatter">' . "\n"
            . '  <section id="titlepage" epub:type="titlepage" role="doc-titlepage">' . "\n"
            . '    <h1 class="title">' . $this->esc($metadata['title']) . '</h1>' . "\n"
            . $author
            . '  </section>' . "\n"
            . '</body>' . "\n"
            . '</html>' . "\n";
    }

    /**
     * @param array<string, mixed> $metadata
     * @return list<string>
     */
    private function metadataOpfNodes(array $metadata): array
    {
        $nodes = [];
        $identifierRecords = is_array($metadata['identifierRecords'] ?? null) ? array_values($metadata['identifierRecords']) : [];
        foreach ($identifierRecords as $index => $record) {
            if (!is_array($record) || !is_string($record['text'] ?? null) || $record['text'] === '') {
                continue;
            }

            $nodes[] = $this->dcNode('identifier', $record['text'], [
                'id' => $index === 0 ? 'bookid' : 'epub-identifier-' . ($index + 1),
            ]);
        }

        $titleRecords = is_array($metadata['titleRecords'] ?? null) ? array_values($metadata['titleRecords']) : [];
        foreach ($titleRecords as $index => $record) {
            if (!is_array($record) || !is_string($record['text'] ?? null) || $record['text'] === '') {
                continue;
            }

            $id = 'epub-title-' . ($index + 1);
            $nodes[] = $this->dcNode('title', $record['text'], ['id' => $id]);
            if (is_string($record['file-as'] ?? null) && $record['file-as'] !== '') {
                $nodes[] = $this->opfMetaNode($record['file-as'], ['refines' => '#' . $id, 'property' => 'file-as']);
            }
            if (is_string($record['type'] ?? null) && $record['type'] !== '') {
                $nodes[] = $this->opfMetaNode($record['type'], ['refines' => '#' . $id, 'property' => 'title-type']);
            }
        }

        if (is_string($metadata['date'] ?? null) && $metadata['date'] !== '') {
            $nodes[] = $this->dcNode('date', $metadata['date'], ['id' => 'epub-date']);
        }

        $creatorRecords = is_array($metadata['creatorRecords'] ?? null) ? array_values($metadata['creatorRecords']) : [];
        foreach ($creatorRecords as $index => $record) {
            if (!is_array($record) || !is_string($record['text'] ?? null) || $record['text'] === '') {
                continue;
            }

            $id = 'epub-creator-' . ($index + 1);
            $nodes[] = $this->dcNode('creator', $record['text'], ['id' => $id]);
            if (is_string($record['file-as'] ?? null) && $record['file-as'] !== '') {
                $nodes[] = $this->opfMetaNode($record['file-as'], ['refines' => '#' . $id, 'property' => 'file-as']);
            }
            if (is_string($record['role'] ?? null) && $record['role'] !== '') {
                $nodes[] = $this->opfMetaNode($record['role'], ['refines' => '#' . $id, 'property' => 'role', 'scheme' => 'marc:relators']);
            }
        }

        $contributorRecords = is_array($metadata['contributorRecords'] ?? null) ? array_values($metadata['contributorRecords']) : [];
        foreach ($contributorRecords as $index => $record) {
            if (!is_array($record) || !is_string($record['text'] ?? null) || $record['text'] === '') {
                continue;
            }

            $id = 'epub-contributor-' . ($index + 1);
            $nodes[] = $this->dcNode('contributor', $record['text'], ['id' => $id]);
            if (is_string($record['file-as'] ?? null) && $record['file-as'] !== '') {
                $nodes[] = $this->opfMetaNode($record['file-as'], ['refines' => '#' . $id, 'property' => 'file-as']);
            }
            if (is_string($record['role'] ?? null) && $record['role'] !== '') {
                $nodes[] = $this->opfMetaNode($record['role'], ['refines' => '#' . $id, 'property' => 'role', 'scheme' => 'marc:relators']);
            }
        }

        $nodes[] = $this->dcNode('language', (string) $metadata['lang']);

        $subjectRecords = is_array($metadata['subjectRecords'] ?? null) ? array_values($metadata['subjectRecords']) : [];
        foreach ($subjectRecords as $index => $record) {
            if (!is_array($record) || !is_string($record['text'] ?? null) || $record['text'] === '') {
                continue;
            }

            $id = 'subject-' . ($index + 1);
            $nodes[] = $this->dcNode('subject', $record['text'], ['id' => $id]);
            if (is_string($record['authority'] ?? null) && $record['authority'] !== '') {
                $nodes[] = $this->opfMetaNode($record['authority'], ['refines' => '#' . $id, 'property' => 'authority']);
            }
            if (is_string($record['term'] ?? null) && $record['term'] !== '') {
                $nodes[] = $this->opfMetaNode($record['term'], ['refines' => '#' . $id, 'property' => 'term']);
            }
        }

        foreach (['description', 'type', 'format', 'publisher', 'source', 'relation', 'coverage', 'rights'] as $name) {
            if (is_string($metadata[$name] ?? null) && $metadata[$name] !== '') {
                $nodes[] = $this->dcNode($name, $metadata[$name]);
            }
        }

        $nodes[] = $this->opfMetaNode((string) $metadata['modified'], ['property' => 'dcterms:modified']);

        if (is_string($metadata['belongsToCollection'] ?? null) && $metadata['belongsToCollection'] !== '') {
            $collectionId = 'epub-collection-1';
            $nodes[] = $this->opfMetaNode($metadata['belongsToCollection'], ['property' => 'belongs-to-collection', 'id' => $collectionId]);
            $nodes[] = $this->opfMetaNode('series', ['refines' => '#' . $collectionId, 'property' => 'collection-type']);
            if (is_string($metadata['groupPosition'] ?? null) && $metadata['groupPosition'] !== '') {
                $nodes[] = $this->opfMetaNode($metadata['groupPosition'], ['refines' => '#' . $collectionId, 'property' => 'group-position']);
            }
        }

        foreach ([
            'schema:accessMode' => $metadata['accessModes'] ?? [],
            'schema:accessModeSufficient' => $metadata['accessModeSufficient'] ?? [],
            'schema:accessibilityFeature' => $metadata['accessibilityFeatures'] ?? [],
            'schema:accessibilityHazard' => $metadata['accessibilityHazards'] ?? [],
        ] as $property => $values) {
            if (!is_array($values)) {
                continue;
            }
            foreach ($values as $value) {
                if (is_string($value) && $value !== '') {
                    $nodes[] = $this->opfMetaNode($value, ['property' => $property]);
                }
            }
        }

        if (is_string($metadata['accessibilitySummary'] ?? null) && $metadata['accessibilitySummary'] !== '') {
            $nodes[] = $this->opfMetaNode($metadata['accessibilitySummary'], ['property' => 'schema:accessibilitySummary']);
        }

        return $nodes;
    }

    /**
     * @param array<string, string> $attrs
     */
    private function dcNode(string $name, string $text, array $attrs = []): string
    {
        return '    <dc:' . $name . $this->xmlAttributes($attrs) . '>' . $this->esc($text) . '</dc:' . $name . '>' . "\n";
    }

    /**
     * @param array<string, string> $attrs
     */
    private function opfMetaNode(string $text, array $attrs = []): string
    {
        return '    <meta' . $this->xmlAttributes($attrs) . '>' . $this->esc($text) . '</meta>' . "\n";
    }

    /**
     * @param array<string, string> $attrs
     */
    private function xmlAttributes(array $attrs): string
    {
        $parts = [];
        foreach ($attrs as $name => $value) {
            if ($value === '') {
                continue;
            }
            $parts[] = $name . '="' . $this->esc($value) . '"';
        }

        return $parts === [] ? '' : ' ' . implode(' ', $parts);
    }

    private function containerXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">' . "\n"
            . '  <rootfiles>' . "\n"
            . '    <rootfile full-path="EPUB/package.opf" media-type="application/oebps-package+xml"/>' . "\n"
            . '  </rootfiles>' . "\n"
            . '</container>' . "\n";
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array{id:string, href:string, packagePath:string, contents:string}|null $coverPage
     * @param array{id:string, href:string, packagePath:string, contents:string, linear:bool}|null $titlePage
     * @param list<array{id:string, href:string, packagePath:string, contents:string}> $chapters
     * @param list<array{id:string, href:string, packagePath:string, mediaType:string, contents:string, properties:list<string>}> $mediaEntries
     * @param list<array{id:string, href:string, packagePath:string, contents:string, source:?string}> $stylesheets
     */
    private function packageOpf(array $metadata, ?array $coverPage, ?array $titlePage, array $chapters, array $mediaEntries, array $stylesheets): string
    {
        $metadataNodes = implode('', $this->metadataOpfNodes($metadata));
        $coverPageItem = '';
        $coverPageSpineItem = '';
        if ($coverPage !== null) {
            $coverPageItem = $this->xhtmlManifestItem($coverPage);
            $coverPageSpineItem = '    <itemref idref="' . $this->esc($coverPage['id']) . '"/>' . "\n";
        }
        $titlePageItem = '';
        $titlePageSpineItem = '';
        if ($titlePage !== null) {
            $titlePageItem = $this->xhtmlManifestItem($titlePage);
            $titlePageSpineItem = '    <itemref idref="' . $this->esc($titlePage['id']) . '" linear="' . ($titlePage['linear'] ? 'yes' : 'no') . '"/>' . "\n";
        }

        $chapterItems = '';
        $spineItems = '';
        foreach ($chapters as $chapter) {
            $chapterItems .= $this->xhtmlManifestItem($chapter);
            $spineItems .= '    <itemref idref="' . $this->esc($chapter['id']) . '"/>' . "\n";
        }

        $mediaItems = '';
        foreach ($mediaEntries as $entry) {
            $mediaItems .= '    <item id="' . $this->esc($entry['id']) . '" href="' . $this->esc($entry['href']) . '" media-type="' . $this->esc($entry['mediaType']) . '"';
            if ($entry['properties'] !== []) {
                $mediaItems .= ' properties="' . $this->esc(implode(' ', $entry['properties'])) . '"';
            }
            $mediaItems .= '/>' . "\n";
        }
        $stylesheetItems = '';
        foreach ($stylesheets as $stylesheet) {
            $stylesheetItems .= '    <item id="' . $this->esc($stylesheet['id']) . '" href="' . $this->esc($stylesheet['href']) . '" media-type="text/css"/>' . "\n";
        }
        $spineAttrs = ['toc' => 'ncx'];
        if (is_string($metadata['pageProgressionDirection'] ?? null) && $metadata['pageProgressionDirection'] !== '') {
            $spineAttrs['page-progression-direction'] = $metadata['pageProgressionDirection'];
        }
        $guide = $this->packageGuide($metadata, $coverPage);

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid" xml:lang="' . $this->esc($metadata['lang']) . '">' . "\n"
            . '  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:opf="http://www.idpf.org/2007/opf">' . "\n"
            . $metadataNodes
            . '  </metadata>' . "\n"
            . '  <manifest>' . "\n"
            . '    <item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>' . "\n"
            . '    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>' . "\n"
            . $coverPageItem
            . $titlePageItem
            . $chapterItems
            . $stylesheetItems
            . $mediaItems
            . '  </manifest>' . "\n"
            . '  <spine' . $this->xmlAttributes($spineAttrs) . '>' . "\n"
            . $coverPageSpineItem
            . $titlePageSpineItem
            . $spineItems
            . '  </spine>' . "\n"
            . $guide
            . '</package>' . "\n";
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array{id:string, href:string, packagePath:string, contents:string}|null $coverPage
     */
    private function packageGuide(array $metadata, ?array $coverPage): string
    {
        $references = [
            '    <reference type="toc" title="' . $this->esc((string) $metadata['title']) . '" href="nav.xhtml"/>',
        ];
        if ($coverPage !== null) {
            $references[] = '    <reference type="cover" title="Cover" href="' . $this->esc($coverPage['href']) . '"/>';
        }

        return '  <guide>' . "\n"
            . implode("\n", $references) . "\n"
            . '  </guide>' . "\n";
    }

    /**
     * @param array{id:string, href:string, contents:string} $entry
     */
    private function xhtmlManifestItem(array $entry): string
    {
        $properties = $this->xhtmlManifestProperties($entry['contents']);
        $attrs = [
            'id' => $entry['id'],
            'href' => $entry['href'],
            'media-type' => 'application/xhtml+xml',
        ];
        if ($properties !== []) {
            $attrs['properties'] = implode(' ', $properties);
        }

        return '    <item' . $this->xmlAttributes($attrs) . '/>' . "\n";
    }

    /**
     * @return list<string>
     */
    private function xhtmlManifestProperties(string $contents): array
    {
        $properties = [];
        if (str_contains($contents, '<math')) {
            $properties[] = 'mathml';
        }
        if (str_contains($contents, '<svg')) {
            $properties[] = 'svg';
        }

        return $properties;
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array{id:string, href:string, packagePath:string, contents:string}|null $coverPage
     * @param array{id:string, href:string, packagePath:string, contents:string, linear:bool}|null $titlePage
     * @param array<int, string> $headingHrefs
     * @param list<array{id:string, href:string, packagePath:string, contents:string, source:?string}> $stylesheets
     */
    private function navXhtml(AstNode $document, array $metadata, ?array $coverPage, ?array $titlePage, array $headingHrefs, string $defaultHref, array $stylesheets): string
    {
        $entries = $this->navEntries($document, $metadata['title'], $headingHrefs, $defaultHref);
        $entryIndex = 0;
        $list = $this->renderNavList($entries, $entryIndex, 0, 4);
        $landmarks = $this->renderLandmarksNav($coverPage, $titlePage);
        $stylesheetLinks = $this->stylesheetLinks($stylesheets, false);

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<!DOCTYPE html>' . "\n"
            . '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" xml:lang="' . $this->esc($metadata['lang']) . '" lang="' . $this->esc($metadata['lang']) . '">' . "\n"
            . '<head>' . "\n"
            . '  <title>Contents</title>' . "\n"
            . $stylesheetLinks
            . '</head>' . "\n"
            . '<body>' . "\n"
            . '  <nav epub:type="toc" id="toc">' . "\n"
            . '    <h1>Contents</h1>' . "\n"
            . $list . "\n"
            . '  </nav>' . "\n"
            . $landmarks
            . '</body>' . "\n"
            . '</html>' . "\n";
    }

    /**
     * @param array<int, string> $headingHrefs
     * @return list<array{label:string, href:string, level:int}>
     */
    private function navEntries(AstNode $document, string $title, array $headingHrefs, string $defaultHref): array
    {
        $entries = [];
        foreach ($document->children as $child) {
            if ($child->type !== 'heading') {
                continue;
            }
            $label = $this->plainBlockText($child);
            if ($label === '') {
                continue;
            }
            $id = trim((string) $child->attr('id', ''));
            $href = $headingHrefs[spl_object_id($child)] ?? $defaultHref;
            $entries[] = [
                'label' => $label,
                'href' => $href . ($id === '' ? '' : '#' . $id),
                'level' => max(1, min(6, (int) $child->attr('level', 1))),
            ];
        }

        return $entries === [] ? [['label' => $title, 'href' => $defaultHref, 'level' => 1]] : $entries;
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array{id:string, href:string, packagePath:string, contents:string, linear:bool}|null $titlePage
     * @param array<int, string> $headingHrefs
     */
    private function tocNcx(AstNode $document, array $metadata, ?array $titlePage, array $headingHrefs, string $defaultHref): string
    {
        $entries = $this->navEntries($document, $metadata['title'], $headingHrefs, $defaultHref);
        $entryIndex = 0;
        $navPointIndex = 1;
        $navPoints = [];
        if ($titlePage !== null) {
            $navPoints[] = '    <navPoint id="navPoint-0">' . "\n"
                . '      <navLabel><text>' . $this->esc($metadata['title']) . '</text></navLabel>' . "\n"
                . '      <content src="' . $this->esc($titlePage['href']) . '"/>' . "\n"
                . '    </navPoint>';
        }
        $renderedEntries = $this->renderNcxNavPoints($entries, $entryIndex, 0, $navPointIndex, 4);
        if ($renderedEntries !== '') {
            $navPoints[] = $renderedEntries;
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">' . "\n"
            . '  <head>' . "\n"
            . '    <meta name="dtb:uid" content="' . $this->esc($metadata['identifier']) . '"/>' . "\n"
            . '    <meta name="dtb:depth" content="1"/>' . "\n"
            . '    <meta name="dtb:totalPageCount" content="0"/>' . "\n"
            . '    <meta name="dtb:maxPageNumber" content="0"/>' . "\n"
            . '  </head>' . "\n"
            . '  <docTitle><text>' . $this->esc($metadata['title']) . '</text></docTitle>' . "\n"
            . '  <navMap>' . "\n"
            . implode("\n", $navPoints) . "\n"
            . '  </navMap>' . "\n"
            . '</ncx>' . "\n";
    }

    /**
     * @param list<array{label:string, href:string, level:int}> $entries
     */
    private function renderNcxNavPoints(array $entries, int &$index, int $parentLevel, int &$navPointIndex, int $indent): string
    {
        $lines = [];
        $count = count($entries);
        while ($index < $count) {
            $entry = $entries[$index];
            if ($entry['level'] <= $parentLevel) {
                break;
            }

            $index++;
            $id = 'navPoint-' . $navPointIndex;
            $navPointIndex++;
            $lines[] = str_repeat(' ', $indent) . '<navPoint id="' . $this->esc($id) . '">';
            $lines[] = str_repeat(' ', $indent + 2) . '<navLabel><text>' . $this->esc($entry['label']) . '</text></navLabel>';
            $lines[] = str_repeat(' ', $indent + 2) . '<content src="' . $this->esc($entry['href']) . '"/>';
            if ($index < $count && $entries[$index]['level'] > $entry['level']) {
                $nested = $this->renderNcxNavPoints($entries, $index, $entry['level'], $navPointIndex, $indent + 2);
                if ($nested !== '') {
                    $lines[] = $nested;
                }
            }
            $lines[] = str_repeat(' ', $indent) . '</navPoint>';
        }

        return implode("\n", $lines);
    }

    /**
     * @param array{id:string, href:string, packagePath:string, contents:string}|null $coverPage
     * @param array{id:string, href:string, packagePath:string, contents:string, linear:bool}|null $titlePage
     */
    private function renderLandmarksNav(?array $coverPage, ?array $titlePage): string
    {
        $items = [];
        if ($titlePage !== null) {
            $items[] = '      <li><a href="' . $this->esc($titlePage['href']) . '" epub:type="titlepage">Title Page</a></li>';
        }
        if ($coverPage !== null) {
            $items[] = '      <li><a href="' . $this->esc($coverPage['href']) . '" epub:type="cover">Cover</a></li>';
        }
        $items[] = '      <li><a href="#toc" epub:type="toc">Table of Contents</a></li>';

        return '  <nav epub:type="landmarks" id="landmarks" hidden="hidden">' . "\n"
            . '    <h2>Landmarks</h2>' . "\n"
            . '    <ol>' . "\n"
            . implode("\n", $items) . "\n"
            . '    </ol>' . "\n"
            . '  </nav>' . "\n";
    }

    /**
     * @param list<array{label:string, href:string, level:int}> $entries
     */
    private function renderNavList(array $entries, int &$index, int $parentLevel, int $indent): string
    {
        $lines = [str_repeat(' ', $indent) . '<ol>'];
        $count = count($entries);
        while ($index < $count) {
            $entry = $entries[$index];
            if ($entry['level'] <= $parentLevel) {
                break;
            }

            $index++;
            $lines[] = str_repeat(' ', $indent + 2)
                . '<li><a href="' . $this->esc($entry['href']) . '">' . $this->esc($entry['label']) . '</a>';
            if ($index < $count && $entries[$index]['level'] > $entry['level']) {
                $lines[] = $this->renderNavList($entries, $index, $entry['level'], $indent + 4);
            }
            $lines[] = str_repeat(' ', $indent + 2) . '</li>';
        }
        $lines[] = str_repeat(' ', $indent) . '</ol>';

        return implode("\n", $lines);
    }

    private function stylesheet(): string
    {
        return <<<'CSS'
body {
  font-family: serif;
  line-height: 1.5;
}
table {
  border-collapse: collapse;
}
th,
td {
  border: 1px solid #666;
  padding: 0.25em 0.4em;
}
pre {
  white-space: pre-wrap;
}

CSS;
    }

    /**
     * @return list<array{id:string, href:string, packagePath:string, contents:string, source:?string}>
     */
    private function stylesheets(AstNode $document): array
    {
        $sources = $this->stylesheetSources($document);
        if ($sources === []) {
            return [[
                'id' => 'stylesheet',
                'href' => 'styles/stylesheet.css',
                'packagePath' => self::STYLESHEET_PATH,
                'contents' => $this->stylesheet(),
                'source' => null,
            ]];
        }

        $resources = $this->stylesheetResourcesOption();
        $entries = [];
        foreach ($sources as $index => $source) {
            $number = $index + 1;
            $entries[] = [
                'id' => 'stylesheet' . $number,
                'href' => 'styles/stylesheet' . $number . '.css',
                'packagePath' => 'EPUB/styles/stylesheet' . $number . '.css',
                'contents' => $this->stylesheetContents($source, $resources),
                'source' => $source,
            ];
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function stylesheetSources(AstNode $document): array
    {
        $sources = [];
        $meta = $document->attr('meta', []);
        if (is_array($meta)) {
            $sources = array_merge($sources, $this->metaStringList($meta, ['css', 'stylesheet']));
        }
        $sources = array_merge($sources, $this->optionStringList(['css', 'stylesheet', 'stylesheets', 'epubStylesheets']));

        $deduped = [];
        foreach ($sources as $source) {
            $source = trim($source);
            if ($source === '' || in_array($source, $deduped, true)) {
                continue;
            }
            $deduped[] = $source;
        }

        return $deduped;
    }

    /**
     * @param list<string> $keys
     * @return list<string>
     */
    private function optionStringList(array $keys): array
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $this->options)) {
                continue;
            }

            $values = $this->stringListFromMetaValue($this->options[$key]);
            if ($values !== []) {
                return $values;
            }
        }

        return [];
    }

    /**
     * @return array<string, string|array{contents?:string, data?:string, mimeType?:string|null}>|null
     */
    private function stylesheetResourcesOption(): ?array
    {
        foreach (['stylesheetResources', 'cssResources'] as $key) {
            $value = $this->options[$key] ?? null;
            if (is_array($value)) {
                return $value;
            }
        }

        return $this->mediaResourcesOption();
    }

    /**
     * @param array<string, string|array{contents?:string, data?:string, mimeType?:string|null}>|null $resources
     */
    private function stylesheetContents(string $source, ?array $resources): string
    {
        $resource = $resources === null ? null : $this->mediaResourceForSource($source, $resources);
        if ($resource !== null) {
            return is_array($resource)
                ? (string) ($resource['contents'] ?? $resource['data'] ?? '')
                : (string) $resource;
        }

        if (is_file($source) && is_readable($source)) {
            $contents = file_get_contents($source);

            return $contents === false ? '' : $contents;
        }

        if (str_contains($source, "\n") || str_contains($source, '{') || str_contains($source, '}')) {
            return $source;
        }

        return '/* unresolved stylesheet resource: ' . str_replace('*/', '* /', $source) . ' */' . "\n";
    }

    /**
     * @param list<array{id:string, href:string, packagePath:string, contents:string, source:?string}> $stylesheets
     */
    private function stylesheetLinks(array $stylesheets, bool $fromTextDirectory): string
    {
        $prefix = $fromTextDirectory ? '../' : '';
        $links = '';
        foreach ($stylesheets as $stylesheet) {
            $links .= '  <link rel="stylesheet" type="text/css" href="' . $this->esc($prefix . $stylesheet['href']) . '" />' . "\n";
        }

        return $links;
    }

    /**
     * @return array{
     *     document:AstNode,
     *     entries:list<array{id:string, href:string, packagePath:string, mediaType:string, contents:string, properties:list<string>}>,
     *     diagnostics:list<string>
     * }
     */
    private function withMediaResources(AstNode $document): array
    {
        $bag = new MediaBag();
        $resources = $this->mediaResourcesOption();
        $diagnostics = [];
        $coverImage = $this->coverImageSource($document);
        if ($resources !== null) {
            $this->insertConfiguredCoverResource($bag, $resources, $coverImage, $diagnostics);
            $filled = $bag->fillDocument($document, $resources);
            $document = $filled['document'];
            $diagnostics = array_merge($diagnostics, $filled['diagnostics']);
        } else {
            $this->insertConfiguredCoverResource($bag, null, $coverImage, $diagnostics);
            $this->loadDataUriMedia($document, $bag, $diagnostics);
        }

        if ($bag->directory() === []) {
            return ['document' => $document, 'entries' => [], 'diagnostics' => $diagnostics];
        }

        $extracted = $bag->extractMedia($document, 'media');
        $document = $extracted['document'];
        $diagnostics = array_merge($diagnostics, $extracted['diagnostics']);
        $entries = [];
        $index = 1;
        foreach ($extracted['entries'] as $entry) {
            $path = $this->normalizeMediaPath((string) $entry['path']);
            $properties = [];
            if ($this->isCoverImageEntry($entry, $path, $coverImage)) {
                $properties[] = 'cover-image';
            }
            $entries[] = [
                'id' => 'media' . $index,
                'href' => 'text/' . $path,
                'packagePath' => 'EPUB/text/' . $path,
                'mediaType' => (string) $entry['mimeType'],
                'contents' => (string) $entry['contents'],
                'properties' => $properties,
            ];
            $index++;
        }

        return ['document' => $document, 'entries' => $entries, 'diagnostics' => $diagnostics];
    }

    /**
     * @return array<string, string|array{contents?:string, data?:string, mimeType?:string|null}>|null
     */
    private function mediaResourcesOption(): ?array
    {
        foreach (['mediaResources', 'resources', 'resourceMap', 'media'] as $key) {
            $value = $this->options[$key] ?? null;
            if (is_array($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param list<string> $diagnostics
     */
    private function loadDataUriMedia(AstNode $node, MediaBag $bag, array &$diagnostics): void
    {
        if ($node->type === 'image' || $node->type === 'link') {
            $url = (string) $node->attr('url', $node->attr('src', ''));
            if (str_starts_with($url, 'data:') && !$bag->has($url)) {
                try {
                    $bag->insertDataUri($url);
                    $diagnostics[] = $node->type === 'link'
                        ? 'media-resource-link-loaded:data-uri'
                        : 'media-resource-loaded:data-uri';
                } catch (\InvalidArgumentException) {
                    $diagnostics[] = $node->type === 'link'
                        ? 'media-resource-link-invalid:data-uri'
                        : 'media-resource-invalid:data-uri';
                }
            }
        }

        foreach ($node->children as $child) {
            $this->loadDataUriMedia($child, $bag, $diagnostics);
        }
    }

    private function normalizeMediaPath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        $parts = [];
        foreach (explode('/', $path) as $part) {
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
     * @param array<string, string|array{contents?:string, data?:string, mimeType?:string|null}>|null $resources
     * @param list<string> $diagnostics
     */
    private function insertConfiguredCoverResource(MediaBag $bag, ?array $resources, ?string $coverImage, array &$diagnostics): void
    {
        if ($coverImage === null || $bag->has($coverImage)) {
            return;
        }

        if (str_starts_with($coverImage, 'data:')) {
            try {
                $bag->insertDataUri($coverImage);
                $diagnostics[] = 'media-resource-cover-loaded:data-uri';
            } catch (\InvalidArgumentException) {
                $diagnostics[] = 'media-resource-cover-invalid:data-uri';
            }

            return;
        }

        if ($resources === null) {
            return;
        }

        $resource = $this->mediaResourceForSource($coverImage, $resources);
        if ($resource === null) {
            return;
        }

        $contents = is_array($resource)
            ? (string) ($resource['contents'] ?? $resource['data'] ?? '')
            : (string) $resource;
        $mimeType = is_array($resource) ? ($resource['mimeType'] ?? null) : null;
        $bag->insertMedia($coverImage, is_string($mimeType) ? $mimeType : null, $contents);
        $diagnostics[] = 'media-resource-cover-loaded:' . $coverImage;
    }

    /**
     * @param array<string, string|array{contents?:string, data?:string, mimeType?:string|null}> $resources
     * @return string|array{contents?:string, data?:string, mimeType?:string|null}|null
     */
    private function mediaResourceForSource(string $source, array $resources): string|array|null
    {
        if (array_key_exists($source, $resources)) {
            return $resources[$source];
        }

        $normalizedSource = $this->normalizeMediaComparisonPath($source);
        foreach ($resources as $candidate => $resource) {
            if ($this->normalizeMediaComparisonPath((string) $candidate) === $normalizedSource) {
                return $resource;
            }
        }

        return null;
    }

    private function coverImageSource(AstNode $document): ?string
    {
        foreach (['coverImage', 'cover-image', 'epubCoverImage', 'epub-cover-image', 'epubCoverImagePath'] as $key) {
            $value = $this->options[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        $meta = $document->attr('meta', []);
        if (is_array($meta)) {
            foreach (['coverImage', 'cover-image', 'epubCoverImage', 'epub-cover-image', 'epubCoverImagePath'] as $key) {
                $value = $meta[$key] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function isCoverImageEntry(array $entry, string $path, ?string $coverImage): bool
    {
        if ($coverImage === null) {
            return false;
        }

        $cover = $this->normalizeMediaComparisonPath($coverImage);
        foreach (['source', 'sourcePath', 'mediaPath', 'path'] as $key) {
            $value = $entry[$key] ?? null;
            if (is_string($value) && $this->normalizeMediaComparisonPath($value) === $cover) {
                return true;
            }
        }

        foreach ([$path, 'text/' . $path, 'EPUB/text/' . $path] as $candidate) {
            if ($this->normalizeMediaComparisonPath($candidate) === $cover) {
                return true;
            }
        }

        return false;
    }

    private function normalizeMediaComparisonPath(string $path): string
    {
        $path = rawurldecode(trim(str_replace('\\', '/', $path)));
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        $path = ltrim($path, '/');

        return strtolower($path);
    }

    private function plainBlockText(AstNode $node): string
    {
        if ($node->children === []) {
            $text = $node->attr('text', '');

            return is_string($text) ? trim($text) : '';
        }

        return trim($this->plainInlineText($node->children));
    }

    /**
     * @param list<mixed> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if (!$node instanceof AstNode) {
                continue;
            }
            if ($node->type === 'text' || $node->type === 'code') {
                $text .= (string) $node->attr('text', '');
                continue;
            }
            if ($node->type === 'softbreak' || $node->type === 'linebreak') {
                $text .= ' ';
                continue;
            }
            $text .= $this->plainInlineText($node->children);
        }

        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
