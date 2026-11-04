<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OpenDocumentReader
{
    public const OFFICE_NS = 'urn:oasis:names:tc:opendocument:xmlns:office:1.0';
    public const TEXT_NS = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';
    public const STYLE_NS = 'urn:oasis:names:tc:opendocument:xmlns:style:1.0';
    public const TABLE_NS = 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';
    public const DRAW_NS = 'urn:oasis:names:tc:opendocument:xmlns:drawing:1.0';
    public const MANIFEST_NS = 'urn:oasis:names:tc:opendocument:xmlns:manifest:1.0';
    public const XLINK_NS = 'http://www.w3.org/1999/xlink';
    public const DC_NS = 'http://purl.org/dc/elements/1.1/';
    public const META_NS = 'urn:oasis:names:tc:opendocument:xmlns:meta:1.0';
    public const FO_NS = 'urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0';
    public const SVG_NS = 'urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0';

    public const ODT_MEDIA_TYPE = 'application/vnd.oasis.opendocument.text';

    /**
     * @return array{
     *     document:AstNode,
     *     metadata:array<string, mixed>,
     *     manifest:array<string, array{fullPath:string, mediaType:string, version:?string}>,
     *     contentPart:string
     * }
     */
    public function readPackage(ZipPackage $package): array
    {
        $manifest = $this->readManifest($package);
        $this->assertMimetypeEntry($package, $manifest);
        $styles = $this->loadStyles($package);
        $document = $this->parseContentXml($package->read('content.xml'), $package, $manifest, $styles);

        return [
            'document' => $document,
            'metadata' => $this->readMetadata($package),
            'manifest' => $manifest,
            'contentPart' => 'content.xml',
        ];
    }

    public function readDocument(ZipPackage $package): AstNode
    {
        return $this->readPackage($package)['document'];
    }

    /**
     * @return array<string, array{fullPath:string, mediaType:string, version:?string}>
     */
    private function readManifest(ZipPackage $package): array
    {
        if (!$package->has('META-INF/manifest.xml')) {
            throw new \RuntimeException('ODT package is missing META-INF/manifest.xml');
        }

        $dom = self::loadXml($package->read('META-INF/manifest.xml'), 'ODT manifest XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || !$this->isElement($root, self::MANIFEST_NS, 'manifest')) {
            throw new \InvalidArgumentException('ODT manifest XML must use a manifest:manifest root');
        }

        $entries = [];
        foreach ($root->childNodes as $child) {
            if (!$child instanceof \DOMElement || !$this->isElement($child, self::MANIFEST_NS, 'file-entry')) {
                continue;
            }

            $fullPath = $this->namespacedAttr($child, self::MANIFEST_NS, 'full-path');
            if ($fullPath === null || $fullPath === '') {
                continue;
            }
            $this->assertManifestPath($fullPath);

            $mediaType = (string) ($this->namespacedAttr($child, self::MANIFEST_NS, 'media-type') ?? '');
            $version = $this->namespacedAttr($child, self::MANIFEST_NS, 'version');
            $entries[$fullPath] = [
                'fullPath' => $fullPath,
                'mediaType' => $mediaType,
                'version' => $version === '' ? null : $version,
            ];
        }

        if (($entries['/']['mediaType'] ?? '') !== self::ODT_MEDIA_TYPE) {
            throw new \RuntimeException('ODT manifest root entry must declare application/vnd.oasis.opendocument.text');
        }

        if (!isset($entries['content.xml'])) {
            throw new \RuntimeException('ODT manifest is missing content.xml');
        }

        if (!$package->has('content.xml')) {
            throw new \RuntimeException('ODT package is missing content.xml');
        }

        return $entries;
    }

    /**
     * @param array<string, array{fullPath:string, mediaType:string, version:?string}> $manifest
     */
    private function assertMimetypeEntry(ZipPackage $package, array $manifest): void
    {
        if (!$package->has('mimetype')) {
            return;
        }

        $mimetype = trim($package->read('mimetype'));
        if ($mimetype !== ($manifest['/']['mediaType'] ?? self::ODT_MEDIA_TYPE)) {
            throw new \RuntimeException('ODT mimetype entry does not match the manifest root media type');
        }
    }

    /**
     * @return array{
     *     paragraph:array<string, array{displayName:?string, parent:?string, headingLevel:?int, listStyle:?string}>,
     *     text:array<string, array{bold:bool, italic:bool, underline:bool, strikeout:bool, superscript:bool, subscript:bool, smallCaps:bool}>,
     *     lists:array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>>
     * }
     */
    private function loadStyles(ZipPackage $package): array
    {
        $styles = [
            'paragraph' => [],
            'text' => [],
            'lists' => [],
        ];

        foreach (['styles.xml', 'content.xml'] as $part) {
            if (!$package->has($part)) {
                continue;
            }

            $dom = self::loadXml($package->read($part), 'ODT ' . $part);
            $root = $dom->documentElement;
            if (!$root instanceof \DOMElement) {
                continue;
            }

            foreach ($root->getElementsByTagNameNS(self::STYLE_NS, 'style') as $styleElement) {
                if (!$styleElement instanceof \DOMElement) {
                    continue;
                }

                $family = (string) ($this->namespacedAttr($styleElement, self::STYLE_NS, 'family') ?? '');
                $styleName = $this->namespacedAttr($styleElement, self::STYLE_NS, 'name');
                if ($styleName === null || $styleName === '') {
                    continue;
                }

                if ($family === 'paragraph') {
                    $styles['paragraph'][$styleName] = $this->paragraphStyleDefinition($styleElement);
                    continue;
                }

                if ($family === 'text') {
                    $styles['text'][$styleName] = $this->textStyleDefinition($styleElement);
                }
            }

            foreach ($root->getElementsByTagNameNS(self::TEXT_NS, 'list-style') as $listStyle) {
                if (!$listStyle instanceof \DOMElement) {
                    continue;
                }

                $styleName = $this->namespacedAttr($listStyle, self::STYLE_NS, 'name');
                if ($styleName === null || $styleName === '') {
                    continue;
                }

                $styles['lists'][$styleName] = $this->listStyleDefinition($listStyle);
            }
        }

        return $styles;
    }

    /**
     * @return array{displayName:?string, parent:?string, headingLevel:?int, listStyle:?string}
     */
    private function paragraphStyleDefinition(\DOMElement $styleElement): array
    {
        $name = (string) ($this->namespacedAttr($styleElement, self::STYLE_NS, 'name') ?? '');
        $displayName = $this->namespacedAttr($styleElement, self::STYLE_NS, 'display-name');
        $parent = $this->namespacedAttr($styleElement, self::STYLE_NS, 'parent-style-name');
        $listStyle = $this->namespacedAttr($styleElement, self::STYLE_NS, 'list-style-name');
        $properties = $this->firstChildElement($styleElement, self::STYLE_NS, 'paragraph-properties');
        if ($properties instanceof \DOMElement) {
            $listStyle = $this->namespacedAttr($properties, self::STYLE_NS, 'list-style-name') ?? $listStyle;
        }

        return [
            'displayName' => $displayName === '' ? null : $displayName,
            'parent' => $parent === '' ? null : $parent,
            'headingLevel' => $this->headingLevelFromStyleLabel($displayName)
                ?? $this->headingLevelFromStyleLabel($name),
            'listStyle' => $listStyle === '' ? null : $listStyle,
        ];
    }

    /**
     * @return array{bold:bool, italic:bool, underline:bool, strikeout:bool, superscript:bool, subscript:bool, smallCaps:bool}
     */
    private function textStyleDefinition(\DOMElement $styleElement): array
    {
        $properties = $this->firstChildElement($styleElement, self::STYLE_NS, 'text-properties');
        if (!$properties instanceof \DOMElement) {
            return [
                'bold' => false,
                'italic' => false,
                'underline' => false,
                'strikeout' => false,
                'superscript' => false,
                'subscript' => false,
                'smallCaps' => false,
            ];
        }

        $weight = strtolower((string) ($this->namespacedAttr($properties, self::FO_NS, 'font-weight') ?? ''));
        $style = strtolower((string) ($this->namespacedAttr($properties, self::FO_NS, 'font-style') ?? ''));
        $underline = strtolower((string) ($this->namespacedAttr($properties, self::STYLE_NS, 'text-underline-style') ?? ''));
        $strikeout = strtolower((string) ($this->namespacedAttr($properties, self::STYLE_NS, 'text-line-through-style') ?? ''));
        $position = strtolower((string) ($this->namespacedAttr($properties, self::STYLE_NS, 'text-position') ?? ''));
        $variant = strtolower((string) ($this->namespacedAttr($properties, self::FO_NS, 'font-variant') ?? ''));

        return [
            'bold' => in_array($weight, ['bold', '700', '800', '900'], true),
            'italic' => in_array($style, ['italic', 'oblique'], true),
            'underline' => $underline !== '' && $underline !== 'none',
            'strikeout' => $strikeout !== '' && $strikeout !== 'none',
            'superscript' => str_starts_with($position, 'super'),
            'subscript' => str_starts_with($position, 'sub'),
            'smallCaps' => $variant === 'small-caps',
        ];
    }

    /**
     * @return array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>
     */
    private function listStyleDefinition(\DOMElement $listStyle): array
    {
        $levels = [];
        foreach ($listStyle->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::TEXT_NS) {
                continue;
            }

            if (!in_array($child->localName, ['list-level-style-number', 'list-level-style-bullet', 'list-level-style-image'], true)) {
                continue;
            }

            $level = max(1, $this->intAttr($child, self::TEXT_NS, 'level', 1));
            if ($child->localName !== 'list-level-style-number') {
                $levels[$level] = [
                    'ordered' => false,
                    'style' => 'default',
                    'delimiter' => 'period',
                    'start' => 1,
                    'format' => 'bullet',
                ];
                continue;
            }

            $format = (string) ($this->namespacedAttr($child, self::STYLE_NS, 'num-format') ?? '1');
            $prefix = (string) ($this->namespacedAttr($child, self::STYLE_NS, 'num-prefix') ?? '');
            $suffix = (string) ($this->namespacedAttr($child, self::STYLE_NS, 'num-suffix') ?? '.');
            $levels[$level] = [
                'ordered' => true,
                'style' => $this->orderedListStyle($format),
                'delimiter' => $this->orderedListDelimiter($prefix, $suffix),
                'start' => max(0, $this->intAttr($child, self::TEXT_NS, 'start-value', 1)),
                'format' => $format,
            ];
        }

        return $levels;
    }

    /**
     * @param array<string, array{fullPath:string, mediaType:string, version:?string}> $manifest
     * @param array{
     *     paragraph:array<string, array{displayName:?string, parent:?string, headingLevel:?int, listStyle:?string}>,
     *     text:array<string, array{bold:bool, italic:bool, underline:bool, strikeout:bool, superscript:bool, subscript:bool, smallCaps:bool}>,
     *     lists:array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>>
     * } $styles
     */
    private function parseContentXml(string $xml, ZipPackage $package, array $manifest, array $styles): AstNode
    {
        $dom = self::loadXml($xml, 'ODT content XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || !$this->isElement($root, self::OFFICE_NS, 'document-content')) {
            throw new \InvalidArgumentException('ODT content XML must use an office:document-content root');
        }

        $body = $this->firstChildElement($root, self::OFFICE_NS, 'body');
        $text = $body instanceof \DOMElement ? $this->firstChildElement($body, self::OFFICE_NS, 'text') : null;
        if (!$text instanceof \DOMElement) {
            throw new \InvalidArgumentException('ODT content XML is missing office:body/office:text');
        }

        return new AstNode('document', ['sourceFormat' => 'odt', 'documentPart' => 'content.xml'], $this->blockChildren(
            $text,
            $package,
            $manifest,
            $styles
        ));
    }

    /**
     * @param array<string, array{fullPath:string, mediaType:string, version:?string}> $manifest
     * @param array{
     *     paragraph:array<string, array{displayName:?string, parent:?string, headingLevel:?int, listStyle:?string}>,
     *     text:array<string, array{bold:bool, italic:bool, underline:bool, strikeout:bool, superscript:bool, subscript:bool, smallCaps:bool}>,
     *     lists:array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>>
     * } $styles
     * @return list<AstNode>
     */
    private function blockChildren(\DOMElement $container, ZipPackage $package, array $manifest, array $styles): array
    {
        $blocks = [];
        foreach ($container->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($this->isElement($child, self::TEXT_NS, 'h')) {
                $blocks[] = $this->headingNode($child, $package, $manifest, $styles);
                continue;
            }

            if ($this->isElement($child, self::TEXT_NS, 'p')) {
                $paragraph = $this->paragraphNode($child, $package, $manifest, $styles);
                if ($paragraph instanceof AstNode) {
                    $blocks[] = $paragraph;
                }
                continue;
            }

            if ($this->isElement($child, self::TEXT_NS, 'list')) {
                $blocks[] = $this->listNode($child, $package, $manifest, $styles);
                continue;
            }

            if ($this->isElement($child, self::TABLE_NS, 'table')) {
                $blocks[] = $this->tableNode($child, $package, $manifest, $styles);
                continue;
            }

            if ($this->isElement($child, self::TEXT_NS, 'section')) {
                array_push($blocks, ...$this->blockChildren($child, $package, $manifest, $styles));
                continue;
            }

            if ($this->isElement($child, self::DRAW_NS, 'frame')) {
                $images = $this->frameImageNodes($child, $package, $manifest);
                if ($images !== []) {
                    $blocks[] = new AstNode('paragraph', [], $images);
                }
            }
        }

        return $blocks;
    }

    /**
     * @param array<string, array{fullPath:string, mediaType:string, version:?string}> $manifest
     * @param array{
     *     paragraph:array<string, array{displayName:?string, parent:?string, headingLevel:?int, listStyle:?string}>,
     *     text:array<string, array{bold:bool, italic:bool, underline:bool, strikeout:bool, superscript:bool, subscript:bool, smallCaps:bool}>,
     *     lists:array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>>
     * } $styles
     */
    private function headingNode(\DOMElement $heading, ZipPackage $package, array $manifest, array $styles): AstNode
    {
        $children = $this->coalesceTextNodes($this->inlineNodes($heading, $package, $manifest, $styles));
        $text = $this->plainInlineText($children);
        $styleName = $this->textStyleName($heading);
        $level = max(1, min(6, $this->intAttr($heading, self::TEXT_NS, 'outline-level', 1)));

        return new AstNode('heading', [
            'level' => $level,
            'style' => $styleName,
            'text' => $text,
            'id' => $this->slugify($text),
        ], $children);
    }

    /**
     * @param array<string, array{fullPath:string, mediaType:string, version:?string}> $manifest
     * @param array{
     *     paragraph:array<string, array{displayName:?string, parent:?string, headingLevel:?int, listStyle:?string}>,
     *     text:array<string, array{bold:bool, italic:bool, underline:bool, strikeout:bool, superscript:bool, subscript:bool, smallCaps:bool}>,
     *     lists:array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>>
     * } $styles
     */
    private function paragraphNode(\DOMElement $paragraph, ZipPackage $package, array $manifest, array $styles): ?AstNode
    {
        $children = $this->coalesceTextNodes($this->inlineNodes($paragraph, $package, $manifest, $styles));
        $text = $this->plainInlineText($children);
        if ($children === [] && $text === '') {
            return null;
        }

        $styleName = $this->textStyleName($paragraph);
        $headingLevel = $this->paragraphHeadingLevel($styleName, $styles['paragraph']);
        if ($headingLevel !== null) {
            return new AstNode('heading', [
                'level' => $headingLevel,
                'style' => $styleName,
                'text' => $text,
                'id' => $this->slugify($text),
            ], $children);
        }

        return new AstNode('paragraph', $styleName === null ? [] : ['style' => $styleName], $children);
    }

    /**
     * @param array<string, array{displayName:?string, parent:?string, headingLevel:?int, listStyle:?string}> $paragraphStyles
     */
    private function paragraphHeadingLevel(?string $styleName, array $paragraphStyles): ?int
    {
        $seen = [];
        while ($styleName !== null && $styleName !== '' && !isset($seen[$styleName])) {
            $seen[$styleName] = true;
            $definition = $paragraphStyles[$styleName] ?? null;
            if ($definition === null) {
                return $this->headingLevelFromStyleLabel($styleName);
            }

            if ($definition['headingLevel'] !== null) {
                return $definition['headingLevel'];
            }

            $styleName = $definition['parent'];
        }

        return null;
    }

    /**
     * @param array<string, array{fullPath:string, mediaType:string, version:?string}> $manifest
     * @param array{
     *     paragraph:array<string, array{displayName:?string, parent:?string, headingLevel:?int, listStyle:?string}>,
     *     text:array<string, array{bold:bool, italic:bool, underline:bool, strikeout:bool, superscript:bool, subscript:bool, smallCaps:bool}>,
     *     lists:array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>>
     * } $styles
     */
    private function listNode(\DOMElement $list, ZipPackage $package, array $manifest, array $styles): AstNode
    {
        $styleName = $this->textStyleName($list);
        $level = $this->listLevel($list);
        $definition = $this->listDefinition($styleName, $level, $styles);
        $start = $this->intAttr($list, self::TEXT_NS, 'start-value', $definition['start']);
        $items = [];

        foreach ($list->childNodes as $child) {
            if (!$child instanceof \DOMElement || !$this->isElement($child, self::TEXT_NS, 'list-item')) {
                continue;
            }

            $itemBlocks = $this->blockChildren($child, $package, $manifest, $styles);
            if ($itemBlocks !== []) {
                $items[] = new AstNode('list_item', ['level' => $level - 1], $itemBlocks);
            }
        }

        $attrs = [
            'sourceFormat' => 'odt',
            'styleName' => $styleName,
            'level' => $level - 1,
        ];
        if ($definition['ordered']) {
            $attrs['style'] = $definition['style'];
            $attrs['delimiter'] = $definition['delimiter'];
            $attrs['start'] = $start;
        } else {
            $attrs['format'] = $definition['format'];
        }

        return new AstNode($definition['ordered'] ? 'ordered_list' : 'bullet_list', $attrs, $items);
    }

    /**
     * @param array{
     *     paragraph:array<string, array{displayName:?string, parent:?string, headingLevel:?int, listStyle:?string}>,
     *     text:array<string, array{bold:bool, italic:bool, underline:bool, strikeout:bool, superscript:bool, subscript:bool, smallCaps:bool}>,
     *     lists:array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>>
     * } $styles
     * @return array{ordered:bool, style:string, delimiter:string, start:int, format:string}
     */
    private function listDefinition(?string $styleName, int $level, array $styles): array
    {
        if ($styleName !== null && isset($styles['lists'][$styleName])) {
            return $styles['lists'][$styleName][$level]
                ?? $styles['lists'][$styleName][1]
                ?? $this->defaultListDefinition();
        }

        if ($styleName !== null && isset($styles['paragraph'][$styleName]['listStyle'])) {
            $listStyleName = $styles['paragraph'][$styleName]['listStyle'];
            if ($listStyleName !== null && isset($styles['lists'][$listStyleName])) {
                return $styles['lists'][$listStyleName][$level]
                    ?? $styles['lists'][$listStyleName][1]
                    ?? $this->defaultListDefinition();
            }
        }

        return $this->defaultListDefinition();
    }

    /**
     * @return array{ordered:bool, style:string, delimiter:string, start:int, format:string}
     */
    private function defaultListDefinition(): array
    {
        return [
            'ordered' => false,
            'style' => 'default',
            'delimiter' => 'period',
            'start' => 1,
            'format' => 'bullet',
        ];
    }

    private function listLevel(\DOMElement $list): int
    {
        $level = 1;
        $node = $list->parentNode;
        while ($node instanceof \DOMNode) {
            if ($node instanceof \DOMElement && $this->isElement($node, self::TEXT_NS, 'list')) {
                $level++;
            }
            $node = $node->parentNode;
        }

        return $level;
    }

    /**
     * @param array<string, array{fullPath:string, mediaType:string, version:?string}> $manifest
     * @param array{
     *     paragraph:array<string, array{displayName:?string, parent:?string, headingLevel:?int, listStyle:?string}>,
     *     text:array<string, array{bold:bool, italic:bool, underline:bool, strikeout:bool, superscript:bool, subscript:bool, smallCaps:bool}>,
     *     lists:array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>>
     * } $styles
     */
    private function tableNode(\DOMElement $table, ZipPackage $package, array $manifest, array $styles): AstNode
    {
        $rows = [];
        foreach ($table->childNodes as $rowElement) {
            if (!$rowElement instanceof \DOMElement || !$this->isElement($rowElement, self::TABLE_NS, 'table-row')) {
                continue;
            }

            $row = $this->tableRowNode($rowElement, $package, $manifest, $styles);
            $repeat = max(1, min(1000, $this->intAttr($rowElement, self::TABLE_NS, 'number-rows-repeated', 1)));
            for ($index = 0; $index < $repeat; $index++) {
                $rows[] = $row;
            }
        }

        return new AstNode('table', [
            'caption' => '',
            'name' => $this->namespacedAttr($table, self::TABLE_NS, 'name'),
        ], [new AstNode('table_body', [], $rows)]);
    }

    /**
     * @param array<string, array{fullPath:string, mediaType:string, version:?string}> $manifest
     * @param array{
     *     paragraph:array<string, array{displayName:?string, parent:?string, headingLevel:?int, listStyle:?string}>,
     *     text:array<string, array{bold:bool, italic:bool, underline:bool, strikeout:bool, superscript:bool, subscript:bool, smallCaps:bool}>,
     *     lists:array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>>
     * } $styles
     */
    private function tableRowNode(\DOMElement $rowElement, ZipPackage $package, array $manifest, array $styles): AstNode
    {
        $cells = [];
        foreach ($rowElement->childNodes as $cellElement) {
            if (!$cellElement instanceof \DOMElement || !$this->isElement($cellElement, self::TABLE_NS, 'table-cell')) {
                continue;
            }

            $cell = $this->tableCellNode($cellElement, $package, $manifest, $styles);
            $repeat = max(1, min(1000, $this->intAttr($cellElement, self::TABLE_NS, 'number-columns-repeated', 1)));
            for ($index = 0; $index < $repeat; $index++) {
                $cells[] = $cell;
            }
        }

        return new AstNode('table_row', [], $cells);
    }

    /**
     * @param array<string, array{fullPath:string, mediaType:string, version:?string}> $manifest
     * @param array{
     *     paragraph:array<string, array{displayName:?string, parent:?string, headingLevel:?int, listStyle:?string}>,
     *     text:array<string, array{bold:bool, italic:bool, underline:bool, strikeout:bool, superscript:bool, subscript:bool, smallCaps:bool}>,
     *     lists:array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>>
     * } $styles
     */
    private function tableCellNode(\DOMElement $cellElement, ZipPackage $package, array $manifest, array $styles): AstNode
    {
        $blocks = $this->blockChildren($cellElement, $package, $manifest, $styles);
        $attrs = [
            'text' => $this->plainBlockText($blocks),
            'colspan' => max(1, $this->intAttr($cellElement, self::TABLE_NS, 'number-columns-spanned', 1)),
            'rowspan' => max(1, $this->intAttr($cellElement, self::TABLE_NS, 'number-rows-spanned', 1)),
        ];

        return new AstNode('table_cell', $attrs, $blocks);
    }

    /**
     * @param array<string, array{fullPath:string, mediaType:string, version:?string}> $manifest
     * @param array{
     *     paragraph:array<string, array{displayName:?string, parent:?string, headingLevel:?int, listStyle:?string}>,
     *     text:array<string, array{bold:bool, italic:bool, underline:bool, strikeout:bool, superscript:bool, subscript:bool, smallCaps:bool}>,
     *     lists:array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>>
     * } $styles
     * @return list<AstNode>
     */
    private function inlineNodes(\DOMElement $container, ZipPackage $package, array $manifest, array $styles): array
    {
        $nodes = [];
        foreach ($container->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                if ($child->nodeValue !== '') {
                    $nodes[] = new AstNode('text', ['text' => $child->nodeValue]);
                }
                continue;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            array_push($nodes, ...$this->inlineElementNodes($child, $package, $manifest, $styles));
        }

        return $nodes;
    }

    /**
     * @param array<string, array{fullPath:string, mediaType:string, version:?string}> $manifest
     * @param array{
     *     paragraph:array<string, array{displayName:?string, parent:?string, headingLevel:?int, listStyle:?string}>,
     *     text:array<string, array{bold:bool, italic:bool, underline:bool, strikeout:bool, superscript:bool, subscript:bool, smallCaps:bool}>,
     *     lists:array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>>
     * } $styles
     * @return list<AstNode>
     */
    private function inlineElementNodes(\DOMElement $element, ZipPackage $package, array $manifest, array $styles): array
    {
        if ($this->isElement($element, self::TEXT_NS, 'span')) {
            $children = $this->coalesceTextNodes($this->inlineNodes($element, $package, $manifest, $styles));

            return $this->applyTextStyle($this->textStyleName($element), $children, $styles['text']);
        }

        if ($this->isElement($element, self::TEXT_NS, 'a')) {
            return [new AstNode('link', [
                'url' => (string) ($this->namespacedAttr($element, self::XLINK_NS, 'href') ?? ''),
            ], $this->coalesceTextNodes($this->inlineNodes($element, $package, $manifest, $styles)))];
        }

        if ($this->isElement($element, self::TEXT_NS, 's')) {
            return [new AstNode('text', [
                'text' => str_repeat(' ', max(1, $this->intAttr($element, self::TEXT_NS, 'c', 1))),
            ])];
        }

        if ($this->isElement($element, self::TEXT_NS, 'tab')) {
            return [new AstNode('text', ['text' => "\t"])];
        }

        if ($this->isElement($element, self::TEXT_NS, 'line-break')) {
            return [new AstNode('linebreak')];
        }

        if ($this->isElement($element, self::TEXT_NS, 'note')) {
            return [$this->noteNode($element, $package, $manifest, $styles)];
        }

        if ($this->isElement($element, self::DRAW_NS, 'frame')) {
            return $this->frameImageNodes($element, $package, $manifest);
        }

        if ($this->isElement($element, self::TEXT_NS, 'bookmark')
            || $this->isElement($element, self::TEXT_NS, 'bookmark-start')
            || $this->isElement($element, self::TEXT_NS, 'bookmark-end')
        ) {
            return [];
        }

        return $this->coalesceTextNodes($this->inlineNodes($element, $package, $manifest, $styles));
    }

    /**
     * @param list<AstNode> $nodes
     * @param array<string, array{bold:bool, italic:bool, underline:bool, strikeout:bool, superscript:bool, subscript:bool, smallCaps:bool}> $textStyles
     * @return list<AstNode>
     */
    private function applyTextStyle(?string $styleName, array $nodes, array $textStyles): array
    {
        if ($styleName === null || !isset($textStyles[$styleName]) || $nodes === []) {
            return $nodes;
        }

        $style = $textStyles[$styleName];
        $wrap = static fn (string $type, array $children): array => [new AstNode($type, [], $children)];

        if ($style['underline']) {
            $nodes = $wrap('underline', $nodes);
        }
        if ($style['strikeout']) {
            $nodes = $wrap('strikeout', $nodes);
        }
        if ($style['subscript']) {
            $nodes = $wrap('subscript', $nodes);
        }
        if ($style['superscript']) {
            $nodes = $wrap('superscript', $nodes);
        }
        if ($style['smallCaps']) {
            $nodes = $wrap('small_caps', $nodes);
        }
        if ($style['italic']) {
            $nodes = $wrap('emph', $nodes);
        }
        if ($style['bold']) {
            $nodes = $wrap('strong', $nodes);
        }

        return $nodes;
    }

    /**
     * @param array<string, array{fullPath:string, mediaType:string, version:?string}> $manifest
     * @param array{
     *     paragraph:array<string, array{displayName:?string, parent:?string, headingLevel:?int, listStyle:?string}>,
     *     text:array<string, array{bold:bool, italic:bool, underline:bool, strikeout:bool, superscript:bool, subscript:bool, smallCaps:bool}>,
     *     lists:array<string, array<int, array{ordered:bool, style:string, delimiter:string, start:int, format:string}>>
     * } $styles
     */
    private function noteNode(\DOMElement $note, ZipPackage $package, array $manifest, array $styles): AstNode
    {
        $body = $this->firstChildElement($note, self::TEXT_NS, 'note-body');
        $citation = $this->firstChildElement($note, self::TEXT_NS, 'note-citation');
        $id = $this->namespacedAttr($note, self::TEXT_NS, 'id');
        $class = $this->namespacedAttr($note, self::TEXT_NS, 'note-class');

        return new AstNode('note', [
            'id' => $id,
            'citation' => $citation instanceof \DOMElement ? trim($citation->textContent) : null,
            'class' => $class,
        ], $body instanceof \DOMElement ? $this->blockChildren($body, $package, $manifest, $styles) : []);
    }

    /**
     * @param array<string, array{fullPath:string, mediaType:string, version:?string}> $manifest
     * @return list<AstNode>
     */
    private function frameImageNodes(\DOMElement $frame, ZipPackage $package, array $manifest): array
    {
        $nodes = [];
        foreach ($frame->getElementsByTagNameNS(self::DRAW_NS, 'image') as $image) {
            if (!$image instanceof \DOMElement) {
                continue;
            }

            $href = $this->namespacedAttr($image, self::XLINK_NS, 'href');
            if ($href === null || $href === '') {
                continue;
            }

            $part = $this->normalizePackageHref($href);
            $isPackaged = $part !== null && $package->has($part);
            $url = $isPackaged ? $part : $href;
            $alt = $this->frameText($frame, self::SVG_NS, 'desc')
                ?? $this->frameText($frame, self::SVG_NS, 'title')
                ?? (string) ($this->namespacedAttr($frame, self::DRAW_NS, 'name') ?? '');
            $title = $this->frameText($frame, self::SVG_NS, 'title') ?? '';
            $attrs = [
                'url' => $url,
                'alt' => $alt,
            ];

            if ($title !== '') {
                $attrs['title'] = $title;
            }

            if ($isPackaged && $part !== null) {
                $attrs['sourcePart'] = '/' . $part;
                $attrs['bytes'] = strlen($package->read($part));
                if (isset($manifest[$part]) && $manifest[$part]['mediaType'] !== '') {
                    $attrs['mediaType'] = $manifest[$part]['mediaType'];
                }
            }

            $nodes[] = new AstNode('image', $attrs, $alt === '' ? [] : [new AstNode('text', ['text' => $alt])]);
        }

        return $nodes;
    }

    private function frameText(\DOMElement $frame, string $namespace, string $localName): ?string
    {
        $element = $this->firstChildElement($frame, $namespace, $localName);
        if (!$element instanceof \DOMElement) {
            return null;
        }

        $text = trim($element->textContent);

        return $text === '' ? null : $text;
    }

    private function normalizePackageHref(string $href): ?string
    {
        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $href) === 1) {
            return null;
        }

        $path = strtok($href, '#?');
        if ($path === false || $path === '') {
            return null;
        }

        if (str_starts_with($path, '/')) {
            $path = substr($path, 1);
        }
        while (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }
        $this->assertManifestPath($path);

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function readMetadata(ZipPackage $package): array
    {
        if (!$package->has('meta.xml')) {
            return [];
        }

        $dom = self::loadXml($package->read('meta.xml'), 'ODT metadata XML');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement || !$this->isElement($root, self::OFFICE_NS, 'document-meta')) {
            return [];
        }

        $meta = $this->firstChildElement($root, self::OFFICE_NS, 'meta');
        if (!$meta instanceof \DOMElement) {
            return [];
        }

        $metadata = [];
        $map = [
            'title' => [self::DC_NS, 'title'],
            'creator' => [self::DC_NS, 'creator'],
            'description' => [self::DC_NS, 'description'],
            'subject' => [self::DC_NS, 'subject'],
            'modified' => [self::DC_NS, 'date'],
            'created' => [self::META_NS, 'creation-date'],
            'initialCreator' => [self::META_NS, 'initial-creator'],
            'generator' => [self::META_NS, 'generator'],
        ];

        foreach ($map as $name => [$namespace, $localName]) {
            $element = $this->firstChildElement($meta, $namespace, $localName);
            if ($element instanceof \DOMElement && trim($element->textContent) !== '') {
                $metadata[$name] = trim($element->textContent);
            }
        }

        $keywords = [];
        foreach ($meta->getElementsByTagNameNS(self::META_NS, 'keyword') as $keyword) {
            if ($keyword instanceof \DOMElement && trim($keyword->textContent) !== '') {
                $keywords[] = trim($keyword->textContent);
            }
        }

        if ($keywords !== []) {
            $metadata['keywords'] = $keywords;
        }

        return $metadata;
    }

    private function textStyleName(\DOMElement $element): ?string
    {
        $styleName = $this->namespacedAttr($element, self::TEXT_NS, 'style-name')
            ?? $this->namespacedAttr($element, self::DRAW_NS, 'style-name')
            ?? $this->namespacedAttr($element, self::STYLE_NS, 'name');

        return $styleName === '' ? null : $styleName;
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

        return 'period';
    }

    private function headingLevelFromStyleLabel(?string $label): ?int
    {
        if ($label === null || $label === '') {
            return null;
        }

        $decoded = preg_replace_callback('/_([0-9A-Fa-f]{2})_/', static function (array $match): string {
            return chr((int) hexdec($match[1]));
        }, $label) ?? $label;
        $normalized = trim(str_replace(['_', '-', '.'], ' ', $decoded));
        if (preg_match('/\bheading\s*([1-6])\b/i', $normalized, $match) === 1) {
            return (int) $match[1];
        }

        return null;
    }

    private function isElement(\DOMElement $element, string $namespace, string $localName): bool
    {
        return $element->namespaceURI === $namespace && $element->localName === $localName;
    }

    private function firstChildElement(\DOMElement $element, string $namespace, string $localName): ?\DOMElement
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->namespaceURI === $namespace && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }

    private function namespacedAttr(\DOMElement $element, string $namespace, string $localName): ?string
    {
        if ($element->hasAttributeNS($namespace, $localName)) {
            return $element->getAttributeNS($namespace, $localName);
        }

        return null;
    }

    private function intAttr(\DOMElement $element, string $namespace, string $localName, int $default): int
    {
        $value = $this->namespacedAttr($element, $namespace, $localName);
        if ($value === null || preg_match('/^-?\d+$/', $value) !== 1) {
            return $default;
        }

        return (int) $value;
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function coalesceTextNodes(array $nodes): array
    {
        $coalesced = [];
        foreach ($nodes as $node) {
            $lastIndex = count($coalesced) - 1;
            if ($node->type === 'text' && $lastIndex >= 0 && $coalesced[$lastIndex]->type === 'text') {
                $coalesced[$lastIndex] = new AstNode('text', [
                    'text' => (string) $coalesced[$lastIndex]->attr('text', '') . (string) $node->attr('text', ''),
                ]);
                continue;
            }

            $coalesced[] = $node;
        }

        return $coalesced;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if ($node->type === 'text') {
                $text .= (string) $node->attr('text', '');
                continue;
            }
            if ($node->type === 'linebreak') {
                $text .= "\n";
                continue;
            }
            if ($node->type === 'image') {
                $text .= (string) $node->attr('alt', '');
                continue;
            }

            $text .= $this->plainInlineText($node->children);
        }

        return $text;
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function plainBlockText(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            $parts[] = $this->plainInlineText($block->children);
        }

        return trim(implode("\n", array_filter($parts, static fn (string $part): bool => $part !== '')));
    }

    private function slugify(string $text): string
    {
        $slug = strtolower(trim($text));
        $slug = preg_replace('/[^\pL\pN]+/u', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug === '' ? 'section' : $slug;
    }

    private function assertManifestPath(string $path): void
    {
        if ($path === '/') {
            return;
        }

        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\') || str_starts_with($path, '/')) {
            throw new \InvalidArgumentException('Unsafe ODT manifest path: ' . $path);
        }

        $normalized = rtrim($path, '/');
        if ($normalized === '') {
            throw new \InvalidArgumentException('Unsafe ODT manifest path: ' . $path);
        }

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('Unsafe ODT manifest path: ' . $path);
            }
        }
    }

    private static function loadXml(string $xml, string $label): \DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->resolveExternals = false;
        $dom->substituteEntities = false;
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new \InvalidArgumentException('Unable to parse ' . $label);
        }

        return $dom;
    }
}
