<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MediaWikiReader
{
    /** @var list<string> */
    private array $lines = [];

    private int $index = 0;

    /** @var array<string, int> */
    private array $headingIds = [];

    private int $externalLinkCounter = 0;

    public function read(string $text): AstNode
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $this->lines = explode("\n", $normalized);
        $this->index = 0;
        $this->headingIds = [];
        $this->externalLinkCounter = 0;

        return new AstNode('document', [
            'sourceFormat' => 'mediawiki',
            'mediawiki' => [
                'reader' => self::class,
                'readerScope' => 'bounded-mediawiki-reader-core-block-inline-semantics',
                'sourceBytes' => strlen($text),
                'upstreamEvidence' => [
                    'source' => 'Pandoc MediaWiki reader behavior probes and documented MediaWiki core syntax',
                    'readerUnitGroups' => [
                        'headings',
                        'paragraphs',
                        'bold italic and code-like inline markup',
                        'internal and external links',
                        'images',
                        'bullet ordered and definition lists',
                        'preformatted and syntax-highlight code blocks',
                        'simple tables with captions header rows and cell attributes',
                        'comments entities nowiki math references line breaks and unexpanded template omission',
                    ],
                    'fixtureStatus' => 'Expanded native PHP reader slice; unexpanded templates and variables follow Pandoc by being omitted, while parser functions remain literal. Full template expansion, parser-function evaluation, transclusion, and full table geometry parity remain open.',
                ],
            ],
        ], $this->parseBlocks());
    }

    /**
     * @return list<AstNode>
     */
    private function parseBlocks(): array
    {
        $blocks = [];
        while ($this->index < count($this->lines)) {
            $line = rtrim($this->lines[$this->index], "\n");
            $trimmed = trim($line);
            if ($trimmed === '' || $this->isCommentOnlyLine($trimmed)) {
                ++$this->index;
                continue;
            }

            if ($this->isDelimitedCodeBlockStart($trimmed)) {
                $blocks[] = $this->parseDelimitedCodeBlock($trimmed);
                continue;
            }
            if ($this->isRawHtmlBlockLine($trimmed)) {
                ++$this->index;
                $blocks[] = new AstNode('raw_html', ['format' => 'html', 'text' => $trimmed]);
                continue;
            }
            if (str_starts_with($trimmed, '{|')) {
                $blocks[] = $this->parseTable();
                continue;
            }
            if (preg_match('/^(={1,6})(?!\=)\\s*.*?\\s*\\1$/u', $trimmed) === 1) {
                $blocks[] = $this->parseHeading($trimmed);
                continue;
            }
            if ($trimmed === '----') {
                ++$this->index;
                $blocks[] = new AstNode('horizontal_rule');
                continue;
            }
            if (str_starts_with($line, ' ')) {
                $blocks[] = $this->parsePreBlock();
                continue;
            }
            if ($this->isListLine($trimmed)) {
                $blocks[] = $this->parseListBlock();
                continue;
            }
            if ($this->isDefinitionListLine($trimmed)) {
                $blocks[] = $this->parseDefinitionList();
                continue;
            }

            $paragraph = $this->parseParagraph();
            if (!$this->isEmptyParagraph($paragraph)) {
                $blocks[] = $paragraph;
            }
        }

        return $blocks;
    }

    private function parseHeading(string $trimmed): AstNode
    {
        ++$this->index;
        if (preg_match('/^(={1,6})(?!\=)\\s*(.*?)\\s*\\1$/u', $trimmed, $match) === 1) {
            $level = strlen($match[1]);
            $inlines = $this->parseInlines($match[2]);
            $text = $this->plainInlineText($inlines);

            return new AstNode('heading', [
                'level' => $level,
                'id' => $this->headingId($text),
                'text' => $text,
            ], $inlines);
        }

        return $this->paragraphFromText($trimmed);
    }

    private function parseParagraph(): AstNode
    {
        $parts = [];
        while ($this->index < count($this->lines)) {
            $line = rtrim($this->lines[$this->index], "\n");
            $trimmed = trim($line);
            if ($trimmed === '' || ($parts !== [] && $this->isBlockStart($line))) {
                break;
            }
            if ($this->isCommentOnlyLine($trimmed)) {
                ++$this->index;
                continue;
            }
            $parts[] = $trimmed;
            ++$this->index;
        }

        return $this->paragraphFromText(implode(' ', $parts));
    }

    private function isBlockStart(string $line): bool
    {
        $trimmed = trim($line);

        return str_starts_with($trimmed, '{|')
            || $this->isDelimitedCodeBlockStart($trimmed)
            || $this->isRawHtmlBlockLine($trimmed)
            || preg_match('/^(={1,6})(?!\=)\\s*.*?\\s*\\1$/u', $trimmed) === 1
            || $trimmed === '----'
            || str_starts_with($line, ' ')
            || $this->isListLine($trimmed)
            || $this->isDefinitionListLine($trimmed);
    }

    private function isCommentOnlyLine(string $trimmed): bool
    {
        $visible = preg_replace('/<!--.*?-->/su', '', $trimmed);

        return trim((string) $visible) === '';
    }

    private function isDelimitedCodeBlockStart(string $trimmed): bool
    {
        return preg_match('/^<(pre|syntaxhighlight|source)\\b[^>]*>/iu', $trimmed) === 1;
    }

    private function parseDelimitedCodeBlock(string $openingLine): AstNode
    {
        $tag = 'pre';
        $classes = [];
        if (preg_match('/^<(pre|syntaxhighlight|source)\\b([^>]*)>(.*)$/iu', $openingLine, $match) === 1) {
            $tag = strtolower($match[1]);
            $attrs = $this->parseHtmlAttributes($match[2]);
            $lang = (string) ($attrs['lang'] ?? $attrs['language'] ?? '');
            if ($lang !== '') {
                $classes[] = $lang;
            }
            $afterOpen = (string) $match[3];
        } else {
            $afterOpen = '';
        }

        ++$this->index;
        $body = [];
        $closingPattern = '/<\\/' . preg_quote($tag, '/') . '\\s*>/iu';
        if (preg_match($closingPattern, $afterOpen, $closeMatch, PREG_OFFSET_CAPTURE) === 1) {
            $body[] = substr($afterOpen, 0, (int) $closeMatch[0][1]);
        } else {
            if ($afterOpen !== '') {
                $body[] = $afterOpen;
            }
            while ($this->index < count($this->lines)) {
                $line = rtrim($this->lines[$this->index], "\n");
                ++$this->index;
                if (preg_match($closingPattern, $line, $closeMatch, PREG_OFFSET_CAPTURE) === 1) {
                    $beforeClose = substr($line, 0, (int) $closeMatch[0][1]);
                    if ($beforeClose !== '') {
                        $body[] = $beforeClose;
                    }
                    break;
                }
                $body[] = $line;
            }
        }

        return new AstNode('code_block', [
            'text' => implode("\n", $body),
            'classes' => $classes,
        ]);
    }

    private function isRawHtmlBlockLine(string $trimmed): bool
    {
        return preg_match('/^<hr\\b[^>]*\\/?>$/iu', $trimmed) === 1;
    }

    private function isEmptyParagraph(AstNode $paragraph): bool
    {
        return count($paragraph->children) === 1
            && $paragraph->children[0]->type === 'text'
            && (string) $paragraph->children[0]->attr('text', '') === '';
    }

    private function headingId(string $text): string
    {
        $base = strtolower(trim($text));
        $base = preg_replace('/[^a-z0-9_\\-]+/u', '-', $base) ?? '';
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'section';
        }

        $count = $this->headingIds[$base] ?? 0;
        $this->headingIds[$base] = $count + 1;

        return $count === 0 ? $base : $base . '_' . $count;
    }

    private function parsePreBlock(): AstNode
    {
        $body = [];
        while ($this->index < count($this->lines)) {
            $line = rtrim($this->lines[$this->index], "\n");
            if (!str_starts_with($line, ' ')) {
                break;
            }
            $body[] = substr($line, 1);
            ++$this->index;
        }

        return new AstNode('code_block', [
            'text' => implode("\n", $body),
            'classes' => [],
        ]);
    }

    private function isListLine(string $trimmed): bool
    {
        return preg_match('/^[*#]+\\s*\\S/u', $trimmed) === 1;
    }

    private function parseListBlock(): AstNode
    {
        $entries = [];
        $topStyle = null;
        while ($this->index < count($this->lines)) {
            $line = trim($this->lines[$this->index]);
            if (preg_match('/^([*#]+)\\s*(.*)$/u', $line, $match) !== 1) {
                break;
            }
            $marker = $match[1];
            $lineTopStyle = $marker[0];
            if ($entries !== [] && strlen($marker) === 1 && $topStyle !== null && $lineTopStyle !== $topStyle) {
                break;
            }
            $topStyle ??= $lineTopStyle;
            $entries[] = [
                'marker' => $marker,
                'text' => trim($match[2]),
            ];
            ++$this->index;
        }

        $cursor = 0;
        $style = str_starts_with($entries[0]['marker'] ?? '*', '#') ? 'ordered' : 'bullet';

        return $this->buildListAt($entries, $cursor, 1, $style);
    }

    /**
     * @param list<array{marker:string, text:string}> $entries
     */
    private function buildListAt(array $entries, int &$cursor, int $level, string $style): AstNode
    {
        $items = [];
        while ($cursor < count($entries)) {
            $entry = $entries[$cursor];
            $entryLevel = strlen($entry['marker']);
            $entryStyle = str_ends_with($entry['marker'], '#') ? 'ordered' : 'bullet';
            if ($entryLevel < $level) {
                break;
            }
            if ($entryLevel > $level) {
                if ($items === []) {
                    ++$cursor;
                    continue;
                }
                $last = array_pop($items);
                $items[] = new AstNode('list_item', ['loose' => false], array_merge(
                    $last->children,
                    [$this->buildListAt($entries, $cursor, $level + 1, $entryStyle)]
                ));
                continue;
            }
            if ($entryStyle !== $style) {
                break;
            }

            $inlines = $this->parseInlines($entry['text']);
            $items[] = new AstNode('list_item', ['loose' => false], [
                new AstNode('plain', ['text' => $this->plainInlineText($inlines)], $inlines),
            ]);
            ++$cursor;
        }

        if ($style === 'ordered') {
            return new AstNode('ordered_list', [
                'start' => 1,
                'style' => 'default',
                'delimiter' => 'default',
                'loose' => false,
            ], $items);
        }

        return new AstNode('bullet_list', ['loose' => false], $items);
    }

    private function isDefinitionListLine(string $trimmed): bool
    {
        return preg_match('/^[;:]\\s*\\S/u', $trimmed) === 1;
    }

    private function parseDefinitionList(): AstNode
    {
        $items = [];
        while ($this->index < count($this->lines)) {
            $line = trim($this->lines[$this->index]);
            if (!$this->isDefinitionListLine($line)) {
                break;
            }
            if (str_starts_with($line, ';')) {
                [$term, $definition] = $this->splitDefinitionTerm(substr($line, 1));
                ++$this->index;
                $items[] = [
                    'term' => trim($term),
                    'definitions' => $definition === '' ? [] : [trim($definition)],
                ];
                continue;
            }

            preg_match('/^:\\s*(.*)$/u', $line, $match);
            ++$this->index;
            if ($items === []) {
                $items[] = ['term' => '', 'definitions' => []];
            }
            $items[array_key_last($items)]['definitions'][] = trim((string) ($match[1] ?? ''));
        }

        return new AstNode('definition_list', [], array_map(
            fn (array $item): AstNode => $this->definitionItem($item['term'], $item['definitions'] === [] ? [''] : $item['definitions']),
            $items
        ));
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitDefinitionTerm(string $source): array
    {
        if (preg_match('/^(.*?)\\s+:\\s+(.*)$/u', $source, $match) === 1) {
            return [$match[1], $match[2]];
        }

        return [$source, ''];
    }

    /**
     * @param list<string> $definitions
     */
    private function definitionItem(string $term, array $definitions): AstNode
    {
        $termInlines = $this->parseInlines($term);
        $children = [
            new AstNode('definition_term', ['text' => $this->plainInlineText($termInlines)], $termInlines),
        ];
        foreach ($definitions as $definition) {
            $definitionInlines = $this->parseInlines($definition);
            $children[] = new AstNode('definition', [], [
                new AstNode('plain', ['text' => $this->plainInlineText($definitionInlines)], $definitionInlines),
            ]);
        }

        return new AstNode('definition_item', ['term' => $this->plainInlineText($termInlines)], $children);
    }

    private function parseTable(): AstNode
    {
        ++$this->index;
        $caption = '';
        $captionInlines = null;
        $headRows = [];
        $bodyRows = [];
        $currentCells = [];
        $currentHeader = null;
        $bodyStarted = false;

        while ($this->index < count($this->lines)) {
            $line = trim($this->lines[$this->index]);
            ++$this->index;
            if ($line === '|}') {
                $this->finalizeTableRow($headRows, $bodyRows, $currentCells, $currentHeader, $bodyStarted);
                break;
            }
            if ($line === '') {
                continue;
            }
            if (str_starts_with($line, '|-')) {
                $this->finalizeTableRow($headRows, $bodyRows, $currentCells, $currentHeader, $bodyStarted);
                continue;
            }
            if (str_starts_with($line, '|+')) {
                $captionCell = $this->parseTableCellSource(substr($line, 2));
                $captionInlines = $this->parseInlines($captionCell['text']);
                $caption = $this->plainInlineText($captionInlines);
                continue;
            }
            if (str_starts_with($line, '!')) {
                $currentHeader = ($currentHeader ?? true) && true;
                array_push($currentCells, ...$this->splitTableCells(substr($line, 1)));
                continue;
            }
            if (str_starts_with($line, '|')) {
                $currentHeader = false;
                array_push($currentCells, ...$this->splitTableCells(substr($line, 1)));
            }
        }

        $columnCount = max(0, ...array_map(fn (AstNode $row): int => $this->tableRowColumnCount($row), [...$headRows, ...$bodyRows]));

        $attrs = [
            'caption' => $caption,
            'alignments' => $this->tableAlignments([...$headRows, ...$bodyRows], $columnCount),
            'widths' => array_fill(0, $columnCount, null),
            'nativeColumnCount' => $columnCount,
        ];
        if (is_array($captionInlines)) {
            $attrs['captionInlines'] = $captionInlines;
        }

        return new AstNode('table', $attrs, [
            new AstNode('table_head', [], $headRows),
            new AstNode('table_body', [], $bodyRows),
            new AstNode('table_foot'),
        ]);
    }

    /**
     * @param list<AstNode> $headRows
     * @param list<AstNode> $bodyRows
     * @param list<array<string, mixed>> $currentCells
     */
    private function finalizeTableRow(array &$headRows, array &$bodyRows, array &$currentCells, ?bool &$currentHeader, bool &$bodyStarted): void
    {
        if ($currentCells === []) {
            $currentHeader = null;
            return;
        }

        if ($currentHeader === true && !$bodyStarted) {
            $headRows[] = $this->tableRow($currentCells, true);
        } else {
            $bodyRows[] = $this->tableRow($currentCells, false);
            $bodyStarted = true;
        }

        $currentCells = [];
        $currentHeader = null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function splitTableCells(string $source): array
    {
        $parts = preg_split('/\\s*(?:!!|\\|\\|)\\s*/u', $source);
        if ($parts === false) {
            $parts = [$source];
        }

        return array_map(fn (string $cell): array => $this->parseTableCellSource($cell), $parts);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseTableCellSource(string $source): array
    {
        $source = trim($source);
        $attrs = [
            'classes' => [],
            'attributes' => [],
        ];

        if (str_contains($source, '|')) {
            [$before, $after] = array_pad(explode('|', $source, 2), 2, '');
            if ($this->looksLikeTableCellAttributes($before)) {
                $attrs = $this->parseTableCellAttributes($before);
                $source = $after;
            }
        }

        return ['text' => trim($source)] + $attrs;
    }

    private function looksLikeTableCellAttributes(string $source): bool
    {
        return preg_match('/(?:^|\\s)(?:[A-Za-z_:][A-Za-z0-9_.:-]*\\s*=|class|style|align|colspan|rowspan|scope)\\b/iu', trim($source)) === 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseTableCellAttributes(string $source): array
    {
        $raw = $this->parseHtmlAttributes($source);
        $attrs = [
            'classes' => [],
            'attributes' => [],
        ];

        foreach ($raw as $name => $value) {
            $lower = strtolower($name);
            if ($lower === 'class') {
                $classes = preg_split('/\\s+/', trim($value)) ?: [];
                $attrs['classes'] = array_values(array_filter($classes, static fn (string $class): bool => $class !== ''));
                continue;
            }
            if ($lower === 'align') {
                $alignment = strtolower($value);
                if (in_array($alignment, ['left', 'right', 'center'], true)) {
                    $attrs['align'] = $alignment;
                }
                continue;
            }
            if ($lower === 'colspan') {
                $colspan = max(1, (int) $value);
                if ($colspan > 1) {
                    $attrs['colspan'] = $colspan;
                }
                continue;
            }
            if ($lower === 'rowspan') {
                $rowspan = max(1, (int) $value);
                if ($rowspan > 1) {
                    $attrs['rowspan'] = $rowspan;
                }
                continue;
            }

            $attrs['attributes'][$name] = $value;
        }

        return $attrs;
    }

    /**
     * @param list<array<string, mixed>> $cells
     */
    private function tableRow(array $cells, bool $header): AstNode
    {
        return new AstNode('table_row', ['header' => $header], array_map(
            function (array $cell) use ($header): AstNode {
                $inlines = $this->parseInlines((string) ($cell['text'] ?? ''));
                $attrs = [
                    'header' => $header,
                    'text' => $this->plainInlineText($inlines),
                ];
                foreach (['classes', 'attributes', 'align', 'colspan', 'rowspan'] as $name) {
                    if (isset($cell[$name]) && $cell[$name] !== [] && $cell[$name] !== '') {
                        $attrs[$name] = $cell[$name];
                    }
                }

                return new AstNode('table_cell', $attrs, [
                    new AstNode('paragraph', ['text' => $this->plainInlineText($inlines)], $inlines),
                ]);
            },
            $cells
        ));
    }

    private function tableRowColumnCount(AstNode $row): int
    {
        $count = 0;
        foreach ($row->children as $cell) {
            if ($cell->type === 'table_cell') {
                $count += max(1, (int) $cell->attr('colspan', 1));
            }
        }

        return $count;
    }

    /**
     * @param list<AstNode> $rows
     * @return list<string>
     */
    private function tableAlignments(array $rows, int $columnCount): array
    {
        $alignments = array_fill(0, $columnCount, 'default');
        foreach ($rows as $row) {
            $column = 0;
            foreach ($row->children as $cell) {
                if ($cell->type !== 'table_cell') {
                    continue;
                }
                $alignment = (string) $cell->attr('align', '');
                $span = max(1, (int) $cell->attr('colspan', 1));
                if (in_array($alignment, ['left', 'right', 'center'], true)) {
                    for ($index = $column; $index < min($column + $span, $columnCount); $index++) {
                        $alignments[$index] = $alignment;
                    }
                }
                $column += $span;
            }
        }

        return $alignments;
    }

    private function paragraphFromText(string $text): AstNode
    {
        $inlines = $this->parseInlines($text);

        return new AstNode('paragraph', ['text' => $this->plainInlineText($inlines)], $inlines);
    }

    /**
     * @return list<AstNode>
     */
    private function parseInlines(string $text): array
    {
        $nodes = [];
        $buffer = '';
        $length = strlen($text);
        for ($offset = 0; $offset < $length; ++$offset) {
            $tail = substr($text, $offset);

            if (str_starts_with($tail, '<!--')) {
                $end = strpos($text, '-->', $offset + 4);
                if ($end === false) {
                    break;
                }
                $nextOffset = $end + 3;
                if ($this->endsWithWhitespace($buffer)) {
                    while ($nextOffset < $length && ctype_space($text[$nextOffset])) {
                        ++$nextOffset;
                    }
                }
                $offset = $nextOffset - 1;
                continue;
            }

            if (preg_match('/^<nowiki\\b[^>]*>/iu', $tail, $match) === 1) {
                $start = $offset + strlen($match[0]);
                $closing = $this->findClosingTag($text, 'nowiki', $start);
                if ($closing !== null) {
                    $buffer .= html_entity_decode(substr($text, $start, $closing['start'] - $start), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $offset = $closing['end'] - 1;
                    continue;
                }
            }

            if (preg_match('/^<math\\b[^>]*>/iu', $tail, $match) === 1) {
                $start = $offset + strlen($match[0]);
                $closing = $this->findClosingTag($text, 'math', $start);
                if ($closing !== null) {
                    $this->flushText($nodes, $buffer);
                    $math = html_entity_decode(substr($text, $start, $closing['start'] - $start), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $nodes[] = new AstNode('math', ['text' => trim($math), 'display' => false]);
                    $offset = $closing['end'] - 1;
                    continue;
                }
            }

            if (preg_match('/^<ref\\b[^>]*\\/\\s*>/iu', $tail, $match) === 1) {
                $this->flushText($nodes, $buffer);
                $nodes[] = new AstNode('note');
                $offset += strlen($match[0]) - 1;
                continue;
            }

            if (preg_match('/^<ref\\b[^>]*>/iu', $tail, $match) === 1) {
                $start = $offset + strlen($match[0]);
                $closing = $this->findClosingTag($text, 'ref', $start);
                if ($closing !== null) {
                    $this->flushText($nodes, $buffer);
                    $content = trim(substr($text, $start, $closing['start'] - $start));
                    $nodes[] = new AstNode('note', [], $content === '' ? [] : [
                        new AstNode('plain', [], $this->parseInlines($content)),
                    ]);
                    $offset = $closing['end'] - 1;
                    continue;
                }
            }

            if (preg_match('/^<br\\b[^>]*\\/?>/iu', $tail, $match) === 1) {
                $this->flushText($nodes, $buffer);
                $nodes[] = new AstNode('linebreak');
                $offset += strlen($match[0]) - 1;
                continue;
            }

            $htmlInline = $this->parsePairedHtmlInlineAt($text, $offset, 'sup', 'superscript')
                ?? $this->parsePairedHtmlInlineAt($text, $offset, 'sub', 'subscript');
            if ($htmlInline instanceof AstNode) {
                $this->flushText($nodes, $buffer);
                $nodes[] = $this->withoutPrivateAttrs($htmlInline);
                $offset = (int) $htmlInline->attr('_endOffset');
                continue;
            }

            if (str_starts_with($tail, '[[')) {
                $end = strpos($text, ']]', $offset + 2);
                if ($end !== false) {
                    $this->flushText($nodes, $buffer);
                    $nodes[] = $this->parseInternalLink(substr($text, $offset + 2, $end - $offset - 2));
                    $offset = $end + 1;
                    continue;
                }
            }

            if (str_starts_with($tail, '[') && preg_match('/^\\[(https?:\\/\\/[^\\s\\]]+)(?:\\s+([^\\]]+))?\\]/u', $tail, $match) === 1) {
                $this->flushText($nodes, $buffer);
                $label = array_key_exists(2, $match) && trim((string) $match[2]) !== ''
                    ? trim((string) $match[2])
                    : (string) ++$this->externalLinkCounter;
                $nodes[] = new AstNode('link', ['url' => $match[1], 'title' => ''], $this->parseInlines($label));
                $offset += strlen($match[0]) - 1;
                continue;
            }

            $styled = $this->parseStyleAt($text, $offset);
            if ($styled instanceof AstNode) {
                $this->flushText($nodes, $buffer);
                $nodes[] = $this->withoutPrivateAttrs($styled);
                $offset = (int) $styled->attr('_endOffset');
                continue;
            }

            $template = $this->parseMediaWikiTemplateAt($text, $offset);
            if ($template !== null) {
                if ($template['literal']) {
                    $this->flushText($nodes, $buffer);
                    $nodes[] = new AstNode('text', ['text' => $template['source']]);
                } elseif ($buffer === '' && $nodes === []) {
                    $nextOffset = $template['end'];
                    while ($nextOffset < $length && ctype_space($text[$nextOffset])) {
                        ++$nextOffset;
                    }
                    $offset = $nextOffset - 1;
                    continue;
                } elseif ($this->endsWithWhitespace($buffer)) {
                    $nextOffset = $template['end'];
                    while ($nextOffset < $length && ctype_space($text[$nextOffset])) {
                        ++$nextOffset;
                    }
                    $offset = $nextOffset - 1;
                    continue;
                }

                $offset = $template['end'] - 1;
                continue;
            }

            if (preg_match('/^https?:\\/\\/[^\\s<]+/u', $tail, $match) === 1) {
                $url = rtrim($match[0], '.,;:)');
                $trailing = substr($match[0], strlen($url));
                $this->flushText($nodes, $buffer);
                $nodes[] = new AstNode('link', ['url' => $url, 'title' => ''], [new AstNode('text', ['text' => $url])]);
                $buffer .= $trailing;
                $offset += strlen($match[0]) - 1;
                continue;
            }

            if ($text[$offset] === '&' && preg_match('/^&([A-Za-z][A-Za-z0-9]+|#[0-9]+|#x[0-9A-Fa-f]+);/u', $tail, $match) === 1) {
                $buffer .= html_entity_decode($match[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $offset += strlen($match[0]) - 1;
                continue;
            }

            if (preg_match('/^<\\/?[A-Za-z][A-Za-z0-9:-]*(?:\\s+[^<>]*)?\\s*\\/?>/u', $tail, $match) === 1) {
                $this->flushText($nodes, $buffer);
                $nodes[] = new AstNode('raw_html_inline', ['format' => 'html', 'text' => $match[0]]);
                $offset += strlen($match[0]) - 1;
                continue;
            }

            $buffer .= $text[$offset];
        }
        $this->flushText($nodes, $buffer);

        return $nodes === [] ? [new AstNode('text', ['text' => ''])] : $nodes;
    }

    /**
     * @return array{end:int,literal:bool,source:string}|null
     */
    private function parseMediaWikiTemplateAt(string $text, int $offset): ?array
    {
        if (!str_starts_with(substr($text, $offset), '{{')) {
            return null;
        }

        $length = strlen($text);
        $stack = [str_starts_with(substr($text, $offset), '{{{') ? 3 : 2];
        $cursor = $offset + $stack[0];

        while ($cursor < $length) {
            if (str_starts_with(substr($text, $cursor), '{{{')) {
                $stack[] = 3;
                $cursor += 3;
                continue;
            }
            if (str_starts_with(substr($text, $cursor), '{{')) {
                $stack[] = 2;
                $cursor += 2;
                continue;
            }

            $closingLength = $stack[count($stack) - 1];
            if (substr($text, $cursor, $closingLength) === str_repeat('}', $closingLength)) {
                array_pop($stack);
                $cursor += $closingLength;
                if ($stack === []) {
                    $source = substr($text, $offset, $cursor - $offset);
                    $name = ltrim(substr($source, 2, -2));

                    return [
                        'end' => $cursor,
                        'literal' => str_starts_with($name, '#') || trim($name) === '!',
                        'source' => $source,
                    ];
                }
                continue;
            }

            ++$cursor;
        }

        return null;
    }

    private function endsWithWhitespace(string $text): bool
    {
        return $text !== '' && ctype_space($text[strlen($text) - 1]);
    }

    /**
     * @return array{start:int,end:int}|null
     */
    private function findClosingTag(string $text, string $tag, int $start): ?array
    {
        $tail = substr($text, $start);
        if (preg_match('/<\\/' . preg_quote($tag, '/') . '\\s*>/iu', $tail, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $closingStart = $start + (int) $match[0][1];

        return [
            'start' => $closingStart,
            'end' => $closingStart + strlen($match[0][0]),
        ];
    }

    private function parsePairedHtmlInlineAt(string $text, int $offset, string $tag, string $nodeType): ?AstNode
    {
        $tail = substr($text, $offset);
        if (preg_match('/^<' . preg_quote($tag, '/') . '\\b[^>]*>/iu', $tail, $match) !== 1) {
            return null;
        }

        $start = $offset + strlen($match[0]);
        $closing = $this->findClosingTag($text, $tag, $start);
        if ($closing === null) {
            return null;
        }

        return new AstNode($nodeType, ['_endOffset' => $closing['end'] - 1], $this->parseInlines(substr($text, $start, $closing['start'] - $start)));
    }

    /**
     * @return array<string, string>
     */
    private function parseHtmlAttributes(string $source): array
    {
        $attrs = [];
        if (preg_match_all("/([A-Za-z_:][A-Za-z0-9_.:-]*)\\s*=\\s*(?:\"([^\"]*)\"|'([^']*)'|([^\\s\"'>]+))/u", $source, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $value = $match[2] !== '' ? $match[2] : ($match[3] !== '' ? $match[3] : ($match[4] ?? ''));
                $attrs[(string) $match[1]] = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return $attrs;
    }

    private function parseInternalLink(string $source): AstNode
    {
        $parts = array_map('trim', explode('|', $source));
        $target = array_shift($parts) ?? '';
        $lowerTarget = strtolower($target);
        if (str_starts_with($lowerTarget, 'file:') || str_starts_with($lowerTarget, 'image:')) {
            $label = '';
            $alt = '';
            $attributes = [];
            foreach ($parts as $part) {
                $lower = strtolower($part);
                if (in_array($lower, ['thumb', 'thumbnail', 'frame', 'frameless'], true)) {
                    continue;
                }
                if (preg_match('/^(\\d+)\\s*px$/iu', $part, $match) === 1) {
                    $attributes['width'] = $match[1];
                    continue;
                }
                if (preg_match('/^alt\\s*=\\s*(.*)$/iu', $part, $match) === 1) {
                    $alt = trim($match[1]);
                    continue;
                }
                if (!str_contains($part, '=') && $label === '') {
                    $label = $part;
                }
            }
            $url = preg_replace('/^(?:file|image):/i', '', $target) ?? $target;
            if ($label === '') {
                $label = $alt;
            }
            $labelInlines = $this->parseInlines($label);

            return new AstNode('image', [
                'url' => trim($url),
                'title' => $this->plainInlineText($labelInlines),
                'alt' => $label,
                'attributes' => $attributes,
            ], $labelInlines);
        }

        $label = $parts[0] ?? str_replace('_', ' ', $target);
        $url = str_starts_with($target, '#') ? $target : str_replace(' ', '_', $target);
        $labelInlines = $this->parseInlines($label);

        return new AstNode('link', [
            'url' => $url,
            'title' => $this->plainInlineText($labelInlines),
            'classes' => ['wikilink'],
        ], $labelInlines);
    }

    private function parseStyleAt(string $text, int $offset): ?AstNode
    {
        foreach ([
            "'''''" => ['strong', 'emph'],
            "'''" => ['strong'],
            "''" => ['emph'],
            '<code>' => ['code'],
        ] as $marker => $types) {
            if (!str_starts_with(substr($text, $offset), $marker)) {
                continue;
            }
            $closing = $marker === '<code>' ? '</code>' : $marker;
            $start = $offset + strlen($marker);
            $end = $marker === '<code>'
                ? strpos($text, $closing, $start)
                : $this->findApostropheStyleClosing($text, $closing, $start);
            if ($end === false) {
                continue;
            }
            $inner = substr($text, $start, $end - $start);
            $node = in_array('code', $types, true)
                ? new AstNode('code', ['text' => $inner, '_endOffset' => $end + strlen($closing) - 1])
                : $this->wrapStyles($this->parseInlines($inner), $types, $end + strlen($closing) - 1);

            return $node;
        }

        return null;
    }

    private function findApostropheStyleClosing(string $text, string $marker, int $start): int|false
    {
        $position = $start;
        $markerLength = strlen($marker);
        while (($position = strpos($text, $marker, $position)) !== false) {
            $before = $position > 0 ? $text[$position - 1] : '';
            $after = $text[$position + $markerLength] ?? '';
            if ($before !== "'" && $after !== "'") {
                return $position;
            }
            ++$position;
        }

        return false;
    }

    /**
     * @param list<AstNode> $children
     * @param list<string> $types
     */
    private function wrapStyles(array $children, array $types, int $endOffset): AstNode
    {
        $type = array_shift($types) ?? 'span';
        $node = $types === []
            ? new AstNode($type, ['_endOffset' => $endOffset], $children)
            : new AstNode($type, ['_endOffset' => $endOffset], [$this->wrapStyles($children, $types, $endOffset)]);

        return $node;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function flushText(array &$nodes, string &$buffer): void
    {
        if ($buffer === '') {
            return;
        }
        $nodes[] = new AstNode('text', ['text' => $buffer]);
        $buffer = '';
    }

    private function withoutPrivateAttrs(AstNode $node): AstNode
    {
        $attrs = [];
        foreach ($node->attrs as $key => $value) {
            if (!str_starts_with((string) $key, '_')) {
                $attrs[$key] = $value;
            }
        }

        return new AstNode($node->type, $attrs, array_map(
            fn (AstNode $child): AstNode => $this->withoutPrivateAttrs($child),
            $node->children
        ));
    }

    /**
     * @return list<AstNode>
     */
    private function textInlines(string $text): array
    {
        return $text === '' ? [] : [new AstNode('text', ['text' => $text])];
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if (in_array($node->type, ['text', 'code', 'raw_inline'], true)) {
                $text .= (string) $node->attr('text', '');
            } elseif ($node->type === 'math') {
                $text .= (string) $node->attr('text', '');
            } elseif ($node->type === 'linebreak' || $node->type === 'softbreak') {
                $text .= ' ';
            } elseif ($node->type === 'image') {
                $text .= (string) $node->attr('alt', $this->plainInlineText($node->children));
            } else {
                $text .= $this->plainInlineText($node->children);
            }
        }

        return $text;
    }
}
