<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class RtfReader
{
    /** @var list<AstNode> */
    private array $blocks = [];

    /** @var list<AstNode> */
    private array $inlines = [];

    /** @var list<array{bold:bool, italic:bool, underline:bool, strike:bool, skip:bool, ucSkip:int}> */
    private array $stack = [];

    /** @var array{bold:bool, italic:bool, underline:bool, strike:bool, skip:bool, ucSkip:int} */
    private array $state = [
        'bold' => false,
        'italic' => false,
        'underline' => false,
        'strike' => false,
        'skip' => false,
        'ucSkip' => 1,
    ];

    private int $skipFallbackBytes = 0;

    private int $groupDepth = 0;

    private bool $firstControlInGroup = false;

    /** @var array<string, true> */
    private const DESTINATION_WORDS = [
        'aftncn' => true,
        'aftnsep' => true,
        'aftnsepc' => true,
        'annotation' => true,
        'atnauthor' => true,
        'atnid' => true,
        'atnparent' => true,
        'atnref' => true,
        'atntime' => true,
        'colortbl' => true,
        'datastore' => true,
        'doccomm' => true,
        'fonttbl' => true,
        'footer' => true,
        'footerf' => true,
        'footerl' => true,
        'footerr' => true,
        'footnote' => true,
        'ftncn' => true,
        'ftnsep' => true,
        'ftnsepc' => true,
        'generator' => true,
        'header' => true,
        'headerf' => true,
        'headerl' => true,
        'headerr' => true,
        'info' => true,
        'object' => true,
        'pict' => true,
        'stylesheet' => true,
        'themedata' => true,
        'xmlnstbl' => true,
    ];

    /** @var array<string, true> */
    private const IGNORABLE_SYMBOL_DESTINATIONS = [
        '*' => true,
    ];

    public function read(string $rtf): AstNode
    {
        $this->blocks = [];
        $this->inlines = [];
        $this->stack = [];
        $this->state = [
            'bold' => false,
            'italic' => false,
            'underline' => false,
            'strike' => false,
            'skip' => false,
            'ucSkip' => 1,
        ];
        $this->skipFallbackBytes = 0;
        $this->groupDepth = 0;
        $this->firstControlInGroup = false;

        $length = strlen($rtf);
        for ($index = 0; $index < $length; $index++) {
            $char = $rtf[$index];
            if ($this->skipFallbackBytes > 0) {
                if ($char === '\\') {
                    $index = $this->skipControlFallback($rtf, $index);
                } else {
                    $this->skipFallbackBytes--;
                }
                continue;
            }

            if ($char === '{') {
                $this->pushGroup();
                continue;
            }

            if ($char === '}') {
                $this->popGroup();
                continue;
            }

            if ($char === '\\') {
                $index = $this->consumeControl($rtf, $index);
                continue;
            }

            if ($char === "\r" || $char === "\n") {
                continue;
            }

            $this->appendText($char);
        }
        $this->flushParagraph();

        return new AstNode('document', [
            'source' => 'rtf',
            'format' => 'rtf',
            'reader' => self::class,
        ], $this->blocks);
    }

    private function pushGroup(): void
    {
        $this->stack[] = $this->state;
        $this->groupDepth++;
        $this->firstControlInGroup = true;
    }

    private function popGroup(): void
    {
        if ($this->stack === []) {
            return;
        }

        $this->state = array_pop($this->stack);
        $this->groupDepth = max(0, $this->groupDepth - 1);
        $this->firstControlInGroup = false;
    }

    private function consumeControl(string $rtf, int $index): int
    {
        $length = strlen($rtf);
        if ($index + 1 >= $length) {
            return $index;
        }

        $next = $rtf[$index + 1];
        if (!ctype_alpha($next)) {
            return $this->consumeControlSymbol($rtf, $index, $next);
        }

        $cursor = $index + 1;
        while ($cursor < $length && ctype_alpha($rtf[$cursor])) {
            $cursor++;
        }

        $word = strtolower(substr($rtf, $index + 1, $cursor - $index - 1));
        $negative = false;
        if ($cursor < $length && $rtf[$cursor] === '-') {
            $negative = true;
            $cursor++;
        }

        $digitsStart = $cursor;
        while ($cursor < $length && ctype_digit($rtf[$cursor])) {
            $cursor++;
        }

        $parameter = null;
        if ($cursor > $digitsStart) {
            $parameter = (int) substr($rtf, $digitsStart, $cursor - $digitsStart);
            if ($negative) {
                $parameter *= -1;
            }
        }

        $hasDelimiter = $cursor < $length && $rtf[$cursor] === ' ';
        $this->handleControlWord($word, $parameter);
        $this->firstControlInGroup = false;

        return $hasDelimiter ? $cursor : $cursor - 1;
    }

    private function consumeControlSymbol(string $rtf, int $index, string $symbol): int
    {
        if (isset(self::IGNORABLE_SYMBOL_DESTINATIONS[$symbol]) && $this->firstControlInGroup) {
            $this->state['skip'] = true;
            $this->firstControlInGroup = false;
            return $index + 1;
        }

        if ($symbol === "'") {
            $hex = substr($rtf, $index + 2, 2);
            if (preg_match('/^[0-9a-fA-F]{2}$/', $hex) === 1) {
                $decoded = UnicodeText::decodeBytes(hex2bin($hex) ?: '', 'windows-1252');
                $this->appendText($decoded['text']);
                $this->firstControlInGroup = false;
                return $index + 3;
            }
        }

        match ($symbol) {
            '\\', '{', '}' => $this->appendText($symbol),
            '~' => $this->appendText("\u{00A0}"),
            '-' => $this->appendText("\u{00AD}"),
            '_' => $this->appendText("\u{2011}"),
            default => null,
        };
        $this->firstControlInGroup = false;

        return $index + 1;
    }

    private function handleControlWord(string $word, ?int $parameter): void
    {
        if ($this->firstControlInGroup && isset(self::DESTINATION_WORDS[$word])) {
            $this->state['skip'] = true;
            return;
        }

        if ($this->state['skip']) {
            return;
        }

        switch ($word) {
            case 'rtf':
            case 'ansi':
            case 'ansicpg':
            case 'deff':
            case 'deflang':
            case 'f':
            case 'fs':
            case 'plain':
            case 'ql':
            case 'qr':
            case 'qc':
            case 'qj':
            case 'li':
            case 'ri':
            case 'fi':
            case 'sa':
            case 'sb':
                if ($word === 'plain') {
                    $this->state['bold'] = false;
                    $this->state['italic'] = false;
                    $this->state['underline'] = false;
                    $this->state['strike'] = false;
                }
                return;

            case 'pard':
                $this->flushParagraph();
                return;

            case 'b':
                $this->state['bold'] = $parameter !== 0;
                return;

            case 'i':
                $this->state['italic'] = $parameter !== 0;
                return;

            case 'ul':
                $this->state['underline'] = $parameter !== 0;
                return;

            case 'ulnone':
                $this->state['underline'] = false;
                return;

            case 'strike':
                $this->state['strike'] = $parameter !== 0;
                return;

            case 'par':
                $this->flushParagraph();
                return;

            case 'line':
                $this->appendLineBreak();
                return;

            case 'tab':
                $this->appendText("\t");
                return;

            case 'uc':
                $this->state['ucSkip'] = max(0, $parameter ?? 1);
                return;

            case 'u':
                $this->appendUnicodeScalar($parameter ?? 0);
                $this->skipFallbackBytes = $this->state['ucSkip'];
                return;
        }
    }

    private function appendUnicodeScalar(int $value): void
    {
        if ($value < 0) {
            $value += 65536;
        }

        if ($value < 0 || $value > 0x10FFFF || ($value >= 0xD800 && $value <= 0xDFFF)) {
            $this->appendText("\u{FFFD}");
            return;
        }

        $this->appendText(mb_chr($value, 'UTF-8'));
    }

    private function skipControlFallback(string $rtf, int $index): int
    {
        $length = strlen($rtf);
        if ($index + 1 >= $length) {
            $this->skipFallbackBytes--;
            return $index;
        }

        if ($rtf[$index + 1] === "'"
            && $index + 3 < $length
            && preg_match('/^[0-9a-fA-F]{2}$/', substr($rtf, $index + 2, 2)) === 1
        ) {
            $this->skipFallbackBytes--;
            return $index + 3;
        }

        $this->skipFallbackBytes--;
        return $index + 1;
    }

    private function appendText(string $text): void
    {
        if ($text === '' || $this->state['skip']) {
            return;
        }

        $node = new AstNode('text', ['text' => $text]);
        if ($this->state['strike']) {
            $node = new AstNode('strikeout', ['source' => 'rtf'], [$node]);
        }
        if ($this->state['underline']) {
            $node = new AstNode('underline', ['source' => 'rtf'], [$node]);
        }
        if ($this->state['italic']) {
            $node = new AstNode('emph', ['source' => 'rtf'], [$node]);
        }
        if ($this->state['bold']) {
            $node = new AstNode('strong', ['source' => 'rtf'], [$node]);
        }

        $this->inlines[] = $node;
    }

    private function appendLineBreak(): void
    {
        if (!$this->state['skip']) {
            $this->inlines[] = new AstNode('linebreak');
        }
    }

    private function flushParagraph(): void
    {
        if ($this->inlines === []) {
            return;
        }

        if (trim($this->plainText($this->inlines)) === '') {
            $this->inlines = [];
            return;
        }

        $this->blocks[] = new AstNode('paragraph', ['source' => 'rtf'], $this->mergeAdjacentText($this->inlines));
        $this->inlines = [];
    }

    /**
     * @param list<AstNode> $nodes
     */
    private function plainText(array $nodes): string
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
            $text .= $this->plainText($node->children);
        }

        return $text;
    }

    /**
     * @param list<AstNode> $nodes
     * @return list<AstNode>
     */
    private function mergeAdjacentText(array $nodes): array
    {
        $merged = [];
        foreach ($nodes as $node) {
            if ($node->children !== []) {
                $node = new AstNode($node->type, $node->attrs, $this->mergeAdjacentText($node->children));
            }

            $last = $merged[array_key_last($merged) ?? -1] ?? null;
            if ($last instanceof AstNode && $last->type === 'text' && $node->type === 'text') {
                array_pop($merged);
                $merged[] = new AstNode('text', ['text' => (string) $last->attr('text', '') . (string) $node->attr('text', '')]);
                continue;
            }
            if ($last instanceof AstNode
                && $last->type === $node->type
                && $last->attrs === $node->attrs
                && $last->children !== []
                && $node->children !== []
            ) {
                array_pop($merged);
                $merged[] = new AstNode(
                    $last->type,
                    $last->attrs,
                    $this->mergeAdjacentText([...$last->children, ...$node->children])
                );
                continue;
            }
            $merged[] = $node;
        }

        return $merged;
    }
}
