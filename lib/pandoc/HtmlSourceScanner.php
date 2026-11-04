<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Source-level HTML boundary scanner for Markdown's raw HTML blocks.
 *
 * This is deliberately not an HTML tree builder. It only finds a matching
 * element boundary while honoring comment, declaration, and raw-text
 * contexts, so source delimiters such as </div> inside a script string do
 * not become structural tags.
 */
final class HtmlSourceScanner
{
    /** @var list<string> */
    private const RAW_TEXT_ELEMENT_NAMES = [
        'iframe',
        'noembed',
        'noframes',
        'noscript',
        'plaintext',
        'script',
        'style',
        'textarea',
        'title',
        'xmp',
    ];

    /**
     * @return array{openStart:int,openEnd:int,closeStart:int,closeEnd:int}|null
     */
    public static function matchingElementBounds(string $source, string $name, int $offset = 0): ?array
    {
        $offset = max(0, $offset);
        $state = self::newElementBoundaryState($name, $offset);

        return self::feedElementBoundaryState($state, substr($source, $offset));
    }

    /**
     * Return every properly matched boundary for one element name in source.
     * Bounds are ordered by opening position, and use source byte offsets.
     *
     * @return list<array{openStart:int,openEnd:int,closeStart:int,closeEnd:int}>
     */
    public static function matchingElementBoundsAll(string $source, string $name, int $offset = 0): array
    {
        $offset = max(0, $offset);
        $state = self::newElementBoundaryState($name, $offset, true);
        self::feedElementBoundaryState($state, substr($source, $offset));

        $matches = $state['matches'];
        usort($matches, static fn (array $left, array $right): int => $left['openStart'] <=> $right['openStart']);

        return $matches;
    }

    /**
     * Find the inclusive byte offset of a markup declaration's closing `>`.
     *
     * Unlike a marker search this honours quoted values, DTD internal subsets,
     * and comments inside those subsets. The caller is responsible for first
     * confirming that `$offset` points at `<!`.
     */
    public static function declarationEndOffset(string $source, int $offset = 0): ?int
    {
        $offset = max(0, $offset);
        if (substr($source, $offset, 2) !== '<!') {
            return null;
        }

        $state = [
            'mode' => 'declaration',
            'declarationQuote' => null,
            'declarationSubsetDepth' => 0,
            'declarationRecent' => '',
            'markerTail' => '',
        ];
        $length = strlen($source);
        for ($position = $offset + 2; $position < $length; $position++) {
            self::consumeElementBoundaryDeclaration($state, $source[$position]);
            if ($state['mode'] === 'normal') {
                return $position;
            }
        }

        return null;
    }

