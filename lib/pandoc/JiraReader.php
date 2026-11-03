<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class JiraReader
{
    private const MAX_INLINE_NESTING = 128;

    /** @var list<string> */
    private array $lines = [];

    private int $index = 0;

    private int $inlineNesting = 0;

    public function read(string $text): AstNode
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $this->lines = explode("\n", $normalized);
        $this->index = 0;
        $this->inlineNesting = 0;

        return new AstNode('document', [
            'sourceFormat' => 'jira',
            'jira' => [
                'reader' => self::class,
                'readerScope' => 'pinned-pandoc-jira-reader-unit-semantics',
                'sourceBytes' => strlen($text),
                'upstreamEvidence' => [
                    'source' => 'Pandoc 912bfa5e Text.Pandoc.Readers.Jira and test/Tests/Readers/Jira.hs',
                    'readerUnitGroups' => [
                        'para',
                        'header',
                        'list',
                        'block quote',
                        'table',
                        'panel',
                        'inlines',
                    ],
                    'fixtureStatus' => 'jira-reader.jira/native remains a larger partial-parity fixture',
                ],
            ],
        ], $this->parseBlocks());
    }

    /**
     * @return list<AstNode>
     */
    private function parseBlocks(?string $terminator = null): array
    {
        $blocks = [];
        $count = count($this->lines);
        while ($this->index < $count) {
            $line = rtrim($this->lines[$this->index], "\n");
            $trimmed = trim($line);
            if ($terminator !== null && $trimmed === $terminator) {
                $this->index++;
                break;
            }
            if ($trimmed === '') {
                $this->index++;
                continue;
            }

            if ($this->isCodeStart($trimmed)) {
                $blocks[] = $this->parseCodeBlock($trimmed);
                continue;
            }
            if ($trimmed === '{quote}') {
                $this->index++;
                $blocks[] = new AstNode('blockquote', [], $this->parseBlocks('{quote}'));
                continue;
            }
            if ($this->isPanelStart($trimmed)) {
                $blocks[] = $this->parsePanel($trimmed);
                continue;
            }
            if ($this->isColorBlockStart($trimmed)) {
                $blocks[] = $this->parseColorBlock($trimmed);
                continue;
            }
            if (preg_match('/^bq\\.\\s*(.*)$/u', $trimmed, $match) === 1) {
                $this->index++;
                $blocks[] = new AstNode('blockquote', [], [$this->paragraphFromText($match[1])]);
                continue;
            }
            if (preg_match('/^h([1-6])\\.\\s*(.*)$/u', $trimmed, $match) === 1) {
                $this->index++;
                $blocks[] = new AstNode('heading', ['level' => (int) $match[1]], $this->parseInlines($match[2]));
                continue;
            }
            if ($trimmed === '----') {
                $this->index++;
                $blocks[] = new AstNode('horizontal_rule');
                continue;
            }
            if (str_starts_with($trimmed, '|')) {
                $blocks[] = $this->parseTable();
                continue;
            }
            if ($this->isListLine($trimmed)) {
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
        $count = count($this->lines);
        while ($this->index < $count) {
            $line = rtrim($this->lines[$this->index], "\n");
            $trimmed = trim($line);
            if ($trimmed === '') {
                break;
            }
            if ($parts !== [] && $this->isBlockStart($trimmed)) {
                break;
            }
            $parts[] = $trimmed;
            $this->index++;
        }

        return $this->paragraphFromText(implode("\n", $parts));
    }

    private function isBlockStart(string $trimmed): bool
    {
        return $this->isCodeStart($trimmed)
            || $trimmed === '{quote}'
            || $this->isPanelStart($trimmed)
            || $this->isColorBlockStart($trimmed)
            || preg_match('/^bq\\.\\s*/u', $trimmed) === 1
            || preg_match('/^h[1-6]\\.\\s*/u', $trimmed) === 1
            || $trimmed === '----'
            || str_starts_with($trimmed, '|')
            || $this->isListLine($trimmed);
    }

    private function isCodeStart(string $trimmed): bool
    {
        return preg_match('/^\\{code(?::[^}]*)?\\}$/u', $trimmed) === 1
            || preg_match('/^\\{noformat(?:[:}].*)?\\}$/u', $trimmed) === 1;
    }

    private function parseCodeBlock(string $trimmed): AstNode
    {
        $language = '';
        $end = '{code}';
        if (preg_match('/^\\{code:([^}]*)\\}$/u', $trimmed, $match) === 1) {
            $language = trim($match[1]);
        } elseif (str_starts_with($trimmed, '{noformat')) {
            $end = '{noformat}';
        }
        $this->index++;

        $body = [];
        while ($this->index < count($this->lines)) {
            $line = rtrim($this->lines[$this->index], "\n");
            if (trim($line) === $end) {
                $this->index++;
                break;
            }
            $body[] = $line;
            $this->index++;
        }

        return new AstNode('code_block', [
            'text' => implode("\n", $body),
            'classes' => $language === '' ? [] : [$language],
        ]);
    }

    private function isPanelStart(string $trimmed): bool
    {
        return preg_match('/^\\{panel(?:[:}].*)?\\}$/u', $trimmed) === 1;
    }

    private function parsePanel(string $trimmed): AstNode
    {
        $params = $this->parametersInside($trimmed, 'panel');
        $this->index++;
        $children = $this->parseBlocks('{panel}');
        $attributes = $params;
        $panelChildren = $children;
        if (isset($attributes['title'])) {
            $title = $attributes['title'];
            unset($attributes['title']);
            array_unshift($panelChildren, new AstNode('div', ['classes' => ['panelheader']], [
                new AstNode('plain', [], [new AstNode('strong', [], $this->textInlines($title))]),
            ]));
        }

        return new AstNode('div', [
            'classes' => ['panel'],
            'attributes' => $attributes,
        ], $panelChildren);
    }

    private function isColorBlockStart(string $trimmed): bool
    {
        return preg_match('/^\\{color:([^}]*)\\}$/u', $trimmed) === 1;
    }

    private function parseColorBlock(string $trimmed): AstNode
    {
        preg_match('/^\\{color:([^}]*)\\}$/u', $trimmed, $match);
        $color = trim($match[1] ?? '');
        $this->index++;

        return new AstNode('div', [
            'attributes' => ['color' => $color],
        ], $this->parseBlocks('{color}'));
    }

    /**
     * @return array<string, string>
     */
    private function parametersInside(string $trimmed, string $name): array
    {
        if (preg_match('/^\\{' . preg_quote($name, '/') . ':([^}]*)\\}$/u', $trimmed, $match) !== 1) {
            return [];
        }

        $params = [];
        foreach (explode('|', $match[1]) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $params[trim($key)] = trim($value);
        }

        return $params;
    }

    private function isListLine(string $trimmed): bool
    {
        return preg_match('/^(?:[*#]+|-)(?:\\s+|$)/u', $trimmed) === 1;
    }

    private function parseListBlock(): AstNode
    {
        $entries = [];
        while ($this->index < count($this->lines)) {
            $line = trim($this->lines[$this->index]);
            if (preg_match('/^([*#]+|-)\\s*(.*)$/u', $line, $match) !== 1) {
                break;
            }
            $marker = $match[1] === '-' ? '*' : $match[1];
            $text = rtrim($match[2]);
            $this->index++;
            while ($this->index < count($this->lines)) {
                $continuation = trim($this->lines[$this->index]);
                if ($continuation === '' || $this->isBlockStart($continuation)) {
                    break;
                }
                $text .= "\n" . $continuation;
                $this->index++;
            }
            $entries[] = ['marker' => $marker, 'text' => $text];
        }

        $cursor = 0;
        $firstMarker = $entries[0]['marker'] ?? '*';

        return $this->buildListAt($entries, $cursor, 1, $firstMarker[0] ?? '*');
    }

    /**
     * @param list<array{marker:string, text:string}> $entries
     */
    private function buildListAt(array $entries, int &$cursor, int $level, string $style): AstNode
    {
        $items = [];
        while ($cursor < count($entries)) {
            $entry = $entries[$cursor];
            $marker = $entry['marker'];
            $depth = strlen($marker);
            if ($depth < $level) {
                break;
            }
            if ($depth > $level) {
                if ($items === []) {
                    $cursor++;
                    continue;
                }
                $nestedStyle = $marker[$level] ?? $marker[$depth - 1];
                $last = array_pop($items);
                $items[] = new AstNode('list_item', [], array_merge(
                    $last->children,
                    [$this->buildListAt($entries, $cursor, $level + 1, $nestedStyle)]
                ));
                continue;
            }
            if (($marker[$level - 1] ?? '*') !== $style) {
                break;
            }

            $items[] = new AstNode('list_item', [], [$this->paragraphFromText($entry['text'])]);
            $cursor++;
        }

        return new AstNode($style === '#' ? 'ordered_list' : 'bullet_list', [
            'start' => 1,
            'style' => 'default',
            'delimiter' => 'default',
        ], $items);
    }

    private function parseTable(): AstNode
    {
        $rows = [];
        while ($this->index < count($this->lines)) {
            $line = trim($this->lines[$this->index]);
            if (!str_starts_with($line, '|')) {
                break;
            }
            $rows[] = $this->parseTableRow($line);
            $this->index++;
        }

        $header = [];
        if ($rows !== [] && $this->rowIsAllHeader($rows[0])) {
            $header = array_shift($rows);
        }
        $columnCount = max(count($header), ...array_map('count', $rows ?: [[]]));

        return new AstNode('table', [
            'caption' => '',
            'alignments' => array_fill(0, $columnCount, 'default'),
        ], [
            new AstNode('table_head', [], $header === [] ? [] : [$this->tableRow($header, true)]),
            new AstNode('table_body', [], array_map(fn (array $row): AstNode => $this->tableRow($row, false), $rows)),
        ]);
    }

    /**
     * @return list<array{header:bool, text:string}>
     */
    private function parseTableRow(string $line): array
    {
        $cells = [];
        $offset = 0;
        $length = strlen($line);
        while ($offset < $length && $line[$offset] === '|') {
            $header = substr($line, $offset, 2) === '||';
            $offset += $header ? 2 : 1;
            $nextDouble = strpos($line, '||', $offset);
            $nextSingle = strpos($line, '|', $offset);
            if ($nextSingle === false) {
                $next = $length;
            } elseif ($nextDouble !== false && $nextDouble === $nextSingle) {
                $next = $nextDouble;
            } else {
                $next = $nextSingle;
            }
            $text = trim(substr($line, $offset, $next - $offset));
            if ($text !== '' || $next < $length) {
                $cells[] = ['header' => $header, 'text' => $text];
            }
            $offset = $next;
            if ($offset >= $length) {
                break;
            }
        }

        return array_values(array_filter(
            $cells,
            static fn (array $cell): bool => $cell['text'] !== ''
        ));
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
                $this->paragraphFromText($cell['text']),
            ]),
            $row
        ));
    }

    private function paragraphFromText(string $text): AstNode
    {
        $parts = explode("\n", $text);
        $children = [];
        foreach ($parts as $index => $part) {
            if ($index > 0) {
                $children[] = new AstNode('linebreak');
            }
            array_push($children, ...$this->parseInlines($part));
        }

        return new AstNode('paragraph', ['text' => str_replace("\n", ' ', $text)], $children);
    }

    /**
     * @return list<AstNode>
     */
    private function parseInlines(string $text, bool $allowAutolinks = true): array
    {
        if ($this->inlineNesting >= self::MAX_INLINE_NESTING) {
            return $text === '' ? [] : [new AstNode('text', ['text' => $text])];
        }

        ++$this->inlineNesting;
        try {
            return $this->parseInlinesWithSpanIndex($text, $allowAutolinks, new JiraInlineSpanIndex($text));
        } finally {
            --$this->inlineNesting;
        }
    }

    /**
     * @return list<AstNode>
     */
    private function parseInlinesWithSpanIndex(string $text, bool $allowAutolinks, JiraInlineSpanIndex $spans): array
    {
        $nodes = [];
        $buffer = '';
        /** @var array<string, array{start:int,end:int,result:bool}> $linkTargetSafety */
        $linkTargetSafety = [];
        $length = strlen($text);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $text[$offset];

            $anchorEnd = $char === '{' && substr_compare($text, '{anchor:', $offset, strlen('{anchor:')) === 0 && $spans->hasValidUtf8From($offset)
                ? $spans->anchorEnd($offset)
                : null;
            if ($anchorEnd !== null) {
                $this->flushText($nodes, $buffer);
                $nodes[] = new AstNode('span', ['id' => substr($text, $offset + strlen('{anchor:'), $anchorEnd - $offset - strlen('{anchor:'))], []);
                $offset = $anchorEnd;
                continue;
            }

            $colorSpan = $char === '{' && substr_compare($text, '{color:', $offset, strlen('{color:')) === 0 && $spans->hasValidUtf8From($offset)
                ? $spans->colorSpan($offset)
                : null;
            if ($colorSpan !== null) {
                $this->flushText($nodes, $buffer);
                $innerStart = $colorSpan['headerEnd'] + 1;
                $nodes[] = new AstNode('span', [
                    'attributes' => ['color' => substr($text, $offset + strlen('{color:'), $colorSpan['headerEnd'] - $offset - strlen('{color:'))],
                ], $this->parseInlines(substr($text, $innerStart, $colorSpan['end'] - $innerStart)));
                $offset = $colorSpan['end'] + strlen('{color}') - 1;
                continue;
            }

            $codeEnd = $char === '{' && ($text[$offset + 1] ?? '') === '{'
                ? $spans->codeEnd($offset)
                : null;
            if ($codeEnd !== null) {
                $this->flushText($nodes, $buffer);
                $inner = $this->decodeEntities(substr($text, $offset + 2, $codeEnd - $offset - 2));
                $nodes[] = new AstNode('code', ['text' => $this->stringifyInlines($this->parseInlines($inner))]);
                $offset = $codeEnd + 1;
                continue;
            }

            $bracketEnd = $char === '[' ? $spans->bracketEnd($offset) : null;
            if ($bracketEnd !== null && $this->linkRangeCanProduceNode($text, $offset + 1, $bracketEnd, $spans, $linkTargetSafety)) {
                $link = $this->parseLink(substr($text, $offset + 1, $bracketEnd - $offset - 1));
                if ($link instanceof AstNode) {
                    $this->flushText($nodes, $buffer);
                    $nodes[] = $link;
                    $offset = $bracketEnd;
                    continue;
                }
            }

            $imageEnd = $char === '!' ? $spans->simplePairEnd('!', $offset) : null;
            if ($imageEnd !== null) {
                $image = $this->parseImage(substr($text, $offset + 1, $imageEnd - $offset - 1));
                if ($image instanceof AstNode) {
                    $this->flushText($nodes, $buffer);
                    $nodes[] = $image;
                    $offset = $imageEnd;
                    continue;
                }
            }

            if (
                $allowAutolinks
                && (($char >= 'A' && $char <= 'Z') || ($char >= 'a' && $char <= 'z'))
            ) {
                $autolink = $this->parseAutolink($text, $offset);
                if ($autolink instanceof AstNode) {
                    $this->flushText($nodes, $buffer);
                    $nodes[] = $autolink;
                    $offset = (int) $autolink->attr('_endOffset');
                    $nodes[count($nodes) - 1] = new AstNode($autolink->type, $this->withoutPrivateAttrs($autolink->attrs), $autolink->children);
                    continue;
                }
            }

            $quoteEnd = $char === '?' && ($text[$offset + 1] ?? '') === '?'
                ? $spans->simplePairEnd('??', $offset)
                : null;
            if ($quoteEnd !== null) {
                $this->flushText($nodes, $buffer);
                $nodes[] = new AstNode('text', ['text' => "\u{2014}"]);
                $nodes[] = new AstNode('text', ['text' => ' ']);
                $nodes[] = new AstNode('emph', [], $this->parseInlines(substr($text, $offset + 2, $quoteEnd - $offset - 2)));
                $offset = $quoteEnd + 1;
                continue;
            }

            $braceStyle = $this->parseBraceStyle($text, $offset, $spans);
            if ($braceStyle instanceof AstNode) {
                $this->flushText($nodes, $buffer);
                $nodes[] = $braceStyle;
                $offset = (int) $braceStyle->attr('_endOffset');
                $nodes[count($nodes) - 1] = new AstNode($braceStyle->type, $this->withoutPrivateAttrs($braceStyle->attrs), $braceStyle->children);
                continue;
            }

            $styled = $this->parseDelimitedStyle($text, $offset, $spans);
            if ($styled instanceof AstNode) {
                $this->flushText($nodes, $buffer);
                $nodes[] = $styled;
                $offset = (int) $styled->attr('_endOffset');
                $nodes[count($nodes) - 1] = new AstNode($styled->type, $this->withoutPrivateAttrs($styled->attrs), $styled->children);
                continue;
            }

            if ($char === '&' && preg_match('/\\G&([A-Za-z][A-Za-z0-9]+|#[0-9]+|#x[0-9A-Fa-f]+);/u', $text, $match, 0, $offset) === 1) {
                $buffer .= $this->decodeEntities($match[0]);
                $offset += strlen($match[0]) - 1;
                continue;
            }

            $buffer .= $text[$offset];
        }
        $this->flushText($nodes, $buffer);

        return $nodes;
    }

    private function parseBraceStyle(string $text, int $offset, JiraInlineSpanIndex $spans): ?AstNode
    {
        foreach ([
            '{^}' => 'superscript',
            '{~}' => 'subscript',
        ] as $marker => $type) {
            if (substr_compare($text, $marker, $offset, strlen($marker)) !== 0) {
                continue;
            }
            $end = $spans->simplePairEnd($marker, $offset);
            if ($end === null) {
                continue;
            }

            return new AstNode($type, ['_endOffset' => $end + strlen($marker) - 1], $this->parseInlines(substr($text, $offset + strlen($marker), $end - $offset - strlen($marker))));
        }

        return null;
    }

    /**
     * Check the target-bearing portion of a bracket candidate before copying
     * its full label. Nested malformed brackets can share one far-away close;
     * validating the common target once keeps those failed candidates from
     * repeatedly materializing ever-larger substrings.
     *
     * @param array<string, array{start:int,end:int,result:bool}> $targetSafety
     */
    private function linkRangeCanProduceNode(
        string $text,
        int $start,
        int $end,
        JiraInlineSpanIndex $spans,
        array &$targetSafety
    ): bool {
        if ($start >= $end) {
            return false;
        }

        $first = $text[$start] ?? '';
        if ($first === '^') {
            return $this->cachedLinkTargetSafety(
                $text,
                'attachment',
                $start + 1,
                $end,
                $targetSafety,
                fn (string $target): bool => $this->isJiraSafeAttachmentTarget($target)
            );
        }

        // This is intentionally before pipe parsing: the established Jira
        // grammar permits pipes in the label of [label|part^attachment].
        $caret = $spans->attachmentCaretBefore($end);
        if ($caret !== null && $caret > $start && $spans->hasValidUtf8Range($start, $end)) {
            return $this->cachedLinkTargetSafety(
                $text,
                'labelled-attachment',
                $caret + 1,
                $end,
                $targetSafety,
                fn (string $target): bool => $this->isJiraSafeAttachmentTarget($target)
            );
        }

        $firstPipe = $spans->linkPipe($start, 1);
        if ($firstPipe !== null && $firstPipe < $end) {
            $secondPipe = $spans->linkPipe($firstPipe + 1, 2);
            if ($secondPipe === null || $secondPipe >= $end) {
                return $this->cachedLinkTargetSafety(
                    $text,
                    'pipe',
                    $firstPipe + 1,
                    $end,
                    $targetSafety,
                    fn (string $target): bool => $this->isJiraSafeUserAccountTarget($target)
                        || $this->isJiraSafeMailtoTarget($target)
                        || $this->isJiraSafeFragmentTarget($target)
                        || $this->isJiraSafeExternalTarget($target)
                );
            }

            $thirdPipe = $spans->linkPipe($secondPipe + 1, 3);
            if ($thirdPipe !== null && $thirdPipe < $end) {
                return false;
            }

            if (!$this->cachedLinkTargetSafety(
                $text,
                'smart-type',
                $secondPipe + 1,
                $end,
                $targetSafety,
                static fn (string $type): bool => in_array($type, ['smart-link', 'smart-card'], true)
            )) {
                return false;
            }

            return $this->cachedLinkTargetSafety(
                $text,
                'smart-target',
                $firstPipe + 1,
                $secondPipe,
                $targetSafety,
                fn (string $target): bool => $this->isJiraSafeExternalTarget($target)
            );
        }

        if ($first !== '~' && $first !== '#' && !ctype_alpha($first)) {
            return false;
        }

        return $this->cachedLinkTargetSafety(
            $text,
            'direct',
            $start,
            $end,
            $targetSafety,
            fn (string $target): bool => $this->isJiraSafeUserAccountTarget($target)
                || $this->isJiraSafeMailtoTarget($target)
                || $this->isJiraSafeFragmentTarget($target)
                || $this->isJiraSafeExternalTarget($target)
        );
    }

    /**
     * @param array<string, array{start:int,end:int,result:bool}> $targetSafety
     * @param callable(string):bool $validator
     */
    private function cachedLinkTargetSafety(
        string $text,
        string $kind,
        int $start,
        int $end,
        array &$targetSafety,
        callable $validator
    ): bool {
        if ($start >= $end) {
            return false;
        }

        $cached = $targetSafety[$kind] ?? null;
        if ($cached !== null && $cached['start'] === $start && $cached['end'] === $end) {
            return $cached['result'];
        }

        $result = $validator(substr($text, $start, $end - $start));
        $targetSafety[$kind] = ['start' => $start, 'end' => $end, 'result' => $result];

        return $result;
    }

    private function parseAutolink(string $text, int $offset): ?AstNode
    {
        $previous = $offset === 0 ? '' : $text[$offset - 1];
        if ($previous !== '' && !ctype_space($previous) && !in_array($previous, ['(', '[', '{', '<'], true)) {
            return null;
        }

        if (preg_match('/\\G(?:[A-Za-z][A-Za-z0-9+.-]*:\\/\\/|mailto:)[^\\s<>{}\\[\\]"\']+/u', $text, $match, 0, $offset) !== 1) {
            return null;
        }

        $target = $this->trimAutolinkTrailingPunctuation($match[0]);
        if ($target === '' || strcasecmp($target, 'mailto:') === 0 || !$this->isJiraSafeAutolinkTarget($target)) {
            return null;
        }

        return new AstNode('link', [
            'url' => $target,
            'classes' => [],
            '_endOffset' => $offset + strlen($target) - 1,
        ], $this->textInlines($target));
    }

    private function trimAutolinkTrailingPunctuation(string $target): string
    {
        $target = rtrim($target, ".,;:!?");
        while (str_ends_with($target, ')') && substr_count($target, '(') < substr_count($target, ')')) {
            $target = substr($target, 0, -1);
        }

        return $target;
    }

    private function parseDelimitedStyle(string $text, int $offset, JiraInlineSpanIndex $spans): ?AstNode
    {
        $map = [
            '*' => 'strong',
            '_' => 'emph',
            '-' => 'strikeout',
            '+' => 'underline',
            '~' => 'subscript',
            '^' => 'superscript',
        ];
        $marker = $text[$offset] ?? '';
        if (!isset($map[$marker]) || !$this->canOpenDelimited($text, $offset)) {
            return null;
        }

        $end = $spans->delimitedStyleEnd($marker, $offset);
        if ($end !== null) {
            return new AstNode($map[$marker], ['_endOffset' => $end], $this->parseInlines(substr($text, $offset + 1, $end - $offset - 1)));
        }

        return null;
    }

    private function canOpenDelimited(string $text, int $offset): bool
    {
        $previous = $offset === 0 ? '' : $text[$offset - 1];
        $next = $text[$offset + 1] ?? '';
        if ($next === '' || ctype_space($next)) {
            return false;
        }
        if ($previous !== '' && (ctype_alnum($previous) || $previous === '.')) {
            return false;
        }

        return true;
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

    private function parseLink(string $content): ?AstNode
    {
        if ($content === '') {
            return null;
        }
        if (str_starts_with($content, '^')) {
            $target = substr($content, 1);

            return $this->isJiraSafeAttachmentTarget($target)
                ? $this->link($target, $target, ['attachment'])
                : null;
        }
        if (preg_match('/^(.+)\\^([^|]+)$/u', $content, $match) === 1) {
            return $this->isJiraSafeAttachmentTarget($match[2])
                ? $this->link($match[2], $match[1], ['attachment'])
                : null;
        }

        $parts = explode('|', $content);
        if (count($parts) === 3 && in_array($parts[2], ['smart-link', 'smart-card'], true)) {
            return $this->isJiraSafeExternalTarget($parts[1])
                ? $this->link($parts[1], $parts[0], [$parts[2]])
                : null;
        }
        if (count($parts) === 2) {
            $target = $parts[1];
            $label = $parts[0];
            if ($this->isJiraSafeUserAccountTarget($target)) {
                return $this->link($target, $label, ['user-account']);
            }
            if (
                $this->isJiraSafeMailtoTarget($target)
                || $this->isJiraSafeFragmentTarget($target)
                || $this->isJiraSafeExternalTarget($target)
            ) {
                return $this->link($target, $label);
            }

            return null;
        }

        if (count($parts) !== 1) {
            return null;
        }

        if ($this->isJiraSafeUserAccountTarget($content)) {
            return $this->link($content, $content, ['user-account']);
        }
        if ($this->isJiraSafeMailtoTarget($content)) {
            return $this->link($content, substr($content, strlen('mailto:')));
        }
        if ($this->isJiraSafeFragmentTarget($content) || $this->isJiraSafeExternalTarget($content)) {
            return $this->link($content, $content);
        }

        return null;
    }

    private function isJiraSafeAutolinkTarget(string $target): bool
    {
        return $this->isJiraSafeMailtoTarget($target) || $this->isJiraSafeExternalTarget($target);
    }

    private function isJiraSafeExternalTarget(string $target): bool
    {
        return !$this->jiraTargetHasUnsafeScheme($target)
            && preg_match('/^[a-z][a-z0-9+.-]*:\\/\\//i', $target) === 1;
    }

    private function isJiraSafeMailtoTarget(string $target): bool
    {
        return strlen($target) > strlen('mailto:')
            && strncasecmp($target, 'mailto:', strlen('mailto:')) === 0
            && !$this->jiraTargetHasUnsafeScheme($target);
    }

    private function isJiraSafeFragmentTarget(string $target): bool
    {
        return str_starts_with($target, '#') && !$this->jiraTargetHasUnsafeScheme($target);
    }

    private function isJiraSafeUserAccountTarget(string $target): bool
    {
        return strlen($target) > 1
            && str_starts_with($target, '~')
            && preg_match('/[\x00-\x1F\x7F]/', $target) !== 1;
    }

    private function isJiraSafeAttachmentTarget(string $target): bool
    {
        return $target !== ''
            && !$this->jiraTargetHasUnsafeScheme($target)
            && preg_match('/[\\\\\/:;|\x00-\x1F\x7F]/', $target) !== 1;
    }

    private function jiraTargetHasUnsafeScheme(string $target): bool
    {
        $candidate = $this->normalizedJiraTargetForScheme($target);

        return preg_match('/^(?:javascript|vbscript|data):/i', $candidate) === 1;
    }

    private function normalizedJiraTargetForScheme(string $target): string
    {
        $candidate = $target;
        for ($pass = 0; $pass < 4; $pass++) {
            $previous = $candidate;
            $candidate = html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $candidate = preg_replace('/[\x00-\x20\x7F-\x9F]/u', '', $candidate) ?? $candidate;
            $candidate = rawurldecode($candidate);
            if ($candidate === $previous) {
                break;
            }
        }

        return $candidate;
    }

    /**
     * @param list<string> $classes
     */
    private function link(string $target, string $label, array $classes = []): AstNode
    {
        return new AstNode('link', [
            'url' => $target,
            'classes' => $classes,
        ], $this->parseInlines($label, false));
    }

    private function isJiraSafeImageTarget(string $target): bool
    {
        $target = trim($target);
        if ($target === '' || preg_match('/[\x00-\x20\x7F-\x9F]/u', $target) === 1) {
            return false;
        }

        if ($this->isJiraSafeRasterImageDataTarget($target)) {
            return true;
        }
        if ($this->jiraTargetHasUnsafeScheme($target)) {
            return false;
        }

        $normalizedTarget = $this->normalizedJiraTargetForScheme($target);
        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $normalizedTarget) !== 1) {
            return true;
        }

        return preg_match('/^https?:\/\//i', $target) === 1
            && preg_match('/^https?:\/\//i', $normalizedTarget) === 1;
    }

    private function isJiraSafeRasterImageDataTarget(string $target): bool
    {
        if (preg_match('/^data:image\/(?:png|gif|jpe?g|webp);base64,([A-Za-z0-9+\/]+={0,2})$/i', $target, $match) !== 1) {
            return false;
        }

        return base64_decode((string) $match[1], true) !== false;
    }

    private function parseImage(string $content): ?AstNode
    {
        if ($content === '') {
            return null;
        }

        $parts = array_map('trim', explode('|', $content));
        $url = array_shift($parts);
        if ($url === null || !$this->isJiraSafeImageTarget($url)) {
            return null;
        }

        $classes = [];
        $attributes = [];
        $title = '';
        foreach ($parts as $part) {
            if ($part === 'thumbnail') {
                $classes[] = 'thumbnail';
                continue;
            }
            foreach (explode(',', $part) as $param) {
                $param = trim($param);
                if ($param === '') {
                    continue;
                }
                [$key, $value] = array_pad(explode('=', $param, 2), 2, '');
                $key = trim($key);
                $value = trim($value);
                if ($key === 'title') {
                    $title = $value;
                } elseif ($key !== '') {
                    $attributes[$key] = $value;
                }
            }
        }

        return new AstNode('image', [
            'url' => $url,
            'src' => $url,
            'title' => $title,
            'classes' => $classes,
            'attributes' => $attributes,
        ]);
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function stringifyInlines(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $node) {
            $text .= match ($node->type) {
                'text' => (string) $node->attr('text', ''),
                'linebreak', 'softbreak' => ' ',
                'code' => (string) $node->attr('text', ''),
                'image' => (string) $node->attr('alt', ''),
                default => $this->stringifyInlines($node->children),
            };
        }

        return $text;
    }

    private function decodeEntities(string $text): string
    {
        $text = str_replace('&bsol;', '\\', $text);
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $decoded === '' && $text !== '' ? $text : $decoded;
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function flushText(array &$nodes, string &$buffer): void
    {
        if ($buffer === '') {
            return;
        }
        $decoded = $this->decodeEntities($buffer);
        $last = $nodes[count($nodes) - 1] ?? null;
        if ($last instanceof AstNode && $last->type === 'text') {
            $attrs = $last->attrs;
            $attrs['text'] = (string) ($attrs['text'] ?? '') . $decoded;
            $nodes[count($nodes) - 1] = new AstNode('text', $attrs);
        } else {
            $nodes[] = new AstNode('text', ['text' => $decoded]);
        }
        $buffer = '';
    }

    /**
     * @return list<AstNode>
     */
    private function textInlines(string $text): array
    {
        return $text === '' ? [] : [new AstNode('text', ['text' => $text])];
    }
}
