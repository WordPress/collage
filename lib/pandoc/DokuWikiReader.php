<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DokuWikiReader
{
    /** @var list<string> */
    private array $lines = [];

    private int $index = 0;

    public function read(string $text): AstNode
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $this->lines = explode("\n", $normalized);
        $this->index = 0;

        return new AstNode('document', [
            'sourceFormat' => 'dokuwiki',
            'dokuwiki' => [
                'reader' => self::class,
                'readerScope' => 'bounded-pandoc-dokuwiki-reader-core-block-inline-semantics',
                'sourceBytes' => strlen($text),
                'upstreamEvidence' => [
                    'source' => 'Pandoc Text.Pandoc.Readers.DokuWiki executable native output probes',
                    'readerUnitGroups' => [
                        'headings',
                        'paragraphs',
                        'inline styles',
                        'links and images',
                        'lists',
                        'tables',
                        'code blocks',
                    ],
                    'fixtureStatus' => 'Bounded reader tests mirror current pandoc -f dokuwiki -t native shapes for core syntax.',
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
            if ($trimmed === '') {
                ++$this->index;
                continue;
            }

            if ($this->isCodeBlockStart($trimmed)) {
                $blocks[] = $this->parseCodeBlock($trimmed);
                continue;
            }
            if (preg_match('/^(={2,6})\\s*(.*?)\\s*\\1\\s*$/u', $trimmed, $match) === 1) {
                ++$this->index;
                $level = 7 - strlen($match[1]);
                $inlines = $this->parseInlines($match[2]);
                $blocks[] = new AstNode('heading', [
                    'level' => $level,
                    'text' => $this->plainInlineText($inlines),
                ], $inlines);
                continue;
            }
            if ($this->isTableLine($trimmed)) {
                $blocks[] = $this->parseTable();
                continue;
            }
            if ($this->isListLine($line)) {
                $blocks[] = $this->parseListBlock();
                continue;
            }

            $blocks[] = $this->parseParagraph();
        }

        return $blocks;
    }

    private function parseParagraph(): AstNode
    {
        $parts = [];
        while ($this->index < count($this->lines)) {
            $line = rtrim($this->lines[$this->index], "\n");
            $trimmed = trim($line);
            if ($trimmed === '') {
                break;
            }
            if ($parts !== [] && $this->isBlockStart($line)) {
                break;
            }
            $parts[] = $trimmed;
            ++$this->index;
        }

        $text = implode(' ', $parts);
        $inlines = $this->parseInlines($text);

        return new AstNode('paragraph', ['text' => $this->plainInlineText($inlines)], $inlines);
    }

    private function isBlockStart(string $line): bool
    {
        $trimmed = trim($line);

        return $this->isCodeBlockStart($trimmed)
            || preg_match('/^(={2,6})\\s*(.*?)\\s*\\1\\s*$/u', $trimmed) === 1
            || $this->isTableLine($trimmed)
            || $this->isListLine($line);
    }

    private function isCodeBlockStart(string $trimmed): bool
    {
        return preg_match('/^<(code|file)(?:\\s+[^>]*)?>$/u', $trimmed) === 1;
    }

    private function parseCodeBlock(string $trimmed): AstNode
    {
        preg_match('/^<(code|file)(?:\\s+([^>]*))?>$/u', $trimmed, $match);
        $tag = (string) ($match[1] ?? 'code');
        $info = trim((string) ($match[2] ?? ''));
        $classes = [];
        if ($info !== '') {
            $classes[] = preg_split('/\\s+/', $info)[0] ?? $info;
        }
        ++$this->index;

        $body = [];
        $terminator = '</' . $tag . '>';
        while ($this->index < count($this->lines)) {
            $line = rtrim($this->lines[$this->index], "\n");
            if (trim($line) === $terminator) {
                ++$this->index;
                break;
            }
            $body[] = $line;
            ++$this->index;
        }

        return new AstNode('code_block', [
            'text' => $body === [] ? '' : implode("\n", $body) . "\n",
            'classes' => $classes,
        ]);
    }

    private function isListLine(string $line): bool
    {
        return preg_match('/^(\\s{2,})([*-])\\s+(.*)$/u', $line) === 1;
    }

    private function parseListBlock(): AstNode
    {
        $entries = [];
        $firstTopLevelStyle = null;
        while ($this->index < count($this->lines)) {
            $line = rtrim($this->lines[$this->index], "\n");
            if (preg_match('/^(\\s{2,})([*-])\\s+(.*)$/u', $line, $match) !== 1) {
                break;
            }
            $level = max(1, intdiv(strlen($match[1]), 2));
            $style = $match[2] === '-' ? 'ordered' : 'bullet';
            if ($level === 1 && $firstTopLevelStyle !== null && $style !== $firstTopLevelStyle) {
                break;
            }
            $firstTopLevelStyle ??= $style;
            $entries[] = [
                'level' => $level,
                'style' => $style,
                'text' => rtrim($match[3]),
            ];
            ++$this->index;
        }

        $cursor = 0;
        $style = $entries[0]['style'] ?? 'bullet';

        return $this->buildListAt($entries, $cursor, 1, $style);
    }

    /**
     * @param list<array{level:int, style:string, text:string}> $entries
     */
    private function buildListAt(array $entries, int &$cursor, int $level, string $style): AstNode
    {
        $items = [];
        while ($cursor < count($entries)) {
            $entry = $entries[$cursor];
            if ($entry['level'] < $level) {
                break;
            }
            if ($entry['level'] > $level) {
                if ($items === []) {
                    ++$cursor;
                    continue;
                }
                $last = array_pop($items);
                $nestedStyle = $entry['style'];
                $items[] = new AstNode('list_item', ['loose' => false], array_merge(
                    $last->children,
                    [$this->buildListAt($entries, $cursor, $level + 1, $nestedStyle)]
                ));
                continue;
            }
            if ($entry['style'] !== $style) {
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

    private function isTableLine(string $trimmed): bool
    {
        return str_starts_with($trimmed, '|') || str_starts_with($trimmed, '^');
    }

    private function parseTable(): AstNode
    {
        $rows = [];
        while ($this->index < count($this->lines)) {
            $line = trim($this->lines[$this->index]);
            if (!$this->isTableLine($line)) {
                break;
            }
            $rows[] = $this->parseTableRow($line);
            ++$this->index;
        }

        $header = [];
        if ($rows !== [] && $this->rowIsAllHeader($rows[0])) {
            $header = array_shift($rows);
        }
        $columnCount = max(count($header), ...array_map('count', $rows ?: [[]]));

        return new AstNode('table', [
            'caption' => '',
            'alignments' => array_fill(0, $columnCount, 'default'),
            'widths' => array_fill(0, $columnCount, null),
            'nativeColumnCount' => $columnCount,
        ], [
            new AstNode('table_head', [], $header === [] ? [] : [$this->tableRow($header, true)]),
            new AstNode('table_body', [], array_map(fn (array $row): AstNode => $this->tableRow($row, false), $rows)),
            new AstNode('table_foot'),
        ]);
    }

    /**
     * @return list<array{header:bool, text:string}>
     */
    private function parseTableRow(string $line): array
    {
        $cells = [];
        $length = strlen($line);
        $offset = 0;
        while ($offset < $length) {
            $separator = $line[$offset] ?? '';
            if ($separator !== '|' && $separator !== '^') {
                break;
            }
            ++$offset;
            $nextPipe = strpos($line, '|', $offset);
            $nextCaret = strpos($line, '^', $offset);
            $candidates = array_values(array_filter([$nextPipe, $nextCaret], static fn (int|false $value): bool => $value !== false));
            $next = $candidates === [] ? $length : min($candidates);
            $text = trim(substr($line, $offset, $next - $offset));
            if ($text !== '' || $next < $length) {
                $cells[] = ['header' => $separator === '^', 'text' => $text];
            }
            $offset = $next;
        }

        while ($cells !== [] && $cells[count($cells) - 1]['text'] === '') {
            array_pop($cells);
        }

        return $cells;
    }

    /**
     * @param list<array{header:bool, text:string}> $row
     */
    private function rowIsAllHeader(array $row): bool
    {
        return $row !== [] && count(array_filter($row, static fn (array $cell): bool => !$cell['header'])) === 0;
    }

    /**
     * @param list<array{header:bool, text:string}> $row
     */
    private function tableRow(array $row, bool $header): AstNode
    {
        return new AstNode('table_row', ['header' => $header], array_map(
            fn (array $cell): AstNode => new AstNode('table_cell', ['header' => $header, 'text' => $cell['text']], [
                new AstNode('plain', [], $this->parseInlines($cell['text'])),
            ]),
            $row
        ));
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

            if (str_starts_with($tail, '[[')) {
                $end = strpos($text, ']]', $offset + 2);
                if ($end !== false) {
                    $this->flushText($nodes, $buffer);
                    $nodes[] = $this->parseLink(substr($text, $offset + 2, $end - $offset - 2));
                    $offset = $end + 1;
                    continue;
                }
            }

            if (str_starts_with($tail, '{{')) {
                $end = strpos($text, '}}', $offset + 2);
                if ($end !== false) {
                    $this->flushText($nodes, $buffer);
                    $nodes[] = $this->parseImage(substr($text, $offset + 2, $end - $offset - 2));
                    $offset = $end + 1;
                    continue;
                }
            }

            $styled = $this->parseDelimitedStyle($text, $offset);
            if ($styled instanceof AstNode) {
                $this->flushText($nodes, $buffer);
                $nodes[] = new AstNode($styled->type, $this->withoutPrivateAttrs($styled->attrs), $styled->children);
                $offset = (int) $styled->attr('_endOffset');
                continue;
            }

            if ($text[$offset] === '&' && preg_match('/^&([A-Za-z][A-Za-z0-9]+|#[0-9]+|#x[0-9A-Fa-f]+);/u', $tail, $match) === 1) {
                $buffer .= html_entity_decode($match[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $offset += strlen($match[0]) - 1;
                continue;
            }

            $buffer .= $text[$offset];
        }
        $this->flushText($nodes, $buffer);

        return $nodes;
    }

    private function parseDelimitedStyle(string $text, int $offset): ?AstNode
    {
        foreach ([
            '**' => 'strong',
            '//' => 'emph',
            '__' => 'underline',
            "''" => 'code',
        ] as $marker => $type) {
            if (!str_starts_with(substr($text, $offset), $marker)) {
                continue;
            }
            $end = strpos($text, $marker, $offset + strlen($marker));
            if ($end === false) {
                continue;
            }
            $inner = substr($text, $offset + strlen($marker), $end - $offset - strlen($marker));
            if ($type === 'code') {
                return new AstNode('code', [
                    'text' => $this->plainInlineText($this->parseInlines($inner)),
                    '_endOffset' => $end + strlen($marker) - 1,
                ]);
            }

            return new AstNode($type, [
                '_endOffset' => $end + strlen($marker) - 1,
            ], $this->parseInlines($inner));
        }

        return null;
    }

    private function parseLink(string $contents): AstNode
    {
        [$target, $label] = array_pad(explode('|', $contents, 2), 2, '');
        $target = trim($target);
        $label = trim($label);
        if ($label === '') {
            $label = $target;
        }

        return new AstNode('link', [
            'url' => $target,
            'title' => '',
        ], $this->parseInlines($label));
    }

    private function parseImage(string $contents): AstNode
    {
        [$target, $alt] = array_pad(explode('|', $contents, 2), 2, '');
        $target = trim($target);
        $alt = trim($alt);
        $attributes = [];
        $query = '';
        if (preg_match('/^(.*?)(\\?[0-9]+x[0-9]+)$/u', $target, $match) === 1) {
            $target = $match[1];
            $query = $match[2];
            [$width, $height] = array_pad(explode('x', substr($query, 1), 2), 2, '');
            if ($width !== '') {
                $attributes['width'] = $width;
            }
            if ($height !== '') {
                $attributes['height'] = $height;
            }
            $attributes['query'] = $query;
        }

        return new AstNode('image', [
            'url' => $target,
            'src' => $target,
            'alt' => $alt,
            'title' => '',
            'attributes' => $attributes,
        ], $alt === '' ? [] : $this->parseInlines($alt));
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

    /**
     * @param array<string, mixed> $attrs
     * @return array<string, mixed>
     */
    private function withoutPrivateAttrs(array $attrs): array
    {
        unset($attrs['_endOffset']);

        return $attrs;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainInlineText(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            if ($node->type === 'text' || $node->type === 'code') {
                $text .= (string) $node->attr('text', '');
                continue;
            }
            if (in_array($node->type, ['space', 'softbreak', 'linebreak'], true)) {
                $text .= ' ';
                continue;
            }
            $text .= $this->plainInlineText($node->children);
        }

        return $text;
    }
}
