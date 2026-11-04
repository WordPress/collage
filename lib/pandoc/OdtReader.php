<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OdtReader
{
    private const ODT_MIMETYPE = 'application/vnd.oasis.opendocument.text';
    private const TEXT_NS = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';
    private const TABLE_NS = 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';
    private const STYLE_NS = 'urn:oasis:names:tc:opendocument:xmlns:style:1.0';
    private const FO_NS = 'urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0';
    private const DRAW_NS = 'urn:oasis:names:tc:opendocument:xmlns:drawing:1.0';
    private const XLINK_NS = 'http://www.w3.org/1999/xlink';
    private const MANIFEST_NS = 'urn:oasis:names:tc:opendocument:xmlns:manifest:1.0';
    private const MATH_NS = 'http://www.w3.org/1998/Math/MathML';

    /** @var array<string, array{strong?: bool, emph?: bool}> */
    private array $textStyles = [];

    /** @var array<string, array<int, array{ordered: bool, style?: string, delimiter?: string, start?: int}>> */
    private array $listStyles = [];

    /** @var list<string> */
    private array $referencedResources = [];

    /** @var array<string, string> */
    private array $embeddedObjectContentXml = [];

    public function read(string $bytes): AstNode
    {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-odt-');
        if ($path === false) {
            throw new \RuntimeException('Unable to create temporary ODT path.');
        }

        try {
            if (file_put_contents($path, $bytes) === false) {
                throw new \RuntimeException('Unable to write temporary ODT package.');
            }

            return $this->readOdtFile($path);
        } finally {
            @unlink($path);
        }
    }

    public function readOdtFile(string $path): AstNode
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('ODT analysis needs PHP ZipArchive, which is unavailable in this runtime.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \InvalidArgumentException("Unable to open ODT package '{$path}'.");
        }

        try {
            $content_xml = $zip->getFromName('content.xml');
            if (!is_string($content_xml)) {
                throw new \InvalidArgumentException('ODT package is missing content.xml.');
            }
            $styles_xml = $zip->getFromName('styles.xml');
            $meta_xml = $zip->getFromName('meta.xml');
            $manifest_xml = $zip->getFromName('META-INF/manifest.xml');
            $mimetype = $zip->getFromName('mimetype');
            if (is_string($mimetype) && trim($mimetype) !== self::ODT_MIMETYPE) {
                throw new \RuntimeException('ODT mimetype entry must declare application/vnd.oasis.opendocument.text.');
            }
            $entries = [];
            $image_resources = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = is_array($stat) ? (string) ($stat['name'] ?? '') : '';
                if ($name === '') {
                    continue;
                }
                $entries[] = $name;
                if ($this->pathLooksLikeImage($name)) {
                    $image_resources[] = $this->normalizePackagePath($name);
                }
            }
            $manifest = is_string($manifest_xml) ? $this->manifestMetadata($manifest_xml, $entries) : [];
            $embeddedObjectContentXml = $this->embeddedObjectContentParts($content_xml, $zip);
        } finally {
            $zip->close();
        }

        return $this->readPackage(
            $content_xml,
            is_string($styles_xml) ? $styles_xml : '',
            is_string($meta_xml) ? $meta_xml : '',
            $entries,
            array_values(array_unique($image_resources)),
            $manifest,
            $embeddedObjectContentXml,
        );
    }

    /**
     * @param list<string> $entries
     * @param list<string> $image_resources
     * @param array<string, mixed> $manifest
     * @param array<string, string> $embeddedObjectContentXml
     */
    private function readPackage(
        string $content_xml,
        string $styles_xml = '',
        string $meta_xml = '',
        array $entries = [],
        array $image_resources = [],
        array $manifest = [],
        array $embeddedObjectContentXml = [],
    ): AstNode
    {
        $content = $this->loadXml($content_xml, 'ODT content.xml');
        $this->textStyles = array_replace(
            $styles_xml !== '' ? $this->collectTextStyles($this->loadXml($styles_xml, 'ODT styles.xml')) : [],
            $this->collectTextStyles($content),
        );
        $this->listStyles = array_replace_recursive(
            $styles_xml !== '' ? $this->collectListStyles($this->loadXml($styles_xml, 'ODT styles.xml')) : [],
            $this->collectListStyles($content),
        );

        $metadata = $meta_xml !== '' ? $this->metadata($this->loadXml($meta_xml, 'ODT meta.xml')) : [];
        $body = $this->firstElementByLocalName($content, 'body');
        $text = $body instanceof \DOMElement ? $this->firstChildElementByLocalName($body, 'text') : null;
        if (!$text instanceof \DOMElement) {
            $text = $this->firstElementByLocalName($content, 'text');
        }

        $this->referencedResources = [];
        $this->embeddedObjectContentXml = $embeddedObjectContentXml;
        $children = $text instanceof \DOMElement ? $this->parseBlockChildren($text) : [];
        if ($children === []) {
            $children[] = new AstNode('paragraph', ['text' => 'No readable ODT body content was found.'], [
                new AstNode('text', ['text' => 'No readable ODT body content was found.']),
            ]);
        }

        $referenced_resources = array_values(array_unique($this->referencedResources));
        $metadata['odtTextStyleCount'] = count($this->textStyles);
        $metadata['odtListStyleCount'] = count($this->listStyles);
        $metadata['odtPackageEntries'] = count($entries);
        $metadata['odtReferencedResources'] = $referenced_resources;
        $metadata['odtImageResources'] = $image_resources !== []
            ? $image_resources
            : array_values(array_filter($referenced_resources, fn (string $path): bool => $this->pathLooksLikeImage($path)));
        if ($manifest !== []) {
            $metadata['odtManifestVersion'] = $manifest['version'];
            $metadata['odtManifestEntryCount'] = $manifest['entryCount'];
            $metadata['odtManifestEntries'] = $manifest['entries'];
            $metadata['odtManifestMediaTypes'] = $manifest['mediaTypes'];
            $metadata['odtManifestMissingEntries'] = $manifest['missingEntries'];
            $metadata['odtManifestEncryptedEntries'] = $manifest['encryptedEntries'];
            $metadata['odtManifestImageResources'] = $manifest['imageResources'];
        }

        return new AstNode('document', ['meta' => $metadata], $children);
    }

    /**
     * @return array<string, string>
     */
    private function embeddedObjectContentParts(string $contentXml, \ZipArchive $zip): array
    {
        $content = $this->loadXml($contentXml, 'ODT content.xml');
        $parts = [];

        foreach ($content->getElementsByTagNameNS(self::DRAW_NS, 'object') as $object) {
            if (!$object instanceof \DOMElement) {
                continue;
            }
            $contentPart = $this->objectContentPart($this->attr($object, self::XLINK_NS, 'href'));
            if ($contentPart === null || isset($parts[$contentPart])) {
                continue;
            }
            $objectXml = $zip->getFromName($contentPart);
            if (is_string($objectXml)) {
                $parts[$contentPart] = $objectXml;
            }
        }

        return $parts;
    }

    /**
     * @param list<string> $packageEntries
     * @return array{
     *     version:?string,
     *     entryCount:int,
     *     entries:list<array<string, mixed>>,
     *     mediaTypes:array<string, int>,
     *     missingEntries:list<string>,
     *     encryptedEntries:list<string>,
     *     imageResources:list<string>
     * }
     */
    private function manifestMetadata(string $manifest_xml, array $packageEntries): array
    {
        $dom = $this->loadXml($manifest_xml, 'ODT META-INF/manifest.xml');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || $root->localName !== 'manifest' || $root->namespaceURI !== self::MANIFEST_NS) {
            throw new \InvalidArgumentException('ODT manifest XML must use manifest:manifest as its root element.');
        }

        $packageEntryLookup = array_fill_keys($packageEntries, true);
        $version = $this->attr($root, self::MANIFEST_NS, 'version');
        $items = [];
        $mediaTypes = [];
        $missingEntries = [];
        $encryptedEntries = [];
        $imageResources = [];
        $rootMediaType = null;
        $hasContentXml = false;

        foreach ($root->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'file-entry' || $child->namespaceURI !== self::MANIFEST_NS) {
                continue;
            }

            $fullPath = $this->attr($child, self::MANIFEST_NS, 'full-path');
            if ($fullPath === '') {
                continue;
            }

            $mediaType = $this->attr($child, self::MANIFEST_NS, 'media-type');
            $entryVersion = $this->attr($child, self::MANIFEST_NS, 'version');
            $declaredSize = $this->manifestDeclaredSize($this->attr($child, self::MANIFEST_NS, 'size'));
            $packagePath = $fullPath === '/' ? '' : $this->normalizePackagePath($fullPath);
            $exists = $fullPath === '/' || ($packagePath !== '' && isset($packageEntryLookup[$packagePath]));
            $encrypted = $this->firstChildElementByLocalName($child, 'encryption-data') instanceof \DOMElement;

            if ($fullPath === '/') {
                $rootMediaType = $mediaType;
            }
            if ($fullPath === 'content.xml') {
                $hasContentXml = true;
            }
            if ($mediaType !== '') {
                $mediaTypes[$mediaType] = ($mediaTypes[$mediaType] ?? 0) + 1;
            }
            if (!$exists && $fullPath !== '/') {
                $missingEntries[] = $fullPath;
            }
            if ($encrypted && $fullPath !== '/') {
                $encryptedEntries[] = $fullPath;
            }
            if ($this->pathLooksLikeImage($fullPath)) {
                $imageResources[] = $packagePath === '' ? $fullPath : $packagePath;
            }

            $items[] = [
                'fullPath' => $fullPath,
                'packagePath' => $packagePath === '' ? null : $packagePath,
                'mediaType' => $mediaType,
                'version' => $entryVersion === '' ? null : $entryVersion,
                'declaredSize' => $declaredSize,
                'exists' => $exists,
                'encrypted' => $encrypted,
            ];
        }

        if ($rootMediaType !== self::ODT_MIMETYPE) {
            throw new \RuntimeException('ODT manifest root must identify an OpenDocument text package.');
        }
        if (!$hasContentXml) {
            throw new \RuntimeException('ODT manifest is missing content.xml.');
        }

        ksort($mediaTypes, SORT_STRING);

        return [
            'version' => $version === '' ? null : $version,
            'entryCount' => count($items),
            'entries' => $items,
            'mediaTypes' => $mediaTypes,
            'missingEntries' => array_values(array_unique($missingEntries)),
            'encryptedEntries' => array_values(array_unique($encryptedEntries)),
            'imageResources' => array_values(array_unique($imageResources)),
        ];
    }

    /**
     * @return list<AstNode>
     */
    private function parseBlockChildren(\DOMNode $parent): array
    {
        $blocks = [];
        foreach ($parent->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $block = $this->parseBlock($child);
            if ($block instanceof AstNode) {
                $blocks[] = $block;
            } elseif (is_array($block)) {
                array_push($blocks, ...$block);
            }
        }

        return $blocks;
    }

    private function parseBlock(\DOMElement $element): AstNode|array|null
    {
        return match ($element->localName) {
            'h' => $this->heading($element),
            'p' => $this->paragraph($element),
            'list' => $this->list($element),
            'table' => $this->table($element),
            'section', 'div' => $this->parseBlockChildren($element),
            default => null,
        };
    }

    private function heading(\DOMElement $element): AstNode
    {
        $level = max(1, min(6, (int) ($this->attr($element, self::TEXT_NS, 'outline-level') ?: '1')));
        $inlines = $this->parseInlines($element);
        $text = $this->plainText($inlines);

        return new AstNode('heading', ['level' => $level, 'text' => $text], $inlines);
    }

    private function paragraph(\DOMElement $element): ?AstNode
    {
        $inlines = $this->parseInlines($element);
        $text = trim($this->plainText($inlines));
        if ($text === '' && $inlines === []) {
            return null;
        }

        return new AstNode('paragraph', ['text' => $text], $inlines);
    }

    private function list(\DOMElement $element): AstNode
    {
        $items = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'list-item') {
                continue;
            }
            $blocks = $this->parseBlockChildren($child);
            if ($blocks === []) {
                $text = trim($child->textContent);
                if ($text !== '') {
                    $blocks[] = new AstNode('plain', ['text' => $text], [new AstNode('text', ['text' => $text])]);
                }
            }
            $items[] = new AstNode('list_item', [], $blocks);
        }

        $attrs = $this->listAttributes($element);

        return new AstNode(($attrs['ordered'] ?? false) ? 'ordered_list' : 'bullet_list', $attrs['attrs'] ?? [], $items);
    }

    /**
     * @return array{ordered: bool, attrs: array<string, mixed>}
     */
    private function listAttributes(\DOMElement $element): array
    {
        $styleName = $this->attr($element, self::TEXT_NS, 'style-name');
        $level = $this->listLevel($element);
        $style = $styleName !== '' ? ($this->listStyles[$styleName][$level] ?? $this->listStyles[$styleName][1] ?? null) : null;
        if (!is_array($style) || !($style['ordered'] ?? false)) {
            return ['ordered' => false, 'attrs' => []];
        }

        $attrs = [
            'start' => $this->listStart($element, $style),
            'style' => $style['style'] ?? 'decimal',
            'delimiter' => $style['delimiter'] ?? 'period',
        ];

        return ['ordered' => true, 'attrs' => $attrs];
    }

    private function listLevel(\DOMElement $element): int
    {
        $level = 1;
        for ($parent = $element->parentNode; $parent instanceof \DOMElement; $parent = $parent->parentNode) {
            if ($parent->localName === 'list') {
                $level++;
            }
        }

        return $level;
    }

    /**
     * @param array{ordered: bool, style?: string, delimiter?: string, start?: int} $style
     */
    private function listStart(\DOMElement $element, array $style): int
    {
        $start = $this->attr($element, self::TEXT_NS, 'start-value');
        if ($start !== '' && is_numeric($start)) {
            return max(1, (int) $start);
        }

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'list-item') {
                continue;
            }
            $itemStart = $this->attr($child, self::TEXT_NS, 'start-value');
            if ($itemStart !== '' && is_numeric($itemStart)) {
                return max(1, (int) $itemStart);
            }
            break;
        }

        return max(1, (int) ($style['start'] ?? 1));
    }

    private function table(\DOMElement $element): AstNode
    {
        $headRows = [];
        $bodyRows = [];
        $footRows = [];
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'table-header-rows') {
                array_push($headRows, ...$this->tableRowsFromContainer($child));
                continue;
            }
            if ($child->localName === 'table-footer-rows') {
                array_push($footRows, ...$this->tableRowsFromContainer($child));
                continue;
            }
            if ($child->localName === 'table-row') {
                array_push($bodyRows, ...$this->tableRowsFromContainer($child));
                continue;
            }
            if (in_array($child->localName, ['table-rows', 'table-row-group'], true)) {
                array_push($bodyRows, ...$this->tableRowsFromContainer($child));
            }
        }

        $children = [
            new AstNode('table_head', [], $headRows),
            new AstNode('table_body', [], $bodyRows),
        ];
        if ($footRows !== []) {
            $children[] = new AstNode('table_foot', [], $footRows);
        }

        return new AstNode('table', [], $children);
    }

    /**
     * @return list<AstNode>
     */
    private function tableRowsFromContainer(\DOMElement $container): array
    {
        if ($container->localName === 'table-row') {
            $repeat = max(1, min(50, (int) ($this->attr($container, self::TABLE_NS, 'number-rows-repeated') ?: '1')));
            $row = $this->tableRow($container);

            return array_fill(0, $repeat, $row);
        }

        $rows = [];
        foreach ($container->childNodes as $child) {
            if (!$child instanceof \DOMElement || !in_array($child->localName, ['table-row', 'table-row-group'], true)) {
                continue;
            }
            array_push($rows, ...$this->tableRowsFromContainer($child));
        }

        return $rows;
    }

    private function tableRow(\DOMElement $row): AstNode
    {
        $cells = [];
        foreach ($row->childNodes as $cell) {
            if (!$cell instanceof \DOMElement || !in_array($cell->localName, ['table-cell', 'covered-table-cell'], true)) {
                continue;
            }
            if ($cell->localName === 'covered-table-cell') {
                continue;
            }
            $repeat = max(1, min(50, (int) ($this->attr($cell, self::TABLE_NS, 'number-columns-repeated') ?: '1')));
            $node = $this->tableCell($cell);
            for ($i = 0; $i < $repeat; $i++) {
                $cells[] = $node;
            }
        }

        return new AstNode('table_row', [], $cells);
    }

    private function tableCell(\DOMElement $cell): AstNode
    {
        $attrs = [
            'colspan' => max(1, (int) ($this->attr($cell, self::TABLE_NS, 'number-columns-spanned') ?: '1')),
            'rowspan' => max(1, (int) ($this->attr($cell, self::TABLE_NS, 'number-rows-spanned') ?: '1')),
        ];
        $blocks = $this->parseBlockChildren($cell);
        $text = trim(implode(' ', array_map(fn (AstNode $block): string => $this->nodeText($block), $blocks)));
        if ($blocks === [] && trim($cell->textContent) !== '') {
            $text = trim($cell->textContent);
            $blocks[] = new AstNode('plain', ['text' => $text], [new AstNode('text', ['text' => $text])]);
        }
        $attrs['text'] = $text;

        return new AstNode('table_cell', $attrs, $blocks);
    }

    /**
     * @return list<AstNode>
     */
    private function parseInlines(\DOMNode $parent): array
    {
        $inlines = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                $text = preg_replace('/\s+/u', ' ', $child->nodeValue) ?? $child->nodeValue;
                if ($text !== '') {
                    $inlines[] = new AstNode('text', ['text' => $text]);
                }
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue;
            }
            array_push($inlines, ...$this->parseInlineElement($child));
        }

        return $this->mergeAdjacentText($inlines);
    }

    /**
     * @return list<AstNode>
     */
    private function parseInlineElement(\DOMElement $element): array
    {
        return match ($element->localName) {
            'span' => $this->styledSpan($element),
            'a' => [new AstNode('link', [
                'url' => $this->attr($element, self::XLINK_NS, 'href'),
                'title' => '',
            ], $this->parseInlines($element))],
            'line-break' => [new AstNode('linebreak')],
            'tab' => [new AstNode('text', ['text' => "\t"])],
            's' => [new AstNode('text', ['text' => str_repeat(' ', max(1, (int) ($this->attr($element, self::TEXT_NS, 'c') ?: '1')))])],
            'frame' => $this->frame($element),
            'note' => [$this->note($element)],
            default => $this->parseInlines($element),
        };
    }

    private function note(\DOMElement $element): AstNode
    {
        $noteClass = $this->attr($element, self::TEXT_NS, 'note-class');
        if ($noteClass === '') {
            $noteClass = 'footnote';
        }

        $attrs = [
            'sourceFormat' => 'odt',
            'noteClass' => $noteClass,
        ];
        $id = $this->attr($element, self::TEXT_NS, 'id');
        if ($id !== '') {
            $attrs['id'] = $id;
        }

        $citation = $this->firstChildElementByLocalName($element, 'note-citation');
        if ($citation instanceof \DOMElement) {
            $citationText = $this->plainText($this->parseInlines($citation));
            if ($citationText === '') {
                $citationText = trim(preg_replace('/\s+/', ' ', $citation->textContent) ?? $citation->textContent);
            }
            $attrs['citation'] = $citationText;
        }

        $blocks = [];
        $body = $this->firstChildElementByLocalName($element, 'note-body');
        if ($body instanceof \DOMElement) {
            $blocks = $this->parseBlockChildren($body);
            if ($blocks === []) {
                $inlines = $this->parseInlines($body);
                $text = $this->plainText($inlines);
                if ($text !== '' || $inlines !== []) {
                    $blocks[] = new AstNode('paragraph', ['text' => $text], $inlines);
                }
            }
        }

        return new AstNode('note', $attrs, $blocks);
    }

    /**
     * @return list<AstNode>
     */
    private function styledSpan(\DOMElement $element): array
    {
        $children = $this->parseInlines($element);
        $style = $this->textStyles[$this->attr($element, self::TEXT_NS, 'style-name')] ?? [];
        if (($style['strong'] ?? false) && $children !== []) {
            $children = [new AstNode('strong', [], $children)];
        }
        if (($style['emph'] ?? false) && $children !== []) {
            $children = [new AstNode('emph', [], $children)];
        }

        return $children;
    }

    /**
     * @return list<AstNode>
     */
    private function frame(\DOMElement $element): array
    {
        $math = $this->frameMath($element);
        if ($math instanceof AstNode) {
            return [$math];
        }

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->localName !== 'image') {
                continue;
            }
            $url = $this->attr($child, self::XLINK_NS, 'href');
            if ($url === '') {
                continue;
            }
            if ($this->isPackageRelativeResourceUrl($url)) {
                $url = $this->normalizePackagePath($url);
                $this->referencedResources[] = $url;
            }

            return [new AstNode('image', [
                'url' => $url,
                'title' => '',
                'alt' => trim($element->textContent),
            ], [])];
        }

        return $this->parseInlines($element);
    }

    private function frameMath(\DOMElement $frame): ?AstNode
    {
        foreach ($frame->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::DRAW_NS || $child->localName !== 'object') {
                continue;
            }

            $contentPart = $this->objectContentPart($this->attr($child, self::XLINK_NS, 'href'));
            if ($contentPart === null || !isset($this->embeddedObjectContentXml[$contentPart])) {
                continue;
            }

            try {
                $dom = $this->loadXml($this->embeddedObjectContentXml[$contentPart], 'ODT embedded object ' . $contentPart);
            } catch (\InvalidArgumentException) {
                continue;
            }
            $math = $dom->getElementsByTagNameNS(self::MATH_NS, 'math')->item(0);
            if (!$math instanceof \DOMElement) {
                continue;
            }

            $mathml = $math->ownerDocument instanceof \DOMDocument ? trim((string) $math->ownerDocument->saveXML($math)) : '';
            if ($mathml === '') {
                continue;
            }

            return new AstNode('math', [
                'sourceFormat' => 'odt-mathml',
                'display' => false,
                'text' => $this->mathPlainText($math),
                'mathml' => $mathml,
                'objectPath' => substr($contentPart, 0, -strlen('/content.xml')),
                'sourcePart' => $contentPart,
            ]);
        }

        return null;
    }

    private function objectContentPart(string $href): ?string
    {
        if (!$this->isPackageRelativeResourceUrl($href)) {
            return null;
        }

        $part = $this->normalizePackagePath($href);
        if ($part === '') {
            return null;
        }

        return str_ends_with($part, '/content.xml') ? $part : rtrim($part, '/') . '/content.xml';
    }

    private function mathPlainText(\DOMElement $math): string
    {
        $parts = [];
        $this->collectMathText($math, $parts);
        $text = implode('', $parts);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @param list<string> $parts
     */
    private function collectMathText(\DOMNode $node, array &$parts): void
    {
        if ($node instanceof \DOMElement && $node->namespaceURI === self::MATH_NS && in_array($node->localName, ['annotation', 'annotation-xml'], true)) {
            return;
        }
        if ($node instanceof \DOMText || $node instanceof \DOMCdataSection) {
            $parts[] = $node->textContent;

            return;
        }

        foreach ($node->childNodes as $child) {
            $this->collectMathText($child, $parts);
        }
    }

    /**
     * @param list<AstNode> $inlines
     * @return list<AstNode>
     */
    private function mergeAdjacentText(array $inlines): array
    {
        $merged = [];
        foreach ($inlines as $inline) {
            $lastIndex = array_key_last($merged);
            $last = $lastIndex === null ? null : $merged[$lastIndex];
            if ($inline->type === 'text' && $last instanceof AstNode && $last->type === 'text') {
                $merged[$lastIndex] = new AstNode('text', [
                    'text' => (string) $last->attr('text', '') . (string) $inline->attr('text', ''),
                ]);
                continue;
            }
            $merged[] = $inline;
        }

        return $merged;
    }

    /**
     * @return array<string, array{strong?: bool, emph?: bool}>
     */
    private function collectTextStyles(\DOMDocument $dom): array
    {
        $styles = [];
        foreach ($dom->getElementsByTagName('*') as $style) {
            if (!$style instanceof \DOMElement || $style->localName !== 'style') {
                continue;
            }
            $family = $this->attr($style, self::STYLE_NS, 'family');
            if ($family !== '' && $family !== 'text') {
                continue;
            }
            $name = $this->attr($style, self::STYLE_NS, 'name');
            if ($name === '') {
                continue;
            }
            $entry = [];
            foreach ($style->childNodes as $props) {
                if (!$props instanceof \DOMElement || $props->localName !== 'text-properties') {
                    continue;
                }
                $weight = strtolower($this->attr($props, self::FO_NS, 'font-weight') ?: $this->attr($props, self::STYLE_NS, 'font-weight'));
                $fontStyle = strtolower($this->attr($props, self::FO_NS, 'font-style') ?: $this->attr($props, self::STYLE_NS, 'font-style'));
                if ($weight === 'bold' || (is_numeric($weight) && (int) $weight >= 600)) {
                    $entry['strong'] = true;
                }
                if ($fontStyle === 'italic' || $fontStyle === 'oblique') {
                    $entry['emph'] = true;
                }
            }
            if ($entry !== []) {
                $styles[$name] = $entry;
            }
        }

        return $styles;
    }

    /**
     * @return array<string, array<int, array{ordered: bool, style?: string, delimiter?: string, start?: int}>>
     */
    private function collectListStyles(\DOMDocument $dom): array
    {
        $styles = [];
        foreach ($dom->getElementsByTagName('*') as $style) {
            if (!$style instanceof \DOMElement || $style->localName !== 'list-style') {
                continue;
            }
            $name = $this->attr($style, self::STYLE_NS, 'name');
            if ($name === '') {
                $name = $this->attr($style, self::TEXT_NS, 'style-name');
            }
            if ($name === '') {
                continue;
            }

            foreach ($style->childNodes as $levelStyle) {
                if (!$levelStyle instanceof \DOMElement) {
                    continue;
                }
                if (!in_array($levelStyle->localName, ['list-level-style-number', 'list-level-style-bullet'], true)) {
                    continue;
                }

                $level = max(1, (int) ($this->attr($levelStyle, self::TEXT_NS, 'level') ?: '1'));
                if ($levelStyle->localName === 'list-level-style-bullet') {
                    $styles[$name][$level] = ['ordered' => false];
                    continue;
                }

                $format = $this->attr($levelStyle, self::STYLE_NS, 'num-format');
                if ($format === '') {
                    $format = $levelStyle->getAttribute('style:num-format');
                }
                $entry = [
                    'ordered' => true,
                    'style' => $this->orderedListStyle($format),
                    'delimiter' => $this->orderedListDelimiter(
                        $this->attr($levelStyle, self::STYLE_NS, 'num-prefix'),
                        $this->attr($levelStyle, self::STYLE_NS, 'num-suffix')
                    ),
                ];

                $start = $this->attr($levelStyle, self::TEXT_NS, 'start-value');
                if ($start !== '' && is_numeric($start)) {
                    $entry['start'] = max(1, (int) $start);
                }

                $styles[$name][$level] = $entry;
            }
        }

        return $styles;
    }

    private function orderedListStyle(string $format): string
    {
        return match ($format) {
            'a' => 'lower_alpha',
            'A' => 'upper_alpha',
            'i' => 'lower_roman',
            'I' => 'upper_roman',
            default => 'decimal',
        };
    }

    private function orderedListDelimiter(string $prefix, string $suffix): string
    {
        if ($prefix === '(' && $suffix === ')') {
            return 'two_parens';
        }
        if ($suffix === ')') {
            return 'one_paren';
        }
        if ($suffix === '.') {
            return 'period';
        }

        return 'default';
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(\DOMDocument $dom): array
    {
        $meta = [];
        foreach ($dom->getElementsByTagName('*') as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }
            $text = trim(preg_replace('/\s+/', ' ', $element->textContent) ?? $element->textContent);
            if ($text === '') {
                continue;
            }
            $key = match ($element->localName) {
                'title' => 'title',
                'creator' => 'author',
                'description' => 'description',
                'date', 'creation-date' => 'date',
                'keyword' => 'keywords',
                default => '',
            };
            if ($key === '') {
                continue;
            }
            if (isset($meta[$key])) {
                $meta[$key] = is_array($meta[$key]) ? array_merge($meta[$key], [$text]) : [$meta[$key], $text];
            } else {
                $meta[$key] = $text;
            }
            if ($key === 'title') {
                $meta['titleInlines'] = [new AstNode('text', ['text' => $text])];
            }
        }

        return $meta;
    }

    private function loadXml(string $xml, string $label): \DOMDocument
    {
        if (!class_exists(\DOMDocument::class)) {
            throw new \RuntimeException($label . ' needs DOMDocument, which is unavailable in this runtime.');
        }
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$ok) {
            throw new \InvalidArgumentException($label . ' is not valid XML.');
        }

        return $dom;
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

    private function firstChildElementByLocalName(\DOMElement $parent, string $localName): ?\DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                return $child;
            }
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

    private function isPackageRelativeResourceUrl(string $url): bool
    {
        $url = trim($url);
        return $url !== ''
            && !str_starts_with($url, '#')
            && !str_starts_with(strtolower($url), 'data:')
            && !str_starts_with(strtolower($url), 'mailto:')
            && !$this->isAbsoluteUrl($url);
    }

    private function isAbsoluteUrl(string $url): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) || str_starts_with($url, '//');
    }

    private function normalizePackagePath(string $path): string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
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

    private function pathLooksLikeImage(string $path): bool
    {
        return (bool) preg_match('/\.(?:apng|avif|bmp|gif|ico|jpe?g|png|svgz?|tiff?|webp)$/i', $path);
    }

    private function manifestDeclaredSize(string $value): ?int
    {
        if ($value === '' || !ctype_digit($value)) {
            return null;
        }

        return (int) $value;
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
        if ($node->type === 'note') {
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
}
