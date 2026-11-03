<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class ManReader
{
    /** @var list<string> */
    private array $lines = [];

    private int $index = 0;

    /** @var array<string, mixed> */
    private array $meta = [];

    public function read(string $roff): AstNode
    {
        $this->lines = $this->logicalLines(str_replace(["\r\n", "\r"], "\n", $roff));
        $this->index = 0;
        $this->meta = [];

        $blocks = $this->parseBlocks();
        $attrs = [
            'sourceFormat' => 'man',
            'man' => [
                'reader' => self::class,
                'readerScope' => 'pinned-pandoc-man-reader-unit-semantics',
                'sourceBytes' => strlen($roff),
                'upstreamEvidence' => [
                    'source' => 'Pandoc 4f5226df Text.Pandoc.Readers.Man and test/Tests/Readers/Man.hs',
                    'readerUnitGroups' => [
                        'macros',
                        'escapes',
                        'lists',
                        'definition lists',
                        'title metadata',
                        'links',
                        'synopsis options',
                        'code blocks',
                        'tables',
                    ],
                    'fixtureStatus' => 'Tests.Readers.Man is an inline Haskell builder assertion suite, not a fixture-pair suite.',
                ],
            ],
        ];
        if ($this->meta !== []) {
            $attrs['meta'] = $this->meta;
        }

        return new AstNode('document', $attrs, $blocks);
    }

    /**
     * @return list<string>
     */
    private function logicalLines(string $text): array
    {
        $physical = explode("\n", $text);
        $logical = [];
        $buffer = '';
        foreach ($physical as $line) {
            if ($buffer !== '') {
                $line = $buffer . $line;
                $buffer = '';
            }
            if ($line !== '' && str_ends_with($line, '\\')) {
                $buffer = substr($line, 0, -1);
                continue;
            }
            $logical[] = $line;
        }
        if ($buffer !== '') {
            $logical[] = $buffer;
        }

        return $logical;
    }

    /**
     * @return list<AstNode>
     */
    private function parseBlocks(?string $terminator = null): array
    {
        $blocks = [];
        while ($this->index < count($this->lines)) {
            $line = $this->lines[$this->index];
            $trimmed = trim($line);
            if ($terminator !== null && $trimmed === $terminator) {
                ++$this->index;
                break;
            }
            if ($trimmed === '' || $this->isCommentLine($trimmed)) {
                ++$this->index;
                continue;
            }

            $macro = $this->macroLine($line);
            if ($macro !== null) {
                [$name, $rawArgs] = $macro;
                if ($name === 'RE') {
                    if ($terminator === '.RE') {
                        ++$this->index;
                    }
                    break;
                }
                if ($this->isMacroDefinitionRequest($name)) {
                    $this->skipMacroDefinition();
                    continue;
                }
                if ($name === 'TH') {
                    $this->recordTitleMetadata($rawArgs);
                    ++$this->index;
                    continue;
                }
                if ($name === 'SH' || $name === 'SS') {
                    ++$this->index;
                    $headingInlines = $this->headingInlines($rawArgs);
                    $blocks[] = new AstNode('heading', [
                        'level' => $name === 'SH' ? 1 : 2,
                    ], $headingInlines);
                    continue;
                }
                if ($name === 'TP') {
                    $blocks[] = $this->parseTaggedParagraphList();
                    continue;
                }
                if ($name === 'IP') {
                    $blocks[] = $this->parseListBlock();
                    continue;
                }
                if ($name === 'RS') {
                    ++$this->index;
                    $children = $this->parseBlocks('.RE');
                    if ($children !== []) {
                        $blocks[] = new AstNode('blockquote', [], $children);
                    }
                    continue;
                }
                if ($name === 'nf' || $name === 'EX') {
                    $blocks[] = $this->parseCodeBlock($name === 'EX' ? 'EE' : 'fi');
                    continue;
                }
                if ($name === 'TS') {
                    $blocks[] = $this->parseTable();
                    continue;
                }
                if ($this->isInlineParagraphStart($name)) {
                    $blocks[] = $this->parseParagraph();
                    continue;
                }
                if ($this->isIgnoredRequest($name) || $this->isParagraphBreakMacro($name)) {
                    ++$this->index;
                    continue;
                }

                ++$this->index;
                continue;
            }

            $blocks[] = $this->parseParagraph();
        }

        return $blocks;
    }

    private function parseParagraph(): AstNode
    {
        $inlines = [];
        $joinNextLineTightly = false;
        while ($this->index < count($this->lines)) {
            $line = $this->lines[$this->index];
            $trimmed = trim($line);
            if ($trimmed === '') {
                break;
            }
            if ($inlines !== [] && $this->isBlockStart($line)) {
                break;
            }
            if ($this->isCommentLine($trimmed)) {
                ++$this->index;
                continue;
            }

            $macro = $this->macroLine($line);
            if ($macro !== null) {
                [$name, $rawArgs] = $macro;
                if ($this->isInlineMacro($name)) {
                    ++$this->index;
                    $this->appendInlineNodes($inlines, $this->inlineMacroInlines($name, $rawArgs), $joinNextLineTightly);
                    $joinNextLineTightly = false;
                    continue;
                }
                if ($name === 'UR' || $name === 'MT') {
                    $this->appendInlineNodes($inlines, $this->parseDelimitedLinkInlines($name, $rawArgs), $joinNextLineTightly);
                    $joinNextLineTightly = false;
                    continue;
                }
                if ($this->isIgnoredRequest($name) || $this->isParagraphBreakMacro($name)) {
                    ++$this->index;
                    continue;
                }
                if ($inlines !== []) {
                    break;
                }
                ++$this->index;
                continue;
            }

            $this->appendInlineNodes($inlines, $this->parseInlines($this->paragraphLineText($line)), $joinNextLineTightly);
            $joinNextLineTightly = strpos($line, '\#') !== false;
            ++$this->index;
        }

        return new AstNode('paragraph', [], $this->coalesceTextNodes($inlines));
    }

    private function isBlockStart(string $line): bool
    {
        $macro = $this->macroLine($line);
        if ($macro === null) {
            return false;
        }

        return in_array($macro[0], ['TH', 'SH', 'SS', 'TP', 'IP', 'RS', 'RE', 'nf', 'fi', 'EX', 'EE', 'TS', 'TE'], true)
            || $this->isIgnoredRequest($macro[0])
            || $this->isParagraphBreakMacro($macro[0]);
    }

    private function isCommentLine(string $trimmed): bool
    {
        return str_starts_with($trimmed, '."')
            || str_starts_with($trimmed, '.\\"')
            || str_starts_with($trimmed, '\\"')
            || $this->isControlBraceLine($trimmed);
    }

    private function isControlBraceLine(string $trimmed): bool
    {
        return $trimmed === '.'
            || $trimmed === '..'
            || preg_match('/^[.\']\s*\\\\?[{}]\s*$/', $trimmed) === 1;
    }

    /**
     * @return array{0:string, 1:string}|null
     */
    private function macroLine(string $line): ?array
    {
        if (preg_match('/^[.\']\s*([A-Za-z][A-Za-z0-9]*)(.*)$/', $line, $match) !== 1) {
            return null;
        }

        return [(string) $match[1], ltrim((string) ($match[2] ?? ''))];
    }

    private function isParagraphBreakMacro(string $name): bool
    {
        return in_array($name, ['P', 'PP', 'LP', 'Pp', 'sp'], true);
    }

    private function isIgnoredRequest(string $name): bool
    {
        return in_array($name, [
            'TH',
            'DT',
            'UC',
            'ad',
            'am',
            'am1',
            'de',
            'de1',
            'ds',
            'EE',
            'el',
            'fi',
            'hy',
            'ie',
            'if',
            'in',
            'll',
            'lt',
            'na',
            'ne',
            'nh',
            'nr',
            'ns',
            'PD',
            'pl',
            'po',
            'rm',
            'so',
            'ta',
            'TE',
            'ti',
            'tr',
        ], true);
    }

    private function isMacroDefinitionRequest(string $name): bool
    {
        return in_array($name, ['de', 'de1', 'am', 'am1'], true);
    }

    private function skipMacroDefinition(): void
    {
        ++$this->index;
        while ($this->index < count($this->lines)) {
            if (trim($this->lines[$this->index]) === '..') {
                ++$this->index;
                break;
            }
            ++$this->index;
        }
    }

    private function isInlineMacro(string $name): bool
    {
        return in_array($name, ['B', 'I', 'SM', 'SB', 'BI', 'BR', 'IB', 'IR', 'RB', 'RI', 'SY', 'YS', 'OP', 'br'], true);
    }

    private function isInlineParagraphStart(string $name): bool
    {
        return $this->isInlineMacro($name) || $name === 'UR' || $name === 'MT';
    }

    /**
     * @return list<AstNode>
     */
    private function headingInlines(string $rawArgs): array
    {
        $args = $this->parseArgs($rawArgs);
        if ($args !== []) {
            return $this->parseInlines($this->argsText($args));
        }

        if ($this->index < count($this->lines)
            && trim($this->lines[$this->index]) !== ''
            && !$this->isBlockStart($this->lines[$this->index])
            && $this->macroLine($this->lines[$this->index]) === null
        ) {
            $line = $this->stripInlineComment($this->lines[$this->index]);
            ++$this->index;

            return $this->parseInlines($line);
        }

        return [];
    }

    private function recordTitleMetadata(string $rawArgs): void
    {
        $args = $this->parseArgs($rawArgs);
        $fields = [
            'titleInlines',
            'section',
            'dateInlines',
            'footer',
            'header',
        ];

        foreach ($fields as $offset => $field) {
            if (!isset($args[$offset])) {
                continue;
            }
            $inlines = $this->parseInlines($args[$offset]);
            if ($field === 'titleInlines' || $field === 'dateInlines') {
                $this->meta[$field] = $inlines;
                continue;
            }
            $this->meta[$field] = [
                'type' => 'MetaInlines',
                'value' => $inlines,
            ];
        }
    }

    /**
     * @param list<AstNode> $target
     * @param list<AstNode> $nodes
     */
    private function appendInlineNodes(array &$target, array $nodes, bool $joinTightly = false): void
    {
        if ($nodes === []) {
            return;
        }
        if ($target !== []
            && !$joinTightly
            && !$this->lastInlineIsLineBreak($target)
            && !$this->firstInlineIsLineBreak($nodes)
        ) {
            $target[] = $this->textNode(' ');
        }
        array_push($target, ...$nodes);
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function firstInlineIsLineBreak(array $nodes): bool
    {
        return ($nodes[0] ?? null) instanceof AstNode && $nodes[0]->type === 'linebreak';
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function lastInlineIsLineBreak(array $nodes): bool
    {
        $last = $nodes[count($nodes) - 1] ?? null;

        return $last instanceof AstNode && $last->type === 'linebreak';
    }

    /**
     * @return list<AstNode>
     */
    private function parseDelimitedLinkInlines(string $name, string $rawArgs): array
    {
        $terminator = $name === 'MT' ? 'ME' : 'UE';
        $args = $this->parseArgs($rawArgs);
        $url = $this->argsText(array_slice($args, 0, 1));
        if ($name === 'MT' && $url !== '') {
            $url = 'mailto:' . $url;
        }

        ++$this->index;
        $label = [];
        while ($this->index < count($this->lines)) {
            $line = $this->lines[$this->index];
            $trimmed = trim($line);
            if ($trimmed === '' || $this->isCommentLine($trimmed)) {
                ++$this->index;
                continue;
            }

            $macro = $this->macroLine($line);
            if ($macro !== null && $macro[0] === $terminator) {
                ++$this->index;
                $link = new AstNode('link', ['url' => $url, 'title' => ''], $this->coalesceTextNodes($label));
                $endInlines = $this->parseInlines($this->argsText($this->parseArgs($macro[1])));

                return $endInlines === [] ? [$link] : array_merge([$link], $endInlines);
            }
            if ($macro !== null && $this->isInlineMacro($macro[0])) {
                ++$this->index;
                $this->appendInlineNodes($label, $this->inlineMacroInlines($macro[0], $macro[1]));
                continue;
            }
            if ($macro !== null) {
                break;
            }

            $this->appendInlineNodes($label, $this->parseInlines($this->paragraphLineText($line)));
            ++$this->index;
        }

        return [new AstNode('link', ['url' => $url, 'title' => ''], $this->coalesceTextNodes($label))];
    }

    /**
     * @return list<AstNode>
     */
    private function inlineMacroInlines(string $name, string $rawArgs): array
    {
        if ($name === 'br') {
            return [new AstNode('linebreak')];
        }
        if ($name === 'YS') {
            return [];
        }

        $args = $this->parseArgs($rawArgs);
        if ($args === []
            && $this->inlineMacroCanConsumeNextLine($name)
            && $this->index < count($this->lines)
            && !$this->isBlockStart($this->lines[$this->index])
            && $this->macroLine($this->lines[$this->index]) === null
        ) {
            $args = [$this->stripInlineComment($this->lines[$this->index])];
            ++$this->index;
        }

        if ($name === 'SY') {
            return [new AstNode('strong', [], $this->parseInlines($this->argsText($args)))];
        }
        if ($name === 'OP') {
            if ($args === []) {
                return [];
            }
            $inlines = [$this->textNode('[ '), new AstNode('strong', [], $this->parseInlines((string) $args[0]))];
            $tail = array_slice($args, 1);
            if ($tail !== []) {
                $inlines[] = $this->textNode(' ' . $this->argsText($tail));
            }
            $inlines[] = $this->textNode(' ]');

            return $inlines;
        }
        if ($name === 'B' || $name === 'SB') {
            return [new AstNode('strong', [], $this->parseInlines($this->argsText($args)))];
        }
        if ($name === 'I') {
            return [new AstNode('emph', [], $this->parseInlines($this->argsText($args)))];
        }
        if ($name === 'SM') {
            return [new AstNode('small_caps', [], $this->parseInlines($this->argsText($args)))];
        }

        $inlines = [];
        foreach (array_values($args) as $offset => $arg) {
            $children = $this->parseInlines($arg);
            $style = $this->inlineMacroArgumentStyle($name, $offset);
            if ($style === 'bold') {
                $inlines[] = new AstNode('strong', [], $children);
                continue;
            }
            if ($style === 'italic') {
                $inlines[] = new AstNode('emph', [], $children);
                continue;
            }

            array_push($inlines, ...$children);
        }

        return $inlines;
    }

    private function inlineMacroCanConsumeNextLine(string $name): bool
    {
        return in_array($name, ['B', 'I', 'SM', 'SB', 'BI', 'BR', 'IB', 'IR', 'RB', 'RI'], true);
    }

    private function inlineMacroArgumentStyle(string $name, int $offset): string
    {
        $pattern = match ($name) {
            'BI' => 'BI',
            'BR' => 'BR',
            'IB' => 'IB',
            'IR' => 'IR',
            'RB' => 'RB',
            'RI' => 'RI',
            default => 'R',
        };
        $style = $pattern[$offset % strlen($pattern)];

        return match ($style) {
            'B' => 'bold',
            'I' => 'italic',
            default => 'normal',
        };
    }

    /**
     * @return list<string>
     */
    private function parseArgs(string $raw): array
    {
        $raw = $this->stripInlineComment($raw);
        $args = [];
        $length = strlen($raw);
        $offset = 0;
        while ($offset < $length) {
            while ($offset < $length && ctype_space($raw[$offset])) {
                ++$offset;
            }
            if ($offset >= $length) {
                break;
            }
            if ($raw[$offset] === '"') {
                ++$offset;
                $arg = '';
                while ($offset < $length) {
                    if ($raw[$offset] === '"') {
                        if ($offset + 1 < $length && $raw[$offset + 1] === '"') {
                            $arg .= '"';
                            $offset += 2;
                            continue;
                        }
                        ++$offset;
                        break;
                    }
                    $arg .= $raw[$offset];
                    ++$offset;
                }
                $args[] = $arg;
                continue;
            }

            $start = $offset;
            while ($offset < $length && !ctype_space($raw[$offset])) {
                ++$offset;
            }
            $args[] = substr($raw, $start, $offset - $start);
        }

        return $args;
    }

    /**
     * @param list<string> $args
     */
    private function argsText(array $args): string
    {
        return implode(' ', $args);
    }

    /**
     * @return list<AstNode>
     */
    private function parseInlines(string $text): array
    {
        $segments = [];
        $style = 'normal';
        $buffer = '';
        $source = $this->stripInlineComment($text);
        $length = strlen($source);

        $flush = static function () use (&$segments, &$style, &$buffer): void {
            if ($buffer === '') {
                return;
            }
            $segments[] = ['style' => $style, 'text' => $buffer];
            $buffer = '';
        };

        for ($i = 0; $i < $length; ++$i) {
            $char = $source[$i];
            if ($char !== '\\') {
                $buffer .= $char;
                continue;
            }

            if ($i + 1 < $length && $source[$i + 1] === 'f') {
                $newStyle = $this->fontStyleEscape($source, $i);
                if ($newStyle !== null) {
                    $flush();
                    $style = $newStyle;
                    continue;
                }
            }

            $buffer .= $this->escapeText($source, $i);
        }
        $flush();

        return $this->segmentsToInlines($segments);
    }

    private function stripInlineComment(string $text): string
    {
        $quote = strpos($text, '\\"');
        $hash = strpos($text, '\\#');
        $positions = array_values(array_filter([$quote, $hash], static fn (int|false $position): bool => $position !== false));
        if ($positions === []) {
            return $text;
        }

        return rtrim(substr($text, 0, min($positions)));
    }

    private function paragraphLineText(string $line): string
    {
        return ltrim($this->stripInlineComment($line));
    }

    private function appendRoffTextLine(string &$text, bool &$joinNextLineTightly, string $line): void
    {
        $part = $this->stripInlineComment($line);
        $this->appendPlainTextPart($text, $joinNextLineTightly, $part);

        $joinNextLineTightly = strpos($line, '\#') !== false;
    }

    private function appendPlainTextPart(string &$text, bool &$joinNextLineTightly, string $part): void
    {
        if ($text === '') {
            $text = $part;
        } else {
            $text .= ($joinNextLineTightly ? '' : ' ') . $part;
        }
    }

    private function fontStyleEscape(string $source, int &$offset): ?string
    {
        $length = strlen($source);
        $style = null;
        if ($offset + 3 < $length && $source[$offset + 2] === '[') {
            $end = strpos($source, ']', $offset + 3);
            if ($end === false) {
                return null;
            }
            $font = substr($source, $offset + 3, $end - $offset - 3);
            $offset = $end;
        } elseif ($offset + 2 < $length) {
            $font = $source[$offset + 2];
            $offset += 2;
        } else {
            return null;
        }

        $style = match ($font) {
            'I' => 'italic',
            'B' => 'bold',
            'BI', 'IB' => 'bolditalic',
            'R', 'P' => 'normal',
            default => null,
        };

        return $style;
    }

    private function escapeText(string $source, int &$offset): string
    {
        $length = strlen($source);
        if ($offset + 1 >= $length) {
            return '';
        }

        $next = $source[$offset + 1];
        if ($next === '[') {
            $end = strpos($source, ']', $offset + 2);
            if ($end === false) {
                ++$offset;
                return '[';
            }
            $name = substr($source, $offset + 2, $end - $offset - 2);
            $offset = $end;

            return $this->glyph($name);
        }
        if ($next === '(' && $offset + 3 < $length) {
            $name = substr($source, $offset + 2, 2);
            $offset += 3;

            return $this->glyph($name);
        }
        if ($next === '*' && $offset + 4 < $length && $source[$offset + 2] === '(') {
            $name = substr($source, $offset + 3, 2);
            $offset += 4;

            return $this->stringRegister($name);
        }
        if ($next === '*' && $offset + 3 < $length) {
            $name = substr($source, $offset + 2, 2);
            $offset += 3;

            return $this->stringRegister($name);
        }

        ++$offset;

        return match ($next) {
            '-' => '-',
            ' ' => ' ',
            '\\' => '\\',
            '%' => '',
            ':' => '',
            '0' => "\u{2007}",
            'e' => '\\',
            '`' => '`',
            '^' => "\u{200A}",
            '|' => "\u{2006}",
            '\'' => '\'',
            't' => '',
            '&' => '',
            'q' => '"',
            default => $next,
        };
    }

    private function stringRegister(string $name): string
    {
        return match ($name) {
            'lq' => "\u{201C}",
            'rq' => "\u{201D}",
            '``', "''" => '',
            default => $name,
        };
    }

    private function glyph(string $name): string
    {
        if (preg_match('/^u([0-9A-Fa-f]{4,6})(?:_u([0-9A-Fa-f]{4,6}))*$/', $name) === 1) {
            $chars = [];
            if (preg_match_all('/u([0-9A-Fa-f]{4,6})/', $name, $matches) !== false) {
                foreach ($matches[1] as $hex) {
                    $chars[] = mb_chr((int) hexdec((string) $hex), 'UTF-8');
                }
            }
            $text = implode('', $chars);

            return class_exists(\Normalizer::class) ? \Normalizer::normalize($text, \Normalizer::FORM_C) : $text;
        }

        return match ($name) {
            'bu' => "\u{2022}",
            'lq' => "\u{201C}",
            'rq' => "\u{201D}",
            'em' => "\u{2014}",
            'en' => "\u{2013}",
            'aq' => "'",
            'dq' => '"',
            'ga' => '`',
            'ha' => '^',
            'rs' => '\\',
            'ti' => '~',
            'oA' => "\u{00C5}",
            '~O' => "\u{00D5}",
            'Do' => '$',
            'Ye' => "\u{00A5}",
            'product' => "\u{220F}",
            'ul' => '_',
            default => $name,
        };
    }

    /**
     * @param list<array{style:string, text:string}> $segments
     * @return list<AstNode>
     */
    private function segmentsToInlines(array $segments): array
    {
        $inlines = [];
        $index = 0;
        while ($index < count($segments)) {
            $segment = $segments[$index];
            $style = $segment['style'];
            if ($style === 'normal') {
                $inlines[] = $this->textNode($segment['text']);
                ++$index;
                continue;
            }
            if ($style === 'bold') {
                $children = [];
                while ($index < count($segments) && $segments[$index]['style'] === 'bold') {
                    $children[] = $this->textNode($segments[$index]['text']);
                    ++$index;
                }
                $inlines[] = new AstNode('strong', [], $children);
                continue;
            }

            $children = [];
            while ($index < count($segments) && in_array($segments[$index]['style'], ['italic', 'bolditalic'], true)) {
                if ($segments[$index]['style'] === 'bolditalic') {
                    $children[] = new AstNode('strong', [], [$this->textNode($segments[$index]['text'])]);
                } else {
                    $children[] = $this->textNode($segments[$index]['text']);
                }
                ++$index;
            }
            $inlines[] = new AstNode('emph', [], $children);
        }

        return $this->coalesceTextNodes($inlines);
    }

    private function textNode(string $text): AstNode
    {
        return new AstNode('text', ['text' => $text]);
    }

    /**
     * @param list<AstNode> $inlines
     * @return list<AstNode>
     */
    private function coalesceTextNodes(array $inlines): array
    {
        $coalesced = [];
        foreach ($inlines as $inline) {
            $last = $coalesced[count($coalesced) - 1] ?? null;
            if ($inline->type === 'text' && $last instanceof AstNode && $last->type === 'text') {
                array_pop($coalesced);
                $coalesced[] = new AstNode('text', [
                    'text' => (string) $last->attr('text', '') . (string) $inline->attr('text', ''),
                ]);
                continue;
            }
            $coalesced[] = $inline;
        }

        return $coalesced;
    }

    private function parseListBlock(): AstNode
    {
        $first = $this->currentIpDescriptor();
        $items = [];
        while ($this->index < count($this->lines)) {
            $descriptor = $this->currentIpDescriptor();
            if ($descriptor === null || !$this->sameListKind($first, $descriptor)) {
                break;
            }
            ++$this->index;
            $blocks = $this->parseListItemBlocks();
            if ($first['kind'] === 'definition') {
                $termInlines = $descriptor['termInlines'];
                $termText = (string) $descriptor['term'];
                $items[] = new AstNode('definition_item', ['term' => $termText], [
                    new AstNode('term', ['text' => $termText], $termInlines),
                    new AstNode('definition', [], $blocks),
                ]);
                continue;
            }

            $items[] = new AstNode('list_item', [
                'text' => $this->plainInlineText($blocks),
                'loose' => false,
            ], $blocks);
        }

        if ($first['kind'] === 'bullet') {
            return new AstNode('bullet_list', ['loose' => false], $items);
        }
        if ($first['kind'] === 'definition') {
            return new AstNode('definition_list', [], $items);
        }

        return new AstNode('ordered_list', [
            'start' => $first['start'],
            'style' => $first['style'],
            'delimiter' => $first['delimiter'],
            'loose' => false,
        ], $items);
    }

    /**
     * @return array{kind:string, start:int, style:string, delimiter:string, term:string, termInlines:list<AstNode>}|null
     */
    private function currentIpDescriptor(): ?array
    {
        $macro = $this->macroLine($this->lines[$this->index] ?? '');
        if ($macro === null || $macro[0] !== 'IP') {
            return null;
        }

        $args = $this->parseArgs($macro[1]);
        $marker = $args[0] ?? '';
        $markerInlines = $this->parseInlines($marker);
        $markerText = $this->plainInlineText($markerInlines);
        if ($markerText === "\u{2022}" || in_array($markerText, ['-', '*', '+'], true)) {
            return [
                'kind' => 'bullet',
                'start' => 1,
                'style' => 'default',
                'delimiter' => 'default',
                'term' => $markerText,
                'termInlines' => $markerInlines,
            ];
        }
        if (preg_match('/^([0-9]+)$/', $markerText, $match) === 1) {
            return [
                'kind' => 'ordered',
                'start' => (int) $match[1],
                'style' => 'decimal',
                'delimiter' => 'default',
                'term' => $markerText,
                'termInlines' => $markerInlines,
            ];
        }
        if (preg_match('/^([A-Z])\\)$/', $markerText, $match) === 1) {
            return [
                'kind' => 'ordered',
                'start' => ord((string) $match[1]) - ord('A') + 1,
                'style' => 'upper_alpha',
                'delimiter' => 'one_paren',
                'term' => $markerText,
                'termInlines' => $markerInlines,
            ];
        }

        return [
            'kind' => 'definition',
            'start' => 1,
            'style' => 'default',
            'delimiter' => 'default',
            'term' => $markerText,
            'termInlines' => $markerInlines,
        ];
    }

    /**
     * @param array{kind:string, start:int, style:string, delimiter:string, term:string, termInlines:list<AstNode>} $left
     * @param array{kind:string, start:int, style:string, delimiter:string, term:string, termInlines:list<AstNode>} $right
     */
    private function sameListKind(array $left, array $right): bool
    {
        return $left['kind'] === $right['kind']
            && $left['style'] === $right['style']
            && $left['delimiter'] === $right['delimiter'];
    }

    /**
     * @return list<AstNode>
     */
    private function parseListItemBlocks(): array
    {
        $children = [];
        $paragraphInlines = [];
        $joinNextLineTightly = false;
        $flushParagraph = function () use (&$children, &$paragraphInlines, &$joinNextLineTightly): void {
            if ($paragraphInlines === []) {
                return;
            }

            $children[] = new AstNode('paragraph', [], $this->coalesceTextNodes($paragraphInlines));
            $paragraphInlines = [];
            $joinNextLineTightly = false;
        };
        while ($this->index < count($this->lines)) {
            $line = $this->lines[$this->index];
            $trimmed = trim($line);
            if ($trimmed === '') {
                $flushParagraph();
                ++$this->index;
                break;
            }
            $macro = $this->macroLine($line);
            if ($macro !== null && in_array($macro[0], ['IP', 'TP', 'SH', 'SS', 'RE'], true)) {
                break;
            }
            if ($macro !== null && $macro[0] === 'RS') {
                $flushParagraph();
                ++$this->index;
                array_push($children, ...$this->parseBlocks('.RE'));
                continue;
            }
            if ($macro !== null && $this->isMacroDefinitionRequest($macro[0])) {
                $flushParagraph();
                $this->skipMacroDefinition();
                continue;
            }
            if ($macro !== null && ($macro[0] === 'nf' || $macro[0] === 'EX')) {
                $flushParagraph();
                $children[] = $this->parseCodeBlock($macro[0] === 'EX' ? 'EE' : 'fi');
                continue;
            }
            if ($macro !== null && $macro[0] === 'TS') {
                $flushParagraph();
                $children[] = $this->parseTable();
                continue;
            }
            if ($macro !== null && ($macro[0] === 'UR' || $macro[0] === 'MT')) {
                $this->appendInlineNodes($paragraphInlines, $this->parseDelimitedLinkInlines($macro[0], $macro[1]), $joinNextLineTightly);
                $joinNextLineTightly = false;
                continue;
            }
            if ($macro !== null && ($this->isIgnoredRequest($macro[0]) || $this->isParagraphBreakMacro($macro[0]))) {
                $flushParagraph();
                ++$this->index;
                continue;
            }
            if ($macro !== null && $this->isInlineMacro($macro[0])) {
                ++$this->index;
                $this->appendInlineNodes($paragraphInlines, $this->inlineMacroInlines($macro[0], $macro[1]), $joinNextLineTightly);
                $joinNextLineTightly = false;
                continue;
            }

            if (!$this->isCommentLine($trimmed)) {
                $this->appendInlineNodes($paragraphInlines, $this->parseInlines($this->paragraphLineText($line)), $joinNextLineTightly);
                $joinNextLineTightly = strpos($line, '\#') !== false;
            }
            ++$this->index;
        }
        $flushParagraph();

        return $children;
    }

    private function parseTaggedParagraphList(): AstNode
    {
        $items = [];
        while ($this->index < count($this->lines)) {
            $macro = $this->macroLine($this->lines[$this->index]);
            if ($macro === null || $macro[0] !== 'TP') {
                break;
            }

            ++$this->index;
            $termGroups = [$this->parseTaggedParagraphTerm()];
            while ($this->index < count($this->lines)) {
                $next = $this->macroLine($this->lines[$this->index]);
                if ($next === null || $next[0] !== 'TQ') {
                    break;
                }
                ++$this->index;
                $termGroups[] = $this->parseTaggedParagraphTerm();
            }
            $termInlines = $this->joinTermGroups($termGroups);
            $termText = $this->plainInlineText($termInlines);
            $definitionBlocks = $this->parseTaggedParagraphDefinition();
            $items[] = new AstNode('definition_item', ['term' => $termText], [
                new AstNode('term', ['text' => $termText], $termInlines),
                new AstNode('definition', [], $definitionBlocks),
            ]);
        }

        return new AstNode('definition_list', [], $items);
    }

    /**
     * @param list<list<AstNode>> $groups
     * @return list<AstNode>
     */
    private function joinTermGroups(array $groups): array
    {
        $inlines = [];
        foreach ($groups as $group) {
            if ($inlines !== []) {
                $inlines[] = new AstNode('linebreak');
            }
            array_push($inlines, ...$group);
        }

        return $this->coalesceTextNodes($inlines);
    }

    /**
     * @return list<AstNode>
     */
    private function parseTaggedParagraphTerm(): array
    {
        while ($this->index < count($this->lines)) {
            $line = $this->lines[$this->index];
            $trimmed = trim($line);
            if ($trimmed === '' || $this->isCommentLine($trimmed)) {
                ++$this->index;
                continue;
            }

            $macro = $this->macroLine($line);
            if ($macro !== null && in_array($macro[0], ['TP', 'TQ', 'IP', 'SH', 'SS', 'RE'], true)) {
                return [];
            }
            if ($macro !== null && $this->isMacroDefinitionRequest($macro[0])) {
                $this->skipMacroDefinition();
                continue;
            }
            if ($macro !== null && ($this->isIgnoredRequest($macro[0]) || $this->isParagraphBreakMacro($macro[0]))) {
                ++$this->index;
                continue;
            }
            if ($macro !== null && $this->isInlineMacro($macro[0])) {
                ++$this->index;

                return $this->inlineMacroInlines($macro[0], $macro[1]);
            }
            if ($macro !== null && ($macro[0] === 'UR' || $macro[0] === 'MT')) {
                return $this->parseDelimitedLinkInlines($macro[0], $macro[1]);
            }

            ++$this->index;

            return $this->parseInlines($line);
        }

        return [];
    }

    /**
     * @return list<AstNode>
     */
    private function parseTaggedParagraphDefinition(): array
    {
        $blocks = [];
        while ($this->index < count($this->lines)) {
            $line = $this->lines[$this->index];
            $trimmed = trim($line);
            if ($trimmed === '') {
                if ($blocks !== []) {
                    break;
                }
                ++$this->index;
                continue;
            }
            if ($this->isCommentLine($trimmed)) {
                ++$this->index;
                continue;
            }

            $macro = $this->macroLine($line);
            if ($macro !== null) {
                if (in_array($macro[0], ['TP', 'TQ', 'SH', 'SS', 'RE'], true)) {
                    break;
                }
                if ($this->isMacroDefinitionRequest($macro[0])) {
                    $this->skipMacroDefinition();
                    continue;
                }
                if ($this->isParagraphBreakMacro($macro[0])) {
                    if ($blocks !== []) {
                        break;
                    }
                    ++$this->index;
                    continue;
                }
                if ($this->isIgnoredRequest($macro[0])) {
                    ++$this->index;
                    continue;
                }
                if ($macro[0] === 'UR' || $macro[0] === 'MT') {
                    $blocks[] = $this->parseParagraph();
                    continue;
                }
                if ($macro[0] === 'IP') {
                    $blocks[] = $this->parseListBlock();
                    continue;
                }
                if ($macro[0] === 'RS') {
                    ++$this->index;
                    $children = $this->parseBlocks('.RE');
                    if ($children !== []) {
                        $blocks[] = new AstNode('blockquote', [], $children);
                    }
                    continue;
                }
                if ($macro[0] === 'nf' || $macro[0] === 'EX') {
                    $blocks[] = $this->parseCodeBlock($macro[0] === 'EX' ? 'EE' : 'fi');
                    continue;
                }
                if ($macro[0] === 'TS') {
                    $blocks[] = $this->parseTable();
                    continue;
                }
                if ($this->isInlineMacro($macro[0])) {
                    $blocks[] = $this->parseParagraph();
                    continue;
                }
            }

            $blocks[] = $this->parseParagraph();
        }

        return $blocks;
    }

    private function parseCodeBlock(string $terminator = 'fi'): AstNode
    {
        ++$this->index;
        $lines = [];
        while ($this->index < count($this->lines)) {
            $macro = $this->macroLine($this->lines[$this->index]);
            if ($macro !== null && $macro[0] === $terminator) {
                ++$this->index;
                break;
            }
            if ($macro !== null && ($macro[0] === 'RS' || $macro[0] === 'RE')) {
                ++$this->index;
                continue;
            }
            if ($macro !== null && $this->isIgnoredRequest($macro[0])) {
                ++$this->index;
                continue;
            }
            if ($macro !== null && $this->isParagraphBreakMacro($macro[0])) {
                if ($lines === [] || $lines[count($lines) - 1] !== '') {
                    $lines[] = '';
                }
                ++$this->index;
                continue;
            }
            $lines[] = $this->lines[$this->index];
            ++$this->index;
        }

        while ($lines !== [] && $lines[count($lines) - 1] === '') {
            array_pop($lines);
        }

        return new AstNode('code_block', ['text' => implode("\n", $lines)]);
    }

    private function parseTable(): AstNode
    {
        ++$this->index;
        $alignments = [];
        while ($this->index < count($this->lines)) {
            $line = trim($this->lines[$this->index]);
            ++$this->index;
            if ($line === '' || str_ends_with($line, ';')) {
                continue;
            }
            if (str_ends_with($line, '.')) {
                $format = rtrim(substr($line, 0, -1));
                $alignments = $this->tableAlignments($format);
                break;
            }
        }

        $rows = [];
        while ($this->index < count($this->lines)) {
            $line = $this->lines[$this->index];
            $macro = $this->macroLine($line);
            if ($macro !== null && $macro[0] === 'TE') {
                ++$this->index;
                break;
            }
            if ($macro !== null && ($this->isIgnoredRequest($macro[0]) || $this->isParagraphBreakMacro($macro[0]))) {
                ++$this->index;
                continue;
            }

            if (trim($line) === 'T{') {
                ++$this->index;
                $cellLines = [];
                while ($this->index < count($this->lines) && trim($this->lines[$this->index]) !== 'T}') {
                    $cellLines[] = trim($this->lines[$this->index]);
                    ++$this->index;
                }
                if ($this->index < count($this->lines) && trim($this->lines[$this->index]) === 'T}') {
                    ++$this->index;
                }
                $rows[] = [$this->collapseSpaces(implode(' ', $cellLines))];
                continue;
            }

            $rows[] = array_map(fn (string $cell): string => $this->collapseSpaces($cell), explode("\t", $line));
            ++$this->index;
        }

        $rowNodes = array_map(fn (array $row): AstNode => $this->tableRow($row), $rows);

        return new AstNode('table', [
            'alignments' => $alignments,
            'widths' => array_fill(0, count($alignments), null),
            'nativeColumnCount' => count($alignments),
        ], [
            new AstNode('table_head'),
            new AstNode('table_body', [], $rowNodes),
            new AstNode('table_foot'),
        ]);
    }

    /**
     * @return list<string>
     */
    private function tableAlignments(string $format): array
    {
        $alignments = [];
        foreach (preg_split('/\\s+/', trim($format)) ?: [] as $token) {
            $alignments[] = match (strtolower($token[0] ?? '')) {
                'l' => 'left',
                'r' => 'right',
                'c' => 'center',
                default => 'default',
            };
        }

        return $alignments;
    }

    /**
     * @param list<string> $cells
     */
    private function tableRow(array $cells): AstNode
    {
        return new AstNode('table_row', ['header' => false], array_map(
            fn (string $cell): AstNode => new AstNode('table_cell', ['text' => $cell], [
                new AstNode('plain', [], $this->parseInlines($cell)),
            ]),
            $cells
        ));
    }

    private function collapseSpaces(string $text): string
    {
        return trim(preg_replace('/\\s+/u', ' ', $text) ?? $text);
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
                'space', 'softbreak', 'linebreak' => ' ',
                default => $this->plainInlineText($node->children),
            };
        }

        return trim(preg_replace('/[ \t\r\n\f\v]+/', ' ', $text) ?? $text);
    }

}