    /**
     * Find the line containing a markup declaration's closing `>` without
     * joining the remaining source lines. This is the declaration counterpart
     * to the streaming element-boundary collector.
     *
     * @param list<string> $lines
     * @param (callable(string): string)|null $normalizeLine
     */
    public static function declarationEndLineInLines(
        array $lines,
        int $start,
        ?callable $normalizeLine = null
    ): ?int {
        $start = max(0, $start);
        $count = count($lines);
        if ($start >= $count) {
            return null;
        }

        $first = $normalizeLine === null ? $lines[$start] : $normalizeLine($lines[$start]);
        $declarationStart = strpos($first, '<!');
        if ($declarationStart === false) {
            return null;
        }

        $state = [
            'mode' => 'declaration',
            'declarationQuote' => null,
            'declarationSubsetDepth' => 0,
            'declarationRecent' => '',
            'markerTail' => '',
        ];
        for ($index = $start; $index < $count; $index++) {
            $line = $index === $start
                ? $first
                : ($normalizeLine === null ? $lines[$index] : $normalizeLine($lines[$index]));
            $offset = $index === $start ? $declarationStart + 2 : 0;
            if ($index > $start) {
                self::consumeElementBoundaryDeclaration($state, "\n");
            }
            $length = strlen($line);
            for (; $offset < $length; $offset++) {
                self::consumeElementBoundaryDeclaration($state, $line[$offset]);
                if ($state['mode'] === 'normal') {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * Scan normalized source lines once and return only the matched source
     * prefix. This keeps callers from rebuilding and rescanning every
     * remaining line for each adjacent raw HTML block.
     *
     * @param list<string> $lines
     * @param (callable(string): string)|null $normalizeLine
     * @param (callable(string,int): bool)|null $stopBeforeLine
     * @return array{
     *     source:string,
     *     end:int,
     *     tail:string,
     *     bounds:array{openStart:int,openEnd:int,closeStart:int,closeEnd:int}
     * }|null
     */
    public static function matchingElementBoundsInLines(
        array $lines,
        int $start,
        string $name,
        ?callable $normalizeLine = null,
        ?callable $stopBeforeLine = null
    ): ?array {
        $start = max(0, $start);
        $state = self::newElementBoundaryState($name);
        $parts = [];
        $count = count($lines);

        for ($index = $start; $index < $count; $index++) {
            $line = $normalizeLine === null ? $lines[$index] : $normalizeLine($lines[$index]);
            if ($index > $start && $stopBeforeLine !== null && $stopBeforeLine($line, $index)) {
                return null;
            }
            $parts[] = $line;
            $chunkStart = $state['position'];
            $lineStart = $chunkStart + ($index === $start ? 0 : 1);
            $chunk = $index === $start ? $line : "\n" . $line;
            $bounds = self::feedElementBoundaryState($state, $chunk);
            if ($bounds === null) {
                if ($state['terminal']) {
                    return null;
                }
                continue;
            }

            $source = implode("\n", $parts);
            $closeColumn = $bounds['closeEnd'] - $lineStart;

            return [
                'source' => substr($source, 0, $bounds['closeEnd'] + 1),
                'end' => $index,
                'tail' => substr($line, $closeColumn + 1),
                'bounds' => $bounds,
            ];
        }

        return null;
    }

    /**
     * Materialize one indexed element's source prefix on demand.
     *
     * Boundary indexes intentionally retain only offsets, not a source copy
     * for every matching opening. A deeply nested document has one match per
     * line, so retaining each nested substring would turn a linear index into
     * quadratic memory. Callers materialize only the boundary they consume.
     *
     * @param list<string> $lines
     * @param (callable(string): string)|null $normalizeLine
     */
    public static function sourcePrefixInLines(
        array $lines,
        int $start,
        int $end,
        int $endOffset,
        ?callable $normalizeLine = null
    ): string {
        $start = max(0, $start);
        $end = min(count($lines) - 1, $end);
        if ($start > $end) {
            return '';
        }

        $parts = [];
        for ($index = $start; $index <= $end; $index++) {
            $parts[] = $normalizeLine === null ? $lines[$index] : $normalizeLine($lines[$index]);
        }

        return substr(implode("\n", $parts), 0, max(0, $endOffset) + 1);
    }

    /**
     * Build a one-pass index of matched source elements, keyed by the line
     * containing each opening tag. At most one boundary is retained per line:
     * the first opening there, which is the only one a line-oriented Markdown
     * reader can begin from. Source is materialized on demand rather than
     * retained once per match, preserving linear memory for nested elements.
     *
     * @param list<string> $lines
     * @param (callable(string): string)|null $normalizeLine
     * @return array<int, array{
     *     end:int,
     *     tail:string,
     *     bounds:array{openStart:int,openEnd:int,closeStart:int,closeEnd:int}
     * }>
     */
    public static function matchingElementBoundsInLinesByOpeningLine(
        array $lines,
        int $start,
        string $name,
        ?callable $normalizeLine = null
    ): array {
        $start = max(0, $start);
        $normalized = [];
        $lineOffsets = [];
        $position = 0;
        $count = count($lines);

        for ($index = $start; $index < $count; $index++) {
            $lineOffsets[] = $position;
            $line = $normalizeLine === null ? $lines[$index] : $normalizeLine($lines[$index]);
            $normalized[] = $line;
            $position += strlen($line) + 1;
        }

        if ($normalized === []) {
            return [];
        }

        $source = implode("\n", $normalized);
        $results = [];
        foreach (self::matchingElementBoundsAll($source, $name) as $bounds) {
            $openLine = self::sourceLineIndexForOffset($lineOffsets, $bounds['openStart']);
            $closeLine = self::sourceLineIndexForOffset($lineOffsets, $bounds['closeEnd']);
            $openLineStart = $lineOffsets[$openLine];
            $closeLineStart = $lineOffsets[$closeLine];
            $absoluteLine = $start + $openLine;
            if (isset($results[$absoluteLine])) {
                continue;
            }

            $relativeBounds = [
                'openStart' => $bounds['openStart'] - $openLineStart,
                'openEnd' => $bounds['openEnd'] - $openLineStart,
                'closeStart' => $bounds['closeStart'] - $openLineStart,
                'closeEnd' => $bounds['closeEnd'] - $openLineStart,
            ];
            $closeColumn = $bounds['closeEnd'] - $closeLineStart;
            $results[$absoluteLine] = [
                'end' => $start + $closeLine,
                'tail' => substr($normalized[$closeLine], $closeColumn + 1),
                'bounds' => $relativeBounds,
            ];
        }

        return $results;
    }

    /**
     * @return array{
     *     name:string,
     *     position:int,
     *     depth:int,
     *     openStart:?int,
     *     openEnd:?int,
     *     rawTextElement:?string,
     *     templateDepth:int,
     *     mode:string,
     *     tagStart:?int,
     *     tagBuffer:string,
     *     tagQuote:?string,
     *     tagRaw:bool,
     *     markerTail:string,
     *     declarationQuote:?string,
     *     declarationSubsetDepth:int,
     *     declarationRecent:string,
     *     terminal:bool,
     *     collectAll:bool,
     *     openStack:list<array{openStart:int,openEnd:int}>,
     *     matches:list<array{openStart:int,openEnd:int,closeStart:int,closeEnd:int}>
     * }
     */
    private static function newElementBoundaryState(string $name, int $position = 0, bool $collectAll = false): array
    {
        return [
            'name' => strtolower($name),
            'position' => $position,
            'depth' => 0,
            'openStart' => null,
            'openEnd' => null,
            'rawTextElement' => null,
            'templateDepth' => 0,
            'mode' => 'normal',
            'tagStart' => null,
            'tagBuffer' => '',
            'tagQuote' => null,
            'tagRaw' => false,
            'markerTail' => '',
            'declarationQuote' => null,
            'declarationSubsetDepth' => 0,
            'declarationRecent' => '',
            'terminal' => false,
            'collectAll' => $collectAll,
            'openStack' => [],
            'matches' => [],
        ];
    }

    /**
     * @param array{
     *     name:string,
     *     position:int,
     *     depth:int,
     *     openStart:?int,
     *     openEnd:?int,
     *     rawTextElement:?string,
     *     templateDepth:int,
     *     mode:string,
     *     tagStart:?int,
     *     tagBuffer:string,
     *     tagQuote:?string,
     *     tagRaw:bool,
     *     markerTail:string,
     *     declarationQuote:?string,
     *     declarationSubsetDepth:int,
     *     declarationRecent:string,
     *     terminal:bool,
     *     collectAll:bool,
     *     openStack:list<array{openStart:int,openEnd:int}>,
     *     matches:list<array{openStart:int,openEnd:int,closeStart:int,closeEnd:int}>
     * } $state
     * @return array{openStart:int,openEnd:int,closeStart:int,closeEnd:int}|null
     */
    private static function feedElementBoundaryState(array &$state, string $chunk): ?array
    {
        $length = strlen($chunk);
        for ($offset = 0; $offset < $length; $offset++) {
            if ($state['terminal']) {
                return null;
            }

            $char = $chunk[$offset];
            $position = $state['position'];
            $state['position']++;

            if ($state['mode'] === 'normal') {
                if ($char === '<') {
                    self::startElementBoundaryTag($state, $position, false);
                }
                continue;
            }

            if ($state['mode'] === 'raw') {
                if ($char === '<') {
                    self::startElementBoundaryTag($state, $position, true);
                }
                continue;
            }

            if ($state['mode'] === 'tag_start' || $state['mode'] === 'raw_tag_start') {
                self::consumeElementBoundaryTagStart($state, $char, $position);
                continue;
            }

            if ($state['mode'] === 'tag' || $state['mode'] === 'raw_tag') {
                $bounds = self::consumeElementBoundaryTag($state, $char, $position);
                if ($bounds !== null) {
                    return $bounds;
                }
                if ($state['terminal']) {
                    return null;
                }
                continue;
            }

            if ($state['mode'] === 'bang') {
                self::consumeElementBoundaryBang($state, $char);
                continue;
            }

            if ($state['mode'] === 'comment') {
                if (self::appendElementBoundaryMarker($state, $char, '-->')) {
                    $state['mode'] = 'normal';
                }
                continue;
            }

            if ($state['mode'] === 'cdata') {
                if (self::appendElementBoundaryMarker($state, $char, ']]>')) {
                    $state['mode'] = 'normal';
                }
                continue;
            }

            if ($state['mode'] === 'processing_instruction') {
                if (self::appendElementBoundaryMarker($state, $char, '?>')) {
                    $state['mode'] = 'normal';
                }
                continue;
            }

            self::consumeElementBoundaryDeclaration($state, $char);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function startElementBoundaryTag(array &$state, int $position, bool $raw): void
    {
        $state['mode'] = $raw ? 'raw_tag_start' : 'tag_start';
        $state['tagStart'] = $position;
        $state['tagBuffer'] = '<';
        $state['tagQuote'] = null;
        $state['tagRaw'] = $raw;
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function consumeElementBoundaryTagStart(array &$state, string $char, int $position): void
    {
        $raw = $state['tagRaw'] === true;
        $state['tagBuffer'] .= $char;

        if ($raw) {
            if ($char === '/') {
                $state['mode'] = 'raw_tag';
                return;
            }

            if ($char === '<') {
                self::startElementBoundaryTag($state, $position, true);
                return;
            }

            $state['mode'] = 'raw';

            return;
        }

        if ($char === '!') {
            $state['mode'] = 'bang';

            return;
        }
        if ($char === '?') {
            $state['mode'] = 'processing_instruction';
            $state['markerTail'] = '';

            return;
        }
        if ($char === '/') {
            $state['mode'] = 'tag';

            return;
        }
        if (self::isAsciiAlpha($char)) {
            $state['mode'] = 'tag';

            return;
        }
        if ($char === '<') {
            self::startElementBoundaryTag($state, $position, false);

            return;
        }

        $state['mode'] = 'normal';
    }

    /**
     * @param array<string, mixed> $state
     * @return array{openStart:int,openEnd:int,closeStart:int,closeEnd:int}|null
     */
    private static function consumeElementBoundaryTag(array &$state, string $char, int $position): ?array
    {
        $state['tagBuffer'] .= $char;
        $buffer = $state['tagBuffer'];
        if (str_starts_with($buffer, '</') && strlen($buffer) === 3 && !self::isAsciiAlpha($char)) {
            self::discardElementBoundaryTag($state, $char, $position);

            return null;
        }

        if ($state['tagQuote'] !== null) {
            if ($char === $state['tagQuote']) {
                $state['tagQuote'] = null;
            }

            return null;
        }

        if ($char === '"' || $char === "'") {
            $state['tagQuote'] = $char;

            return null;
        }

        if ($char === '<') {
            self::startElementBoundaryTag($state, $position, $state['tagRaw'] === true);

            return null;
        }

        if ($char !== '>') {
            return null;
        }

        $tagStart = $state['tagStart'];
        $raw = $state['tagRaw'] === true;
        $closing = Html5Dom::rawHtmlClosingTagAt($buffer);
        if ($closing !== null) {
            if ($raw && $closing['name'] !== $state['rawTextElement']) {
                $state['mode'] = 'raw';

                return null;
            }

            if ($raw) {
                $state['rawTextElement'] = null;
            }
            $state['mode'] = 'normal';

            return $tagStart === null ? null : self::applyElementBoundaryClosingTag($state, $closing['name'], $tagStart, $position);
        }

        $opening = Html5Dom::rawHtmlOpeningTagAt($buffer);
        if ($opening !== null && !$raw) {
            $state['mode'] = 'normal';

            return $tagStart === null
                ? null
                : self::applyElementBoundaryOpeningTag($state, $opening['name'], $opening['selfClosing'], $tagStart, $position);
        }

        $state['mode'] = $raw ? 'raw' : 'normal';

        return null;
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function discardElementBoundaryTag(array &$state, string $char, int $position): void
    {
        $raw = $state['tagRaw'] === true;
        if ($char === '<') {
            self::startElementBoundaryTag($state, $position, $raw);

            return;
        }

        $state['mode'] = $raw ? 'raw' : 'normal';
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function consumeElementBoundaryBang(array &$state, string $char): void
    {
        $state['tagBuffer'] .= $char;
        $buffer = $state['tagBuffer'];
        if (str_starts_with('<!--', $buffer)) {
            if ($buffer === '<!--') {
                $state['mode'] = 'comment';
                $state['markerTail'] = '';
            }

            return;
        }
        if (str_starts_with('<![CDATA[', $buffer)) {
            if ($buffer === '<![CDATA[') {
                $state['mode'] = 'cdata';
                $state['markerTail'] = '';
            }

            return;
        }

        $state['mode'] = 'declaration';
        $state['declarationQuote'] = null;
        $state['declarationSubsetDepth'] = 0;
        $state['declarationRecent'] = '';
        foreach (str_split(substr($buffer, 2)) as $declarationChar) {
            self::consumeElementBoundaryDeclaration($state, $declarationChar);
        }
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function appendElementBoundaryMarker(array &$state, string $char, string $marker): bool
    {
        $state['markerTail'] = substr($state['markerTail'] . $char, -strlen($marker));

        return $state['markerTail'] === $marker;
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function consumeElementBoundaryDeclaration(array &$state, string $char): void
    {
        if ($state['mode'] === 'declaration_comment') {
            if (self::appendElementBoundaryMarker($state, $char, '-->')) {
                $state['mode'] = 'declaration';
                $state['markerTail'] = '';
                $state['declarationRecent'] = '';
            }

            return;
        }

        if ($state['declarationQuote'] !== null) {
            if ($char === $state['declarationQuote']) {
                $state['declarationQuote'] = null;
                $state['declarationRecent'] = '';
            }

            return;
        }

        if ($char === '"' || $char === "'") {
            $state['declarationQuote'] = $char;
            $state['declarationRecent'] = '';

            return;
        }

        $state['declarationRecent'] = substr($state['declarationRecent'] . $char, -4);
        if ($state['declarationRecent'] === '<!--') {
            $state['mode'] = 'declaration_comment';
            $state['markerTail'] = '';

            return;
        }

        if ($char === '[') {
            $state['declarationSubsetDepth']++;

            return;
        }
        if ($char === ']' && $state['declarationSubsetDepth'] > 0) {
            $state['declarationSubsetDepth']--;

            return;
        }
        if ($char === '>' && $state['declarationSubsetDepth'] === 0) {
            $state['mode'] = 'normal';
            $state['declarationRecent'] = '';
        }
    }

    /**
     * @param array<string, mixed> $state
     * @return array{openStart:int,openEnd:int,closeStart:int,closeEnd:int}|null
     */
    private static function applyElementBoundaryOpeningTag(
        array &$state,
        string $openingName,
        bool $selfClosing,
        int $openStart,
        int $openEnd
    ): ?array {
        $insideTemplate = $state['templateDepth'] > 0;
        if (($state['name'] === 'template' || !$insideTemplate) && $openingName === $state['name'] && !$selfClosing) {
            if ($state['collectAll']) {
                $state['openStack'][] = [
                    'openStart' => $openStart,
                    'openEnd' => $openEnd,
                ];
            } elseif ($state['depth'] === 0) {
                $state['openStart'] = $openStart;
                $state['openEnd'] = $openEnd;
            }
            $state['depth']++;
        }
        if ($openingName === 'template' && !$selfClosing) {
            $state['templateDepth']++;
        }
        if (!$selfClosing && in_array($openingName, self::RAW_TEXT_ELEMENT_NAMES, true)) {
            if ($openingName === 'plaintext') {
                $state['terminal'] = true;

                return null;
            }
            $state['rawTextElement'] = $openingName;
            $state['mode'] = 'raw';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $state
     * @return array{openStart:int,openEnd:int,closeStart:int,closeEnd:int}|null
     */
    private static function applyElementBoundaryClosingTag(
        array &$state,
        string $closingName,
        int $closeStart,
        int $closeEnd
    ): ?array {
        $insideTemplate = $state['templateDepth'] > 0;
        if ($closingName === 'template' && $insideTemplate) {
            if ($state['name'] === 'template' && $state['depth'] > 0) {
                $state['depth']--;
                if ($state['collectAll']) {
                    self::appendElementBoundaryMatch($state, $closeStart, $closeEnd);
                }
                if ($state['depth'] === 0 && $state['openStart'] !== null && $state['openEnd'] !== null) {
                    return [
                        'openStart' => $state['openStart'],
                        'openEnd' => $state['openEnd'],
                        'closeStart' => $closeStart,
                        'closeEnd' => $closeEnd,
                    ];
                }
            }
            $state['templateDepth']--;

            return null;
        }

        if (!$insideTemplate && $closingName === $state['name'] && $state['depth'] > 0) {
            $state['depth']--;
            if ($state['collectAll']) {
                self::appendElementBoundaryMatch($state, $closeStart, $closeEnd);
            }
            if ($state['depth'] === 0 && $state['openStart'] !== null && $state['openEnd'] !== null) {
                return [
                    'openStart' => $state['openStart'],
                    'openEnd' => $state['openEnd'],
                    'closeStart' => $closeStart,
                    'closeEnd' => $closeEnd,
                ];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function appendElementBoundaryMatch(array &$state, int $closeStart, int $closeEnd): void
    {
        $open = array_pop($state['openStack']);
        if (!is_array($open)) {
            return;
        }

        $state['matches'][] = [
            'openStart' => $open['openStart'],
            'openEnd' => $open['openEnd'],
            'closeStart' => $closeStart,
            'closeEnd' => $closeEnd,
        ];
    }

    /**
     * @param list<int> $lineOffsets
     */
    private static function sourceLineIndexForOffset(array $lineOffsets, int $offset): int
    {
        $low = 0;
        $high = count($lineOffsets) - 1;
        while ($low <= $high) {
            $middle = intdiv($low + $high, 2);
            if ($lineOffsets[$middle] <= $offset) {
                $low = $middle + 1;
                continue;
            }

            $high = $middle - 1;
        }

        return max(0, $high);
    }

    private static function isAsciiAlpha(string $char): bool
    {
        return ($char >= 'A' && $char <= 'Z') || ($char >= 'a' && $char <= 'z');
    }

    /**
     * Return true when a closing-looking tag occurs inside script/style-like
     * source text. Passing that source through the legacy DOM bridge would
     * reinterpret the bytes as document structure, so callers can preserve
     * the original raw block instead.
     */
    public static function rawTextContainsClosingTag(string $source, string $name): bool
    {
        $name = strtolower($name);
        $length = strlen($source);
        $cursor = 0;
        $rawTextElement = null;

        while (($tagOffset = strpos($source, '<', $cursor)) !== false) {
            if ($rawTextElement !== null) {
                $closing = Html5Dom::rawHtmlClosingTagAt($source, $tagOffset);
                if ($closing !== null && $closing['name'] === $name && $closing['name'] !== $rawTextElement) {
                    return true;
                }
                if ($closing !== null && $closing['name'] === $rawTextElement) {
                    $rawTextElement = null;
                    $cursor = $closing['next'];
                    continue;
                }
                $cursor = $tagOffset + 1;
                continue;
            }

            if (substr_compare($source, '<!--', $tagOffset, 4) === 0) {
                $commentEnd = strpos($source, '-->', $tagOffset + 4);
                $cursor = $commentEnd === false ? $length : $commentEnd + 3;
                continue;
            }
            if (substr_compare($source, '<![CDATA[', $tagOffset, 9) === 0) {
                $cdataEnd = strpos($source, ']]>', $tagOffset + 9);
                $cursor = $cdataEnd === false ? $length : $cdataEnd + 3;
                continue;
            }
            if (($source[$tagOffset + 1] ?? '') === '!') {
                $cursor = self::declarationEnd($source, $tagOffset + 2);
                continue;
            }
            if (($source[$tagOffset + 1] ?? '') === '?') {
                $processingInstructionEnd = strpos($source, '?>', $tagOffset + 2);
                $cursor = $processingInstructionEnd === false ? $length : $processingInstructionEnd + 2;
                continue;
            }

            $opening = Html5Dom::rawHtmlOpeningTagAt($source, $tagOffset);
            if ($opening !== null) {
                if (!$opening['selfClosing'] && in_array($opening['name'], self::RAW_TEXT_ELEMENT_NAMES, true)) {
                    if ($opening['name'] === 'plaintext') {
                        return false;
                    }
                    $rawTextElement = $opening['name'];
                }
                $cursor = $opening['next'];
                continue;
            }

            $cursor = $tagOffset + 1;
        }

        return false;
    }

    private static function declarationEnd(string $source, int $offset): int
    {
        $length = strlen($source);
        $cursor = $offset;
        $quote = null;
        $subsetDepth = 0;
        $inComment = false;

        while ($cursor < $length) {
            $char = $source[$cursor];
            if ($inComment) {
                if (substr_compare($source, '-->', $cursor, 3) === 0) {
                    $inComment = false;
                    $cursor += 3;
                    continue;
                }
                $cursor++;
                continue;
            }
            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }
                $cursor++;
                continue;
            }
            if (substr_compare($source, '<!--', $cursor, 4) === 0) {
                $inComment = true;
                $cursor += 4;
                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;
                $cursor++;
                continue;
            }
            if ($char === '[') {
                $subsetDepth++;
                $cursor++;
                continue;
            }
            if ($char === ']' && $subsetDepth > 0) {
                $subsetDepth--;
                $cursor++;
                continue;
            }
            if ($char === '>' && $subsetDepth === 0) {
                return $cursor + 1;
            }
            $cursor++;
        }

        return $length;
    }
}
