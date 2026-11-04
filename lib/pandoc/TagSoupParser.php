<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class TagSoupParser
{
    private string $source = '';
    private int $length = 0;
    private int $offset = 0;
    private int $row = 1;
    private int $column = 1;
    private TagSoupParseOptions $options;

    /** @var list<TagSoupTag>|TagSoupTokenStream */
    private array|TagSoupTokenStream $tokens = [];

    /**
     * @return list<TagSoupTag>
     */
    public function parse(string $html, ?TagSoupParseOptions $options = null): array
    {
        $tokens = $this->parseInto($html, $options, false);
        if (!is_array($tokens)) {
            throw new \LogicException('Array token parsing did not produce an array.');
        }

        return $tokens;
    }

    /**
     * @return TagSoupTokenStream
     */
    public function parseCanonicalStream(string $html, ?TagSoupParseOptions $options = null): TagSoupTokenStream
    {
        $options ??= TagSoupParseOptions::defaults();
        $tokens = $this->parseInto($html, $options->withCanonicalizedTags(), true);
        if (!$tokens instanceof TagSoupTokenStream) {
            throw new \LogicException('Compact token parsing did not produce a token stream.');
        }

        return $tokens;
    }

    /**
     * @return list<TagSoupTag>|TagSoupTokenStream
     */
    private function parseInto(string $html, ?TagSoupParseOptions $options, bool $compactTokens): array|TagSoupTokenStream
    {
        $this->source = $html;
        $this->length = strlen($html);
        $this->offset = 0;
        $this->row = 1;
        $this->column = 1;
        $this->tokens = $compactTokens ? new TagSoupTokenStream() : [];
        $this->options = $options ?? TagSoupParseOptions::defaults();

        while ($this->offset < $this->length) {
            if ($this->source[$this->offset] === '<') {
                if ($this->parseMarkup()) {
                    continue;
                }

                $row = $this->row;
                $column = $this->column;
                $this->advance(1);
                $this->emit(TagSoupTag::text('<'), $row, $column);
                continue;
            }

            $this->parseText();
        }

        return $this->tokens;
    }

    /**
     * Parse directly into the tag form consumed by the Pandoc-compatible HTML
     * readers. This avoids retaining a second full token array just to lower
     * tag and attribute names after tokenization.
     *
     * @return list<TagSoupTag>
     */
    public function parseCanonical(string $html, ?TagSoupParseOptions $options = null): array
    {
        $options ??= TagSoupParseOptions::defaults();

        return $this->parse($html, $options->withCanonicalizedTags());
    }

    /**
     * @param list<TagSoupTag> $tokens
     * @return list<TagSoupTag>
     */
    public static function canonicalizeTags(array $tokens): array
    {
        $canonical = [];
        foreach ($tokens as $token) {
            if ($token->type === TagSoupTag::OPEN) {
                $canonical[] = TagSoupTag::open(
                    self::canonicalOpenTagName($token->name),
                    self::canonicalAttributes($token->attributes),
                );
                continue;
            }

            if ($token->type === TagSoupTag::CLOSE) {
                $canonical[] = TagSoupTag::close(self::canonicalCloseTagName($token->name));
                continue;
            }

            $canonical[] = $token;
        }

        return $canonical;
    }

    private static function stripTagNamespacePrefix(string $name): string
    {
        $colon = strrpos($name, ':');

        return $colon === false ? $name : substr($name, $colon + 1);
    }

    private static function canonicalOpenTagName(string $name): string
    {
        if (str_starts_with($name, '!')) {
            $name = '!' . strtoupper(substr($name, 1));
        } else {
            $name = strtolower($name);
        }

        return self::stripTagNamespacePrefix($name);
    }

    private static function canonicalCloseTagName(string $name): string
    {
        return self::stripTagNamespacePrefix(strtolower($name));
    }

    /**
     * @param list<array{name:string,value:string}> $attributes
     * @return list<array{name:string,value:string}>
     */
    private static function canonicalAttributes(array $attributes): array
    {
        if ($attributes === []) {
            return [];
        }

        $canonical = [];
        foreach ($attributes as $attribute) {
            $canonical[] = [
                'name' => strtolower($attribute['name']),
                'value' => $attribute['value'],
            ];
        }

        return $canonical;
    }

    private function parseText(): void
    {
        $start = $this->offset;
        $row = $this->row;
        $column = $this->column;
        while ($this->offset < $this->length && $this->source[$this->offset] !== '<') {
            $this->advance(1);
        }

        $raw = substr($this->source, $start, $this->offset - $start);
        $text = $this->options->decodeEntities ? $this->decodeEntities($raw) : $raw;
        $this->emit(TagSoupTag::text($text), $row, $column);
    }

    private function parseMarkup(): bool
    {
        if ($this->offset + 1 >= $this->length) {
            return false;
        }

        $next = $this->source[$this->offset + 1];
        if ($next === '!') {
            if (substr($this->source, $this->offset, 4) === '<!--') {
                $this->parseComment();
                return true;
            }

            if (substr($this->source, $this->offset, 9) === '<![CDATA[') {
                $this->parseCdata();
                return true;
            }

            if ($this->offset + 2 < $this->length && self::isAsciiAlpha($this->source[$this->offset + 2])) {
                $this->parseOpenTag('!', false);
                return true;
            }

            $this->parseBogusComment(2);
            return true;
        }

        if ($next === '?') {
            if ($this->offset + 2 < $this->length && self::isAsciiAlpha($this->source[$this->offset + 2])) {
                $this->parseOpenTag('?', true);
                return true;
            }

            return false;
        }

        if ($next === '/') {
            if ($this->offset + 2 < $this->length) {
                $nameStart = $this->source[$this->offset + 2];
                if (self::isAsciiAlpha($nameStart) || $nameStart === '?' || $nameStart === '!') {
                    $this->parseCloseTag();
                    return true;
                }

                if ($nameStart === '>') {
                    $row = $this->row;
                    $column = $this->column;
                    $this->advance(3);
                    $this->warn('Unexpected "</>"', $row, $column);
                    $this->emit(TagSoupTag::text('</>'), $row, $column);
                    return true;
                }
            }

            $this->parseBogusComment(2);
            return true;
        }

        if ($next === '>') {
            $row = $this->row;
            $column = $this->column;
            $this->advance(2);
            $this->warn('Unexpected "<>"', $row, $column);
            $this->emit(TagSoupTag::text('<>'), $row, $column);
            return true;
        }

        if (self::isAsciiAlpha($next)) {
            $this->parseOpenTag('', false);
            return true;
        }

        return false;
    }

    private function parseComment(): void
    {
        $row = $this->row;
        $column = $this->column;
        $end = strpos($this->source, '-->', $this->offset + 4);
        if ($end === false) {
            $text = substr($this->source, $this->offset + 4);
            $this->advance($this->length - $this->offset);
            $this->warn('Expected "-->"', $row, $column);
            $this->emit(TagSoupTag::comment($text), $row, $column);
            return;
        }

        $text = substr($this->source, $this->offset + 4, $end - ($this->offset + 4));
        $this->advance($end + 3 - $this->offset);
        $this->emit(TagSoupTag::comment($text), $row, $column);
    }

    private function parseCdata(): void
    {
        $row = $this->row;
        $column = $this->column;
        $end = strpos($this->source, ']]>', $this->offset + 9);
        if ($end === false) {
            $text = substr($this->source, $this->offset + 9);
            $this->advance($this->length - $this->offset);
            $this->emit(TagSoupTag::text($text), $row, $column);
            return;
        }

        $text = substr($this->source, $this->offset + 9, $end - ($this->offset + 9));
        $this->advance($end + 3 - $this->offset);
        $this->emit(TagSoupTag::text($text), $row, $column);
    }

    private function parseBogusComment(int $afterOpenBytes): void
    {
        $row = $this->row;
        $column = $this->column;
        $end = strpos($this->source, '>', $this->offset + $afterOpenBytes);
        if ($end === false) {
            $text = substr($this->source, $this->offset + $afterOpenBytes);
            $this->advance($this->length - $this->offset);
        } else {
            $text = substr($this->source, $this->offset + $afterOpenBytes, $end - ($this->offset + $afterOpenBytes));
            $this->advance($end + 1 - $this->offset);
        }

        $this->warn('Expected tag name', $row, $column);
        $this->emit(TagSoupTag::comment($text), $row, $column);
    }

    private function parseOpenTag(string $prefix, bool $xml): void
    {
        $row = $this->row;
        $column = $this->column;
        $cursor = $this->offset + 1 + ($prefix === '' ? 0 : 1);
        $nameStart = $cursor;
        while ($cursor < $this->length) {
            $char = $this->source[$cursor];
            if (self::isHtmlSpace($char) || $char === '/' || $char === '>' || ($xml && $char === '?')) {
                break;
            }
            $cursor++;
        }

        $name = $prefix . substr($this->source, $nameStart, $cursor - $nameStart);
        [$attributes, $cursor, $selfClosing] = $this->parseAttributes($cursor, $xml, $prefix !== '');
        $this->advance($cursor - $this->offset);
        $tagName = $this->options->canonicalizeTags ? self::canonicalOpenTagName($name) : $name;
        if ($this->options->canonicalizeTags) {
            $attributes = self::canonicalAttributes($attributes);
        }
        $this->emit(TagSoupTag::open($tagName, $attributes), $row, $column);
        if ($selfClosing) {
            $this->emit(TagSoupTag::close($this->options->canonicalizeTags ? self::canonicalCloseTagName($name) : $name), $row, $column);
            return;
        }

        $rawTextName = strtolower($name);
        if (in_array($rawTextName, ['script', 'style', 'textarea', 'xmp'], true)) {
            $this->parseRawTextBody($rawTextName);
        }
    }

    /**
     * @return array{0:list<array{name:string,value:string}>,1:int,2:bool}
     */
    private function parseAttributes(int $cursor, bool $xml, bool $allowEmptyNameAttributes): array
    {
        $attributes = [];
        while ($cursor < $this->length) {
            while ($cursor < $this->length && self::isHtmlSpace($this->source[$cursor])) {
                $cursor++;
            }

            if ($cursor >= $this->length) {
                $this->warn('Expected ">"', $this->row, $this->column);
                return [$attributes, $cursor, false];
            }

            $char = $this->source[$cursor];
            if ($char === '>') {
                return [$attributes, $cursor + 1, false];
            }

            if ($char === '/' && $cursor + 1 < $this->length && $this->source[$cursor + 1] === '>') {
                return [$attributes, $cursor + 2, true];
            }

            if ($xml && $char === '?' && $cursor + 1 < $this->length && $this->source[$cursor + 1] === '>') {
                return [$attributes, $cursor + 2, false];
            }

            if ($allowEmptyNameAttributes && ($char === '"' || $char === "'")) {
                [$value, $cursor] = $this->parseAttributeValue($cursor);
                $attributes[] = [
                    'name' => '',
                    'value' => $this->options->decodeEntities ? $this->decodeEntities($value) : $value,
                ];
                continue;
            }

            $nameStart = $cursor;
            while ($cursor < $this->length) {
                $char = $this->source[$cursor];
                if (self::isHtmlSpace($char) || $char === '/' || $char === '>' || $char === '=' || ($xml && $char === '?')) {
                    break;
                }
                $cursor++;
            }
            $name = substr($this->source, $nameStart, $cursor - $nameStart);
            if ($name === '') {
                $this->warn('Unexpected attribute character', $this->row, $this->column);
                $cursor++;
                continue;
            }

            while ($cursor < $this->length && self::isHtmlSpace($this->source[$cursor])) {
                $cursor++;
            }

            $value = '';
            if ($cursor < $this->length && $this->source[$cursor] === '=') {
                $cursor++;
                while ($cursor < $this->length && self::isHtmlSpace($this->source[$cursor])) {
                    $cursor++;
                }
                [$value, $cursor] = $this->parseAttributeValue($cursor);
            }

            $attributes[] = [
                'name' => $name,
                'value' => $this->options->decodeEntities ? $this->decodeEntities($value) : $value,
            ];
        }

        return [$attributes, $cursor, false];
    }

    /**
     * @return array{0:string,1:int}
     */
    private function parseAttributeValue(int $cursor): array
    {
        if ($cursor >= $this->length) {
            return ['', $cursor];
        }

        $quote = $this->source[$cursor];
        if ($quote === '"' || $quote === "'") {
            $cursor++;
            $start = $cursor;
            while ($cursor < $this->length && $this->source[$cursor] !== $quote) {
                $cursor++;
            }
            $value = substr($this->source, $start, $cursor - $start);
            if ($cursor < $this->length) {
                $cursor++;
            }

            return [$value, $cursor];
        }

        $start = $cursor;
        while ($cursor < $this->length) {
            $char = $this->source[$cursor];
            if (self::isHtmlSpace($char) || $char === '>') {
                break;
            }
            if ($char === '/' && $cursor + 1 < $this->length && $this->source[$cursor + 1] === '>') {
                break;
            }
            $cursor++;
        }

        return [substr($this->source, $start, $cursor - $start), $cursor];
    }

    private function parseCloseTag(): void
    {
        $row = $this->row;
        $column = $this->column;
        $cursor = $this->offset + 2;
        $nameStart = $cursor;
        while ($cursor < $this->length) {
            $char = $this->source[$cursor];
            if (self::isHtmlSpace($char) || $char === '/' || $char === '>') {
                break;
            }
            $cursor++;
        }

        $name = substr($this->source, $nameStart, $cursor - $nameStart);
        $end = strpos($this->source, '>', $cursor);
        if ($end === false) {
            $this->warn('Expected ">"', $row, $column);
            $this->advance($this->length - $this->offset);
        } else {
            $this->advance($end + 1 - $this->offset);
        }

        $this->emit(TagSoupTag::close($this->options->canonicalizeTags ? self::canonicalCloseTagName($name) : $name), $row, $column);
    }

    private function parseRawTextBody(string $name): void
    {
        if ($this->offset >= $this->length) {
            return;
        }

        $lower = strtolower($this->source);
        $close = strpos($lower, '</' . $name, $this->offset);
        if ($close === false) {
            $row = $this->row;
            $column = $this->column;
            $text = substr($this->source, $this->offset);
            $this->advance($this->length - $this->offset);
            $this->emit(TagSoupTag::text($text), $row, $column);
            return;
        }

        if ($close > $this->offset) {
            $row = $this->row;
            $column = $this->column;
            $text = substr($this->source, $this->offset, $close - $this->offset);
            $this->advance($close - $this->offset);
            $this->emit(TagSoupTag::text($text), $row, $column);
        }
    }

    private function decodeEntities(string $text): string
    {
        $result = '';
        $length = strlen($text);
        $cursor = 0;
        while ($cursor < $length) {
            $char = $text[$cursor];
            if ($char !== '&') {
                $result .= $char;
                $cursor++;
                continue;
            }

            if ($cursor + 1 >= $length || str_contains("\t\n\f <&", $text[$cursor + 1])) {
                $result .= '&';
                $cursor++;
                continue;
            }

            if ($text[$cursor + 1] === '#') {
                [$entity, $next, $hadSemicolon] = $this->readNumericEntity($text, $cursor);
                if ($entity === null) {
                    $this->warn('Unexpected "&"', $this->row, $this->column);
                    $result .= '&';
                    $cursor++;
                    continue;
                }

                $decoded = TagSoupEntity::lookup($entity);
                if ($decoded === null) {
                    $this->warn('Unknown entity: ' . $entity, $this->row, $this->column);
                    $result .= '&' . $entity . ($hadSemicolon ? ';' : '');
                } else {
                    if (!$hadSemicolon) {
                        $this->warn('Expected ";"', $this->row, $this->column);
                    }
                    $result .= $decoded;
                }
                $cursor = $next;
                continue;
            }

            if (!self::isAsciiAlpha($text[$cursor + 1])) {
                $this->warn('Unexpected "&"', $this->row, $this->column);
                $result .= '&';
                $cursor++;
                continue;
            }

            [$name, $next, $hadSemicolon] = $this->readNamedEntity($text, $cursor);
            $lookup = $name . ($hadSemicolon ? ';' : '');
            $decoded = TagSoupEntity::lookup($lookup);
            if ($decoded === null) {
                $this->warn('Unknown entity: ' . $lookup, $this->row, $this->column);
                $result .= '&' . $lookup;
            } else {
                if (!$hadSemicolon) {
                    $this->warn('Expected ";"', $this->row, $this->column);
                }
                $result .= $decoded;
            }
            $cursor = $next;
        }

        return $result;
    }

    /**
     * @return array{0:?string,1:int,2:bool}
     */
    private function readNumericEntity(string $text, int $ampOffset): array
    {
        $cursor = $ampOffset + 2;
        $length = strlen($text);
        $hex = false;
        if ($cursor < $length && ($text[$cursor] === 'x' || $text[$cursor] === 'X')) {
            $hex = true;
            $cursor++;
        }

        $digitsStart = $cursor;
        while ($cursor < $length && ($hex ? ctype_xdigit($text[$cursor]) : ctype_digit($text[$cursor]))) {
            $cursor++;
        }

        if ($cursor === $digitsStart) {
            return [null, $ampOffset + 1, false];
        }

        $hadSemicolon = $cursor < $length && $text[$cursor] === ';';
        $entity = '#' . ($hex ? 'x' : '') . substr($text, $digitsStart, $cursor - $digitsStart);

        return [$entity, $hadSemicolon ? $cursor + 1 : $cursor, $hadSemicolon];
    }

    /**
     * @return array{0:string,1:int,2:bool}
     */
    private function readNamedEntity(string $text, int $ampOffset): array
    {
        $cursor = $ampOffset + 1;
        $length = strlen($text);
        $start = $cursor;
        while ($cursor < $length && self::isEntityNameChar($text[$cursor])) {
            $cursor++;
        }

        $hadSemicolon = $cursor < $length && $text[$cursor] === ';';

        return [
            substr($text, $start, $cursor - $start),
            $hadSemicolon ? $cursor + 1 : $cursor,
            $hadSemicolon,
        ];
    }

    private function emit(TagSoupTag $token, int $row, int $column): void
    {
        if ($token->type === TagSoupTag::TEXT && $token->text === '') {
            return;
        }

        if ($this->tokens instanceof TagSoupTokenStream) {
            if ($this->options->includePositions && $token->type !== TagSoupTag::POSITION) {
                $this->tokens->append(TagSoupTag::position($row, $column));
            }

            if ($this->options->mergeAdjacentText && $token->type === TagSoupTag::TEXT && !$this->tokens->isEmpty()) {
                $last = $this->tokens->lastToken();
                if ($last instanceof TagSoupTag && $last->type === TagSoupTag::TEXT) {
                    $this->tokens->replaceLast(TagSoupTag::text($last->text . $token->text));
                    return;
                }
            }

            $this->tokens->append($token);

            return;
        }

        if ($this->options->includePositions && $token->type !== TagSoupTag::POSITION) {
            $this->tokens[] = TagSoupTag::position($row, $column);
        }

        if ($this->options->mergeAdjacentText && $token->type === TagSoupTag::TEXT && $this->tokens !== []) {
            $lastIndex = array_key_last($this->tokens);
            $last = $lastIndex === null ? null : $this->tokens[$lastIndex];
            if ($last instanceof TagSoupTag && $last->type === TagSoupTag::TEXT) {
                $this->tokens[$lastIndex] = TagSoupTag::text($last->text . $token->text);
                return;
            }
        }

        $this->tokens[] = $token;
    }

    private function warn(string $message, int $row, int $column): void
    {
        if ($this->options->includeWarnings) {
            $this->emit(TagSoupTag::warning($message), $row, $column);
        }
    }

    private function advance(int $bytes): void
    {
        $end = min($this->length, $this->offset + $bytes);
        while ($this->offset < $end) {
            $char = $this->source[$this->offset];
            if ($char === "\n") {
                $this->row++;
                $this->column = 1;
            } elseif ($char === "\t") {
                $this->column += 8 - (($this->column - 1) % 8);
            } else {
                $this->column++;
            }
            $this->offset++;
        }
    }

    private static function isHtmlSpace(string $char): bool
    {
        return $char === ' ' || $char === "\t" || $char === "\n" || $char === "\f" || $char === "\r";
    }

    private static function isAsciiAlpha(string $char): bool
    {
        $ord = ord($char);

        return ($ord >= 65 && $ord <= 90) || ($ord >= 97 && $ord <= 122);
    }

    private static function isEntityNameChar(string $char): bool
    {
        return self::isAsciiAlpha($char)
            || ctype_digit($char)
            || $char === ':'
            || $char === '-'
            || $char === '_';
    }
}
