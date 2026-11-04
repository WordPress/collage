<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class XmlReader
{
    private const MATHML_NAMESPACE = 'http://www.w3.org/1998/Math/MathML';
    private const XLINK_NAMESPACE = 'http://www.w3.org/1999/xlink';
    private const JATS_ROOTS = ['article', 'book', 'book-part'];
    private const JATS_BODY_ROOTS = ['body', 'book-body'];
    private const JATS_BACK_MATTER_ROOTS = ['back', 'book-back', 'book-part-back'];
    private const JATS_METADATA_ROOTS = ['front', 'article-meta', 'book-meta', 'book-part-meta', 'journal-meta'];
    private const JATS_IMAGE_NAMES = ['graphic', 'inline-graphic'];
    private const JATS_FORMULA_NAMES = ['inline-formula', 'disp-formula'];
    private const TABLE_CELL_NAMES = ['td', 'th', 'entry'];
    private const BLOCK_CONTAINER_NAMES = [
        'article',
        'book',
        'book-part',
        'body',
        'book-body',
        'back',
        'book-back',
        'book-part-back',
        'section',
        'sec',
        'chapter',
        'part',
        'appendix',
        'app',
        'front',
        'article-meta',
        'book-meta',
        'book-part-meta',
        'journal-meta',
        'abstract',
        'kwd-group',
        'ref-list',
        'list',
        'ordered-list',
        'bullet-list',
        'fig',
        'caption',
        'table-wrap',
        'table',
        'thead',
        'tbody',
        'tfoot',
    ];
    private const PARAGRAPH_NAMES = ['p', 'para', 'paragraph', 'license-p', 'title-group'];
    private const TITLE_NAMES = [
        'title',
        'heading',
        'article-title',
        'book-title',
        'subtitle',
        'trans-title',
        'alt-title',
    ];
    private const UNORDERED_LIST_NAMES = ['ul', 'bullet-list', 'unordered-list', 'list', 'itemizedlist'];
    private const ORDERED_LIST_NAMES = ['ol', 'ordered-list', 'numbered-list', 'orderedlist'];
    private const LIST_ITEM_NAMES = ['li', 'item', 'list-item', 'listitem'];

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly string $format = 'xml',
        private readonly array $options = [],
    ) {
    }

    public function read(string $bytes): AstNode
    {
        $format = $this->normalizedFormat();
        // JATS-family exports in the wild can contain recoverable XML defects
        // such as repeated namespaced metadata attributes. Keep generic XML
        // strict, but retain the readable document structure for these formats.
        $dom = XmlHtmlDom::loadXmlDocument($bytes, strtoupper($format) . ' input', false, $format !== 'xml');
        $root = $dom->documentElement;
        if (!$root instanceof \DOMElement) {
            throw new \InvalidArgumentException('XML reader requires a document element.');
        }

        if ($format === 'jats' || $format === 'bits') {
            return $this->readJats($dom, $root, $bytes, $format);
        }

        return $this->readGenericXml($dom, $root, $bytes, $format);
    }

    private function normalizedFormat(): string
    {
        $format = strtolower(trim($this->format));

        return in_array($format, ['jats', 'bits'], true) ? $format : 'xml';
    }

    private function readGenericXml(\DOMDocument $dom, \DOMElement $root, string $bytes, string $format): AstNode
    {
        $blocks = $this->blocksFromElement($root, 1, false);
        if ($blocks === []) {
            $text = XmlHtmlDom::normalizedText($root);
            if ($text !== '') {
                $blocks[] = $this->paragraph($text);
            }
        }

        return new AstNode('document', [
            'sourceFormat' => $format,
            'meta' => $this->baseMetadata($dom, $root, $bytes, $format, [
                'reader' => self::class,
                'readerScope' => 'bounded-generic-xml-reader',
                'xmlElementCount' => count(XmlHtmlDom::descendantElements($root)) + 1,
                'xmlDetectedTables' => $this->countNodesOfType($blocks, 'table'),
                'xmlDetectedHeadings' => $this->countNodesOfType($blocks, 'heading'),
            ]),
        ], $blocks);
    }

    private function readJats(\DOMDocument $dom, \DOMElement $root, string $bytes, string $format): AstNode
    {
        if (!in_array($root->localName, self::JATS_ROOTS, true)) {
            throw new \InvalidArgumentException('JATS/BITS reader root must be article, book, or book-part.');
        }

        $packet = XmlHtmlDom::summarizeJatsFrontMatter($dom, $format);
        $blocks = [];
        $title = $this->cleanText($packet['title'] ?? '');
        $abstractText = $this->cleanText($packet['abstractText'] ?? '');

        // Pandoc maps JATS/BITS front matter to document metadata. It does
        // not duplicate titles, subtitles, or abstracts into the body AST.
        $bodyHeadingLevel = $root->localName === 'book' ? 2 : 1;

        $body = $this->firstJatsBodyElement($root);
        if ($body instanceof \DOMElement) {
            array_push($blocks, ...$this->blocksFromElement($body, $bodyHeadingLevel, true));
        }
        $backMatter = $this->firstJatsBackMatterElement($root);
        if ($backMatter instanceof \DOMElement) {
            array_push($blocks, ...$this->blocksFromElement($backMatter, $bodyHeadingLevel, true));
        }

        if ($blocks === []) {
            foreach ($this->blocksFromElement($root, 1, true) as $block) {
                $blocks[] = $block;
            }
        }

        $meta = $this->baseMetadata($dom, $root, $bytes, $format, [
            'reader' => self::class,
            'readerScope' => 'bounded-jats-bits-reader',
            'jatsPacket' => $packet,
            'jatsTitle' => $title === '' ? null : $title,
            'jatsAbstractText' => $abstractText === '' ? null : $abstractText,
            'jatsSectionCount' => $packet['sectionCount'] ?? 0,
            'jatsTableWrapCount' => $packet['tableWrapCount'] ?? 0,
            'jatsReferenceCount' => $packet['referenceCount'] ?? 0,
            'xmlDetectedTables' => $this->countNodesOfType($blocks, 'table'),
            'xmlDetectedHeadings' => $this->countNodesOfType($blocks, 'heading'),
        ]);
        if ($title !== '') {
            $meta['title'] = $title;
            $meta['titleInlines'] = $this->textInlines($title);
        }
        $contributors = $packet['contributorNames'] ?? [];
        if (is_array($contributors) && $contributors !== []) {
            $meta['authors'] = array_values(array_filter($contributors, 'is_string'));
        }

        return new AstNode('document', [
            'sourceFormat' => $format,
            'meta' => $meta,
        ], $blocks);
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function baseMetadata(\DOMDocument $dom, \DOMElement $root, string $bytes, string $format, array $extra = []): array
    {
        return array_replace([
            'sourceFormat' => $format,
            'sourceBytes' => strlen($bytes),
            'sourceSha256' => hash('sha256', $bytes),
            'rootName' => $root->localName,
            'rootNamespace' => $root->namespaceURI,
            'rootAttributes' => $this->attributes($root),
            'namespaceSummary' => XmlHtmlDom::summarizeXmlNamespaceScopes($dom),
            'payloadExposurePolicy' => 'xml-dom-text-and-structural-metadata-only',
        ], $extra);
    }

    /**
     * @return list<AstNode>
     */
    private function blocksFromElement(\DOMElement $element, int $headingLevel, bool $jatsMode): array
    {
        $name = $this->name($element);
        if ($jatsMode && in_array($name, self::JATS_METADATA_ROOTS, true)) {
            return [];
        }
        if (in_array($name, ['table', 'informaltable'], true)) {
            $table = $this->tableFromElement($element, $this->nodeAttrs($element));

            return $table === null ? [] : [$table];
        }
        if ($name === 'table-wrap') {
            $table = $this->tableWrapFromElement($element);

            return $table === null ? [] : [$table];
        }
        if (in_array($name, self::UNORDERED_LIST_NAMES, true)) {
            $list = $jatsMode
                ? $this->jatsListFromElement($element, $headingLevel)
                : $this->listFromElement($element, false, $this->nodeAttrs($element));

            return $list === null ? [] : [$list];
        }
        if (in_array($name, self::ORDERED_LIST_NAMES, true)) {
            $list = $jatsMode
                ? $this->jatsListFromElement($element, $headingLevel, true)
                : $this->listFromElement($element, true, $this->nodeAttrs($element));

            return $list === null ? [] : [$list];
        }
        if (in_array($name, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
            $level = (int) substr($name, 1);
            $text = XmlHtmlDom::normalizedText($element);

            return $text === '' ? [] : [$this->heading($text, $level, $this->nodeAttrs($element))];
        }
        if (in_array($name, self::TITLE_NAMES, true)) {
            $text = XmlHtmlDom::normalizedText($element);

            return $text === '' ? [] : [$this->heading($text, $headingLevel, $this->nodeAttrs($element))];
        }
        if (in_array($name, self::PARAGRAPH_NAMES, true)) {
            $paragraph = $this->paragraphFromElement($element);

            return $paragraph === null ? [] : [$paragraph];
        }
        if ($jatsMode && $name === 'fig') {
            $figure = $this->jatsFigureFromElement($element);

            return $figure === null ? [] : [$figure];
        }
        if ($jatsMode && in_array($name, self::JATS_IMAGE_NAMES, true)) {
            $image = $this->jatsImageFromElement($element);
            $paragraph = $image instanceof AstNode
                ? $this->paragraphFromInlines([$image], $this->nodeAttrs($element))
                : null;

            return $paragraph === null ? [] : [$paragraph];
        }
        if ($jatsMode && in_array($name, self::JATS_FORMULA_NAMES, true)) {
            $inlines = $this->jatsFormulaInlines($element, $name === 'disp-formula');
            $paragraph = $this->paragraphFromInlines($inlines, $this->nodeAttrs($element));

            return $paragraph === null ? [] : [$paragraph];
        }
        if ($jatsMode && $name === 'alternatives') {
            $paragraph = $this->paragraphFromInlines($this->jatsAlternativeInlines($element), $this->nodeAttrs($element));

            return $paragraph === null ? [] : [$paragraph];
        }
        if ($jatsMode && $name === 'def-list') {
            $list = $this->jatsDefinitionListFromElement($element, $headingLevel);

            return $list === null ? [] : [$list];
        }
        if ($jatsMode && in_array($name, ['preformat', 'pre'], true)) {
            $text = trim((string) $element->textContent, "\r\n");

            return $text === '' ? [] : [new AstNode('code_block', array_replace($this->nodeAttrs($element), ['text' => $text]))];
        }

        $blocks = [];
        foreach (XmlHtmlDom::childElements($element) as $child) {
            $childLevel = $headingLevel;
            if (
                in_array($name, ['sec', 'section', 'chapter', 'part', 'appendix', 'app'], true)
                && in_array($this->name($child), ['sec', 'section', 'chapter', 'part', 'appendix', 'app'], true)
            ) {
                $childLevel = min(6, $headingLevel + 1);
            }

            array_push($blocks, ...$this->blocksFromElement($child, $childLevel, $jatsMode));
        }

        if ($blocks !== []) {
            return $this->attachElementAnchor($blocks, $element);
        }
        if ($this->hasBlockContainerChild($element)) {
            return [];
        }

        $text = XmlHtmlDom::normalizedText($element);

        return $text === '' ? [] : [$this->paragraph($text, $this->nodeAttrs($element))];
    }

    private function paragraphFromElement(\DOMElement $element): ?AstNode
    {
        $inlines = $this->inlineNodes($element);

        return $this->paragraphFromInlines($inlines, $this->nodeAttrs($element));
    }

    /**
     * @param list<AstNode> $inlines
     * @param array<string, mixed> $attrs
     */
    private function paragraphFromInlines(array $inlines, array $attrs = []): ?AstNode
    {
        $text = $this->plainInlineText($inlines);
        if ($text === '' && !$this->hasRenderableInline($inlines)) {
            return null;
        }

        return new AstNode('paragraph', array_replace($attrs, ['text' => $text]), $inlines);
    }

    private function tableWrapFromElement(\DOMElement $tableWrap): ?AstNode
    {
        $table = XmlHtmlDom::firstDescendantElement($tableWrap, 'table')
            ?? XmlHtmlDom::firstDescendantElement($tableWrap, 'informaltable');
        if (!$table instanceof \DOMElement) {
            return null;
        }

        $attrs = $this->nodeAttrs($tableWrap);
        $caption = $this->tableWrapCaption($tableWrap);
        if ($caption !== '') {
            $attrs['caption'] = $caption;
        }

        return $this->tableFromElement($table, $attrs);
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function tableFromElement(\DOMElement $table, array $attrs = []): ?AstNode
    {
        $headRows = [];
        $bodyRows = [];
        $footRows = [];

        foreach ($this->tableRows($table) as $row) {
            $cells = $this->tableCells($row);
            if ($cells === []) {
                continue;
            }
            $rowNode = new AstNode('table_row', $this->nodeAttrs($row), $cells);
            $parentName = $row->parentNode instanceof \DOMElement ? $this->name($row->parentNode) : '';
            if ($parentName === 'thead') {
                $headRows[] = $rowNode;
            } elseif ($parentName === 'tfoot') {
                $footRows[] = $rowNode;
            } elseif ($this->rowIsHeader($cells) && $headRows === [] && $bodyRows === []) {
                $headRows[] = $rowNode;
            } else {
                $bodyRows[] = $rowNode;
            }
        }

        if ($headRows === [] && $bodyRows !== []) {
            $headRows[] = array_shift($bodyRows);
        }
        if ($headRows === [] && $bodyRows === [] && $footRows === []) {
            return null;
        }

        $children = [];
        if ($headRows !== []) {
            $children[] = new AstNode('table_head', [], $headRows);
        }
        $children[] = new AstNode('table_body', [], $bodyRows);
        if ($footRows !== []) {
            $children[] = new AstNode('table_foot', [], $footRows);
        }

        return new AstNode('table', $attrs, $children);
    }

    /**
     * @return list<\DOMElement>
     */
    private function tableRows(\DOMElement $table): array
    {
        $rows = [];
        foreach (XmlHtmlDom::descendantElements($table) as $candidate) {
            $name = $this->name($candidate);
            if ($name === 'tr' || $name === 'row') {
                $rows[] = $candidate;
            }
        }

        return $rows;
    }

    /**
     * @return list<AstNode>
     */
    private function tableCells(\DOMElement $row): array
    {
        $cells = [];
        foreach (XmlHtmlDom::childElements($row) as $cell) {
            $name = $this->name($cell);
            if (!in_array($name, self::TABLE_CELL_NAMES, true)) {
                continue;
            }

            $attrs = $this->nodeAttrs($cell);
            if ($name === 'th') {
                $attrs['header'] = true;
            }
            $rowspan = $this->positiveIntAttr($cell, ['rowspan', 'morerows']);
            if ($rowspan > 1) {
                $attrs['rowspan'] = $name === 'entry' && XmlHtmlDom::attribute($cell, 'morerows') !== null
                    ? $rowspan + 1
                    : $rowspan;
            }
            $colspan = $this->positiveIntAttr($cell, ['colspan', 'namest']);
            if ($colspan > 1) {
                $attrs['colspan'] = $colspan;
            }

            $inlines = $this->inlineNodes($cell);
            $text = $this->plainInlineText($inlines);
            $attrs['text'] = $text;
            $cells[] = new AstNode('table_cell', $attrs, [
                new AstNode('plain', ['text' => $text], $inlines),
            ]);
        }

        return $cells;
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function listFromElement(\DOMElement $list, bool $ordered, array $attrs = []): ?AstNode
    {
        $items = [];
        foreach (XmlHtmlDom::childElements($list) as $child) {
            if (!in_array($this->name($child), self::LIST_ITEM_NAMES, true)) {
                continue;
            }
            $inlines = $this->inlineNodes($child);
            $text = $this->plainInlineText($inlines);
            if ($text === '' && !$this->hasRenderableInline($inlines)) {
                continue;
            }
            $items[] = new AstNode('list_item', array_replace($this->nodeAttrs($child), ['text' => $text]), $inlines);
        }

        return $items === [] ? null : new AstNode($ordered ? 'ordered_list' : 'bullet_list', $attrs, $items);
    }

    private function jatsListFromElement(\DOMElement $list, int $headingLevel, bool $forceOrdered = false): ?AstNode
    {
        $items = [];
        foreach (XmlHtmlDom::childElements($list) as $item) {
            if (!in_array($this->name($item), ['li', 'item', 'list-item'], true)) {
                continue;
            }

            $blocks = [];
            foreach (XmlHtmlDom::childElements($item) as $child) {
                $name = $this->name($child);
                if ($name === 'label') {
                    continue;
                }
                if (
                    in_array($name, self::PARAGRAPH_NAMES, true)
                    || in_array($name, ['ul', 'ol', 'bullet-list', 'ordered-list', 'list', 'def-list'], true)
                ) {
                    array_push($blocks, ...$this->blocksFromElement($child, $headingLevel, true));
                }
            }

            if ($blocks === []) {
                $inlines = $this->inlineNodes($item);
                $paragraph = $this->paragraphFromInlines($inlines);
                if ($paragraph instanceof AstNode) {
                    $blocks[] = $paragraph;
                }
            }
            if ($blocks === []) {
                continue;
            }

            $attrs = array_replace($this->nodeAttrs($item), [
                'text' => XmlHtmlDom::normalizedText($item),
            ]);
            $items[] = new AstNode('list_item', $attrs, $blocks);
        }

        if ($items === []) {
            return null;
        }

        $attrs = $this->nodeAttrs($list);
        $ordered = $forceOrdered || $this->jatsListIsOrdered($list);
        $start = $this->positiveIntAttr($list, ['start', 'start-number', 'startnum']);
        if ($ordered && $start > 1) {
            $attrs['start'] = $start;
        }

        return new AstNode($ordered ? 'ordered_list' : 'bullet_list', $attrs, $items);
    }

    private function jatsListIsOrdered(\DOMElement $list): bool
    {
        $type = strtolower(trim((string) (XmlHtmlDom::attribute($list, 'list-type') ?? '')));
        if ($type === '') {
            return false;
        }

        return str_contains($type, 'order')
            || str_contains($type, 'roman')
            || str_contains($type, 'alpha')
            || str_contains($type, 'number');
    }

    private function jatsDefinitionListFromElement(\DOMElement $list, int $headingLevel): ?AstNode
    {
        $items = [];
        foreach (XmlHtmlDom::childElements($list, 'def-item') as $item) {
            $termElement = XmlHtmlDom::firstDescendantElement($item, 'term');
            if (!$termElement instanceof \DOMElement) {
                continue;
            }

            $termInlines = $this->inlineNodes($termElement);
            $termText = $this->plainInlineText($termInlines);
            if ($termText === '') {
                $termText = XmlHtmlDom::normalizedText($termElement);
                $termInlines = $this->textInlines($termText);
            }
            if ($termText === '') {
                continue;
            }

            $definitions = [];
            foreach (XmlHtmlDom::childElements($item, 'def') as $definition) {
                $blocks = $this->blocksFromElement($definition, $headingLevel, true);
                if ($blocks === []) {
                    $text = XmlHtmlDom::normalizedText($definition);
                    if ($text !== '') {
                        $blocks = [$this->paragraph($text, $this->nodeAttrs($definition))];
                    }
                }
                if ($blocks !== []) {
                    $definitions[] = new AstNode('definition', $this->nodeAttrs($definition), $blocks);
                }
            }
            if ($definitions === []) {
                continue;
            }

            $items[] = new AstNode(
                'definition_item',
                array_replace($this->nodeAttrs($item), ['term' => $termText]),
                array_merge([
                    new AstNode(
                        'term',
                        array_replace($this->nodeAttrs($termElement), ['text' => $termText]),
                        $termInlines
                    ),
                ], $definitions)
            );
        }

        return $items === [] ? null : new AstNode('definition_list', $this->nodeAttrs($list), $items);
    }

    /**
     * @return list<AstNode>
     */
    private function inlineNodes(\DOMNode $node): array
    {
        $nodes = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText || $child instanceof \DOMCdataSection) {
                $this->appendTextNode($nodes, preg_replace('/\s+/u', ' ', $child->nodeValue ?? '') ?? '');
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $name = $this->name($child);
            if ($name === 'br') {
                $nodes[] = new AstNode('linebreak');
                continue;
            }
            if (in_array($name, self::JATS_IMAGE_NAMES, true)) {
                $image = $this->jatsImageFromElement($child);
                if ($image instanceof AstNode) {
                    $nodes[] = $image;
                    continue;
                }
            }
            if ($this->isMathMlElement($child)) {
                $nodes[] = $this->mathFromMathMlElement($child);
                continue;
            }
            if ($name === 'tex-math') {
                $math = $this->mathFromTexElement($child);
                if ($math instanceof AstNode) {
                    $nodes[] = $math;
                }
                continue;
            }
            if ($name === 'alternatives') {
                array_push($nodes, ...$this->jatsAlternativeInlines($child));
                continue;
            }
            if (in_array($name, self::JATS_FORMULA_NAMES, true)) {
                array_push($nodes, ...$this->jatsFormulaInlines($child, $name === 'disp-formula'));
                continue;
            }
            if (in_array($name, ['xref', 'ext-link', 'uri', 'a', 'link'], true)) {
                $children = $this->inlineNodes($child);
                $text = $this->plainInlineText($children);
                if ($text === '') {
                    $text = XmlHtmlDom::normalizedText($child);
                    $children = $this->textInlines($text);
                }
                $url = $this->linkTarget($child);
                $nodes[] = new AstNode('link', ['url' => $url, 'title' => ''], $children);
                continue;
            }

            $children = $this->inlineNodes($child);
            if ($children === []) {
                $text = XmlHtmlDom::normalizedText($child);
                $children = $this->textInlines($text);
            }
            if ($children === []) {
                continue;
            }

            $nodes[] = match ($name) {
                'bold', 'b', 'strong' => new AstNode('strong', [], $children),
                'italic', 'i', 'em', 'emph' => new AstNode('emph', [], $children),
                'sup' => new AstNode('superscript', [], $children),
                'sub' => new AstNode('subscript', [], $children),
                'code', 'monospace' => new AstNode('code', ['text' => $this->plainInlineText($children)]),
                default => count($children) === 1 ? $children[0] : new AstNode('span', $this->nodeAttrs($child), $children),
            };
        }

        return $this->trimInlineBoundary($this->coalesceTextNodes($nodes));
    }

    private function jatsFigureFromElement(\DOMElement $figure): ?AstNode
    {
        $graphic = $this->firstJatsImageElement($figure);
        if (!$graphic instanceof \DOMElement) {
            return null;
        }

        $image = $this->jatsImageFromElement($graphic, $this->jatsFigureAltText($figure));
        if (!$image instanceof AstNode) {
            return null;
        }

        $attrs = $this->nodeAttrs($figure);
        $caption = $this->jatsFigureCaption($figure);
        if ($caption !== '') {
            $attrs['caption'] = $caption;
            $attrs['captionInlines'] = $this->textInlines($caption);
        }

        return new AstNode('figure', $attrs, [$image]);
    }

    private function jatsImageFromElement(\DOMElement $element, string $fallbackAlt = ''): ?AstNode
    {
        $url = trim((string) (
            XmlHtmlDom::attribute($element, 'href', self::XLINK_NAMESPACE)
            ?? XmlHtmlDom::attribute($element, 'href')
            ?? XmlHtmlDom::attribute($element, 'src')
            ?? ''
        ));
        if ($url === '') {
            return null;
        }

        $attrs = $this->nodeAttrs($element);
        $attrs['url'] = $url;
        $attrs['src'] = $url;
        $attrs['alt'] = $this->jatsImageAltText($element, $fallbackAlt);
        $attrs['title'] = $this->jatsImageTitle($element);
        $dimensions = [];
        foreach (['width', 'height'] as $name) {
            $value = XmlHtmlDom::attribute($element, $name);
            if ($value !== null && trim($value) !== '') {
                $dimensions[$name] = trim($value);
            }
        }
        if ($dimensions !== []) {
            $attrs['attributes'] = $dimensions;
        }

        return new AstNode('image', $attrs, $this->textInlines((string) $attrs['alt']));
    }

    /**
     * @return list<AstNode>
     */
    private function jatsAlternativeInlines(\DOMElement $alternatives): array
    {
        $candidates = XmlHtmlDom::descendantElements($alternatives);
        foreach ($candidates as $candidate) {
            if ($this->isMathMlElement($candidate)) {
                return [$this->mathFromMathMlElement($candidate)];
            }
        }
        foreach ($candidates as $candidate) {
            if ($this->name($candidate) !== 'tex-math') {
                continue;
            }
            $math = $this->mathFromTexElement($candidate);
            if ($math instanceof AstNode) {
                return [$math];
            }
        }
        foreach ($candidates as $candidate) {
            if (!in_array($this->name($candidate), self::JATS_IMAGE_NAMES, true)) {
                continue;
            }
            $image = $this->jatsImageFromElement($candidate);
            if ($image instanceof AstNode) {
                return [$image];
            }
        }

        return $this->inlineNodes($alternatives);
    }

    /**
     * @return list<AstNode>
     */
    private function jatsFormulaInlines(\DOMElement $formula, bool $display): array
    {
        $inlines = $this->inlineNodes($formula);

        return $display ? $this->withMathDisplay($inlines, true) : $inlines;
    }

    private function mathFromMathMlElement(\DOMElement $math): AstNode
    {
        $text = (new MathMlToTexReader())->texFromElement($math);
        if (!is_string($text) || $text === '') {
            $text = $this->cleanText($math->textContent ?? '');
        }

        return new AstNode('math', array_replace($this->nodeAttrs($math), [
            'display' => strtolower(trim((string) XmlHtmlDom::attribute($math, 'display'))) === 'block',
            'text' => $text,
            'mathml' => $this->mathMlElementXml($math),
        ]));
    }

    private function mathFromTexElement(\DOMElement $math): ?AstNode
    {
        $text = $this->cleanText($math->textContent ?? '');
        if ($text === '') {
            return null;
        }

        return new AstNode('math', array_replace($this->nodeAttrs($math), [
            'display' => strtolower(trim((string) XmlHtmlDom::attribute($math, 'display'))) === 'block',
            'text' => $text,
        ]));
    }

    private function isMathMlElement(\DOMElement $element): bool
    {
        return $this->name($element) === 'math' && $element->namespaceURI === self::MATHML_NAMESPACE;
    }

    private function mathMlElementXml(\DOMElement $math): string
    {
        $xml = $math->ownerDocument instanceof \DOMDocument ? $math->ownerDocument->saveXML($math) : '';
        $xml = trim(is_string($xml) ? $xml : '');
        $prefix = $math->prefix;
        if ($xml === '' || !is_string($prefix) || $prefix === '') {
            return $xml;
        }

        $quotedPrefix = preg_quote($prefix, '/');
        $xml = preg_replace('/<(\\/?)' . $quotedPrefix . ':/u', '<$1', $xml) ?? $xml;

        return preg_replace('/\\s+xmlns:' . $quotedPrefix . '\\s*=/u', ' xmlns=', $xml, 1) ?? $xml;
    }

    private function jatsImageAltText(\DOMElement $element, string $fallback = ''): string
    {
        foreach ([
            XmlHtmlDom::attribute($element, 'alt-text', self::XLINK_NAMESPACE),
            XmlHtmlDom::attribute($element, 'alt-text'),
            XmlHtmlDom::attribute($element, 'alt'),
        ] as $candidate) {
            $candidate = $this->cleanText($candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        $altText = XmlHtmlDom::firstChildElement($element, 'alt-text');
        if ($altText instanceof \DOMElement) {
            $candidate = XmlHtmlDom::normalizedText($altText);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return $this->cleanText($fallback);
    }

    private function jatsImageTitle(\DOMElement $element): string
    {
        return $this->cleanText(
            XmlHtmlDom::attribute($element, 'title', self::XLINK_NAMESPACE)
            ?? XmlHtmlDom::attribute($element, 'title')
            ?? ''
        );
    }

    private function jatsFigureAltText(\DOMElement $figure): string
    {
        $altText = XmlHtmlDom::firstChildElement($figure, 'alt-text');

        return $altText instanceof \DOMElement ? XmlHtmlDom::normalizedText($altText) : '';
    }

    private function jatsFigureCaption(\DOMElement $figure): string
    {
        $caption = XmlHtmlDom::firstChildElement($figure, 'caption');

        return $caption instanceof \DOMElement ? XmlHtmlDom::normalizedText($caption) : '';
    }

    private function firstJatsImageElement(\DOMElement $element): ?\DOMElement
    {
        foreach (XmlHtmlDom::childElements($element) as $child) {
            if (in_array($this->name($child), self::JATS_IMAGE_NAMES, true)) {
                return $child;
            }
        }
        foreach (XmlHtmlDom::descendantElements($element) as $child) {
            if (in_array($this->name($child), self::JATS_IMAGE_NAMES, true)) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function withMathDisplay(array $nodes, bool $display): array
    {
        $result = [];
        foreach ($nodes as $node) {
            $attrs = $node->type === 'math' ? array_replace($node->attrs, ['display' => $display]) : $node->attrs;
            $result[] = new AstNode($node->type, $attrs, $this->withMathDisplay($node->children, $display));
        }

        return $result;
    }

    private function appendTextNode(array &$nodes, string $text): void
    {
        if ($text === '') {
            return;
        }

        $nodes[] = new AstNode('text', ['text' => $text]);
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function coalesceTextNodes(array $nodes): array
    {
        $coalesced = [];
        foreach ($nodes as $node) {
            if ($node->type === 'text' && $coalesced !== []) {
                $lastIndex = array_key_last($coalesced);
                $last = $coalesced[$lastIndex];
                if ($last instanceof AstNode && $last->type === 'text') {
                    $coalesced[$lastIndex] = new AstNode('text', [
                        'text' => (string) $last->attr('text', '') . (string) $node->attr('text', ''),
                    ]);
                    continue;
                }
            }
            $coalesced[] = $node;
        }

        return $coalesced;
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function trimInlineBoundary(array $nodes): array
    {
        if ($nodes === []) {
            return [];
        }

        $first = $nodes[0];
        if ($first->type === 'text') {
            $text = ltrim((string) $first->attr('text', ''));
            if ($text === '') {
                array_shift($nodes);
            } else {
                $nodes[0] = new AstNode('text', ['text' => $text]);
            }
        }
        if ($nodes === []) {
            return [];
        }

        $lastIndex = array_key_last($nodes);
        $last = $nodes[$lastIndex];
        if ($last->type === 'text') {
            $text = rtrim((string) $last->attr('text', ''));
            if ($text === '') {
                array_pop($nodes);
            } else {
                $nodes[$lastIndex] = new AstNode('text', ['text' => $text]);
            }
        }

        return array_values($nodes);
    }

    /**
     * @return list<AstNode>
     */
    private function textInlines(string $text): array
    {
        $text = $this->cleanText($text);

        return $text === '' ? [] : [new AstNode('text', ['text' => $text])];
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function paragraph(string $text, array $attrs = []): AstNode
    {
        $text = $this->cleanText($text);

        return new AstNode('paragraph', array_replace($attrs, ['text' => $text]), $this->textInlines($text));
    }

    /**
     * @param array<string, mixed> $attrs
     */
    private function heading(string $text, int $level, array $attrs = []): AstNode
    {
        $text = $this->cleanText($text);
        $level = max(1, min(6, $level));

        return new AstNode('heading', array_replace($attrs, ['level' => $level, 'text' => $text]), $this->textInlines($text));
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            $text .= match ($node->type) {
                'text', 'code' => (string) $node->attr('text', ''),
                'linebreak', 'softbreak' => ' ',
                default => $this->plainInlineText($node->children),
            };
        }

        return $this->cleanText($text);
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function hasRenderableInline(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if (in_array($node->type, ['image', 'math', 'raw_html_inline', 'raw_inline'], true)) {
                return true;
            }
            if ($this->hasRenderableInline($node->children)) {
                return true;
            }
        }

        return false;
    }

    private function cleanText(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', (string) $value) ?? (string) $value);
    }

    private function linkTarget(\DOMElement $element): string
    {
        foreach ([
            ['href', null],
            ['href', 'http://www.w3.org/1999/xlink'],
            ['rid', null],
            ['id', null],
        ] as [$name, $namespace]) {
            $value = XmlHtmlDom::attribute($element, $name, $namespace);
            if ($value === null || trim($value) === '') {
                continue;
            }

            return $name === 'rid' || ($name === 'id' && !str_starts_with($value, '#')) ? '#' . $value : $value;
        }

        $text = XmlHtmlDom::normalizedText($element);

        return preg_match('/^[a-z][a-z0-9+.-]*:/i', $text) === 1 ? $text : '#';
    }

    /**
     * @return array<string, mixed>
     */
    private function nodeAttrs(\DOMElement $element): array
    {
        $attrs = [];
        $id = XmlHtmlDom::attribute($element, 'id');
        if ($id !== null && trim($id) !== '') {
            $attrs['id'] = $id;
        }
        $role = XmlHtmlDom::attribute($element, 'specific-use') ?? XmlHtmlDom::attribute($element, 'content-type');
        if ($role !== null && trim($role) !== '') {
            $attrs['classes'] = [$role];
        }

        return $attrs;
    }

    /**
     * @return array<string, string>
     */
    private function attributes(\DOMElement $element): array
    {
        $attrs = [];
        foreach ($element->attributes ?? [] as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }
            $attrs[$attribute->name] = $attribute->value;
        }

        return $attrs;
    }

    private function name(\DOMElement $element): string
    {
        return strtolower($element->localName);
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<AstNode>
     */
    private function attachElementAnchor(array $blocks, \DOMElement $element): array
    {
        $id = trim((string) (XmlHtmlDom::attribute($element, 'id') ?? ''));
        if ($id === '' || $blocks === []) {
            return $blocks;
        }

        $first = $blocks[0];
        if ((string) $first->attr('id', '') !== '') {
            return $blocks;
        }

        $blocks[0] = new AstNode($first->type, array_replace($first->attrs, ['id' => $id]), $first->children);

        return $blocks;
    }

    private function firstJatsBodyElement(\DOMElement $root): ?\DOMElement
    {
        foreach (self::JATS_BODY_ROOTS as $name) {
            $body = XmlHtmlDom::firstChildElement($root, $name);
            if ($body instanceof \DOMElement) {
                return $body;
            }
        }
        foreach (self::JATS_BODY_ROOTS as $name) {
            $body = XmlHtmlDom::firstDescendantElement($root, $name);
            if ($body instanceof \DOMElement) {
                return $body;
            }
        }

        return null;
    }

    private function firstJatsBackMatterElement(\DOMElement $root): ?\DOMElement
    {
        foreach (self::JATS_BACK_MATTER_ROOTS as $name) {
            $backMatter = XmlHtmlDom::firstChildElement($root, $name);
            if ($backMatter instanceof \DOMElement) {
                return $backMatter;
            }
        }
        foreach (self::JATS_BACK_MATTER_ROOTS as $name) {
            $backMatter = XmlHtmlDom::firstDescendantElement($root, $name);
            if ($backMatter instanceof \DOMElement) {
                return $backMatter;
            }
        }

        return null;
    }

    private function tableWrapCaption(\DOMElement $tableWrap): string
    {
        $caption = XmlHtmlDom::firstChildElement($tableWrap, 'caption') ?? XmlHtmlDom::firstDescendantElement($tableWrap, 'caption');

        return $caption instanceof \DOMElement ? XmlHtmlDom::normalizedText($caption) : '';
    }

    /**
     * @param list<AstNode> $cells
     */
    private function rowIsHeader(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell->attr('header') !== true) {
                return false;
            }
        }

        return $cells !== [];
    }

    /**
     * @param list<string> $names
     */
    private function positiveIntAttr(\DOMElement $element, array $names): int
    {
        foreach ($names as $name) {
            $value = XmlHtmlDom::attribute($element, $name);
            if ($value === null || !is_numeric($value)) {
                continue;
            }

            return max(1, (int) $value);
        }

        return 1;
    }

    private function hasBlockContainerChild(\DOMElement $element): bool
    {
        foreach (XmlHtmlDom::childElements($element) as $child) {
            if (in_array($this->name($child), self::BLOCK_CONTAINER_NAMES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function countNodesOfType(array $nodes, string $type): int
    {
        $count = 0;
        foreach ($nodes as $node) {
            if ($node->type === $type) {
                $count++;
            }
            $count += $this->countNodesOfType($node->children, $type);
        }

        return $count;
    }
}
