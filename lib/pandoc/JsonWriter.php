<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class JsonWriter
{
    public function write(AstNode $document): string
    {
        if ($document->type !== 'document') {
            throw new \InvalidArgumentException('JSON writer expects a document node');
        }

        return json_encode([
            'pandoc-api-version' => JsonReader::PANDOC_API_VERSION,
            'meta' => $this->metaData($document->attr('meta', [])),
            'blocks' => $this->blockListData($document->children),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    }

    /**
     * @param mixed $meta
     * @return array<string, mixed>
     */
    private function metaData(mixed $meta): array
    {
        if (!is_array($meta)) {
            return [];
        }

        $data = [];
        foreach ($this->normalizedMetaEntries($meta) as $key => $value) {
            $data[$key] = $this->metaValueData($value);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function normalizedMetaEntries(array $meta): array
    {
        $entries = [];
        foreach ($meta as $key => $value) {
            if (in_array($key, ['titleInlines', 'authorInlines', 'dateInlines', 'authors', 'abstractBlocks'], true)) {
                continue;
            }

            $entries[(string) $key] = $value;
        }

        if (isset($meta['titleInlines']) && is_array($meta['titleInlines'])) {
            $entries['title'] = ['type' => 'MetaInlines', 'value' => $meta['titleInlines']];
        } elseif (isset($meta['title'])) {
            $entries['title'] = ['type' => 'MetaInlines', 'value' => $this->textInlines($this->metaScalarText($meta['title']))];
        }

        if (isset($meta['authorInlines']) && is_array($meta['authorInlines'])) {
            $authors = [];
            foreach ($meta['authorInlines'] as $author) {
                if (is_array($author)) {
                    $authors[] = ['type' => 'MetaInlines', 'value' => $author];
                }
            }
            if ($authors !== []) {
                $entries['author'] = ['type' => 'MetaList', 'value' => $authors];
            }
        } elseif (isset($meta['author']) && is_array($meta['author']) && !isset($meta['author']['type'])) {
            $authors = [];
            foreach ($meta['author'] as $author) {
                $authors[] = ['type' => 'MetaInlines', 'value' => $this->textInlines($this->metaScalarText($author))];
            }
            if ($authors !== []) {
                $entries['author'] = ['type' => 'MetaList', 'value' => $authors];
            }
        }

        if (isset($meta['dateInlines']) && is_array($meta['dateInlines'])) {
            $entries['date'] = ['type' => 'MetaInlines', 'value' => $meta['dateInlines']];
        } elseif (isset($meta['date'])) {
            $entries['date'] = ['type' => 'MetaInlines', 'value' => $this->textInlines($this->metaScalarText($meta['date']))];
        }

        if (isset($meta['abstractBlocks']) && is_array($meta['abstractBlocks']) && $this->isAstNodeList($meta['abstractBlocks'])) {
            $entries['abstract'] = ['type' => 'MetaBlocks', 'value' => $meta['abstractBlocks']];
        }

        ksort($entries);

        return $entries;
    }

    private function metaScalarText(mixed $value): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (!is_array($value)) {
            return '';
        }
        if (isset($value['text']) && is_scalar($value['text'])) {
            return (string) $value['text'];
        }

        $parts = [];
        foreach ($value as $item) {
            if (is_scalar($item)) {
                $parts[] = (string) $item;
            }
        }

        return implode(', ', $parts);
    }

    private function metaValueData(mixed $value): array
    {
        if (is_array($value) && isset($value['type'])) {
            $type = (string) $value['type'];
            $payload = $value['value'] ?? null;

            return match ($type) {
                'MetaMap' => $this->tagged('MetaMap', $this->metaMapData(is_array($payload) ? $payload : [])),
                'MetaList' => $this->tagged('MetaList', array_map(fn (mixed $item): array => $this->metaValueData($item), $this->listData(is_array($payload) ? $payload : []))),
                'MetaBool' => $this->tagged('MetaBool', (bool) $payload),
                'MetaString' => $this->tagged('MetaString', (string) $payload),
                'MetaInlines' => $this->tagged('MetaInlines', $this->inlineListData(is_array($payload) ? $payload : [])),
                'MetaBlocks' => $this->tagged('MetaBlocks', $this->blockListData(is_array($payload) ? $payload : [])),
                default => $this->tagged('MetaString', (string) $payload),
            };
        }

        if ($value instanceof AstNode) {
            if ($this->isInlineNode($value)) {
                return $this->tagged('MetaInlines', $this->inlineListData([$value]));
            }

            return $this->tagged('MetaBlocks', $this->blockListData([$value]));
        }

        if (is_bool($value)) {
            return $this->tagged('MetaBool', $value);
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return $this->tagged('MetaString', (string) $value);
        }

        if (is_array($value)) {
            if ($this->isAstNodeList($value)) {
                $inlineNodes = array_filter($value, fn (mixed $node): bool => $node instanceof AstNode && $this->isInlineNode($node));
                if (count($inlineNodes) === count($value)) {
                    return $this->tagged('MetaInlines', $this->inlineListData($value));
                }

                return $this->tagged('MetaBlocks', $this->blockListData($value));
            }

            if ($this->isList($value)) {
                return $this->tagged('MetaList', array_map(fn (mixed $item): array => $this->metaValueData($item), $value));
            }

            return $this->tagged('MetaMap', $this->metaMapData($value));
        }

        return $this->tagged('MetaString', (string) $value);
    }

    /**
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function metaMapData(array $value): array
    {
        ksort($value);
        $data = [];
        foreach ($value as $key => $item) {
            $data[(string) $key] = $this->metaValueData($item);
        }

        return $data;
    }

    /**
     * @param list<AstNode> $blocks
     * @return list<array<string, mixed>>
     */
    private function blockListData(array $blocks): array
    {
        return array_map(fn (AstNode $block): array => $this->blockData($block), array_values($blocks));
    }

    /**
     * @return array<string, mixed>
     */
    private function blockData(AstNode $node): array
    {
        return match ($node->type) {
            'plain' => $this->tagged('Plain', $this->inlineListData($node->children)),
            'paragraph' => $this->tagged('Para', $this->inlineListData($node->children)),
            'line_block' => $this->tagged('LineBlock', array_map(fn (AstNode $line): array => $this->inlineListData($this->lineInlines($line)), $node->children)),
            'code_block' => $this->tagged('CodeBlock', [$this->attrData($node), (string) $node->attr('text', '')]),
            'raw_html' => $this->tagged('RawBlock', ['html', (string) $node->attr('html', $node->attr('text', ''))]),
            'raw_tex' => $this->tagged('RawBlock', ['tex', (string) $node->attr('tex', $node->attr('text', ''))]),
            'raw_block', 'raw_markdown' => $this->tagged('RawBlock', [(string) $node->attr('format', 'markdown'), (string) $node->attr('text', '')]),
            'blockquote' => $this->tagged('BlockQuote', $this->blockListData($node->children)),
            'ordered_list' => $this->tagged('OrderedList', [$this->listAttributesData($node), $this->listItemsData($node->children)]),
            'bullet_list' => $this->tagged('BulletList', $this->listItemsData($node->children)),
            'definition_list' => $this->tagged('DefinitionList', $this->definitionItemsData($node->children)),
            'heading' => $this->tagged('Header', [
                max(1, min(6, (int) $node->attr('level', 1))),
                $this->attrData($node),
                $this->inlineListData($node->children === [] ? $this->textInlines((string) $node->attr('text', '')) : $node->children),
            ]),
            'horizontal_rule' => $this->tagged('HorizontalRule'),
            'null_block' => $this->tagged('Null'),
            'table' => $this->tableData($node),
            'figure' => $this->tagged('Figure', [$this->attrData($node), $this->captionData($node), $this->blockListData($this->figureBlocks($node))]),
            'div' => $this->tagged('Div', [$this->attrData($node), $this->blockListData($node->children)]),
            default => throw new \InvalidArgumentException("JSON writer does not support block node '{$node->type}'"),
        };
    }

    /**
     * @param list<AstNode> $items
     * @return list<list<array<string, mixed>>>
     */
    private function listItemsData(array $items): array
    {
        $data = [];
        foreach ($items as $item) {
            if ($item->type === 'list_item') {
                $data[] = $this->blockListData($this->listItemBlocks($item));
            }
        }

        return $data;
    }

    /**
     * @return list<AstNode>
     */
    private function listItemBlocks(AstNode $item): array
    {
        $blocks = [];
        $inlines = [];
        foreach ($item->children as $child) {
            if ($this->isInlineNode($child)) {
                $inlines[] = $child;
                continue;
            }

            if ($inlines !== []) {
                $blocks[] = new AstNode('plain', [], $inlines);
                $inlines = [];
            }
            $blocks[] = $child;
        }

        if ($inlines !== []) {
            $blocks[] = new AstNode('plain', [], $inlines);
        }

        return $blocks;
    }

    /**
     * @param list<AstNode> $items
     * @return list<array{0:list<array<string, mixed>>, 1:list<list<array<string, mixed>>>}>
     */
    private function definitionItemsData(array $items): array
    {
        $data = [];
        foreach ($items as $item) {
            if ($item->type !== 'definition_item') {
                continue;
            }

            $term = null;
            $definitions = [];
            foreach ($item->children as $child) {
                if ($child->type === 'term') {
                    $term = $child;
                } elseif ($child->type === 'definition') {
                    $definitions[] = $this->blockListData($child->children);
                }
            }

            $termInlines = $term instanceof AstNode
                ? ($term->children === [] ? $this->textInlines((string) $term->attr('text', $item->attr('term', ''))) : $term->children)
                : $this->textInlines((string) $item->attr('term', ''));
            $data[] = [$this->inlineListData($termInlines), $definitions];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function tableData(AstNode $table): array
    {
        $columnCount = $this->tableColumnCount($table);

        return $this->tagged('Table', [
            $this->attrData($table),
            $this->captionData($table),
            $this->tableColSpecsData($table, $columnCount),
            $this->tableHeadData($this->tableSection($table, 'table_head')),
            $this->tableBodiesData($table),
            $this->tableFootData($this->tableSection($table, 'table_foot')),
        ]);
    }

    /**
     * @return list<array{0:array<string, mixed>, 1:array<string, mixed>}>
     */
    private function tableColSpecsData(AstNode $table, int $columnCount): array
    {
        if ($columnCount <= 0) {
            return [];
        }

        $alignments = $this->tableAlignments($table, $columnCount);
        $widths = $this->tableWidths($table, $columnCount);
        $specs = [];
        for ($index = 0; $index < $columnCount; $index++) {
            $specs[] = [
                $this->alignmentData($alignments[$index] ?? 'default'),
                $this->columnWidthData($widths[$index] ?? null),
            ];
        }

        return $specs;
    }

    /**
     * @return array<string, mixed>
     */
    private function tableHeadData(?AstNode $head): array
    {
        return $this->tagged('TableHead', [
            $this->attrData($head ?? new AstNode('table_head')),
            $this->tableRowsData($head instanceof AstNode ? $this->tableRowsFromChildren($head->children) : []),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tableBodiesData(AstNode $table): array
    {
        $data = [];
        foreach ($this->tableBodies($table) as $body) {
            $data[] = $this->tagged('TableBody', [
                $this->attrData($body),
                max(0, (int) $body->attr('rowHeadColumns', 0)),
                $this->tableRowsData($this->tableBodyHeadRows($body)),
                $this->tableRowsData($this->tableRowsFromChildren($body->children)),
            ]);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function tableFootData(?AstNode $foot): array
    {
        return $this->tagged('TableFoot', [
            $this->attrData($foot ?? new AstNode('table_foot')),
            $this->tableRowsData($foot instanceof AstNode ? $this->tableRowsFromChildren($foot->children) : []),
        ]);
    }

    /**
     * @param list<AstNode> $rows
     * @return list<array<string, mixed>>
     */
    private function tableRowsData(array $rows): array
    {
        $data = [];
        foreach ($rows as $row) {
            if ($row->type === 'table_row') {
                $data[] = $this->tagged('Row', [$this->attrData($row), $this->tableCellsData($row->children)]);
            }
        }

        return $data;
    }

    /**
     * @param list<AstNode> $cells
     * @return list<array<string, mixed>>
     */
    private function tableCellsData(array $cells): array
    {
        $data = [];
        foreach ($cells as $cell) {
            if ($cell->type !== 'table_cell') {
                continue;
            }
            $data[] = $this->tagged('Cell', [
                $this->attrData($cell),
                $this->alignmentData((string) $cell->attr('align', 'default')),
                max(1, (int) $cell->attr('rowspan', 1)),
                max(1, (int) $cell->attr('colspan', 1)),
                $this->blockListData($this->tableCellBlocks($cell)),
            ]);
        }

        return $data;
    }

    /**
     * @return list<AstNode>
     */
    private function tableCellBlocks(AstNode $cell): array
    {
        if ($cell->children === []) {
            $text = (string) $cell->attr('text', '');

            return $text === '' ? [] : [new AstNode('plain', [], $this->textInlines($text))];
        }

        $blocks = [];
        $inlines = [];
        foreach ($cell->children as $child) {
            if ($this->isInlineNode($child)) {
                $inlines[] = $child;
                continue;
            }

            if ($inlines !== []) {
                $blocks[] = new AstNode('plain', [], $inlines);
                $inlines = [];
            }
            $blocks[] = $child;
        }

        if ($inlines !== []) {
            $blocks[] = new AstNode('plain', [], $inlines);
        }

        return $blocks;
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<array<string, mixed>>
     */
    private function inlineListData(array $nodes): array
    {
        $data = [];
        foreach ($nodes as $node) {
            array_push($data, ...$this->inlineData($node));
        }

        return $data;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function inlineData(AstNode $node): array
    {
        return match ($node->type) {
            'text' => $this->textInlineData((string) $node->attr('text', '')),
            'softbreak' => [$this->tagged('SoftBreak')],
            'linebreak' => [$this->tagged('LineBreak')],
            'emph' => [$this->tagged('Emph', $this->inlineListData($node->children))],
            'underline' => [$this->tagged('Underline', $this->inlineListData($node->children))],
            'strong' => [$this->tagged('Strong', $this->inlineListData($node->children))],
            'strikeout' => [$this->tagged('Strikeout', $this->inlineListData($node->children))],
            'superscript' => [$this->tagged('Superscript', $this->inlineListData($node->children))],
            'subscript' => [$this->tagged('Subscript', $this->inlineListData($node->children))],
            'small_caps' => [$this->tagged('SmallCaps', $this->inlineListData($node->children))],
            'quoted' => [$this->tagged('Quoted', [$this->tagged(((string) $node->attr('kind', 'double')) === 'single' ? 'SingleQuote' : 'DoubleQuote'), $this->inlineListData($node->children)])],
            'citation' => [$this->citationData($node)],
            'code' => [$this->tagged('Code', [$this->attrData($node), (string) $node->attr('text', '')])],
            'math' => [$this->tagged('Math', [$this->tagged($this->mathIsDisplay($node) ? 'DisplayMath' : 'InlineMath'), (string) $node->attr('text', '')])],
            'raw_html_inline' => [$this->tagged('RawInline', ['html', (string) $node->attr('html', $node->attr('text', ''))])],
            'raw_tex_inline' => [$this->tagged('RawInline', ['tex', (string) $node->attr('tex', $node->attr('text', ''))])],
            'raw_inline' => [$this->tagged('RawInline', [(string) $node->attr('format', 'markdown'), (string) $node->attr('text', '')])],
            'link' => [$this->tagged('Link', [$this->attrData($node), $this->inlineListData($node->children), [(string) $node->attr('url', ''), (string) $node->attr('title', '')]])],
            'image' => [$this->tagged('Image', [
                $this->attrData($node),
                $this->inlineListData($node->children === [] ? $this->textInlines((string) $node->attr('alt', '')) : $node->children),
                [(string) $node->attr('url', $node->attr('src', '')), (string) $node->attr('title', '')],
            ])],
            'note' => [$this->tagged('Note', $this->blockListData($node->children))],
            'span' => [$this->tagged('Span', [$this->attrData($node), $this->inlineListData($node->children)])],
            default => throw new \InvalidArgumentException("JSON writer does not support inline node '{$node->type}'"),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function citationData(AstNode $node): array
    {
        $citations = $this->citationEntries($node);
        $display = $node->children;
        if ($display === []) {
            $display = $this->textInlines((string) $node->attr('text', $this->citationDisplayText($citations)));
        }

        return $this->tagged('Cite', [
            array_map(fn (array $citation): array => $this->citationEntryData($citation), $citations),
            $this->inlineListData($display),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function citationEntries(AstNode $node): array
    {
        $citations = $node->attr('citations', null);
        if (is_array($citations) && $citations !== []) {
            $entries = [];
            foreach ($citations as $citation) {
                if ($citation instanceof AstNode) {
                    $entries[] = $this->citationEntryFromNode($citation);
                } elseif (is_array($citation)) {
                    $entries[] = $citation;
                }
            }

            return $entries;
        }

        return [$this->citationEntryFromNode($node)];
    }

    /**
     * @return array<string, mixed>
     */
    private function citationEntryFromNode(AstNode $node): array
    {
        return [
            'id' => (string) $node->attr('id', ''),
            'prefix' => $node->attr('prefix', []),
            'suffix' => $node->attr('suffix', []),
            'mode' => (string) $node->attr('mode', 'normal'),
            'noteNum' => $node->attr('noteNum', $node->attr('citationNoteNum', 1)),
            'hash' => $node->attr('hash', $node->attr('citationHash', 0)),
        ];
    }

    /**
     * @param array<string, mixed> $citation
     * @return array<string, mixed>
     */
    private function citationEntryData(array $citation): array
    {
        return [
            'citationId' => (string) ($citation['id'] ?? $citation['citationId'] ?? ''),
            'citationPrefix' => $this->citationAffixData($citation['prefix'] ?? []),
            'citationSuffix' => $this->citationAffixData($citation['suffix'] ?? []),
            'citationMode' => $this->citationModeData((string) ($citation['mode'] ?? 'normal')),
            'citationNoteNum' => (int) ($citation['noteNum'] ?? $citation['citationNoteNum'] ?? 1),
            'citationHash' => (int) ($citation['hash'] ?? $citation['citationHash'] ?? 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function citationAffixData(mixed $value): array
    {
        if (is_string($value)) {
            return $value === '' ? [] : $this->inlineListData($this->textInlines($value));
        }
        if (!is_array($value)) {
            return [];
        }

        $nodes = [];
        foreach ($value as $inline) {
            if ($inline instanceof AstNode) {
                $nodes[] = $inline;
            } elseif (is_string($inline)) {
                array_push($nodes, ...$this->textInlines($inline));
            }
        }

        return $nodes === [] ? [] : $this->inlineListData($nodes);
    }

    /**
     * @return array<string, mixed>
     */
    private function citationModeData(string $mode): array
    {
        return $this->tagged(match (strtolower(str_replace(['-', '_'], '', $mode))) {
            'authorintext' => 'AuthorInText',
            'suppressauthor' => 'SuppressAuthor',
            default => 'NormalCitation',
        });
    }

    private function mathIsDisplay(AstNode $node): bool
    {
        $display = $node->attr('display', null);
        if (is_bool($display)) {
            return $display;
        }

        return (string) $node->attr('kind', $display ?? 'inline') === 'display';
    }

    /**
     * @return array<string, mixed>
     */
    private function captionData(AstNode $node): array
    {
        $shortCaption = $this->captionInlines($node->attr('shortCaptionInlines', null), $node->attr('shortCaption', null));
        $longBlocks = $this->captionBlocks($node);

        return $this->tagged('Caption', [
            $shortCaption === null ? null : $this->inlineListData($shortCaption),
            $this->blockListData($longBlocks),
        ]);
    }

    /**
     * @return list<AstNode>
     */
    private function captionBlocks(AstNode $node): array
    {
        $captionBlocks = $node->attr('captionBlocks', null);
        if (is_array($captionBlocks) && $this->isAstNodeList($captionBlocks)) {
            return $captionBlocks;
        }

        $captionInlines = $this->captionInlines($node->attr('captionInlines', null), $node->attr('caption', null));
        if ($captionInlines === null) {
            return [];
        }

        return [new AstNode('plain', [], $captionInlines)];
    }

    /**
     * @return list<AstNode>|null
     */
    private function captionInlines(mixed $inlines, mixed $fallback): ?array
    {
        if (is_array($inlines) && $this->isAstNodeList($inlines)) {
            return $inlines;
        }
        if ($fallback instanceof AstNode) {
            return $this->isInlineNode($fallback) ? [$fallback] : null;
        }
        if (is_string($fallback) && $fallback !== '') {
            return $this->textInlines($fallback);
        }

        return null;
    }

    /**
     * @return list<AstNode>
     */
    private function figureBlocks(AstNode $node): array
    {
        $blocks = [];
        $inlines = [];
        foreach ($node->children as $child) {
            if ($this->isInlineNode($child)) {
                $inlines[] = $child;
                continue;
            }

            if ($inlines !== []) {
                $blocks[] = new AstNode('plain', [], $inlines);
                $inlines = [];
            }
            $blocks[] = $child;
        }

        if ($inlines !== []) {
            $blocks[] = new AstNode('plain', [], $inlines);
        }

        return $blocks;
    }

    /**
     * @return list<AstNode>
     */
    private function lineInlines(AstNode $line): array
    {
        return $line->children === [] ? $this->textInlines((string) $line->attr('text', '')) : $line->children;
    }

    /**
     * @return list<AstNode>
     */
    private function textInlines(string $text): array
    {
        $nodes = [];
        foreach ($this->textParts($text) as $part) {
            $nodes[] = new AstNode('text', ['text' => $part]);
        }

        return $nodes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function textInlineData(string $text): array
    {
        $parts = [];
        foreach ($this->textParts($text) as $part) {
            if (preg_match('/^[ \t]+$/', $part) === 1) {
                $parts[] = $this->tagged('Space');
            } elseif ($part === "\n") {
                $parts[] = $this->tagged('SoftBreak');
            } else {
                $parts[] = $this->tagged('Str', $part);
            }
        }

        return $parts;
    }

    /**
     * @return list<string>
     */
    private function textParts(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $parts = preg_split('/([ \t]+|\n)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        return $parts === false ? [$text] : $parts;
    }

    /**
     * @return array{0:string, 1:list<string>, 2:list<array{0:string, 1:string}>}
     */
    private function attrData(AstNode $node): array
    {
        $classes = $node->attr('classes', []);
        $attributes = $node->attr('attributes', []);
        if (!is_array($classes)) {
            $classes = [];
        }
        if (!is_array($attributes)) {
            $attributes = [];
        }
        ksort($attributes);

        $pairs = [];
        foreach ($attributes as $key => $value) {
            $pairs[] = [(string) $key, (string) $value];
        }

        return [
            (string) $node->attr('id', ''),
            array_map(static fn (mixed $class): string => (string) $class, array_values($classes)),
            $pairs,
        ];
    }

    /**
     * @return array{0:int, 1:array<string, mixed>, 2:array<string, mixed>}
     */
    private function listAttributesData(AstNode $node): array
    {
        return [
            (int) $node->attr('start', 1),
            $this->listStyleData((string) $node->attr('style', 'decimal')),
            $this->listDelimiterData((string) $node->attr('delimiter', 'period')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listStyleData(string $style): array
    {
        return $this->tagged(match ($style) {
            'default' => 'DefaultStyle',
            'lower_alpha' => 'LowerAlpha',
            'upper_alpha' => 'UpperAlpha',
            'lower_roman' => 'LowerRoman',
            'upper_roman' => 'UpperRoman',
            'example' => 'Example',
            default => 'Decimal',
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function listDelimiterData(string $delimiter): array
    {
        return $this->tagged(match ($delimiter) {
            'default' => 'DefaultDelim',
            'one_paren' => 'OneParen',
            'two_parens' => 'TwoParens',
            default => 'Period',
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function alignmentData(string $alignment): array
    {
        return $this->tagged(match ($alignment) {
            'left' => 'AlignLeft',
            'right' => 'AlignRight',
            'center' => 'AlignCenter',
            default => 'AlignDefault',
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function columnWidthData(mixed $width): array
    {
        if (is_numeric($width) && (float) $width > 0.0) {
            return $this->tagged('ColWidth', (float) $width);
        }

        return $this->tagged('ColWidthDefault');
    }

    private function tableColumnCount(AstNode $table): int
    {
        $max = max(count($this->tableAlignments($table, 0)), count($this->tableWidths($table, 0)));
        foreach ($this->tableAllRows($table) as $row) {
            $columns = 0;
            foreach ($row->children as $cell) {
                if ($cell->type === 'table_cell') {
                    $columns += max(1, (int) $cell->attr('colspan', 1));
                }
            }
            $max = max($max, $columns);
        }

        return $max;
    }

    /**
     * @return list<string>
     */
    private function tableAlignments(AstNode $table, int $columnCount): array
    {
        $alignments = $table->attr('alignments', []);
        if (!is_array($alignments)) {
            $alignments = [];
        }

        $normalized = [];
        foreach ($alignments as $alignment) {
            $normalized[] = in_array($alignment, ['left', 'right', 'center'], true) ? (string) $alignment : 'default';
        }
        while ($columnCount > 0 && count($normalized) < $columnCount) {
            $normalized[] = 'default';
        }

        return $columnCount > 0 ? array_slice($normalized, 0, $columnCount) : $normalized;
    }

    /**
     * @return list<float>
     */
    private function tableWidths(AstNode $table, int $columnCount): array
    {
        $widths = $table->attr('widths', []);
        if (!is_array($widths)) {
            $widths = [];
        }

        $normalized = [];
        foreach ($widths as $width) {
            $normalized[] = is_numeric($width) ? max(0.0, (float) $width) : 0.0;
        }
        while ($columnCount > 0 && count($normalized) < $columnCount) {
            $normalized[] = 0.0;
        }

        return $columnCount > 0 ? array_slice($normalized, 0, $columnCount) : $normalized;
    }

    private function tableSection(AstNode $table, string $type): ?AstNode
    {
        foreach ($table->children as $child) {
            if ($child->type === $type) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return list<AstNode>
     */
    private function tableBodies(AstNode $table): array
    {
        $bodies = [];
        foreach ($table->children as $child) {
            if ($child->type === 'table_body') {
                $bodies[] = $child;
            }
        }

        return $bodies;
    }

    /**
     * @return list<AstNode>
     */
    private function tableBodyHeadRows(AstNode $body): array
    {
        $headRows = $body->attr('headRows', []);
        if (!is_array($headRows)) {
            return [];
        }

        return array_values(array_filter($headRows, static fn (mixed $row): bool => $row instanceof AstNode && $row->type === 'table_row'));
    }

    /**
     * @param list<AstNode> $children
     * @return list<AstNode>
     */
    private function tableRowsFromChildren(array $children): array
    {
        return array_values(array_filter($children, static fn (AstNode $node): bool => $node->type === 'table_row'));
    }

    /**
     * @return list<AstNode>
     */
    private function tableAllRows(AstNode $table): array
    {
        $rows = [];
        $head = $this->tableSection($table, 'table_head');
        if ($head instanceof AstNode) {
            array_push($rows, ...$this->tableRowsFromChildren($head->children));
        }
        foreach ($this->tableBodies($table) as $body) {
            array_push($rows, ...$this->tableBodyHeadRows($body), ...$this->tableRowsFromChildren($body->children));
        }
        $foot = $this->tableSection($table, 'table_foot');
        if ($foot instanceof AstNode) {
            array_push($rows, ...$this->tableRowsFromChildren($foot->children));
        }

        return $rows;
    }

    /**
     * @param list<mixed> $value
     * @return list<mixed>
     */
    private function listData(array $value): array
    {
        return array_values($value);
    }

    /**
     * @return array<string, mixed>
     */
    private function tagged(string $tag, mixed $contents = null): array
    {
        $data = ['t' => $tag];
        if (func_num_args() > 1) {
            $data['c'] = $contents;
        }

        return $data;
    }

    /**
     * @param array<mixed> $value
     */
    private function isAstNodeList(array $value): bool
    {
        if ($value === []) {
            return false;
        }
        foreach ($value as $item) {
            if (!$item instanceof AstNode) {
                return false;
            }
        }

        return true;
    }

    private function isInlineNode(AstNode $node): bool
    {
        return in_array($node->type, [
            'text',
            'softbreak',
            'linebreak',
            'emph',
            'strong',
            'strikeout',
            'superscript',
            'subscript',
            'underline',
            'small_caps',
            'code',
            'link',
            'image',
            'note',
            'quoted',
            'math',
            'citation',
            'raw_html_inline',
            'raw_tex_inline',
            'raw_inline',
            'span',
        ], true);
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

    /**
     * @param list<array<string, mixed>> $citations
     */
    private function citationDisplayText(array $citations): string
    {
        $parts = [];
        foreach ($citations as $citation) {
            $id = (string) ($citation['id'] ?? $citation['citationId'] ?? '');
            if ($id !== '') {
                $parts[] = '@' . $id;
            }
        }

        return $parts === [] ? '[]' : '[' . implode('; ', $parts) . ']';
    }
}
