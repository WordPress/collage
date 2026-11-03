<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class MdocReader
{
    /** @var list<string> */
    private array $lines = [];

    private int $index = 0;

    private string $documentName = '';

    private ?string $currentSection = null;

    /** @var array<string, true> */
    private const CALLABLE_MACROS = [
        'Ar' => true,
        'Cm' => true,
        'Dq' => true,
        'Dv' => true,
        'Em' => true,
        'Ev' => true,
        'Fl' => true,
        'Ic' => true,
        'Li' => true,
        'Nd' => true,
        'Nm' => true,
        'Ns' => true,
        'Op' => true,
        'Pa' => true,
        'Ql' => true,
        'Sq' => true,
        'Sy' => true,
        'Xr' => true,
    ];

    public function read(string $mdoc): AstNode
    {
        $this->lines = $this->logicalLines(str_replace(["\r\n", "\r"], "\n", $mdoc));
        $this->index = 0;
        $this->documentName = '';
        $this->currentSection = null;

        return new AstNode('document', [
            'sourceFormat' => 'mdoc',
            'mdoc' => [
                'reader' => self::class,
                'readerScope' => 'bounded-pandoc-mdoc-reader-macro-family-semantics',
                'sourceBytes' => strlen($mdoc),
                'upstreamEvidence' => [
                    'source' => 'Pandoc Text.Pandoc.Readers.Mdoc executable native output probes',
                    'readerUnitGroups' => [
                        'document metadata macros',
                        'section headings',
                        'name/description macros',
                        'callable inline macros',
                        'synopsis line blocks',
                        'bullet ordered and tag lists',
                    ],
                    'fixtureStatus' => 'Executable mdoc corpus lane compares normalized AST output against pandoc -f mdoc -t native.',
                ],
            ],
        ], $this->parseBlocks());
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
            if ($terminator !== null && $this->isTerminator($line, $terminator)) {
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
                if ($name === 'El' || $name === 'Ed') {
                    ++$this->index;
                    continue;
                }
                if ($name === 'It') {
                    $blocks[] = $this->parseDetachedListBlock();
                    continue;
                }
                if ($this->isMetadataMacro($name)) {
                    $this->captureMetadata($name, $rawArgs);
                    ++$this->index;
                    continue;
                }
                if ($this->isParagraphBreakMacro($name)) {
                    ++$this->index;
                    continue;
                }
                if ($name === 'Sh' || $name === 'Ss') {
                    ++$this->index;
                    $headingInlines = $this->parseTokenStream($this->parseArgs($rawArgs));
                    $headingText = $this->plainInlineText($headingInlines);
                    $this->currentSection = strtoupper($headingText);
                    $blocks[] = new AstNode('heading', [
                        'level' => $name === 'Sh' ? 1 : 2,
                        'text' => $headingText,
                    ], $headingInlines);
                    continue;
                }
                if ($name === 'Bl') {
                    $blocks[] = $this->parseListBlock();
                    continue;
                }
            }

            if ($this->currentSection === 'SYNOPSIS') {
                $blocks[] = $this->parseSynopsisBlock();
                continue;
            }

            $blocks[] = $this->parseParagraph();
        }

        return $blocks;
    }

    private function isTerminator(string $line, string $terminator): bool
    {
        $macro = $this->macroLine($line);

        return $macro !== null && '.' . $macro[0] === $terminator;
    }

    private function parseParagraph(): AstNode
    {
        $inlines = [];
        while ($this->index < count($this->lines)) {
            $line = $this->lines[$this->index];
            $trimmed = trim($line);
            if ($trimmed === '') {
                ++$this->index;
                break;
            }
            if ($this->isCommentLine($trimmed)) {
                ++$this->index;
                continue;
            }

            $macro = $this->macroLine($line);
            if ($macro !== null) {
                [$name, $rawArgs] = $macro;
                if ($this->isBlockBoundaryMacro($name)) {
                    break;
                }
                if ($this->isMetadataMacro($name)) {
                    $this->captureMetadata($name, $rawArgs);
                    ++$this->index;
                    continue;
                }
                if ($this->isParagraphBreakMacro($name)) {
                    ++$this->index;
                    break;
                }

                ++$this->index;
                $this->appendInlineNodes($inlines, $this->macroInlines($name, $rawArgs));
                continue;
            }

            ++$this->index;
            $this->appendInlineNodes($inlines, $this->textLineInlines($line));
        }

        return new AstNode('paragraph', ['text' => $this->plainInlineText($inlines)], $this->coalesceTextNodes($inlines));
    }

    private function parseSynopsisBlock(): AstNode
    {
        $inlines = [];
        while ($this->index < count($this->lines)) {
            $line = $this->lines[$this->index];
            $trimmed = trim($line);
            if ($trimmed === '') {
                ++$this->index;
                break;
            }
            if ($this->isCommentLine($trimmed)) {
                ++$this->index;
                continue;
            }

            $macro = $this->macroLine($line);
            if ($macro !== null) {
                [$name, $rawArgs] = $macro;
                if ($this->isBlockBoundaryMacro($name)) {
                    break;
                }
                if ($this->isMetadataMacro($name) || $this->isParagraphBreakMacro($name)) {
                    ++$this->index;
                    continue;
                }

                ++$this->index;
                $this->appendInlineNodes($inlines, $this->macroInlines($name, $rawArgs));
                continue;
            }

            ++$this->index;
            $this->appendInlineNodes($inlines, $this->textLineInlines($line));
        }

        $line = new AstNode('line', ['text' => $this->plainInlineText($inlines)], $this->coalesceTextNodes($inlines));

        return new AstNode('line_block', [], [$line]);
    }

    private function parseListBlock(): AstNode
    {
        $macro = $this->macroLine($this->lines[$this->index] ?? '');
        if ($macro === null || $macro[0] !== 'Bl') {
            return new AstNode('bullet_list');
        }

        $kind = $this->listKind($macro[1]);
        ++$this->index;
        $items = [];
        while ($this->index < count($this->lines)) {
            $line = $this->lines[$this->index];
            $trimmed = trim($line);
            if ($trimmed === '' || $this->isCommentLine($trimmed)) {
                ++$this->index;
                continue;
            }

            $itemMacro = $this->macroLine($line);
            if ($itemMacro !== null && $itemMacro[0] === 'El') {
                ++$this->index;
                break;
            }
            if ($itemMacro === null || $itemMacro[0] !== 'It') {
                ++$this->index;
                continue;
            }

            ++$this->index;
            if ($kind === 'tag') {
                $termInlines = $this->parseTokenStream($this->parseArgs($itemMacro[1]));
                $termText = $this->plainInlineText($termInlines);
                $items[] = new AstNode('definition_item', ['term' => $termText], [
                    new AstNode('term', ['text' => $termText], $termInlines),
                    new AstNode('definition', [], $this->parseListItemBlocks()),
                ]);
                continue;
            }

            $initialInlines = $this->parseTokenStream($this->parseArgs($itemMacro[1]));
            $blocks = $this->parseListItemBlocks($initialInlines);
            $items[] = new AstNode('list_item', [
                'text' => $this->plainInlineText($blocks),
                'loose' => false,
            ], $blocks);
        }

        if ($kind === 'enum') {
            return new AstNode('ordered_list', [
                'start' => 1,
                'style' => 'default',
                'delimiter' => 'default',
                'loose' => false,
            ], $items);
        }

        if ($kind === 'tag') {
            return new AstNode('definition_list', [], $items);
        }

        return new AstNode('bullet_list', ['loose' => false], $items);
    }

    private function parseDetachedListBlock(): AstNode
    {
        $items = [];
        while ($this->index < count($this->lines)) {
            $macro = $this->macroLine($this->lines[$this->index] ?? '');
            if ($macro === null || $macro[0] !== 'It') {
                break;
            }

            ++$this->index;
            $initialInlines = $this->parseTokenStream($this->parseArgs($macro[1]));
            $blocks = $this->parseListItemBlocks($initialInlines);
            $items[] = new AstNode('list_item', [
                'text' => $this->plainInlineText($blocks),
                'loose' => false,
            ], $blocks);
        }

        return new AstNode('bullet_list', ['loose' => false], $items);
    }

    /**
     * @param list<AstNode> $initialInlines
     * @return list<AstNode>
     */
    private function parseListItemBlocks(array $initialInlines = []): array
    {
        $blocks = [];
        if ($initialInlines !== []) {
            $blocks[] = new AstNode('paragraph', ['text' => $this->plainInlineText($initialInlines)], $initialInlines);
        }

        while ($this->index < count($this->lines)) {
            $line = $this->lines[$this->index];
            $trimmed = trim($line);
            if ($trimmed === '' || $this->isCommentLine($trimmed)) {
                ++$this->index;
                continue;
            }

            $macro = $this->macroLine($line);
            if ($macro !== null && in_array($macro[0], ['It', 'El', 'Ed'], true)) {
                break;
            }
            if ($macro !== null && ($macro[0] === 'Sh' || $macro[0] === 'Ss')) {
                break;
            }
            if ($macro !== null && $macro[0] === 'Bl') {
                $blocks[] = $this->parseListBlock();
                continue;
            }
            if ($macro !== null && ($this->isMetadataMacro($macro[0]) || $this->isParagraphBreakMacro($macro[0]))) {
                $this->captureMetadata($macro[0], $macro[1]);
                ++$this->index;
                continue;
            }

            $blocks[] = $this->parseParagraph();
        }

        return $blocks;
    }

    private function listKind(string $rawArgs): string
    {
        $args = $this->parseArgs($rawArgs);
        foreach ($args as $arg) {
            if ($arg === '-tag' || $arg === 'tag') {
                return 'tag';
            }
            if ($arg === '-enum' || $arg === 'enum') {
                return 'enum';
            }
            if (in_array($arg, ['-bullet', 'bullet', '-item', 'item', '-dash', 'dash', '-hyphen', 'hyphen'], true)) {
                return 'bullet';
            }
        }

        return 'bullet';
    }

    /**
     * @return list<AstNode>
     */
    private function macroInlines(string $name, string $rawArgs): array
    {
        return $this->parseTokenStream(array_merge([$name], $this->parseArgs($rawArgs)));
    }

    /**
     * @return list<AstNode>
     */
    private function textLineInlines(string $line): array
    {
        return [new AstNode('text', ['text' => $this->decodeRoffEscapes($this->stripInlineComment(trim($line)))])];
    }

    /**
     * @param list<string> $tokens
     * @return list<AstNode>
     */
    private function parseTokenStream(array $tokens): array
    {
        $inlines = [];
        $offset = 0;
        $attachNextToPrevious = false;
        while ($offset < count($tokens)) {
            $token = $tokens[$offset];
            if ($token === '') {
                ++$offset;
                continue;
            }
            if ($token === 'Ns') {
                ++$offset;
                $attachNextToPrevious = true;
                continue;
            }
            if (isset(self::CALLABLE_MACROS[$token])) {
                $this->appendInlineNodes($inlines, $this->consumeCallableMacro($tokens, $offset), $attachNextToPrevious);
                $attachNextToPrevious = false;
                continue;
            }

            ++$offset;
            $this->appendTextToken($inlines, $this->decodeRoffEscapes($token), $attachNextToPrevious || $this->isClosingPunctuation($token));
            $attachNextToPrevious = false;
        }

        return $this->coalesceTextNodes($inlines);
    }

    /**
     * @param list<string> $tokens
     * @return list<AstNode>
     */
    private function consumeCallableMacro(array $tokens, int &$offset): array
    {
        $name = $tokens[$offset];
        ++$offset;

        return match ($name) {
            'Nm' => [$this->codeNode($this->collectText($tokens, $offset) ?: $this->documentNameForDisplay(), $this->currentSection === 'NAME' ? [] : ['Nm'])],
            'Nd' => [$this->textNode("\u{2014}" . $this->prefixedText($this->collectText($tokens, $offset)))],
            'Fl' => [$this->codeNode('-' . ($this->collectText($tokens, $offset, 1) ?: ''), ['Fl'])],
            'Ar' => [$this->codeNode($this->collectText($tokens, $offset, 1) ?: 'file', ['variable'])],
            'Cm', 'Ic', 'Ev', 'Dv', 'Li', 'Ql' => [$this->codeNode($this->collectText($tokens, $offset) ?: $name, [$name])],
            'Pa' => [$this->spanNode($this->collectText($tokens, $offset, 1) ?: '', ['Pa'])],
            'Sy' => [new AstNode('strong', [], $this->textWordsInlines($this->collectText($tokens, $offset)))],
            'Em' => [new AstNode('emph', [], $this->textWordsInlines($this->collectText($tokens, $offset)))],
            'Op' => $this->optionalInlines($tokens, $offset),
            'Dq' => [new AstNode('quoted', ['kind' => 'double'], $this->quotedChildren($tokens, $offset))],
            'Sq' => [new AstNode('quoted', ['kind' => 'single'], $this->quotedChildren($tokens, $offset))],
            'Xr' => [$this->spanNode($this->xrefText($tokens, $offset), ['Xr'])],
            default => $this->textWordsInlines($this->collectText($tokens, $offset)),
        };
    }

    /**
     * @param list<string> $tokens
     */
    private function collectText(array $tokens, int &$offset, ?int $maximum = null): string
    {
        $values = [];
        while ($offset < count($tokens) && ($maximum === null || count($values) < $maximum)) {
            $token = $tokens[$offset];
            if ($token === '') {
                ++$offset;
                continue;
            }
            if (isset(self::CALLABLE_MACROS[$token]) || $this->isClosingPunctuation($token)) {
                break;
            }
            $values[] = $this->decodeRoffEscapes($token);
            ++$offset;
        }

        return implode(' ', $values);
    }

    /**
     * @param list<string> $tokens
     * @return list<AstNode>
     */
    private function optionalInlines(array $tokens, int &$offset): array
    {
        $remaining = array_slice($tokens, $offset);
        $offset = count($tokens);
        $children = $this->parseTokenStream($remaining);
        $inlines = [];
        $this->appendTextToken($inlines, '[', false);
        $this->appendInlineNodesPreservingSpacing($inlines, $children);
        $this->appendTextToken($inlines, ']', true);

        return $inlines;
    }

    /**
     * @param list<string> $tokens
     * @return list<AstNode>
     */
    private function quotedChildren(array $tokens, int &$offset): array
    {
        $remaining = array_slice($tokens, $offset);
        $offset = count($tokens);

        return $this->parseTokenStream($remaining);
    }

    /**
     * @param list<string> $tokens
     */
    private function xrefText(array $tokens, int &$offset): string
    {
        $name = $this->collectText($tokens, $offset, 1);
        $section = $this->collectText($tokens, $offset, 1);
        if ($name === '') {
            return '';
        }

        return $section === '' ? $name : $name . '(' . $section . ')';
    }

    private function prefixedText(string $text): string
    {
        return $text === '' ? '' : ' ' . $text;
    }

    /**
     * @return list<AstNode>
     */
    private function textWordsInlines(string $text): array
    {
        return $text === '' ? [] : [new AstNode('text', ['text' => $text])];
    }

    private function codeNode(string $text, array $classes = []): AstNode
    {
        $attrs = ['text' => $text];
        if ($classes !== []) {
            $attrs['classes'] = $classes;
        }

        return new AstNode('code', $attrs);
    }

    private function spanNode(string $text, array $classes): AstNode
    {
        return new AstNode('span', ['classes' => $classes], $this->textWordsInlines($text));
    }

    private function textNode(string $text): AstNode
    {
        return new AstNode('text', ['text' => $text]);
    }

    /**
     * @param list<AstNode> $inlines
     * @param list<AstNode> $nodes
     */
    private function appendInlineNodes(array &$inlines, array $nodes, bool $attachFirstToPrevious = false): void
    {
        foreach ($nodes as $offset => $node) {
            if ($offset > 0) {
                $inlines[] = $node;
                continue;
            }
            $attachToPrevious = $attachFirstToPrevious && $offset === 0;
            if ($node->type === 'text') {
                $text = (string) $node->attr('text', '');
                $this->appendTextToken($inlines, $text, $attachToPrevious || $this->isClosingPunctuation($text));
                continue;
            }
            $this->appendInlineNode($inlines, $node, $attachToPrevious);
        }
    }

    /**
     * @param list<AstNode> $inlines
     * @param list<AstNode> $nodes
     */
    private function appendInlineNodesPreservingSpacing(array &$inlines, array $nodes): void
    {
        foreach ($nodes as $offset => $node) {
            if ($offset === 0) {
                $this->appendInlineNodes($inlines, [$node]);
                continue;
            }
            $inlines[] = $node;
        }
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function appendTextToken(array &$inlines, string $text, bool $attachToPrevious): void
    {
        if ($text === '') {
            return;
        }

        $this->appendInlineNode($inlines, $this->textNode($text), $attachToPrevious);
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function appendInlineNode(array &$inlines, AstNode $node, bool $attachToPrevious): void
    {
        if (!$attachToPrevious && $inlines !== [] && !$this->lastSuppressesSpace($inlines)) {
            $this->appendRawText($inlines, ' ');
        }
        if ($node->type === 'text') {
            $this->appendRawText($inlines, (string) $node->attr('text', ''));
            return;
        }

        $inlines[] = $node;
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function lastSuppressesSpace(array $inlines): bool
    {
        $last = $inlines[count($inlines) - 1] ?? null;
        if (!$last instanceof AstNode || $last->type !== 'text') {
            return false;
        }

        $text = (string) $last->attr('text', '');

        return $text === '' || str_ends_with($text, ' ') || str_ends_with($text, '[') || str_ends_with($text, '(');
    }

    /**
     * @param list<AstNode> $inlines
     */
    private function appendRawText(array &$inlines, string $text): void
    {
        $last = $inlines[count($inlines) - 1] ?? null;
        if ($last instanceof AstNode && $last->type === 'text') {
            array_pop($inlines);
            $inlines[] = $this->textNode((string) $last->attr('text', '') . $text);
            return;
        }

        $inlines[] = $this->textNode($text);
    }

    private function isClosingPunctuation(string $token): bool
    {
        if ($token === '...') {
            return false;
        }

        return preg_match('/^[\\]\\)\\.,;:!?]+$/', $token) === 1;
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
                $coalesced[] = $this->textNode((string) $last->attr('text', '') . (string) $inline->attr('text', ''));
                continue;
            }
            $coalesced[] = $inline;
        }

        return $coalesced;
    }

    private function isCommentLine(string $trimmed): bool
    {
        return str_starts_with($trimmed, '."') || str_starts_with($trimmed, '.\\"');
    }

    /**
     * @return array{0:string, 1:string}|null
     */
    private function macroLine(string $line): ?array
    {
        if (preg_match('/^\\.([A-Za-z][A-Za-z0-9]*)(?:\\s+(.*))?$/', $line, $match) !== 1) {
            return null;
        }

        return [(string) $match[1], (string) ($match[2] ?? '')];
    }

    private function isBlockBoundaryMacro(string $name): bool
    {
        return in_array($name, ['Sh', 'Ss', 'Bl', 'El', 'Ed', 'It'], true);
    }

    private function isParagraphBreakMacro(string $name): bool
    {
        return in_array($name, ['Pp', 'Lp', 'br', 'sp'], true);
    }

    private function isMetadataMacro(string $name): bool
    {
        return in_array($name, ['Dd', 'Dt', 'Os', 'At', 'Bx', 'Ux'], true);
    }

    private function captureMetadata(string $name, string $rawArgs): void
    {
        if ($name !== 'Dt') {
            return;
        }
        $args = $this->parseArgs($rawArgs);
        $title = (string) ($args[0] ?? '');
        if ($title !== '') {
            $this->documentName = strtolower($title);
        }
    }

    private function documentNameForDisplay(): string
    {
        return $this->documentName === '' ? 'Nm' : $this->documentName;
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

    private function decodeRoffEscapes(string $source): string
    {
        $output = '';
        $length = strlen($source);
        for ($i = 0; $i < $length; ++$i) {
            if ($source[$i] !== '\\') {
                $output .= $source[$i];
                continue;
            }
            $output .= $this->escapeText($source, $i);
        }

        return $output;
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

            return match ($name) {
                'lq' => "\u{201C}",
                'rq' => "\u{201D}",
                default => $name,
            };
        }
        if ($next === '*' && $offset + 3 < $length) {
            $name = substr($source, $offset + 2, 2);
            $offset += 3;

            return match ($name) {
                'lq' => "\u{201C}",
                'rq' => "\u{201D}",
                default => $name,
            };
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
            default => $name,
        };
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
