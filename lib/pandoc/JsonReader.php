<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class JsonReader
{
    /** @var list<int> */
    public const PANDOC_API_VERSION = [1, 23, 1, 2];

    public function read(string $json): AstNode
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!$this->looksLikePandocJsonDocument($decoded)) {
            return $this->fallbackJsonDocument($json, 'not-pandoc-json');
        }

        try {
            if (!array_is_list($decoded)) {
                $document = $this->expectMap($decoded, 'Pandoc JSON document');
                $this->assertCompatibleVersion($document['pandoc-api-version'] ?? null);
                if ($this->isCurrentApiVersion($document['pandoc-api-version'] ?? null)) {
                    $meta = $this->parseMeta($document['meta'] ?? []);
                    $blocks = $this->parseBlockList($document['blocks'] ?? []);

                    return new AstNode('document', $meta === [] ? [] : ['meta' => $meta], $blocks);
                }
            }

            return (new PandocJsonReader())->readPacket($decoded);
        } catch (\InvalidArgumentException $exception) {
            if ($this->isMajorVersionIncompatibility($exception)) {
                throw $exception;
            }

            return $this->fallbackJsonDocument($json, 'pandoc-json-parse-recovery', $exception->getMessage());
        }
    }

    private function looksLikePandocJsonDocument(mixed $decoded): bool
    {
        if (!is_array($decoded)) {
            return false;
        }
        if (array_is_list($decoded)) {
            return count($decoded) === 2;
        }
        if (($decoded['t'] ?? null) === 'Pandoc') {
            return true;
        }

        return array_key_exists('blocks', $decoded)
            || array_key_exists('meta', $decoded)
            || array_key_exists('pandoc-api-version', $decoded);
    }

    private function fallbackJsonDocument(string $json, string $reason, ?string $error = null): AstNode
    {
        $attrs = [
            'sourceFormat' => 'json',
            'jsonFallback' => [
                'reason' => $reason,
                'sourceBytes' => strlen($json),
                'sourceSha256' => hash('sha256', $json),
            ],
        ];
        if ($error !== null && $error !== '') {
            $attrs['jsonFallback']['error'] = $error;
        }

        return new AstNode('document', $attrs, [
            new AstNode('code_block', [
                'text' => $this->formattedJsonSource($json),
                'classes' => ['json'],
            ]),
        ]);
    }

    private function formattedJsonSource(string $json): string
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $json;
        }
    }

    private function isMajorVersionIncompatibility(\InvalidArgumentException $exception): bool
    {
        return str_contains($exception->getMessage(), 'Incompatible Pandoc JSON API major version');
    }

    private function isCurrentApiVersion(mixed $value): bool
    {
        $version = $this->expectList($value, 'pandoc-api-version');

        return ($version[0] ?? null) === self::PANDOC_API_VERSION[0]
            && ($version[1] ?? null) === self::PANDOC_API_VERSION[1];
    }

    private function assertCompatibleVersion(mixed $value): void
    {
        $version = $this->expectList($value, 'pandoc-api-version');
        if (count($version) < 2) {
            throw new \InvalidArgumentException('Pandoc JSON API version must include major and minor numbers');
        }

        $major = $version[0];
        $minor = $version[1];
        if (!is_int($major) || !is_int($minor)) {
            throw new \InvalidArgumentException('Pandoc JSON API version entries must be integers');
        }

        if ($major !== self::PANDOC_API_VERSION[0]) {
            throw new \InvalidArgumentException(sprintf(
                'Incompatible Pandoc JSON API major version %s; expected major %d',
                implode('.', array_map(static fn (mixed $part): string => (string) $part, $version)),
                self::PANDOC_API_VERSION[0],
            ));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseMeta(mixed $value): array
    {
        $entries = $this->expectMap($value, 'meta');
        $meta = [];
        foreach ($entries as $key => $entry) {
            $parsed = $this->parseMetaValue($entry);
            if ($key === 'title' && $this->isTypedValue($parsed, 'MetaInlines')) {
                $meta['titleInlines'] = $parsed['value'];
                $meta['title'] = $this->plainInlineText($parsed['value']);
                continue;
            }

            if ($key === 'author' && $this->isTypedValue($parsed, 'MetaList')) {
                $authorInlines = [];
                $authors = [];
                foreach ($parsed['value'] as $author) {
                    if ($this->isTypedValue($author, 'MetaInlines')) {
                        $authorInlines[] = $author['value'];
                        $authors[] = $this->plainInlineText($author['value']);
                    }
                }
                if ($authorInlines !== []) {
                    $meta['authorInlines'] = $authorInlines;
                    $meta['author'] = $authors;
                    continue;
                }
            }

            if ($key === 'date' && $this->isTypedValue($parsed, 'MetaInlines')) {
                $meta['dateInlines'] = $parsed['value'];
                $meta['date'] = $this->plainInlineText($parsed['value']);
                continue;
            }

            $meta[(string) $key] = $parsed;
        }

        return $meta;
    }

    private function parseMetaValue(mixed $value): mixed
    {
        [$type, $payload] = $this->tagged($value, 'meta value');

        return match ($type) {
            'MetaMap' => ['type' => 'MetaMap', 'value' => $this->parseMetaMap($payload)],
            'MetaList' => ['type' => 'MetaList', 'value' => array_map(fn (mixed $item): mixed => $this->parseMetaValue($item), $this->expectList($payload, 'MetaList'))],
            'MetaBool' => (bool) $payload,
            'MetaString' => $this->expectString($payload, 'MetaString'),
            'MetaInlines' => ['type' => 'MetaInlines', 'value' => $this->parseInlineList($payload)],
            'MetaBlocks' => ['type' => 'MetaBlocks', 'value' => $this->parseBlockList($payload)],
            default => throw new \InvalidArgumentException("Unsupported Pandoc JSON meta value '{$type}'"),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function parseMetaMap(mixed $value): array
    {
        $entries = $this->expectMap($value, 'MetaMap');
        $mapped = [];
        foreach ($entries as $key => $entry) {
            $mapped[(string) $key] = $this->parseMetaValue($entry);
        }

        return $mapped;
    }

    /**
     * @return list<AstNode>
     */
    private function parseBlockList(mixed $value): array
    {
        return array_map(fn (mixed $block): AstNode => $this->parseBlock($block), $this->expectList($this->singleWrappedTaggedListPayload($value), 'block list'));
    }

    private function parseBlock(mixed $value): AstNode
    {
        [$type, $payload] = $this->tagged($value, 'block');

        return match ($type) {
            'Plain' => $this->inlineBlock('plain', $payload),
            'Para' => $this->inlineBlock('paragraph', $payload),
            'LineBlock' => $this->parseLineBlock($payload),
            'CodeBlock' => $this->parseCodeBlock($payload),
            'RawBlock' => $this->parseRawBlock($payload),
            'BlockQuote' => new AstNode('blockquote', [], $this->parseBlockList($payload)),
            'OrderedList' => $this->parseOrderedList($payload),
            'BulletList' => $this->parseBulletList($payload),
            'DefinitionList' => $this->parseDefinitionList($payload),
            'Header' => $this->parseHeader($payload),
            'HorizontalRule' => new AstNode('horizontal_rule'),
            'Null' => new AstNode('null_block'),
            'Table' => $this->parseTable($payload),
            'Figure' => $this->parseFigure($payload),
            'Div' => $this->parseDiv($payload),
            default => throw new \InvalidArgumentException("Unsupported Pandoc JSON block '{$type}'"),
        };
    }

    private function inlineBlock(string $type, mixed $payload): AstNode
    {
        $inlines = $this->parseInlineList($payload);

        return new AstNode($type, ['text' => $this->plainInlineText($inlines)], $inlines);
    }

    private function parseLineBlock(mixed $payload): AstNode
    {
        $lines = [];
        foreach ($this->expectList($payload, 'LineBlock') as $line) {
            $inlines = $this->parseInlineList($line);
            $lines[] = new AstNode('line', ['text' => $this->plainInlineText($inlines)], $inlines);
        }

        return new AstNode('line_block', [], $lines);
    }

    private function parseCodeBlock(mixed $payload): AstNode
    {
        $items = $this->expectTuple($payload, 2, 'CodeBlock');
        $attrs = $this->parseAttr($items[0]);
        $attrs['text'] = $this->expectString($items[1], 'CodeBlock text');

        return new AstNode('code_block', $attrs);
    }

    private function parseRawBlock(mixed $payload): AstNode
    {
        $items = $this->expectTuple($payload, 2, 'RawBlock');
        $format = $this->parseFormat($items[0]);
        $text = $this->expectString($items[1], 'RawBlock text');

        return match ($format) {
            'html' => new AstNode('raw_html', ['html' => $text]),
            'tex' => new AstNode('raw_tex', ['tex' => $text]),
            default => new AstNode('raw_block', ['format' => $format, 'text' => $text]),
        };
    }

    private function parseOrderedList(mixed $payload): AstNode
    {
        $items = $this->expectTuple($payload, 2, 'OrderedList');
        $attrs = $this->parseListAttributes($items[0]);
        $children = [];
        foreach ($this->expectList($items[1], 'OrderedList items') as $item) {
            $children[] = new AstNode('list_item', [], $this->parseBlockList($item));
        }

        return new AstNode('ordered_list', $attrs, $children);
    }

    private function parseBulletList(mixed $payload): AstNode
    {
        $children = [];
        foreach ($this->expectList($payload, 'BulletList') as $item) {
            $children[] = new AstNode('list_item', [], $this->parseBlockList($item));
        }

        return new AstNode('bullet_list', [], $children);
    }

    private function parseDefinitionList(mixed $payload): AstNode
    {
        $items = [];
        foreach ($this->expectList($payload, 'DefinitionList') as $item) {
            [$termValue, $definitionsValue] = $this->expectTuple($item, 2, 'DefinitionList item');
            $termInlines = $this->parseInlineList($termValue);
            $definitions = [];
            foreach ($this->expectList($definitionsValue, 'DefinitionList definitions') as $definition) {
                $definitions[] = new AstNode('definition', [], $this->parseBlockList($definition));
            }

            $items[] = new AstNode('definition_item', ['term' => $this->plainInlineText($termInlines)], array_merge([
                new AstNode('term', ['text' => $this->plainInlineText($termInlines)], $termInlines),
            ], $definitions));
        }

        return new AstNode('definition_list', [], $items);
    }

    private function parseHeader(mixed $payload): AstNode
    {
        $items = $this->expectTuple($payload, 3, 'Header');
        $level = max(1, min(6, (int) $items[0]));
        $attrs = $this->parseAttr($items[1]);
        $inlines = $this->parseInlineList($items[2]);
        $attrs['level'] = $level;
        $attrs['text'] = $this->plainInlineText($inlines);

        return new AstNode('heading', $attrs, $inlines);
    }

    private function parseTable(mixed $payload): AstNode
    {
        $items = $this->expectTuple($payload, 6, 'Table');
        $attrs = $this->parseAttr($items[0]);
        $attrs = array_replace($attrs, $this->parseCaptionAttrs($items[1]));
        [$alignments, $widths] = $this->parseTableColSpecs($items[2]);
        if ($alignments !== []) {
            $attrs['alignments'] = $alignments;
        }
        if ($widths !== []) {
            $attrs['widths'] = $widths;
        }

        $children = [$this->parseTableHead($items[3])];
        array_push($children, ...array_map(fn (mixed $body): AstNode => $this->parseTableBody($body), $this->expectList($items[4], 'Table bodies')));
        $children[] = $this->parseTableFoot($items[5]);

        return new AstNode('table', $attrs, $children);
    }

    private function parseFigure(mixed $payload): AstNode
    {
        $items = $this->expectTuple($payload, 3, 'Figure');
        $attrs = array_replace($this->parseAttr($items[0]), $this->parseCaptionAttrs($items[1]));

        return new AstNode('figure', $attrs, $this->figureChildrenFromNativeBlocks($this->parseBlockList($items[2])));
    }

    private function parseDiv(mixed $payload): AstNode
    {
        $items = $this->expectTuple($payload, 2, 'Div');

        return new AstNode('div', $this->parseAttr($items[0]), $this->parseBlockList($items[1]));
    }

    /**
     * @return list<AstNode>
     */
    private function parseInlineList(mixed $value): array
    {
        return array_map(fn (mixed $inline): AstNode => $this->parseInline($inline), $this->expectList($this->singleWrappedTaggedListPayload($value), 'inline list'));
    }

    private function parseInline(mixed $value): AstNode
    {
        [$type, $payload] = $this->tagged($value, 'inline');

        return match ($type) {
            'Str' => new AstNode('text', ['text' => $this->expectString($payload, 'Str')]),
            'Space' => new AstNode('text', ['text' => ' ']),
            'SoftBreak' => new AstNode('softbreak'),
            'LineBreak' => new AstNode('linebreak'),
            'Emph' => new AstNode('emph', [], $this->parseInlineList($payload)),
            'Underline' => new AstNode('underline', [], $this->parseInlineList($payload)),
            'Strong' => new AstNode('strong', [], $this->parseInlineList($payload)),
            'Strikeout' => new AstNode('strikeout', [], $this->parseInlineList($payload)),
            'Superscript' => new AstNode('superscript', [], $this->parseInlineList($payload)),
            'Subscript' => new AstNode('subscript', [], $this->parseInlineList($payload)),
            'SmallCaps' => new AstNode('small_caps', [], $this->parseInlineList($payload)),
            'Quoted' => $this->parseQuotedInline($payload),
            'Cite' => $this->parseCitationInline($payload),
            'Code' => $this->parseCodeInline($payload),
            'Math' => $this->parseMathInline($payload),
            'RawInline' => $this->parseRawInline($payload),
            'Link' => $this->parseLinkInline($payload),
            'Image' => $this->parseImageInline($payload),
            'Note' => new AstNode('note', [], $this->parseBlockList($payload)),
            'Span' => $this->parseSpanInline($payload),
            default => throw new \InvalidArgumentException("Unsupported Pandoc JSON inline '{$type}'"),
        };
    }

    private function parseQuotedInline(mixed $payload): AstNode
    {
        $items = $this->expectTuple($payload, 2, 'Quoted');
        [$quoteType] = $this->tagged($items[0], 'QuoteType');

        return new AstNode('quoted', ['kind' => $quoteType === 'SingleQuote' ? 'single' : 'double'], $this->parseInlineList($items[1]));
    }

    private function parseCitationInline(mixed $payload): AstNode
    {
        $items = $this->expectTuple($payload, 2, 'Cite');
        $citations = array_map(fn (mixed $citation): array => $this->parseCitationRecord($citation), $this->expectList($items[0], 'Cite citations'));
        $display = $this->parseInlineList($items[1]);

        return new AstNode('citation', [
            'citations' => $citations,
            'text' => $this->plainInlineText($display),
        ], $display);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseCitationRecord(mixed $value): array
    {
        $record = $this->expectMap(
            $this->singleWrappedObject($this->constructorPayload($value, 'Citation', 'Citation')),
            'Citation'
        );

        return [
            'id' => $this->expectString($record['citationId'] ?? '', 'citationId'),
            'prefix' => $this->parseInlineList($record['citationPrefix'] ?? []),
            'suffix' => $this->parseInlineList($record['citationSuffix'] ?? []),
            'mode' => $this->parseCitationMode($record['citationMode'] ?? ['t' => 'NormalCitation']),
            'noteNum' => (int) ($record['citationNoteNum'] ?? 1),
            'hash' => (int) ($record['citationHash'] ?? 0),
        ];
    }

    private function parseCodeInline(mixed $payload): AstNode
    {
        $items = $this->expectTuple($payload, 2, 'Code');
        $attrs = $this->parseAttr($items[0]);
        $attrs['text'] = $this->expectString($items[1], 'Code text');

        return new AstNode('code', $attrs);
    }

    private function parseMathInline(mixed $payload): AstNode
    {
        $items = $this->expectTuple($payload, 2, 'Math');
        [$mathType] = $this->tagged($items[0], 'MathType');

        return new AstNode('math', [
            'display' => $mathType === 'DisplayMath',
            'text' => $this->expectString($items[1], 'Math text'),
        ]);
    }

    private function parseRawInline(mixed $payload): AstNode
    {
        $items = $this->expectTuple($payload, 2, 'RawInline');
        $format = $this->parseFormat($items[0]);
        $text = $this->expectString($items[1], 'RawInline text');

        return match ($format) {
            'html' => new AstNode('raw_html_inline', ['html' => $text]),
            'tex' => new AstNode('raw_tex_inline', ['tex' => $text]),
            default => new AstNode('raw_inline', ['format' => $format, 'text' => $text]),
        };
    }

    private function parseLinkInline(mixed $payload): AstNode
    {
        $items = $this->expectTuple($payload, 3, 'Link');
        $attrs = $this->parseAttr($items[0]);
        [$url, $title] = $this->parseTarget($items[2]);
        $attrs['url'] = $url;
        $attrs['title'] = $title;

        return new AstNode('link', $attrs, $this->parseInlineList($items[1]));
    }

    private function parseImageInline(mixed $payload): AstNode
    {
        $items = $this->expectTuple($payload, 3, 'Image');
        $attrs = $this->parseAttr($items[0]);
        $inlines = $this->parseInlineList($items[1]);
        [$url, $title] = $this->parseTarget($items[2]);
        $attrs['url'] = $url;
        $attrs['title'] = $title;
        $attrs['alt'] = $this->plainInlineText($inlines);

        return new AstNode('image', $attrs, $inlines);
    }

    private function parseSpanInline(mixed $payload): AstNode
    {
        $items = $this->expectTuple($payload, 2, 'Span');

        return new AstNode('span', $this->parseAttr($items[0]), $this->parseInlineList($items[1]));
    }

    /**
     * @return array<string, mixed>
     */
    private function parseAttr(mixed $value): array
    {
        $payload = $this->singleWrappedTuplePayload($this->constructorPayload($value, 'Attr', 'Attr'), 3);
        [$idValue, $classesValue, $attributesValue] = $this->expectTuple($payload, 3, 'Attr');
        $id = $this->expectString($idValue, 'Attr identifier');
        $classes = array_map(fn (mixed $class): string => $this->expectString($class, 'Attr class'), $this->expectList($classesValue, 'Attr classes'));
        $pairs = [];
        foreach ($this->expectList($attributesValue, 'Attr key-value pairs') as $pair) {
            [$key, $pairValue] = $this->expectTuple($pair, 2, 'Attr key-value pair');
            $pairs[$this->expectString($key, 'Attr key')] = $this->expectString($pairValue, 'Attr value');
        }

        $attrs = [];
        if ($id !== '') {
            $attrs['id'] = $id;
        }
        if ($classes !== []) {
            $attrs['classes'] = $classes;
        }
        if ($pairs !== []) {
            $attrs['attributes'] = $pairs;
        }

        return $attrs;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseCaptionAttrs(mixed $value): array
    {
        $payload = $this->constructorPayload($value, 'Caption', 'Caption');
        $payload = $this->singleWrappedTuplePayload($payload, 2);
        [$shortValue, $blocksValue] = $this->expectTuple($payload, 2, 'Caption payload');
        $attrs = [];
        $short = $this->parseShortCaptionInlines($shortValue);
        if ($short !== []) {
            $attrs['shortCaptionInlines'] = $short;
            $attrs['shortCaption'] = $this->plainInlineText($short);
        }

        $blocks = $this->parseBlockList($blocksValue);
        if ($blocks !== []) {
            $attrs['captionBlocks'] = $blocks;
            $attrs['caption'] = $this->plainBlockText($blocks);
            $captionInlines = $this->captionInlinesFromBlocks($blocks);
            if ($captionInlines !== []) {
                $attrs['captionInlines'] = $captionInlines;
            }
        }

        return $attrs;
    }

    /**
     * @return array{0:list<string>, 1:list<float>}
     */
    private function parseTableColSpecs(mixed $value): array
    {
        $alignments = [];
        $widths = [];
        foreach ($this->expectList($value, 'Table column specs') as $spec) {
            [$alignment, $width] = $this->expectTuple($spec, 2, 'Table column spec');
            $alignments[] = $this->parseAlignment($alignment);
            $widths[] = $this->parseColumnWidth($width);
        }

        return [$alignments, $widths];
    }

    private function parseTableHead(mixed $value): AstNode
    {
        [$type, $payload] = $this->tagged($value, 'TableHead');
        if ($type !== 'TableHead') {
            throw new \InvalidArgumentException("Expected TableHead, got '{$type}'");
        }

        [$attrs, $rows] = $this->expectTuple($payload, 2, 'TableHead payload');

        return new AstNode('table_head', $this->parseAttr($attrs), $this->parseTableRows($rows));
    }

    private function parseTableBody(mixed $value): AstNode
    {
        [$type, $payload] = $this->tagged($value, 'TableBody');
        if ($type !== 'TableBody') {
            throw new \InvalidArgumentException("Expected TableBody, got '{$type}'");
        }

        [$attrsValue, $rowHeadColumnsValue, $headRowsValue, $bodyRowsValue] = $this->expectTuple($payload, 4, 'TableBody payload');
        $attrs = $this->parseAttr($attrsValue);
        $attrs['rowHeadColumns'] = $this->parseIntegerHelper($rowHeadColumnsValue, 'RowHeadColumns', 'TableBody rowHeadColumns');
        $headRows = $this->parseTableRows($headRowsValue);
        if ($headRows !== []) {
            $attrs['headRows'] = $headRows;
        }

        return new AstNode('table_body', $attrs, $this->parseTableRows($bodyRowsValue));
    }

    private function parseTableFoot(mixed $value): AstNode
    {
        [$type, $payload] = $this->tagged($value, 'TableFoot');
        if ($type !== 'TableFoot') {
            throw new \InvalidArgumentException("Expected TableFoot, got '{$type}'");
        }

        [$attrs, $rows] = $this->expectTuple($payload, 2, 'TableFoot payload');

        return new AstNode('table_foot', $this->parseAttr($attrs), $this->parseTableRows($rows));
    }

    /**
     * @return list<AstNode>
     */
    private function parseTableRows(mixed $value): array
    {
        return array_map(fn (mixed $row): AstNode => $this->parseTableRow($row), $this->expectList($value, 'Table rows'));
    }

    private function parseTableRow(mixed $value): AstNode
    {
        [$type, $payload] = $this->tagged($value, 'Row');
        if ($type !== 'Row') {
            throw new \InvalidArgumentException("Expected Row, got '{$type}'");
        }

        [$attrs, $cells] = $this->expectTuple($payload, 2, 'Row payload');

        return new AstNode('table_row', $this->parseAttr($attrs), array_map(fn (mixed $cell): AstNode => $this->parseTableCell($cell), $this->expectList($cells, 'Row cells')));
    }

    private function parseTableCell(mixed $value): AstNode
    {
        [$type, $payload] = $this->tagged($value, 'Cell');
        if ($type !== 'Cell') {
            throw new \InvalidArgumentException("Expected Cell, got '{$type}'");
        }

        [$attrsValue, $alignmentValue, $rowspanValue, $colspanValue, $blocksValue] = $this->expectTuple($payload, 5, 'Cell payload');
        $attrs = $this->parseAttr($attrsValue);
        $alignment = $this->parseAlignment($alignmentValue);
        if ($alignment !== 'default') {
            $attrs['align'] = $alignment;
        }
        $rowspan = $this->parseIntegerHelper($rowspanValue, 'RowSpan', 'Cell rowspan');
        if ($rowspan > 1) {
            $attrs['rowspan'] = $rowspan;
        }
        $colspan = $this->parseIntegerHelper($colspanValue, 'ColSpan', 'Cell colspan');
        if ($colspan > 1) {
            $attrs['colspan'] = $colspan;
        }

        return new AstNode('table_cell', $attrs, $this->parseBlockList($blocksValue));
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function parseTarget(mixed $value): array
    {
        $payload = $this->singleWrappedTuplePayload($this->constructorPayload($value, 'Target', 'Target'), 2);
        [$url, $title] = $this->expectTuple($payload, 2, 'Target');

        return [
            $this->expectString($url, 'Target URL'),
            $this->expectString($title, 'Target title'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseListAttributes(mixed $value): array
    {
        $payload = $this->singleWrappedTuplePayload($this->constructorPayload($value, 'ListAttributes', 'ListAttributes'), 3);
        [$start, $style, $delimiter] = $this->expectTuple($payload, 3, 'ListAttributes');

        return [
            'start' => $this->parseIntegerScalar($start, 'ListAttributes start'),
            'style' => $this->parseOrderedListStyle($style),
            'delimiter' => $this->parseOrderedListDelimiter($delimiter),
        ];
    }

    private function parseOrderedListStyle(mixed $value): string
    {
        [$style] = $this->tagged($value, 'ListNumberStyle');

        return match ($style) {
            'DefaultStyle' => 'default',
            'Example' => 'example',
            'Decimal' => 'decimal',
            'LowerRoman' => 'lower_roman',
            'UpperRoman' => 'upper_roman',
            'LowerAlpha' => 'lower_alpha',
            'UpperAlpha' => 'upper_alpha',
            default => 'default',
        };
    }

    private function parseOrderedListDelimiter(mixed $value): string
    {
        [$delimiter] = $this->tagged($value, 'ListNumberDelim');

        return match ($delimiter) {
            'DefaultDelim' => 'default',
            'OneParen' => 'one_paren',
            'TwoParens' => 'two_parens',
            default => 'period',
        };
    }

    private function parseAlignment(mixed $value): string
    {
        [$alignment] = $this->tagged($value, 'Alignment');

        return match ($alignment) {
            'AlignLeft' => 'left',
            'AlignRight' => 'right',
            'AlignCenter' => 'center',
            default => 'default',
        };
    }

    private function parseColumnWidth(mixed $value): float
    {
        [$type, $payload] = $this->tagged($value, 'ColWidth');
        if ($type === 'ColWidthDefault') {
            return 0.0;
        }
        if ($type !== 'ColWidth') {
            throw new \InvalidArgumentException("Unsupported column width '{$type}'");
        }

        $payload = $this->singleWrappedScalar($payload);
        if (!is_int($payload) && !is_float($payload)) {
            throw new \InvalidArgumentException('ColWidth payload must be numeric');
        }

        return (float) $payload;
    }

    private function parseCitationMode(mixed $value): string
    {
        [$mode] = $this->tagged($value, 'CitationMode');

        return match ($mode) {
            'AuthorInText' => 'author_in_text',
            'SuppressAuthor' => 'suppress_author',
            default => 'normal',
        };
    }

    private function parseFormat(mixed $value): string
    {
        if (is_string($value)) {
            return strtolower($value);
        }

        [$type, $payload] = $this->tagged($value, 'Format');
        if ($type !== 'Format') {
            throw new \InvalidArgumentException("Expected Format, got '{$type}'");
        }

        return strtolower($this->expectString($this->singleWrappedScalar($payload), 'Format'));
    }

    /**
     * @return list<AstNode>
     */
    private function parseShortCaptionInlines(mixed $value): array
    {
        if ($value === null || $value === []) {
            return [];
        }

        if ($this->isTaggedMap($value)) {
            [$type, $payload] = $this->tagged($value, 'short caption');
            if ($type === 'Nothing') {
                return [];
            }
            if ($type === 'Just') {
                return $this->parseShortCaptionInlines($this->singleWrappedMaybePayload($payload));
            }
            if ($type === 'ShortCaption') {
                return $this->parseInlineList($this->singleWrappedInlineListPayload($payload));
            }

            throw new \InvalidArgumentException("Unsupported short caption constructor '{$type}'");
        }

        return $this->parseInlineList($this->singleWrappedInlineListPayload($value));
    }

    private function parseIntegerHelper(mixed $value, string $constructor, string $context): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_array($value) && $this->isList($value) && count($value) === 1 && is_int($value[0])) {
            return $value[0];
        }

        $payload = $this->singleWrappedScalar($this->constructorPayload($value, $constructor, $context));

        return $this->parseIntegerScalar($payload, $context);
    }

    private function parseIntegerScalar(mixed $value, string $context): int
    {
        if (!is_int($value)) {
            throw new \InvalidArgumentException("{$context} must be an integer");
        }

        return $value;
    }

    private function constructorPayload(mixed $value, string $constructor, string $context): mixed
    {
        if (!$this->isTaggedMap($value)) {
            return $value;
        }

        [$type, $payload] = $this->tagged($value, $context);
        if ($type !== $constructor) {
            throw new \InvalidArgumentException("Expected {$constructor}, got '{$type}'");
        }

        return $payload;
    }

    private function isTaggedMap(mixed $value): bool
    {
        return is_array($value)
            && !$this->isList($value)
            && isset($value['t'])
            && is_string($value['t'])
            && $value['t'] !== '';
    }

    private function singleWrappedTuplePayload(mixed $value, int $length): mixed
    {
        if (
            is_array($value)
            && $this->isList($value)
            && count($value) === 1
            && is_array($value[0])
            && $this->isList($value[0])
            && count($value[0]) === $length
        ) {
            return $value[0];
        }

        return $value;
    }

    private function singleWrappedObject(mixed $value): mixed
    {
        if (
            is_array($value)
            && $this->isList($value)
            && count($value) === 1
            && is_array($value[0])
            && !$this->isList($value[0])
        ) {
            return $value[0];
        }

        return $value;
    }

    private function singleWrappedMaybePayload(mixed $value): mixed
    {
        if (is_array($value) && $this->isList($value) && count($value) === 1) {
            return $value[0];
        }

        return $value;
    }

    private function singleWrappedInlineListPayload(mixed $value): mixed
    {
        return $this->singleWrappedTaggedListPayload($value);
    }

    private function singleWrappedTaggedListPayload(mixed $value): mixed
    {
        if (
            is_array($value)
            && $this->isList($value)
            && count($value) === 1
            && is_array($value[0])
            && $this->isList($value[0])
            && ($value[0] === [] || $this->isTaggedMap($value[0][0]))
        ) {
            return $value[0];
        }

        return $value;
    }

    private function singleWrappedScalar(mixed $value): mixed
    {
        while (is_array($value) && $this->isList($value) && count($value) === 1) {
            $value = $value[0];
        }

        return $value;
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
     * @return list<AstNode>
     */
    private function figureChildrenFromNativeBlocks(array $blocks): array
    {
        $children = [];
        foreach ($blocks as $block) {
            if ($block->type === 'plain' && count($block->children) === 1 && $block->children[0]->type === 'image') {
                $children[] = $block->children[0];
                continue;
            }

            $children[] = $block;
        }

        return $children;
    }

    /**
     * @param list<AstNode> $blocks
     */
    private function plainBlockText(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $block) {
            if ($block->children !== []) {
                $parts[] = $this->plainInlineText($block->children);
            } else {
                $parts[] = (string) $block->attr('text', '');
            }
        }

        return trim(implode(' ', array_filter($parts, static fn (string $part): bool => $part !== '')));
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function plainInlineText(array $inlines): string
    {
        $text = '';
        foreach ($inlines as $inline) {
            $text .= match ($inline->type) {
                'text', 'code' => (string) $inline->attr('text', ''),
                'softbreak', 'linebreak' => ' ',
                default => $this->plainInlineText($inline->children),
            };
        }

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function isTypedValue(mixed $value, string $type): bool
    {
        return is_array($value)
            && ($value['type'] ?? null) === $type
            && array_key_exists('value', $value);
    }

    /**
     * @return array{0:string, 1:mixed}
     */
    private function tagged(mixed $value, string $context): array
    {
        $node = $this->expectMap($value, $context);
        if (!isset($node['t']) || !is_string($node['t']) || $node['t'] === '') {
            throw new \InvalidArgumentException("Expected tagged Pandoc JSON {$context}");
        }

        return [$node['t'], $node['c'] ?? null];
    }

    /**
     * @return array<int, mixed>
     */
    private function expectTuple(mixed $value, int $length, string $context): array
    {
        $items = $this->expectList($this->singleWrappedTuplePayload($value, $length), $context);
        if (count($items) !== $length) {
            throw new \InvalidArgumentException("Expected {$context} to contain {$length} entries");
        }

        return $items;
    }

    /**
     * @return array<int, mixed>
     */
    private function expectList(mixed $value, string $context): array
    {
        if (!is_array($value) || !$this->isList($value)) {
            throw new \InvalidArgumentException("Expected {$context} to be a JSON array");
        }

        return array_values($value);
    }

    /**
     * @return array<string, mixed>
     */
    private function expectMap(mixed $value, string $context): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException("Expected {$context} to be a JSON object");
        }
        if ($this->isList($value) && $value !== []) {
            throw new \InvalidArgumentException("Expected {$context} to be a JSON object");
        }

        return $value;
    }

    private function expectString(mixed $value, string $context): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException("Expected {$context} to be a string");
        }

        return $value;
    }

    /**
     * @param array<mixed> $value
     */
    private function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
