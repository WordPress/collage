<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PandocHtmlTagSoupTableReader
{
    /** @var list<TagSoupTag> */
    private array $tokens = [];
    private int $index = 0;

    /** @var array<string, list<AstNode>> */
    private array $footnotes = [];

    private PandocHtmlAttributeMapPool $attributeMapPool;

    public function __construct(?PandocHtmlAttributeMapPool $attributeMapPool = null)
    {
        $this->attributeMapPool = $attributeMapPool ?? new PandocHtmlAttributeMapPool();
    }

    /**
     * @return list<TagSoupTag>
     */
    public function tokenize(string $html): array
    {
        return (new TagSoupParser())->parseCanonical($html);
    }

    /**
     * @param list<TagSoupTag> $tokens
     * @param array<string, list<AstNode>>|null $footnotes
     * @return array{table:?AstNode,blocks:list<AstNode>,startIndex:int,nextIndex:int,structured:bool,reason:string}|null
     */
    public function parseFirstTable(array $tokens, ?array $footnotes = null): ?array
    {
        foreach ($tokens as $index => $token) {
            if ($token instanceof TagSoupTag && $token->type === TagSoupTag::OPEN && $token->name === 'table') {
                return $this->parseTableAt($tokens, $index, $footnotes);
            }
        }

        return null;
    }

    /**
     * @param list<TagSoupTag> $tokens
     * @param array<string, list<AstNode>>|null $footnotes
     * @return list<array{table:?AstNode,blocks:list<AstNode>,startIndex:int,nextIndex:int,structured:bool,reason:string}>
     */
    public function parseTables(array $tokens, ?array $footnotes = null): array
    {
        $results = [];
        $index = 0;
        $count = count($tokens);
        $resolvedFootnotes = $footnotes ?? $this->footnoteDefinitionsFromTokens($tokens);

        while ($index < $count) {
            $token = $tokens[$index] ?? null;
            if (!$token instanceof TagSoupTag || $token->type !== TagSoupTag::OPEN || $token->name !== 'table') {
                ++$index;
                continue;
            }

            $result = $this->parseTableAt($tokens, $index, $resolvedFootnotes);
            if ($result === null) {
                ++$index;
                continue;
            }

            $results[] = $result;
            $index = max($index + 1, (int) $result['nextIndex']);
        }

        return $results;
    }

    /**
     * @param list<TagSoupTag> $tokens
     * @param array<string, list<AstNode>>|null $footnotes
     * @return list<AstNode>
     */
    public function parseTableBlocks(array $tokens, ?array $footnotes = null): array
    {
        $blocks = [];
        foreach ($this->parseTables($tokens, $footnotes) as $result) {
            array_push($blocks, ...$result['blocks']);
        }

        return $blocks;
    }

    /**
     * @param list<TagSoupTag> $tokens
     * @param array<string, list<AstNode>>|null $footnotes
     * @return array{table:?AstNode,blocks:list<AstNode>,startIndex:int,nextIndex:int,structured:bool,reason:string}|null
     */
    public function parseTableAt(array $tokens, int $index, ?array $footnotes = null): ?array
    {
        $token = $tokens[$index] ?? null;
        if (!$token instanceof TagSoupTag || $token->type !== TagSoupTag::OPEN || $token->name !== 'table') {
            return null;
        }

        $nextIndex = $this->balancedElementEnd($tokens, $index, 'table', count($tokens));
        $resolvedFootnotes = $footnotes ?? $this->footnoteDefinitionsFromTokens($tokens);
        $this->tokens = $tokens;
        $this->index = $index;
        $this->footnotes = $resolvedFootnotes;

        $table = $this->parseCurrentTable($nextIndex);
        if ($table['invalid']) {
            $blocks = $this->fallbackBlocksFromTableSlice(array_slice($tokens, $index, $nextIndex - $index));

            return [
                'table' => null,
                'blocks' => $blocks,
                'startIndex' => $index,
                'nextIndex' => $nextIndex,
                'structured' => false,
                'reason' => 'invalid-table-children',
            ];
        }

        if (!$table['node'] instanceof AstNode) {
            return [
                'table' => null,
                'blocks' => [],
                'startIndex' => $index,
                'nextIndex' => $nextIndex,
                'structured' => false,
                'reason' => 'empty-table',
            ];
        }

        return [
            'table' => $table['node'],
            'blocks' => [$table['node']],
            'startIndex' => $index,
            'nextIndex' => $nextIndex,
            'structured' => true,
            'reason' => 'structured-table',
        ];
    }

    /**
     * @param list<TagSoupTag> $tokens
     * @return array<string, list<AstNode>>
     */
    public function footnoteDefinitionsFromTokens(array $tokens): array
    {
        $definitions = [];
        $count = count($tokens);

        for ($index = 0; $index < $count; ++$index) {
            $token = $tokens[$index] ?? null;
            if (!$token instanceof TagSoupTag || $token->type !== TagSoupTag::OPEN || !$this->isFootnoteContainer($token)) {
                continue;
            }

            $containerEnd = $this->balancedElementEnd($tokens, $index, $token->name, $count);
            for ($cursor = $index + 1; $cursor < $containerEnd; ++$cursor) {
                $item = $tokens[$cursor] ?? null;
                if (!$item instanceof TagSoupTag || $item->type !== TagSoupTag::OPEN || $item->name !== 'li') {
                    continue;
                }

                $id = trim($this->attribute($item, 'id'));
                if ($id === '' || isset($definitions[$id])) {
                    continue;
                }

                $itemEnd = $this->balancedElementEnd($tokens, $cursor, 'li', $containerEnd);
                $this->tokens = $tokens;
                $this->index = $cursor + 1;
                $this->footnotes = [];
                $definitions[$id] = $this->parseFootnoteBlocks($itemEnd);
                $cursor = max($cursor, $itemEnd - 1);
            }

            $index = max($index, $containerEnd - 1);
        }

        return $definitions;
    }

    /**
     * Scan a compact token stream without materializing an entire document.
     * Only a detected endnote container is expanded into the legacy array
     * parser below, which keeps ordinary HTML imports streaming-friendly.
     *
     * @return array<string, list<AstNode>>
     */
    public function footnoteDefinitionsFromTokenStream(TagSoupTokenStream $tokens): array
    {
        $definitions = [];
        $count = count($tokens);

        for ($index = 0; $index < $count; ++$index) {
            if ($tokens->typeAt($index) !== TagSoupTag::OPEN || !$this->isFootnoteContainerAt($tokens, $index)) {
                continue;
            }

            $containerEnd = $this->balancedTokenStreamElementEnd($tokens, $index, $tokens->nameAt($index) ?? '', $count);
            $container = $tokens->slice($index, $containerEnd - $index);
            foreach ($this->footnoteDefinitionsFromTokens($container) as $id => $blocks) {
                if (!isset($definitions[$id])) {
                    $definitions[$id] = $blocks;
                }
            }
            $index = max($index, $containerEnd - 1);
        }

        return $definitions;
    }

    private function balancedTokenStreamElementEnd(TagSoupTokenStream $tokens, int $start, string $name, int $limit): int
    {
        $depth = 0;
        for ($index = $start; $index < $limit; ++$index) {
            $type = $tokens->typeAt($index);
            if ($type === TagSoupTag::OPEN && $tokens->nameAt($index) === $name) {
                ++$depth;
                continue;
            }
            if ($type === TagSoupTag::CLOSE && $tokens->nameAt($index) === $name) {
                --$depth;
                if ($depth <= 0) {
                    return $index + 1;
                }
            }
        }

        return $limit;
    }

    /**
     * @return array{node:?AstNode,invalid:bool}
     */
    private function parseCurrentTable(int $limit): array
    {
        $table = $this->current();
        if (!$table instanceof TagSoupTag || $table->type !== TagSoupTag::OPEN || $table->name !== 'table') {
            return ['node' => null, 'invalid' => true];
        }

        $this->index++;
        $captionInlines = [];
        $columnRecords = [];
        $headAttrs = [];
        $headRows = [];
        $bodies = [];
        $implicitRows = [];
        $footAttrs = [];
        $footRows = [];
        $invalid = false;

        while ($this->index < $limit && ($token = $this->current()) instanceof TagSoupTag) {
            if ($token->type === TagSoupTag::CLOSE && $token->name === 'table') {
                break;
            }
            if ($this->skipIgnorableOrWhitespace($token)) {
                continue;
            }
            if ($token->type === TagSoupTag::TEXT) {
                if (trim($token->text) !== '') {
                    $invalid = true;
                    break;
                }
                $this->index++;
                continue;
            }
            if ($token->type !== TagSoupTag::OPEN) {
                $this->index++;
                continue;
            }

            if ($token->name === 'caption') {
                $captionInlines = $this->parseCaption($limit);
                continue;
            }
            if ($token->name === 'colgroup') {
                array_push($columnRecords, ...$this->parseColgroup($limit));
                continue;
            }
            if ($token->name === 'col') {
                $columnRecords[] = $this->columnRecord($token, null);
                $this->index++;
                $this->consumeClose('col');
                continue;
            }
            if ($token->name === 'thead') {
                $headAttrs = $this->pandocAttrs($token);
                $headRows = $this->parseSectionRows('thead', true, $limit);
                continue;
            }
            if ($token->name === 'tbody') {
                $rows = $this->parseSectionRows('tbody', false, $limit);
                if ($rows !== []) {
                    [$bodyHeadRows, $bodyRows] = $this->splitLeadingHeaderRows($rows);
                    $bodies[] = $this->tableBody($token, $bodyRows, $bodyHeadRows);
                }
                continue;
            }
            if ($token->name === 'tfoot') {
                $footAttrs = $this->pandocAttrs($token);
                $footRows = $this->parseSectionRows('tfoot', false, $limit);
                continue;
            }
            if ($token->name === 'tr') {
                $row = $this->parseRow(false, $limit);
                if ($row instanceof AstNode) {
                    $implicitRows[] = $row;
                }
                continue;
            }

            $invalid = true;
            break;
        }

        $this->index = $limit;
        if ($invalid) {
            return ['node' => null, 'invalid' => true];
        }

        if ($headRows === [] && $implicitRows !== [] && $this->rowContainsOnlyHeaderCells($implicitRows[0])) {
            $headRows[] = array_shift($implicitRows);
        }

        if ($implicitRows !== []) {
            [$bodyHeadRows, $bodyRows] = $this->splitLeadingHeaderRows($implicitRows);
            $bodyAttrs = $this->tableBodyAttrs($bodyRows);
            if ($bodyHeadRows !== []) {
                $bodyAttrs['headRows'] = $bodyHeadRows;
            }
            $bodies[] = new AstNode('table_body', $bodyAttrs, $bodyRows);
        }

        $maxColumns = $this->tableColumnCount($headRows, $bodies, $footRows);
        if ($maxColumns <= 0) {
            return ['node' => null, 'invalid' => false];
        }

        [$alignments, $widths] = $this->columnSpecs($columnRecords, $maxColumns, $headRows, $bodies, $footRows);
        $attrs = $this->pandocAttrs($table);
        $attrs['caption'] = $this->plainTextFromInlines($captionInlines);
        if ($captionInlines !== []) {
            $attrs['captionInlines'] = $captionInlines;
        }
        if ($alignments !== []) {
            $attrs['alignments'] = $alignments;
        }
        if ($widths !== []) {
            $attrs['widths'] = $widths;
        }

        $children = [
            new AstNode('table_head', $headAttrs, $headRows),
            ...$bodies,
        ];
        if ($footRows !== []) {
            $children[] = new AstNode('table_foot', $footAttrs, $footRows);
        }

        return ['node' => new AstNode('table', $attrs, $children), 'invalid' => false];
    }

    /**
     * @return list<AstNode>
     */
    private function parseCaption(int $limit): array
    {
        $this->index++;
        $inlines = $this->parseInlinesUntil(['caption'], $limit);
        $this->consumeClose('caption');

        return $inlines;
    }

    /**
     * @return list<array{alignment:string,width:?float}>
     */
    private function parseColgroup(int $limit): array
    {
        $group = $this->current();
        if (!$group instanceof TagSoupTag) {
            return [];
        }

        $records = [];
        $this->index++;
        while ($this->index < $limit && ($token = $this->current()) instanceof TagSoupTag) {
            if ($token->type === TagSoupTag::CLOSE && $token->name === 'colgroup') {
                $this->index++;
                break;
            }
            if ($token->type === TagSoupTag::CLOSE && $token->name === 'table') {
                break;
            }
            if ($token->type === TagSoupTag::OPEN && in_array($token->name, ['thead', 'tbody', 'tfoot', 'tr', 'caption'], true)) {
                break;
            }
            if ($this->skipIgnorableOrWhitespace($token)) {
                continue;
            }
            if ($token->type === TagSoupTag::OPEN && $token->name === 'col') {
                $span = $this->positiveSpan($this->attribute($token, 'span'));
                for ($offset = 0; $offset < $span; ++$offset) {
                    $records[] = $this->columnRecord($token, $group);
                }
                $this->index++;
                $this->consumeClose('col');
                continue;
            }
            $this->index++;
        }

        if ($records === []) {
            $span = $this->positiveSpan($this->attribute($group, 'span'));
            for ($offset = 0; $offset < $span; ++$offset) {
                $records[] = $this->columnRecord($group, null);
            }
        }

        return $records;
    }

    /**
     * @return array{alignment:string,width:?float}
     */
    private function columnRecord(TagSoupTag $column, ?TagSoupTag $group): array
    {
        $alignment = $this->tableAlignment($column);
        if ($alignment === 'default' && $group instanceof TagSoupTag) {
            $alignment = $this->tableAlignment($group);
        }

        $width = $this->columnWidth($column);
        if ($width === null && $group instanceof TagSoupTag) {
            $width = $this->columnWidth($group);
        }

        return ['alignment' => $alignment, 'width' => $width];
    }

    /**
     * @return list<AstNode>
     */
    private function parseSectionRows(string $sectionName, bool $header, int $limit): array
    {
        $section = $this->current();
        if (!$section instanceof TagSoupTag) {
            return [];
        }

        $sectionAlign = $this->tableAlignment($section);
        $sectionValign = $this->tableVerticalAlignment($section);
        $rows = [];
        $this->index++;

        while ($this->index < $limit && ($token = $this->current()) instanceof TagSoupTag) {
            if ($token->type === TagSoupTag::CLOSE && $token->name === $sectionName) {
                $this->index++;
                break;
            }
            if ($token->type === TagSoupTag::CLOSE && $token->name === 'table') {
                break;
            }
            if ($token->type === TagSoupTag::OPEN && in_array($token->name, ['thead', 'tbody', 'tfoot'], true)) {
                break;
            }
            if ($this->skipIgnorableOrWhitespace($token)) {
                continue;
            }
            if ($token->type === TagSoupTag::OPEN && $token->name === 'tr') {
                $row = $this->parseRow($header, $limit, $sectionAlign, $sectionValign);
                if ($row instanceof AstNode) {
                    $rows[] = $row;
                }
                continue;
            }
            $this->index++;
        }

        return $this->resolveRowspanToEnd($rows);
    }

    private function parseRow(
        bool $header,
        int $limit,
        string $sectionAlign = 'default',
        string $sectionValign = 'default'
    ): ?AstNode {
        $row = $this->current();
        if (!$row instanceof TagSoupTag || $row->type !== TagSoupTag::OPEN || $row->name !== 'tr') {
            return null;
        }

        $rowAlign = $this->tableAlignment($row);
        if ($rowAlign === 'default') {
            $rowAlign = $sectionAlign;
        }
        $rowValign = $this->tableVerticalAlignment($row);
        if ($rowValign === 'default') {
            $rowValign = $sectionValign;
        }

        $cells = [];
        $this->index++;
        while ($this->index < $limit && ($token = $this->current()) instanceof TagSoupTag) {
            if ($token->type === TagSoupTag::CLOSE && $token->name === 'tr') {
                $this->index++;
                break;
            }
            if ($token->type === TagSoupTag::CLOSE && in_array($token->name, ['thead', 'tbody', 'tfoot', 'table'], true)) {
                break;
            }
            if ($token->type === TagSoupTag::OPEN && $token->name === 'tr') {
                break;
            }
            if ($this->skipIgnorableOrWhitespace($token)) {
                continue;
            }
            if ($token->type === TagSoupTag::OPEN && in_array($token->name, ['td', 'th'], true)) {
                $cells[] = $this->parseCell($header || $token->name === 'th', $limit, $rowAlign, $rowValign);
                continue;
            }
            $this->index++;
        }

        $attrs = $this->pandocAttrs($row);
        $attrs['header'] = $header;

        return $cells === [] ? null : new AstNode('table_row', $attrs, $cells);
    }

    private function parseCell(bool $header, int $limit, string $rowAlign, string $rowValign): AstNode
    {
        $cell = $this->current();
        if (!$cell instanceof TagSoupTag) {
            return new AstNode('table_cell');
        }

        $name = $cell->name;
        $attrs = $this->pandocAttrs($cell, ['colspan', 'rowspan']);
        $attrs['header'] = $header;

        $alignment = $this->tableAlignment($cell);
        if ($alignment === 'default') {
            $alignment = $rowAlign;
        }
        if ($alignment !== 'default') {
            $attrs['align'] = $alignment;
        }

        $colspan = $this->positiveSpan($this->attribute($cell, 'colspan'));
        if ($colspan > 1) {
            $attrs['colspan'] = $colspan;
        }

        $rawRowspan = trim($this->attribute($cell, 'rowspan'));
        $rowspan = $rawRowspan === '0' ? 0 : $this->positiveSpan($rawRowspan);
        if ($rowspan === 0) {
            $attrs['rowspan'] = 0;
            $attrs['rowspanToEnd'] = true;
            $attrs['sourceRowspanAttribute'] = 0;
            $attrs['sourceRowspanMode'] = 'to-section-end';
        } elseif ($rowspan > 1) {
            $attrs['rowspan'] = $rowspan;
        }

        $this->index++;
        $children = $this->parseCellChildren($name, $limit);
        $this->consumeClose($name);
        $text = $this->plainTextFromNodes($children);
        if ($text !== '') {
            $attrs['text'] = $text;
        }

        return new AstNode('table_cell', $attrs, $children);
    }

    /**
     * @return list<AstNode>
     */
    private function parseCellChildren(string $cellName, int $limit): array
    {
        $blocks = [];
        $pendingInlines = [];

        while ($this->index < $limit && ($token = $this->current()) instanceof TagSoupTag) {
            if ($token->type === TagSoupTag::CLOSE && $token->name === $cellName) {
                break;
            }
            if ($this->skipIgnorableOrWhitespace($token)) {
                continue;
            }
            if ($token->type === TagSoupTag::OPEN && $token->name === 'p') {
                $this->flushPendingCellInlines($blocks, $pendingInlines);
                $this->index++;
                $inlines = $this->parseInlinesUntil(['p'], $limit);
                $this->consumeClose('p');
                $blocks[] = new AstNode('paragraph', ['text' => $this->plainTextFromInlines($inlines)], $inlines);
                continue;
            }
            if ($token->type === TagSoupTag::TEXT) {
                $this->appendInlines($pendingInlines, $this->textInlines($token->text));
                $this->index++;
                continue;
            }
            if ($token->type === TagSoupTag::OPEN) {
                $this->appendInlines($pendingInlines, $this->parseOpenInline($token, $limit));
                continue;
            }

            $this->index++;
        }

        if ($blocks === []) {
            return $pendingInlines;
        }

        $this->flushPendingCellInlines($blocks, $pendingInlines);

        return $blocks;
    }

    /**
     * @param list<AstNode> $blocks
     * @param list<AstNode> $inlines
     */
    private function flushPendingCellInlines(array &$blocks, array &$inlines): void
    {
        if ($inlines === []) {
            return;
        }

        $blocks[] = new AstNode('paragraph', ['text' => $this->plainTextFromInlines($inlines)], $inlines);
        $inlines = [];
    }

    /**
     * @param list<AstNode> $rows
     * @return list<AstNode>
     */
    private function resolveRowspanToEnd(array $rows): array
    {
        $rowCount = count($rows);
        $resolved = [];
        foreach ($rows as $rowIndex => $row) {
            $cells = [];
            foreach ($row->children as $cell) {
                if ($cell->type !== 'table_cell' || $cell->attr('rowspanToEnd') !== true) {
                    $cells[] = $cell;
                    continue;
                }

                $cells[] = new AstNode(
                    $cell->type,
                    array_merge($cell->attrs, ['renderRowspan' => max(1, $rowCount - $rowIndex)]),
                    $cell->children
                );
            }
            $resolved[] = new AstNode($row->type, $row->attrs, $cells);
        }

        return $resolved;
    }

    /**
     * @param list<AstNode> $rows
     */
    private function tableBody(TagSoupTag $tbody, array $rows, array $headRows = []): AstNode
    {
        $attrs = array_merge($this->pandocAttrs($tbody), $this->tableBodyAttrs($rows));
        if ($headRows !== []) {
            $attrs['headRows'] = $headRows;
        }

        return new AstNode('table_body', $attrs, $rows);
    }

    /**
     * @param list<AstNode> $rows
     * @return array{0:list<AstNode>,1:list<AstNode>}
     */
    private function splitLeadingHeaderRows(array $rows): array
    {
        $headRows = [];
        while ($rows !== [] && $this->rowContainsOnlyHeaderCells($rows[0])) {
            $headRows[] = array_shift($rows);
        }

        return [$headRows, array_values($rows)];
    }

    private function rowContainsOnlyHeaderCells(AstNode $row): bool
    {
        if ($row->type !== 'table_row' || $row->children === []) {
            return false;
        }

        foreach ($row->children as $cell) {
            if ($cell->type !== 'table_cell' || $cell->attr('header') !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<AstNode> $rows
     * @return array<string, mixed>
     */
    private function tableBodyAttrs(array $rows): array
    {
        $rowHeadColumns = $this->tableBodyRowHeadColumns($rows);

        return $rowHeadColumns > 0 ? ['rowHeadColumns' => $rowHeadColumns] : [];
    }

    /**
     * @param list<AstNode> $rows
     */
    private function tableBodyRowHeadColumns(array $rows): int
    {
        $rowCounts = [];
        $activeRowspans = [];

        foreach ($rows as $row) {
            if ($row->type !== 'table_row') {
                continue;
            }

            $rowSlots = [];
            $nextActiveRowspans = [];
            foreach ($activeRowspans as $column => $cover) {
                $remaining = max(0, (int) ($cover['remaining'] ?? 0));
                if ($remaining <= 0) {
                    continue;
                }

                $rowSlots[(int) $column] = (bool) ($cover['header'] ?? false);
                if ($remaining > 1) {
                    $nextActiveRowspans[(int) $column] = [
                        'remaining' => $remaining - 1,
                        'header' => (bool) ($cover['header'] ?? false),
                    ];
                }
            }

            $column = 0;
            foreach ($row->children as $cell) {
                if ($cell->type !== 'table_cell') {
                    continue;
                }

                while (array_key_exists($column, $rowSlots)) {
                    ++$column;
                }

                $colspan = max(1, (int) $cell->attr('colspan', 1));
                $rowspan = max(1, (int) $cell->attr('rowspan', 1));
                $header = $cell->attr('header') === true;
                for ($coveredColumn = $column; $coveredColumn < $column + $colspan; ++$coveredColumn) {
                    $rowSlots[$coveredColumn] = $header;
                    if ($rowspan > 1) {
                        $nextActiveRowspans[$coveredColumn] = [
                            'remaining' => $rowspan - 1,
                            'header' => $header,
                        ];
                    }
                }
                $column += $colspan;
            }

            $leading = 0;
            while (($rowSlots[$leading] ?? false) === true) {
                ++$leading;
            }
            $rowCounts[] = $leading;
            $activeRowspans = $nextActiveRowspans;
        }

        return $rowCounts === [] ? 0 : min($rowCounts);
    }

    /**
     * @param list<AstNode> $headRows
     * @param list<AstNode> $bodies
     * @param list<AstNode> $footRows
     */
    private function tableColumnCount(array $headRows, array $bodies, array $footRows): int
    {
        $max = 0;
        foreach ($headRows as $row) {
            $max = max($max, $this->rowColumnCount($row));
        }
        foreach ($bodies as $body) {
            foreach ($body->children as $row) {
                $max = max($max, $this->rowColumnCount($row));
            }
        }
        foreach ($footRows as $row) {
            $max = max($max, $this->rowColumnCount($row));
        }

        return $max;
    }

    private function rowColumnCount(AstNode $row): int
    {
        if ($row->type !== 'table_row') {
            return 0;
        }

        $count = 0;
        foreach ($row->children as $cell) {
            if ($cell->type === 'table_cell') {
                $count += max(1, (int) $cell->attr('colspan', 1));
            }
        }

        return $count;
    }

    /**
     * @param list<array{alignment:string,width:?float}> $columnRecords
     * @param list<AstNode> $headRows
     * @param list<AstNode> $bodies
     * @param list<AstNode> $footRows
     * @return array{0:list<string>,1:list<?float>}
     */
    private function columnSpecs(array $columnRecords, int $columnCount, array $headRows, array $bodies, array $footRows): array
    {
        $alignments = [];
        $widths = [];
        $hasExplicitWidth = false;
        for ($column = 0; $column < $columnCount; ++$column) {
            $record = $columnRecords[$column] ?? null;
            $alignments[$column] = is_array($record) ? (string) ($record['alignment'] ?? 'default') : 'default';
            $width = is_array($record) && is_numeric($record['width'] ?? null) && (float) $record['width'] > 0.0
                ? (float) $record['width']
                : null;
            if ($width !== null) {
                $hasExplicitWidth = true;
            }
            $widths[$column] = $width;
        }

        $candidates = array_fill(0, $columnCount, []);
        $this->collectAlignmentCandidates($headRows, $candidates);
        foreach ($bodies as $body) {
            $this->collectAlignmentCandidates($body->children, $candidates);
        }
        $this->collectAlignmentCandidates($footRows, $candidates);

        foreach ($candidates as $column => $values) {
            if (($alignments[$column] ?? 'default') !== 'default') {
                continue;
            }
            $unique = array_values(array_unique($values));
            if (count($unique) === 1) {
                $alignments[$column] = $unique[0];
            }
        }

        if ($columnRecords === []) {
            $widths = [];
        } elseif (!$hasExplicitWidth && $columnCount > 0) {
            $widths = array_fill(0, $columnCount, 1 / $columnCount);
        }

        return [$alignments, $widths];
    }

    /**
     * @param list<AstNode> $rows
     * @param list<list<string>> $candidates
     */
    private function collectAlignmentCandidates(array $rows, array &$candidates): void
    {
        foreach ($rows as $row) {
            if ($row->type !== 'table_row') {
                continue;
            }
            $column = 0;
            foreach ($row->children as $cell) {
                if ($cell->type !== 'table_cell') {
                    continue;
                }
                $alignment = (string) $cell->attr('align', 'default');
                $span = max(1, (int) $cell->attr('colspan', 1));
                if (in_array($alignment, ['left', 'right', 'center'], true)) {
                    for ($offset = 0; $offset < $span; ++$offset) {
                        if (isset($candidates[$column + $offset])) {
                            $candidates[$column + $offset][] = $alignment;
                        }
                    }
                }
                $column += $span;
            }
        }
    }

    /**
     * @return list<AstNode>
     */
    private function fallbackBlocksFromTableSlice(array $slice): array
    {
        $this->tokens = $slice;
        $this->index = 0;
        $this->footnotes = [];
        $limit = count($slice);
        if (($this->current()?->isOpenName('table')) === true) {
            $this->index++;
        }

        return $this->parseFallbackParagraphsUntil(['table'], $limit);
    }

    /**
     * @param list<string> $stopTags
     * @return list<AstNode>
     */
    private function parseFallbackParagraphsUntil(array $stopTags, int $limit): array
    {
        $blocks = [];
        while ($this->index < $limit && ($token = $this->current()) instanceof TagSoupTag) {
            if ($token->type === TagSoupTag::CLOSE && in_array($token->name, $stopTags, true)) {
                break;
            }
            if ($this->skipIgnorableOrWhitespace($token)) {
                continue;
            }
            if ($token->type === TagSoupTag::TEXT) {
                $inlines = $this->textInlines($token->text);
                $this->index++;
                if ($this->plainTextFromInlines($inlines) !== '') {
                    $blocks[] = new AstNode('paragraph', ['text' => $this->plainTextFromInlines($inlines)], $inlines);
                }
                continue;
            }
            if ($token->type === TagSoupTag::OPEN && in_array($token->name, ['thead', 'tbody', 'tfoot', 'tr'], true)) {
                $name = $token->name;
                $this->index++;
                array_push($blocks, ...$this->parseFallbackParagraphsUntil([$name, ...$stopTags], $limit));
                $this->consumeClose($name);
                continue;
            }
            if ($token->type === TagSoupTag::OPEN) {
                $name = $token->name;
                $this->index++;
                $inlines = $this->parseInlinesUntil([$name], $limit);
                $this->consumeClose($name);
                if ($this->plainTextFromInlines($inlines) !== '') {
                    $blocks[] = new AstNode('paragraph', ['text' => $this->plainTextFromInlines($inlines)], $inlines);
                }
                continue;
            }
            $this->index++;
        }

        return $blocks;
    }

    /**
     * @return list<AstNode>
     */
    private function parseFootnoteBlocks(int $limit): array
    {
        $blocks = [];
        while ($this->index < $limit && ($token = $this->current()) instanceof TagSoupTag) {
            if ($token->type === TagSoupTag::CLOSE && $token->name === 'li') {
                break;
            }
            if ($this->skipIgnorableOrWhitespace($token)) {
                continue;
            }
            if ($token->type === TagSoupTag::OPEN && $token->name === 'p') {
                $this->index++;
                $inlines = $this->parseInlinesUntil(['p'], $limit);
                $this->consumeClose('p');
                $blocks[] = new AstNode('paragraph', ['text' => $this->plainTextFromInlines($inlines)], $inlines);
                continue;
            }
            if ($token->type === TagSoupTag::OPEN) {
                $name = $token->name;
                $this->index++;
                $inlines = $this->parseInlinesUntil([$name], $limit);
                $this->consumeClose($name);
                if ($inlines !== []) {
                    $blocks[] = new AstNode('paragraph', ['text' => $this->plainTextFromInlines($inlines)], $inlines);
                }
                continue;
            }
            if ($token->type === TagSoupTag::TEXT && trim($token->text) !== '') {
                $inlines = $this->textInlines($token->text);
                $blocks[] = new AstNode('paragraph', ['text' => $this->plainTextFromInlines($inlines)], $inlines);
            }
            $this->index++;
        }

        return $blocks;
    }

    /**
     * @param list<string> $stopTags
     * @return list<AstNode>
     */
    private function parseInlinesUntil(array $stopTags, int $limit): array
    {
        $inlines = [];
        while ($this->index < $limit && ($token = $this->current()) instanceof TagSoupTag) {
            if ($token->type === TagSoupTag::CLOSE && in_array($token->name, $stopTags, true)) {
                break;
            }
            if ($token->type === TagSoupTag::OPEN && $this->inlineOpenImpliesClose($token->name, $stopTags)) {
                break;
            }
            if ($this->skipIgnorable($token)) {
                continue;
            }
            if ($token->type === TagSoupTag::TEXT) {
                $this->appendInlines($inlines, $this->textInlines($token->text));
                $this->index++;
                continue;
            }
            if ($token->type === TagSoupTag::OPEN) {
                $this->appendInlines($inlines, $this->parseOpenInline($token, $limit));
                continue;
            }
            $this->index++;
        }

        return $this->trimBoundaryWhitespace($inlines);
    }

    /**
     * @return list<AstNode>
     */
    private function parseOpenInline(TagSoupTag $token, int $limit): array
    {
        $name = $token->name;
        if ($name === 'br') {
            $this->index++;
            $this->consumeClose('br');
            return [new AstNode('linebreak')];
        }
        if ($name === 'strong' || $name === 'b') {
            $this->index++;
            $children = $this->parseInlinesUntil([$name], $limit);
            $this->consumeClose($name);
            return [new AstNode('strong', [], $children)];
        }
        if ($name === 'em' || $name === 'i') {
            $this->index++;
            $children = $this->parseInlinesUntil([$name], $limit);
            $this->consumeClose($name);
            return [new AstNode('emph', [], $children)];
        }
        if ($name === 'code' || $name === 'tt') {
            $this->index++;
            $text = $this->collectTextUntilClose($name, $limit);
            $this->consumeClose($name);
            return [new AstNode('code', ['text' => $text])];
        }
        if ($name === 'sup') {
            $this->index++;
            $children = $this->parseInlinesUntil(['sup'], $limit);
            $this->consumeClose('sup');
            return [new AstNode('superscript', [], $children)];
        }
        if ($name === 'sub') {
            $this->index++;
            $children = $this->parseInlinesUntil(['sub'], $limit);
            $this->consumeClose('sub');
            return [new AstNode('subscript', [], $children)];
        }
        if ($name === 'a') {
            if ($this->isFootnoteBacklink($token)) {
                $this->index++;
                $this->skipUntilClose('a', $limit);
                return [];
            }
            if ($this->isFootnoteReference($token)) {
                $href = trim($this->attribute($token, 'href'));
                $id = str_starts_with($href, '#') ? substr($href, 1) : '';
                $this->index++;
                $this->skipUntilClose('a', $limit);
                return [new AstNode('note', [], $this->footnotes[$id] ?? [])];
            }

            $this->index++;
            $children = $this->parseInlinesUntil(['a'], $limit);
            $this->consumeClose('a');
            $href = $this->attribute($token, 'href');
            if ($this->hasAttribute($token, 'href')) {
                return [new AstNode('link', ['url' => $href, 'title' => $this->attribute($token, 'title')], $children)];
            }

            return $children;
        }

        $this->index++;
        $children = $this->parseInlinesUntil([$name], $limit);
        $this->consumeClose($name);

        return $children;
    }

    private function collectTextUntilClose(string $name, int $limit): string
    {
        $text = '';
        while ($this->index < $limit && ($token = $this->current()) instanceof TagSoupTag) {
            if ($token->type === TagSoupTag::CLOSE && $token->name === $name) {
                break;
            }
            if ($token->type === TagSoupTag::TEXT) {
                $text .= $token->text;
            }
            $this->index++;
        }

        return $text;
    }

    /**
     * @return list<AstNode>
     */
    private function textInlines(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $text = trim(preg_replace('/\s+/', ' ', $raw) ?? $raw);
        if (preg_match('/^\s/u', $raw) === 1) {
            $text = ' ' . $text;
        }
        if (preg_match('/\s$/u', $raw) === 1) {
            $text .= ' ';
        }

        return $text === '' ? [] : [new AstNode('text', ['text' => $text])];
    }

    /**
     * @param list<AstNode> $inlines
     * @param list<AstNode> $append
     */
    private function appendInlines(array &$inlines, array $append): void
    {
        foreach ($append as $node) {
            $lastIndex = array_key_last($inlines);
            $last = $lastIndex === null ? null : $inlines[$lastIndex];
            if ($last instanceof AstNode && $last->type === 'text' && $node->type === 'text') {
                $inlines[$lastIndex] = new AstNode('text', [
                    'text' => (string) $last->attr('text', '') . (string) $node->attr('text', ''),
                ]);
                continue;
            }
            $inlines[] = $node;
        }
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function trimBoundaryWhitespace(array $nodes): array
    {
        while ($nodes !== [] && $nodes[0]->type === 'text' && trim((string) $nodes[0]->attr('text', '')) === '') {
            array_shift($nodes);
        }
        if ($nodes !== [] && $nodes[0]->type === 'text') {
            $nodes[0] = new AstNode('text', ['text' => ltrim((string) $nodes[0]->attr('text', ''))]);
            if ($nodes[0]->attr('text', '') === '') {
                array_shift($nodes);
            }
        }

        while ($nodes !== [] && $nodes[count($nodes) - 1]->type === 'text' && trim((string) $nodes[count($nodes) - 1]->attr('text', '')) === '') {
            array_pop($nodes);
        }
        if ($nodes !== [] && $nodes[count($nodes) - 1]->type === 'text') {
            $lastIndex = count($nodes) - 1;
            $nodes[$lastIndex] = new AstNode('text', ['text' => rtrim((string) $nodes[$lastIndex]->attr('text', ''))]);
            if ($nodes[$lastIndex]->attr('text', '') === '') {
                array_pop($nodes);
            }
        }

        return array_values($nodes);
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function plainTextFromInlines(array $inlines): string
    {
        $text = '';
        foreach ($inlines as $inline) {
            if ($inline->type === 'text' || $inline->type === 'code') {
                $text .= (string) $inline->attr('text', '');
                continue;
            }
            if ($inline->type === 'linebreak') {
                $text .= "\n";
                continue;
            }
            $text .= $this->plainTextFromInlines($inline->children);
        }

        return trim(preg_replace('/[ \t\f\v]+/', ' ', $text) ?? $text);
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainTextFromNodes(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if ($node->type === 'text' || $node->type === 'code') {
                $text .= (string) $node->attr('text', '');
                continue;
            }
            if ($node->type === 'linebreak') {
                $text .= "\n";
                continue;
            }
            $text .= ' ' . $this->plainTextFromNodes($node->children);
        }

        return trim(preg_replace('/[ \t\f\v]+/', ' ', $text) ?? $text);
    }

    /**
     * @param list<string> $stopTags
     */
    private function inlineOpenImpliesClose(string $openName, array $stopTags): bool
    {
        if (in_array($openName, ['td', 'th', 'tr', 'thead', 'tbody', 'tfoot', 'caption', 'table'], true)) {
            return array_intersect($stopTags, ['td', 'th', 'tr', 'thead', 'tbody', 'tfoot', 'caption', 'table']) !== [];
        }

        return false;
    }

    private function current(): ?TagSoupTag
    {
        return $this->tokens[$this->index] ?? null;
    }

    private function skipIgnorable(TagSoupTag $token): bool
    {
        if (in_array($token->type, [TagSoupTag::POSITION, TagSoupTag::WARNING, TagSoupTag::COMMENT], true)) {
            $this->index++;
            return true;
        }

        return false;
    }

    private function skipIgnorableOrWhitespace(TagSoupTag $token): bool
    {
        if ($this->skipIgnorable($token)) {
            return true;
        }
        if ($token->type === TagSoupTag::TEXT && trim($token->text) === '') {
            $this->index++;
            return true;
        }

        return false;
    }

    private function consumeClose(string $name): void
    {
        $token = $this->current();
        if ($token instanceof TagSoupTag && $token->type === TagSoupTag::CLOSE && $token->name === $name) {
            $this->index++;
        }
    }

    private function skipUntilClose(string $name, int $limit): void
    {
        $depth = 0;
        while ($this->index < $limit && ($token = $this->current()) instanceof TagSoupTag) {
            if ($token->type === TagSoupTag::OPEN && $token->name === $name) {
                ++$depth;
            } elseif ($token->type === TagSoupTag::CLOSE && $token->name === $name) {
                --$depth;
                $this->index++;
                if ($depth <= 0) {
                    return;
                }
                continue;
            }
            $this->index++;
        }
    }

    /**
     * @param list<TagSoupTag> $tokens
     */
    private function balancedElementEnd(array $tokens, int $start, string $name, int $limit): int
    {
        $depth = 0;
        for ($index = $start; $index < $limit; ++$index) {
            $token = $tokens[$index] ?? null;
            if (!$token instanceof TagSoupTag) {
                continue;
            }
            if ($token->type === TagSoupTag::OPEN && $token->name === $name) {
                ++$depth;
                continue;
            }
            if ($token->type === TagSoupTag::CLOSE && $token->name === $name) {
                --$depth;
                if ($depth <= 0) {
                    return $index + 1;
                }
            }
        }

        return $limit;
    }

    private function isFootnoteReference(TagSoupTag $tag): bool
    {
        return $tag->name === 'a' && strtolower(trim($this->attribute($tag, 'role'))) === 'doc-noteref';
    }

    private function isFootnoteBacklink(TagSoupTag $tag): bool
    {
        return $tag->name === 'a' && strtolower(trim($this->attribute($tag, 'role'))) === 'doc-backlink';
    }

    private function isFootnoteContainer(TagSoupTag $tag): bool
    {
        if (strtolower(trim($this->attribute($tag, 'role'))) === 'doc-endnotes') {
            return true;
        }

        return in_array('footnotes', $this->classes($tag), true);
    }

    private function isFootnoteContainerAt(TagSoupTokenStream $tokens, int $index): bool
    {
        if (strtolower(trim($tokens->attributeAt($index, 'role'))) === 'doc-endnotes') {
            return true;
        }

        $classes = preg_split('/\s+/', trim($tokens->attributeAt($index, 'class')), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return in_array('footnotes', $classes, true);
    }

    /**
     * @return list<string>
     */
    private function classes(TagSoupTag $tag): array
    {
        return preg_split('/\s+/', trim($this->attribute($tag, 'class')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private function attribute(TagSoupTag $tag, string $name): string
    {
        foreach ($tag->attributes as $attribute) {
            if (strtolower($attribute['name']) === $name) {
                return $attribute['value'];
            }
        }

        return '';
    }

    private function hasAttribute(TagSoupTag $tag, string $name): bool
    {
        foreach ($tag->attributes as $attribute) {
            if (strtolower($attribute['name']) === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $skip
     * @return array<string, mixed>
     */
    private function pandocAttrs(TagSoupTag $tag, array $skip = []): array
    {
        $skip = array_map('strtolower', $skip);
        $id = '';
        $classes = [];
        $attributes = [];
        $htmlAttributes = [];

        foreach ($tag->attributes as $attribute) {
            $name = strtolower($attribute['name']);
            if (in_array($name, $skip, true)) {
                continue;
            }

            $value = trim($attribute['value']);
            if ($name === 'id') {
                $id = $value;
                if ($value !== '') {
                    $htmlAttributes['id'] = $value;
                }
                continue;
            }
            if ($name === 'class') {
                $classes = preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                if ($classes !== []) {
                    $htmlAttributes['class'] = implode(' ', $classes);
                }
                continue;
            }
            if ($name === 'style') {
                $value = $this->nonAlignmentStyle($value);
                if ($value === '') {
                    continue;
                }
            }

            $key = str_starts_with($name, 'data-') ? substr($name, 5) : $name;
            if ($key === '') {
                continue;
            }
            $attributes[$key] = $value;
            $htmlAttributes[$name] = $value;
        }

        $attrs = [];
        if ($id !== '') {
            $attrs['id'] = $id;
        }
        if ($classes !== []) {
            $attrs['classes'] = $this->attributeMapPool->intern($classes);
        }
        if ($attributes !== []) {
            $attrs['attributes'] = $this->attributeMapPool->intern($attributes);
        }
        if ($htmlAttributes !== []) {
            $attrs['htmlAttributes'] = $this->attributeMapPool->intern($htmlAttributes);
        }

        return $attrs;
    }

    private function nonAlignmentStyle(string $style): string
    {
        $kept = [];
        foreach (CssDeclarationScanner::declarations($style) as $declaration) {
            if ($declaration['name'] === 'text-align') {
                continue;
            }
            $kept[] = $declaration;
        }

        return CssDeclarationScanner::render($kept);
    }

    private function tableAlignment(TagSoupTag $tag): string
    {
        $align = strtolower(trim($this->attribute($tag, 'align')));
        if (in_array($align, ['left', 'right', 'center'], true)) {
            return $align;
        }

        $textAlign = CssDeclarationScanner::lastValidValue(
            $this->attribute($tag, 'style'),
            'text-align',
            static fn (string $value): bool => preg_match('/^(?:left|right|center)\s*$/i', $value) === 1
        );
        if ($textAlign !== null && preg_match('/^(left|right|center)\b/i', $textAlign, $m) === 1) {
            return strtolower($m[1]);
        }

        return 'default';
    }

    private function tableVerticalAlignment(TagSoupTag $tag): string
    {
        $valign = strtolower(trim($this->attribute($tag, 'valign')));
        if (in_array($valign, ['baseline', 'top', 'middle', 'bottom'], true)) {
            return $valign;
        }

        $verticalAlign = CssDeclarationScanner::lastValidValue(
            $this->attribute($tag, 'style'),
            'vertical-align',
            static fn (string $value): bool => preg_match('/^(?:baseline|top|middle|bottom)\s*$/i', $value) === 1
        );
        if ($verticalAlign !== null && preg_match('/^(baseline|top|middle|bottom)\b/i', $verticalAlign, $m) === 1) {
            return strtolower($m[1]);
        }

        return 'default';
    }

    private function columnWidth(TagSoupTag $tag): ?float
    {
        $styleWidth = CssDeclarationScanner::lastValidValue(
            $this->attribute($tag, 'style'),
            'width',
            static fn (string $value): bool => preg_match('/^[0-9]+(?:\.[0-9]+)?\s*%\s*$/', $value) === 1,
            static fn (string $value): bool => strcasecmp(trim($value), 'auto') === 0
        );
        if ($styleWidth !== null && preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*%/', $styleWidth, $m) === 1) {
            return (float) $m[1] / 100;
        }

        $width = trim($this->attribute($tag, 'width'));
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*%$/', $width, $m) === 1) {
            return (float) $m[1] / 100;
        }

        return null;
    }

    private function positiveSpan(string $value): int
    {
        $value = trim($value);

        return preg_match('/^[1-9]\d*$/', $value) === 1 ? (int) $value : 1;
    }
}
