<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class YamlMetadataReview
{
    /**
     * @var array<string, string>
     */
    private const PROVENANCE_ATTRIBUTES = [
        'yamlMetadataTagProvenance' => 'tag',
        'yamlMetadataDirectiveProvenance' => 'directive',
        'yamlMetadataCommentProvenance' => 'comment',
        'yamlMetadataAnchorProvenance' => 'anchor',
        'yamlMetadataAliasProvenance' => 'alias',
        'yamlMetadataMergeProvenance' => 'merge',
        'yamlMetadataScalarProvenance' => 'scalar',
        'yamlMetadataCollectionProvenance' => 'collection',
        'yamlMetadataStreamProvenance' => 'stream',
    ];

    /**
     * @return array{
     *     meta: array<string, mixed>,
     *     summary: array<string, mixed>,
     *     constructorProvenance: array<string, array<string, mixed>>,
     *     provenanceByPath: array<string, list<array<string, mixed>>>,
     *     diagnosticsByPath: array<string, list<array<string, mixed>>>
     * }
     */
    public static function fromMarkdown(string $markdown, ?MarkdownReader $reader = null): array
    {
        $yamlReview = self::reviewFromYamlFrontMatter($markdown);
        if ($yamlReview !== null) {
            return $yamlReview;
        }

        $document = ($reader ?? new MarkdownReader())->read($markdown);

        return [
            'meta' => self::stringMap($document->attr('meta', [])),
            'summary' => self::stringMap($document->attr('yamlMetadataReviewSummary', [])),
            'constructorProvenance' => self::stringMap($document->attr('metaConstructorProvenance', [])),
            'provenanceByPath' => self::provenanceByPath($document),
            'diagnosticsByPath' => self::diagnosticsByPath($document),
        ];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function provenanceByPath(AstNode $document): array
    {
        $byPath = [];
        foreach (self::PROVENANCE_ATTRIBUTES as $attribute => $category) {
            foreach (self::listOfMaps($document->attr($attribute, [])) as $entry) {
                $path = self::pathFromEntry($entry);
                $entry['category'] = $category;
                $entry['attribute'] = $attribute;
                $byPath[$path][] = $entry;
            }
        }

        return $byPath;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function diagnosticsByPath(AstNode $document): array
    {
        $byPath = [];
        foreach (self::listOfMaps($document->attr('yamlMetadataDiagnostics', [])) as $entry) {
            $byPath[self::pathFromEntry($entry)][] = $entry;
        }

        return $byPath;
    }

    /**
     * @return array{
     *     meta: array<string, mixed>,
     *     summary: array<string, mixed>,
     *     constructorProvenance: array<string, array<string, mixed>>,
     *     provenanceByPath: array<string, list<array<string, mixed>>>,
     *     diagnosticsByPath: array<string, list<array<string, mixed>>>
     * }|null
     */
    private static function reviewFromYamlFrontMatter(string $markdown): ?array
    {
        $lines = preg_split('/\R/u', rtrim($markdown, "\r\n")) ?: [];
        $block = self::extractYamlFrontMatter($lines);
        if ($block === null) {
            return null;
        }

        $state = [
            'anchors' => [],
            'tagHandles' => ['!!' => 'tag:yaml.org,2002:'],
            'tagProvenance' => [],
            'collectionProvenance' => [],
            'aliasProvenance' => [],
            'constructorProvenance' => [],
            'anchorConstructorProvenance' => [],
            'diagnostics' => [],
            'collectionCount' => 0,
        ];
        foreach ($block['directives'] as $directive) {
            self::applyYamlDirective($directive, $state);
        }

        [$metadata] = self::parseYamlBlockMapping(
            $block['yaml'],
            0,
            0,
            '',
            $state,
            $block['startLine']
        );
        if ($metadata === []) {
            return null;
        }

        $diagnosticsByPath = [];
        foreach ($state['diagnostics'] as $diagnostic) {
            if (is_array($diagnostic)) {
                $diagnosticsByPath[self::pathFromEntry($diagnostic)][] = $diagnostic;
            }
        }

        return [
            'meta' => self::publicYamlMetadata($metadata),
            'summary' => [
                'reviewStatus' => $state['diagnostics'] === [] ? 'clean' : 'needs-review',
                'tagCount' => count($state['tagProvenance']),
                'aliasCount' => count($state['aliasProvenance']),
                'collectionCount' => $state['collectionCount'],
            ],
            'constructorProvenance' => self::stringMap($state['constructorProvenance']),
            'provenanceByPath' => self::yamlProvenanceByPath($state),
            'diagnosticsByPath' => $diagnosticsByPath,
        ];
    }

    /**
     * @param list<string> $lines
     * @return array{directives:list<string>, yaml:list<string>, startLine:int}|null
     */
    private static function extractYamlFrontMatter(array $lines): ?array
    {
        $count = count($lines);
        if ($count === 0) {
            return null;
        }

        $cursor = 0;
        $directives = [];
        while ($cursor < $count && self::isYamlDirective($lines[$cursor])) {
            $directives[] = trim($lines[$cursor]);
            $cursor++;
        }

        if (self::yamlDocumentMarker($lines[$cursor] ?? '') !== '---') {
            return null;
        }

        $cursor++;
        $startLine = $cursor + 1;
        $yaml = [];
        for (; $cursor < $count; $cursor++) {
            if (self::yamlDocumentMarker($lines[$cursor]) !== null) {
                return [
                    'directives' => $directives,
                    'yaml' => $yaml,
                    'startLine' => $startLine,
                ];
            }

            $yaml[] = $lines[$cursor];
        }

        return null;
    }

    private static function yamlDocumentMarker(string $line): ?string
    {
        $trimmed = trim($line);
        if ($trimmed !== ltrim($line, " \t")) {
            return null;
        }

        if (str_contains($trimmed, '#')) {
            $trimmed = trim(strstr($trimmed, '#', true) ?: $trimmed);
        }

        return match ($trimmed) {
            '---', '...' => $trimmed,
            default => null,
        };
    }

    private static function isYamlDirective(string $line): bool
    {
        $trimmed = trim($line);

        return preg_match('/^%YAML[ \t]+\d+(?:\.\d+)?$/i', $trimmed) === 1
            || preg_match('/^%TAG[ \t]+(!|!!|![A-Za-z0-9_.-]+!)[ \t]+\S+$/', $trimmed) === 1;
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function applyYamlDirective(string $directive, array &$state): void
    {
        if (preg_match('/^%TAG[ \t]+(!|!!|![A-Za-z0-9_.-]+!)[ \t]+(\S+)$/', trim($directive), $m) !== 1) {
            return;
        }

        $state['tagHandles'][$m[1]] = $m[2];
    }

    /**
     * @param list<string> $lines
     * @param array<string, mixed> $state
     * @return array{0:array<string, mixed>, 1:int}
     */
    private static function parseYamlBlockMapping(
        array $lines,
        int $start,
        int $indent,
        string $path,
        array &$state,
        int $baseLine,
        bool $countCollection = true
    ): array {
        if ($countCollection) {
            self::noteYamlCollection($state);
        }
        if ($path !== '') {
            self::recordYamlNativeConstructor($path, ['t' => 'MetaMap', 'c' => []], $baseLine + $start, $state);
        }
        $map = [];
        $count = count($lines);
        $index = $start;
        while ($index < $count) {
            $line = $lines[$index];
            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                $index++;
                continue;
            }

            $lineIndent = self::yamlIndent($line);
            if ($lineIndent < $indent) {
                break;
            }
            if ($lineIndent > $indent) {
                $index++;
                continue;
            }

            $trimmed = trim($line);
            if (str_starts_with($trimmed, '- ')) {
                break;
            }

            $mapping = self::splitYamlPair($trimmed);
            if ($mapping === null) {
                $index++;
                continue;
            }

            [$sourceKey, $sourceValue] = $mapping;
            $key = self::normalizeYamlKey($sourceKey, $state);
            $entryPath = self::yamlPathAppend($path, $key);
            $nextIndent = self::nextYamlContentIndent($lines, $index + 1);
            $blockScalar = self::yamlBlockScalarHeaderFromSource($sourceValue, $entryPath, $baseLine + $index, $state);
            if ($blockScalar !== null) {
                [$value, $nextIndex] = self::parseYamlBlockScalar($lines, $index + 1, $lineIndent, $blockScalar);
                self::recordYamlNativeConstructor(
                    $entryPath,
                    self::yamlBlockScalarNative($value),
                    $baseLine + $index,
                    $state
                );
                if (($blockScalar['anchor'] ?? null) !== null && $blockScalar['anchor'] !== '') {
                    $state['anchors'][$blockScalar['anchor']] = $value;
                    $state['anchorConstructorProvenance'][$blockScalar['anchor']] = $state['constructorProvenance'][$entryPath] ?? null;
                }
            } elseif ($sourceValue === '' && $nextIndent !== null && $nextIndent > $lineIndent) {
                if (str_starts_with(trim($lines[self::nextYamlContentIndex($lines, $index + 1) ?? $index] ?? ''), '- ')) {
                    [$value, $nextIndex] = self::parseYamlBlockSequence($lines, $index + 1, $nextIndent, $entryPath, $state, $baseLine);
                } else {
                    [$value, $nextIndex] = self::parseYamlBlockMapping($lines, $index + 1, $nextIndent, $entryPath, $state, $baseLine);
                }
            } else {
                $value = self::parseYamlValue($sourceValue, $entryPath, $baseLine + $index, $state);
                $nextIndex = $index + 1;
            }

            if ($key === '<<' && is_array($value)) {
                $map = array_replace($value, $map);
            } else {
                $map[$key] = $value;
            }
            $index = $nextIndex;
        }

        return [$map, $index];
    }

    /**
     * @param list<string> $lines
     * @param array<string, mixed> $state
     * @return array{0:list<mixed>, 1:int}
     */
    private static function parseYamlBlockSequence(
        array $lines,
        int $start,
        int $indent,
        string $path,
        array &$state,
        int $baseLine
    ): array {
        self::noteYamlCollection($state);
        self::recordYamlNativeConstructor($path, ['t' => 'MetaList', 'c' => []], $baseLine + $start, $state);
        $items = [];
        $count = count($lines);
        $index = $start;
        while ($index < $count) {
            $line = $lines[$index];
            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                $index++;
                continue;
            }

            $lineIndent = self::yamlIndent($line);
            if ($lineIndent < $indent) {
                break;
            }
            if ($lineIndent > $indent) {
                $index++;
                continue;
            }

            $trimmed = trim($line);
            if (!str_starts_with($trimmed, '-')) {
                break;
            }

            $source = trim(substr($trimmed, 1));
            $itemPath = self::yamlPathAppend($path, (string) count($items));
            $nextIndent = self::nextYamlContentIndent($lines, $index + 1);
            $mapping = $source === '' ? null : self::splitYamlPair($source);
            if ($mapping !== null) {
                self::noteYamlCollection($state);
                self::recordYamlNativeConstructor($itemPath, ['t' => 'MetaMap', 'c' => []], $baseLine + $index, $state);
                [$sourceKey, $sourceValue] = $mapping;
                $key = self::normalizeYamlKey($sourceKey, $state);
                $fieldPath = self::yamlPathAppend($itemPath, $key);
                $blockScalar = self::yamlBlockScalarHeaderFromSource($sourceValue, $fieldPath, $baseLine + $index, $state);
                if ($blockScalar !== null) {
                    [$fieldValue, $nextIndex] = self::parseYamlBlockScalar($lines, $index + 1, $lineIndent, $blockScalar);
                    self::recordYamlNativeConstructor(
                        $fieldPath,
                        self::yamlBlockScalarNative($fieldValue),
                        $baseLine + $index,
                        $state
                    );
                    if (($blockScalar['anchor'] ?? null) !== null && $blockScalar['anchor'] !== '') {
                        $state['anchors'][$blockScalar['anchor']] = $fieldValue;
                        $state['anchorConstructorProvenance'][$blockScalar['anchor']] = $state['constructorProvenance'][$fieldPath] ?? null;
                    }
                } else {
                    $fieldValue = self::parseYamlValue($sourceValue, $fieldPath, $baseLine + $index, $state);
                    $nextIndex = $index + 1;
                }
                $item = [
                    $key => $fieldValue,
                ];
                if ($blockScalar === null && $nextIndent !== null && $nextIndent > $lineIndent) {
                    [$children, $nextIndex] = self::parseYamlBlockMapping($lines, $index + 1, $nextIndent, $itemPath, $state, $baseLine, false);
                    $item = array_replace($item, $children);
                }
                $items[] = $item;
                $index = $nextIndex;
                continue;
            }

            if ($source === '' && $nextIndent !== null && $nextIndent > $lineIndent) {
                if (str_starts_with(trim($lines[self::nextYamlContentIndex($lines, $index + 1) ?? $index] ?? ''), '- ')) {
                    [$value, $nextIndex] = self::parseYamlBlockSequence($lines, $index + 1, $nextIndent, $itemPath, $state, $baseLine);
                } else {
                    [$value, $nextIndex] = self::parseYamlBlockMapping($lines, $index + 1, $nextIndent, $itemPath, $state, $baseLine);
                }
                $items[] = $value;
                $index = $nextIndex;
                continue;
            }

            $blockScalar = self::yamlBlockScalarHeaderFromSource($source, $itemPath, $baseLine + $index, $state);
            if ($blockScalar !== null) {
                [$value, $nextIndex] = self::parseYamlBlockScalar($lines, $index + 1, $lineIndent, $blockScalar);
                self::recordYamlNativeConstructor(
                    $itemPath,
                    self::yamlBlockScalarNative($value),
                    $baseLine + $index,
                    $state
                );
                if (($blockScalar['anchor'] ?? null) !== null && $blockScalar['anchor'] !== '') {
                    $state['anchors'][$blockScalar['anchor']] = $value;
                    $state['anchorConstructorProvenance'][$blockScalar['anchor']] = $state['constructorProvenance'][$itemPath] ?? null;
                }
                $items[] = $value;
                $index = $nextIndex;
                continue;
            }

            $items[] = self::parseYamlValue($source, $itemPath, $baseLine + $index, $state);
            $index++;
        }

        return [$items, $index];
    }

    /**
     * @param array<string, mixed> $state
     * @return array{style:string, chomping:string, indent:int|null, anchor:string|null}|null
     */
    private static function yamlBlockScalarHeaderFromSource(
        string $source,
        string $path,
        int $line,
        array &$state
    ): ?array {
        [$clean, $anchor, $tags] = self::consumeYamlValueDirectives(trim($source), $state);
        $header = self::parseYamlBlockScalarHeader($clean);
        if ($header === null) {
            return null;
        }

        foreach ($tags as $tag) {
            self::recordYamlTagProvenance($tag, $path, $line, $state);
        }

        return $header + ['anchor' => $anchor];
    }

    /**
     * @return array{style:string, chomping:string, indent:int|null}|null
     */
    private static function parseYamlBlockScalarHeader(string $source): ?array
    {
        if (preg_match('/^([|>])([+-]?[1-9]?|[1-9]?[+-]?)(?:[ \t]*(?:#.*)?)?$/', trim($source), $m) !== 1) {
            return null;
        }

        $chomping = '';
        $indent = null;
        foreach (str_split($m[2]) as $indicator) {
            if ($indicator === '+' || $indicator === '-') {
                $chomping = $indicator;
                continue;
            }

            if ($indicator >= '1' && $indicator <= '9') {
                $indent = (int) $indicator;
            }
        }

        return [
            'style' => $m[1],
            'chomping' => $chomping,
            'indent' => $indent,
        ];
    }

    /**
     * @param list<string> $lines
     * @param array{style:string, chomping:string, indent:int|null, anchor:string|null} $header
     * @return array{0:string, 1:int}
     */
    private static function parseYamlBlockScalar(array $lines, int $start, int $parentIndent, array $header): array
    {
        $contentIndent = $header['indent'] === null ? null : $parentIndent + $header['indent'];
        $contentLines = [];
        $count = count($lines);
        $index = $start;
        for (; $index < $count; $index++) {
            $line = $lines[$index];
            if (trim($line) === '') {
                $contentLines[] = '';
                continue;
            }

            $lineIndent = self::yamlIndent($line);
            if ($lineIndent <= $parentIndent) {
                break;
            }

            if ($contentIndent === null) {
                $contentIndent = $lineIndent;
            }

            $contentLines[] = substr($line, min($contentIndent, strlen($line)));
        }

        return [
            self::yamlBlockScalarText($contentLines, $header['style'], $header['chomping']),
            $index,
        ];
    }

    /**
     * @param list<string> $lines
     */
    private static function yamlBlockScalarText(array $lines, string $style, string $chomping): string
    {
        $renderLines = $lines;
        if ($chomping !== '+') {
            while ($renderLines !== [] && end($renderLines) === '') {
                array_pop($renderLines);
            }
        }

        $text = $style === '>'
            ? self::foldYamlBlockScalarLines($renderLines)
            : implode("\n", $renderLines);

        if ($chomping === '-') {
            return $text;
        }

        return $text === '' && $renderLines === [] ? '' : $text . "\n";
    }

    /**
     * @param list<string> $lines
     */
    private static function foldYamlBlockScalarLines(array $lines): string
    {
        $text = '';
        $paragraphOpen = false;
        foreach ($lines as $line) {
            if ($line === '') {
                $text .= "\n";
                $paragraphOpen = false;
                continue;
            }

            if ($paragraphOpen) {
                $text .= ' ';
            }
            $text .= $line;
            $paragraphOpen = true;
        }

        return $text;
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function parseYamlValue(string $source, string $path, int $line, array &$state): mixed
    {
        $source = trim($source);
        [$source, $anchor, $tags] = self::consumeYamlValueDirectives($source, $state);
        foreach ($tags as $tag) {
            self::recordYamlTagProvenance($tag, $path, $line, $state);
        }

        if ($source === '') {
            $scalar = self::parseYamlScalar($source);
            $value = $scalar['value'];
            self::recordYamlNativeConstructor($path, $scalar['native'], $line, $state);
        } elseif (self::isYamlAlias($source)) {
            $value = self::resolveYamlAlias(substr($source, 1), $path, $line, $state);
        } elseif ($source[0] === '{' && str_ends_with($source, '}')) {
            $value = self::parseYamlFlowMap(substr($source, 1, -1), $path, $line, $state);
        } elseif ($source[0] === '[' && str_ends_with($source, ']')) {
            $value = self::parseYamlFlowSequence(substr($source, 1, -1), $path, $line, $state);
        } else {
            $scalar = self::parseYamlScalar($source);
            $value = $scalar['value'];
            self::recordYamlNativeConstructor($path, $scalar['native'], $line, $state);
        }

        if ($anchor !== null && $anchor !== '') {
            $state['anchors'][$anchor] = self::cloneYamlValue($value);
            $state['anchorConstructorProvenance'][$anchor] = $state['constructorProvenance'][$path] ?? null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private static function parseYamlFlowMap(string $source, string $path, int $line, array &$state): array
    {
        self::noteYamlCollection($state);
        self::recordYamlNativeConstructor($path, ['t' => 'MetaMap', 'c' => []], $line, $state);
        $map = [];
        foreach (self::splitYamlFlowItems($source) as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }

            $explicit = str_starts_with($item, '?');
            if ($explicit) {
                $item = trim(substr($item, 1));
            }

            $pair = self::splitYamlPair($item);
            if ($pair === null) {
                $keyInfo = self::normalizeYamlFlowKey($item, $path, $line, $state);
                $map[$keyInfo['key']] = null;
                continue;
            }

            [$sourceKey, $sourceValue] = $pair;
            $keyInfo = self::normalizeYamlFlowKey($sourceKey, $path, $line, $state);
            $key = $keyInfo['key'];
            $entryPath = self::yamlPathAppend($path, $key);
            $value = self::parseYamlValue($sourceValue, $entryPath, $line, $state);
            if ($key === '<<' && is_array($value)) {
                $map = array_replace($value, $map);
            } else {
                $map[$key] = $value;
            }
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $state
     * @return list<mixed>
     */
    private static function parseYamlFlowSequence(string $source, string $path, int $line, array &$state): array
    {
        self::noteYamlCollection($state);
        self::recordYamlNativeConstructor($path, ['t' => 'MetaList', 'c' => []], $line, $state);
        $items = [];
        foreach (self::splitYamlFlowItems($source) as $item) {
            $items[] = self::parseYamlValue($item, self::yamlPathAppend($path, (string) count($items)), $line, $state);
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $state
     * @return array{key:string}
     */
    private static function normalizeYamlFlowKey(string $source, string $parentPath, int $line, array &$state): array
    {
        $original = trim($source);
        [$clean, , $tags] = self::consumeYamlValueDirectives($original, $state);
        $key = self::normalizeYamlExplicitKeySource($clean);
        $entryPath = self::yamlPathAppend($parentPath, $key);
        foreach ($tags as $tag) {
            self::recordYamlTagProvenance($tag, $entryPath, $line, $state);
        }

        if (self::isYamlFlowCollectionSource($clean)) {
            self::recordYamlCollectionProvenance(
                $entryPath,
                $clean[0] === '[' ? 'sequence' : 'mapping',
                'flow',
                $original,
                self::normalizeYamlCollectionSource($clean),
                $line,
                $state
            );
        }

        return ['key' => $key];
    }

    /**
     * @param array<string, mixed> $state
     * @return array{0:string, 1:string|null, 2:list<string>}
     */
    private static function consumeYamlValueDirectives(string $source, array $state): array
    {
        $source = trim($source);
        $anchor = null;
        $tags = [];
        while ($source !== '') {
            if (preg_match('/^&([A-Za-z0-9_.:-]+)(?=$|[ \t])/', $source, $m) === 1) {
                $anchor = $m[1];
                $source = ltrim(substr($source, strlen($m[0])));
                continue;
            }

            if (preg_match('/^!<([^>]+)>/', $source, $m) === 1) {
                $tags[] = $m[1];
                $source = ltrim(substr($source, strlen($m[0])));
                continue;
            }

            if (preg_match('/^(![A-Za-z0-9_.-]+!)([^ \t\[\]\{\},:]+)/', $source, $m) === 1) {
                $tags[] = (string) (($state['tagHandles'][$m[1]] ?? $m[1]) . $m[2]);
                $source = ltrim(substr($source, strlen($m[0])));
                continue;
            }

            if (preg_match('/^!!([A-Za-z0-9_.:-]+)(?=$|[ \t])/', $source, $m) === 1) {
                $tags[] = 'tag:yaml.org,2002:' . $m[1];
                $source = ltrim(substr($source, strlen($m[0])));
                continue;
            }

            if (preg_match('/^!([A-Za-z0-9_.:-]+)(?=$|[ \t])/', $source, $m) === 1) {
                $tags[] = '!' . $m[1];
                $source = ltrim(substr($source, strlen($m[0])));
                continue;
            }

            break;
        }

        return [$source, $anchor, $tags];
    }

    /**
     * @return array{0:string, 1:string}|null
     */
    private static function splitYamlPair(string $source): ?array
    {
        $length = strlen($source);
        $depth = 0;
        $quote = null;
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $source[$offset];
            if ($quote !== null) {
                if ($char === $quote && ($quote === "'" || $offset === 0 || $source[$offset - 1] !== '\\')) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }
            if ($char === '[' || $char === '{') {
                $depth++;
                continue;
            }
            if ($char === ']' || $char === '}') {
                $depth = max(0, $depth - 1);
                continue;
            }
            if ($char === ':' && $depth === 0) {
                return [
                    trim(substr($source, 0, $offset)),
                    ltrim(substr($source, $offset + 1)),
                ];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function splitYamlFlowItems(string $source): array
    {
        $items = [];
        $start = 0;
        $depth = 0;
        $quote = null;
        $length = strlen($source);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $source[$offset];
            if ($quote !== null) {
                if ($char === $quote && ($quote === "'" || $offset === 0 || $source[$offset - 1] !== '\\')) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                continue;
            }
            if ($char === '[' || $char === '{') {
                $depth++;
                continue;
            }
            if ($char === ']' || $char === '}') {
                $depth = max(0, $depth - 1);
                continue;
            }
            if ($char === ',' && $depth === 0) {
                $items[] = trim(substr($source, $start, $offset - $start));
                $start = $offset + 1;
            }
        }
        $items[] = trim(substr($source, $start));

        return array_values(array_filter($items, static fn (string $item): bool => $item !== ''));
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function normalizeYamlKey(string $source, array $state): string
    {
        [$clean] = self::consumeYamlValueDirectives(trim($source), $state);

        return self::normalizeYamlExplicitKeySource($clean);
    }

    private static function normalizeYamlExplicitKeySource(string $source): string
    {
        $source = trim($source);
        if (self::isYamlFlowCollectionSource($source)) {
            return self::normalizeYamlCollectionSource($source);
        }

        return self::unquoteYamlScalar($source);
    }

    private static function isYamlFlowCollectionSource(string $source): bool
    {
        return ($source[0] ?? '') === '[' && str_ends_with($source, ']')
            || ($source[0] ?? '') === '{' && str_ends_with($source, '}');
    }

    private static function normalizeYamlCollectionSource(string $source): string
    {
        $source = trim($source);
        if (($source[0] ?? '') === '[' && str_ends_with($source, ']')) {
            $items = array_map(
                static fn (string $item): string => self::unquoteYamlScalar(trim($item)),
                self::splitYamlFlowItems(substr($source, 1, -1))
            );

            return '[' . implode(', ', $items) . ']';
        }

        if (($source[0] ?? '') === '{' && str_ends_with($source, '}')) {
            return '{' . trim(substr($source, 1, -1)) . '}';
        }

        return $source;
    }

    private static function unquoteYamlScalar(string $source): string
    {
        $source = trim($source);
        if (strlen($source) >= 2 && $source[0] === "'" && str_ends_with($source, "'")) {
            return str_replace("''", "'", substr($source, 1, -1));
        }
        if (strlen($source) >= 2 && $source[0] === '"' && str_ends_with($source, '"')) {
            return stripcslashes(substr($source, 1, -1));
        }

        return $source;
    }

    /**
     * @return array{value:mixed, native:array<string, mixed>}
     */
    private static function parseYamlScalar(string $source): array
    {
        $trimmed = trim($source);
        if ($trimmed === '' || preg_match('/^(?:null|~)$/i', $trimmed) === 1) {
            return [
                'value' => '',
                'native' => ['t' => 'MetaString', 'c' => ''],
            ];
        }

        if (preg_match('/^true$/i', $trimmed) === 1) {
            return [
                'value' => true,
                'native' => ['t' => 'MetaBool', 'c' => true],
            ];
        }

        if (preg_match('/^false$/i', $trimmed) === 1) {
            return [
                'value' => false,
                'native' => ['t' => 'MetaBool', 'c' => false],
            ];
        }

        $value = self::unquoteYamlScalar($trimmed);

        return [
            'value' => $value,
            'native' => ['t' => 'MetaInlines', 'c' => self::yamlTextNativeInlines($value)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function yamlBlockScalarNative(string $value): array
    {
        if ($value === '') {
            return ['t' => 'MetaString', 'c' => ''];
        }

        return [
            't' => 'MetaBlocks',
            'c' => [
                ['t' => 'Para', 'c' => self::yamlTextNativeInlines(rtrim($value, "\n"))],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function yamlTextNativeInlines(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $parts = preg_split('/([ \t]+|\n)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            $parts = [$text];
        }

        $inlines = [];
        foreach ($parts as $part) {
            if ($part === "\n") {
                $inlines[] = ['t' => 'SoftBreak'];
                continue;
            }
            if (preg_match('/^[ \t]+$/u', $part) === 1) {
                $inlines[] = ['t' => 'Space'];
                continue;
            }

            $inlines[] = ['t' => 'Str', 'c' => $part];
        }

        return $inlines;
    }

    private static function isYamlAlias(string $source): bool
    {
        return preg_match('/^\*[^\s\[\]\{\},]+$/', trim($source)) === 1;
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function resolveYamlAlias(string $anchor, string $path, int $line, array &$state): mixed
    {
        $resolved = array_key_exists($anchor, $state['anchors']);
        if ($resolved && is_array($state['anchorConstructorProvenance'][$anchor] ?? null)) {
            $state['constructorProvenance'][$path] = $state['anchorConstructorProvenance'][$anchor];
        }
        $state['aliasProvenance'][] = [
            'type' => 'yaml-alias',
            'path' => $path,
            'alias' => '*' . $anchor,
            'anchor' => $anchor,
            'resolved' => $resolved ? 'true' : 'false',
            'sourceLine' => (string) $line,
        ];

        return $resolved ? self::cloneYamlValue($state['anchors'][$anchor]) : '*' . $anchor;
    }

    private static function cloneYamlValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $copy = [];
        foreach ($value as $key => $item) {
            $copy[$key] = self::cloneYamlValue($item);
        }

        return $copy;
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function noteYamlCollection(array &$state): void
    {
        $state['collectionCount']++;
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function recordYamlCollectionProvenance(
        string $path,
        string $kind,
        string $style,
        string $source,
        string $collectionSource,
        int $line,
        array &$state
    ): void {
        self::noteYamlCollection($state);
        $state['collectionProvenance'][] = [
            'type' => 'yaml-explicit-key-collection',
            'path' => $path,
            'kind' => $kind,
            'style' => $style,
            'source' => $source,
            'collectionSource' => $collectionSource,
            'sourceLine' => (string) $line,
        ];
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function recordYamlTagProvenance(string $tag, string $path, int $line, array &$state): void
    {
        $normalized = self::normalizeYamlTag($tag);
        if ($normalized === '' || str_starts_with($normalized, 'tag:yaml.org,2002:')) {
            return;
        }

        $state['tagProvenance'][] = [
            'type' => 'yaml-tag',
            'path' => $path,
            'tag' => str_starts_with($tag, '!') ? $tag : '!<' . $tag . '>',
            'normalizedTag' => $normalized,
            'kind' => str_starts_with($tag, '!') ? 'local' : 'verbatim',
            'sourceLine' => (string) $line,
        ];
    }

    /**
     * @param array<string, mixed> $native
     * @param array<string, mixed> $state
     */
    private static function recordYamlNativeConstructor(string $path, array $native, int $line, array &$state): void
    {
        if ($path === '') {
            return;
        }

        $state['constructorProvenance'][$path] = [
            'native' => $native,
            'sourceLine' => (string) $line,
        ];
    }

    private static function normalizeYamlTag(string $tag): string
    {
        if (str_starts_with($tag, 'tag:')) {
            return $tag;
        }
        if (str_starts_with($tag, '!')) {
            return substr($tag, 1);
        }

        return $tag;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, list<array<string, mixed>>>
     */
    private static function yamlProvenanceByPath(array $state): array
    {
        $byPath = [];
        foreach ([
            'tagProvenance' => ['tag', 'yamlMetadataTagProvenance'],
            'collectionProvenance' => ['collection', 'yamlMetadataCollectionProvenance'],
            'aliasProvenance' => ['alias', 'yamlMetadataAliasProvenance'],
        ] as $stateKey => [$category, $attribute]) {
            foreach ($state[$stateKey] as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $entry['category'] = $category;
                $entry['attribute'] = $attribute;
                $byPath[self::pathFromEntry($entry)][] = $entry;
            }
        }

        return $byPath;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private static function publicYamlMetadata(array $metadata): array
    {
        $public = [];
        foreach ($metadata as $key => $value) {
            $field = (string) $key;
            if ($field === '' || str_ends_with($field, '_')) {
                continue;
            }

            $public[$field] = self::publicYamlValue($value);
        }

        return $public;
    }

    private static function publicYamlValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(static fn (mixed $item): mixed => self::publicYamlValue($item), $value);
        }

        $public = [];
        foreach ($value as $key => $item) {
            $field = (string) $key;
            if ($field === '' || str_ends_with($field, '_')) {
                continue;
            }

            $public[$field] = self::publicYamlValue($item);
        }

        return $public;
    }

    private static function yamlIndent(string $line): int
    {
        return strlen($line) - strlen(ltrim($line, ' '));
    }

    /**
     * @param list<string> $lines
     */
    private static function nextYamlContentIndex(array $lines, int $start): ?int
    {
        $count = count($lines);
        for ($index = $start; $index < $count; $index++) {
            if (trim($lines[$index]) !== '' && !str_starts_with(ltrim($lines[$index]), '#')) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<string> $lines
     */
    private static function nextYamlContentIndent(array $lines, int $start): ?int
    {
        $index = self::nextYamlContentIndex($lines, $start);

        return $index === null ? null : self::yamlIndent($lines[$index]);
    }

    private static function yamlPathAppend(string $path, string $segment): string
    {
        $escaped = str_replace(['~', '/'], ['~0', '~1'], $segment);

        return $path === '' ? '/' . $escaped : $path . '/' . $escaped;
    }

    /**
     * @return array<string, mixed>
     */
    private static function stringMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return $value;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function listOfMaps(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $maps = [];
        foreach ($value as $entry) {
            if (is_array($entry)) {
                $maps[] = $entry;
            }
        }

        return $maps;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function pathFromEntry(array $entry): string
    {
        $path = $entry['path'] ?? '';

        return is_string($path) ? $path : '';
    }
}
