<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class PptxNativeAstComparisonHarness
{
    private const DEFAULT_MAX_EXAMPLES = 12;
    private const VERDICT = 'normalized-ast-comparison-pptx-implementation-equivalence-observed';
    private const CLAIM = 'Compares local PHP PPTX reader output with paired upstream .native fixtures by normalized AST shape; local PPTX review/provenance attrs and derived text caches are excluded, but no upstream Haskell runner or writer parity is asserted.';

    /** @var array<string, true> */
    private const IGNORED_ATTRS = [
        'columnWidths' => true,
        'header' => true,
        'nativeColumnCount' => true,
        'relationshipAttribute' => true,
        'relationshipId' => true,
        'singleStrAlt' => true,
        'sourceFormat' => true,
        'src' => true,
    ];

    /**
     * @param array{limit?: int, maxExamples?: int} $options
     * @return array<string, mixed>
     */
    public function run(string $pptxDirectory, array $options = []): array
    {
        $limit = max(0, (int) ($options['limit'] ?? 0));
        $maxExamples = max(0, (int) ($options['maxExamples'] ?? self::DEFAULT_MAX_EXAMPLES));

        if (!is_dir($pptxDirectory)) {
            return [
                'schemaVersion' => 1,
                'tool' => 'pandoc-pptx-native-ast',
                'status' => 'skipped',
                'skipped' => true,
                'reason' => 'upstream-cache-missing',
                'verdict' => self::VERDICT,
                'claim' => self::CLAIM,
                'evidenceKind' => 'pptx-native-normalized-ast-comparison',
                'upstreamPptxDirectory' => $pptxDirectory,
                'normalizationPolicy' => self::normalizationPolicy(),
                'totalPairCount' => 0,
                'comparedPairCount' => 0,
                'pptxParsedCount' => 0,
                'nativeParsedCount' => 0,
                'bothParsedCount' => 0,
                'unpairedPptxCount' => 0,
                'unpairedNativeCount' => 0,
                'unpairedPptxFixtures' => [],
                'unpairedNativeFixtures' => [],
                'parseFailureCount' => 0,
                'normalizedAstMatchCount' => 0,
                'normalizedAstMismatchCount' => 0,
                'normalizedAstMatchPercent' => null,
                'astParityStatus' => 'not-evaluated-source-directory-unavailable',
                'parseFailures' => [],
                'mismatchComparisons' => [],
                'mismatchCategories' => [],
                'fixtureComparisons' => [],
                'orderedRemainingGaps' => self::orderedRemainingGaps(false, 0, 0, 0, 0, 0),
            ];
        }

        $pptxFiles = $this->filesByBasename($pptxDirectory, 'pptx');
        $nativeFiles = $this->filesByBasename($pptxDirectory, 'native');
        $pptxNames = array_keys($pptxFiles);
        $nativeNames = array_keys($nativeFiles);
        $pairNames = array_values(array_intersect($pptxNames, $nativeNames));
        sort($pairNames, SORT_STRING);
        $unpairedPptxNames = array_values(array_diff($pptxNames, $nativeNames));
        $unpairedNativeNames = array_values(array_diff($nativeNames, $pptxNames));
        sort($unpairedPptxNames, SORT_STRING);
        sort($unpairedNativeNames, SORT_STRING);

        $totalPairCount = count($pairNames);
        if ($limit > 0) {
            $pairNames = array_slice($pairNames, 0, $limit);
        }

        $pptxParsedCount = 0;
        $nativeParsedCount = 0;
        $bothParsedCount = 0;
        $matchCount = 0;
        $parseFailures = [];
        $mismatches = [];
        $categoryCounts = [];
        $fixtureComparisons = [];

        foreach ($pairNames as $pairName) {
            $pptxResult = $this->readPptx($pptxFiles[$pairName]);
            if ($pptxResult['ok']) {
                ++$pptxParsedCount;
            }

            $nativeResult = $this->readNative($nativeFiles[$pairName]);
            if ($nativeResult['ok']) {
                ++$nativeParsedCount;
            }

            $fixtureComparison = [
                'fixture' => $pairName,
                'pptxParsed' => (bool) $pptxResult['ok'],
                'nativeParsed' => (bool) $nativeResult['ok'],
                'bothParsed' => false,
                'normalizedAstMatched' => false,
                'status' => 'parse-failure',
            ];

            if (!$pptxResult['ok'] || !$nativeResult['ok']) {
                $parseFailures[] = [
                    'fixture' => $pairName,
                    'pptxError' => $pptxResult['error'],
                    'nativeError' => $nativeResult['error'],
                ];
                if ($pptxResult['error'] !== null) {
                    $fixtureComparison['pptxError'] = $pptxResult['error'];
                }
                if ($nativeResult['error'] !== null) {
                    $fixtureComparison['nativeError'] = $nativeResult['error'];
                }
                $fixtureComparisons[] = $fixtureComparison;
                $this->addCategory($categoryCounts, 'parse-failure', $pairName, $maxExamples);
                continue;
            }

            /** @var AstNode $pptxDocument */
            $pptxDocument = $pptxResult['document'];
            /** @var AstNode $nativeDocument */
            $nativeDocument = $nativeResult['document'];
            ++$bothParsedCount;
            $fixtureComparison['bothParsed'] = true;

            $pptxAst = $this->normalizedNode($pptxDocument);
            $nativeAst = $this->normalizedNode($nativeDocument);
            if ($pptxAst === $nativeAst) {
                ++$matchCount;
                $fixtureComparison['normalizedAstMatched'] = true;
                $fixtureComparison['status'] = 'matched';
                $fixtureComparisons[] = $fixtureComparison;
                continue;
            }

            $difference = $this->firstDifference($pptxAst, $nativeAst) ?? 'unknown-normalized-ast-difference';
            $categories = $this->mismatchCategories($difference);
            $fixtureComparison['status'] = 'mismatched';
            $fixtureComparison['firstDifference'] = $difference;
            $fixtureComparison['categories'] = $categories;
            foreach ($categories as $category) {
                $this->addCategory($categoryCounts, $category, $pairName, $maxExamples);
            }

            if (count($mismatches) < $maxExamples) {
                $mismatches[] = [
                    'fixture' => $pairName,
                    'firstDifference' => $difference,
                    'categories' => $categories,
                    'pptxTopTypes' => $this->topTypeSequence($pptxDocument),
                    'nativeTopTypes' => $this->topTypeSequence($nativeDocument),
                ];
            }
            $fixtureComparisons[] = $fixtureComparison;
        }

        ksort($categoryCounts);
        $comparedPairCount = count($pairNames);
        $mismatchCount = $bothParsedCount - $matchCount;

        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-pptx-native-ast',
            'status' => 'completed',
            'skipped' => false,
            'reason' => null,
            'verdict' => self::VERDICT,
            'claim' => self::CLAIM,
            'evidenceKind' => 'pptx-native-normalized-ast-comparison',
            'upstreamPptxDirectory' => $pptxDirectory,
            'normalizationPolicy' => self::normalizationPolicy(),
            'totalPairCount' => $totalPairCount,
            'comparedPairCount' => $comparedPairCount,
            'pptxParsedCount' => $pptxParsedCount,
            'nativeParsedCount' => $nativeParsedCount,
            'bothParsedCount' => $bothParsedCount,
            'unpairedPptxCount' => count($unpairedPptxNames),
            'unpairedNativeCount' => count($unpairedNativeNames),
            'unpairedPptxFixtures' => $unpairedPptxNames,
            'unpairedNativeFixtures' => $unpairedNativeNames,
            'parseFailureCount' => count($parseFailures),
            'normalizedAstMatchCount' => $matchCount,
            'normalizedAstMismatchCount' => $mismatchCount,
            'normalizedAstMatchPercent' => self::percent($matchCount, $comparedPairCount),
            'astParityStatus' => self::astParityStatus(count($parseFailures), $mismatchCount, $comparedPairCount),
            'parseFailures' => array_slice($parseFailures, 0, $maxExamples),
            'mismatchComparisons' => $mismatches,
            'mismatchCategories' => array_values($categoryCounts),
            'fixtureComparisons' => $fixtureComparisons,
            'orderedRemainingGaps' => self::orderedRemainingGaps(
                true,
                $totalPairCount,
                $comparedPairCount,
                count($parseFailures),
                $matchCount,
                $mismatchCount,
                count($unpairedPptxNames),
                count($unpairedNativeNames)
            ),
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public function formatReport(array $report): string
    {
        $lines = [
            'Pandoc PPTX/native AST comparison: ' . (string) ($report['status'] ?? 'unknown'),
            'Verdict: ' . (string) ($report['verdict'] ?? self::VERDICT),
            'Claim: ' . (string) ($report['claim'] ?? self::CLAIM),
            'upstreamPptxDirectory=' . (string) ($report['upstreamPptxDirectory'] ?? ''),
        ];

        if (($report['skipped'] ?? false) === true) {
            $lines[] = 'reason=' . (string) ($report['reason'] ?? 'unknown');
            $lines = self::appendOrderedRemainingGaps($lines, $report);

            return implode(PHP_EOL, $lines) . PHP_EOL;
        }

        $lines[] = sprintf(
            'pairs: total=%d compared=%d parsedBoth=%d parseFailures=%d unpairedPptx=%d unpairedNative=%d',
            (int) ($report['totalPairCount'] ?? 0),
            (int) ($report['comparedPairCount'] ?? 0),
            (int) ($report['bothParsedCount'] ?? 0),
            (int) ($report['parseFailureCount'] ?? 0),
            (int) ($report['unpairedPptxCount'] ?? 0),
            (int) ($report['unpairedNativeCount'] ?? 0),
        );
        $lines[] = sprintf(
            'normalizedAst: matches=%d (%s) mismatches=%d status=%s',
            (int) ($report['normalizedAstMatchCount'] ?? 0),
            self::formatPercent($report['normalizedAstMatchPercent'] ?? null),
            (int) ($report['normalizedAstMismatchCount'] ?? 0),
            (string) ($report['astParityStatus'] ?? 'unknown'),
        );
        $fixtureComparisons = $report['fixtureComparisons'] ?? [];
        $lines[] = sprintf(
            'fixtureComparisons: rows=%d',
            is_array($fixtureComparisons) ? count($fixtureComparisons) : 0,
        );

        $categories = $report['mismatchCategories'] ?? [];
        if (is_array($categories) && $categories !== []) {
            $lines[] = 'mismatchCategories:';
            foreach ($categories as $category) {
                if (!is_array($category)) {
                    continue;
                }
                $examples = $category['examples'] ?? [];
                $exampleText = is_array($examples) && $examples !== []
                    ? ' examples=' . implode(',', array_map('strval', $examples))
                    : '';
                $lines[] = sprintf(
                    '- %s count=%d%s',
                    (string) ($category['category'] ?? 'unknown'),
                    (int) ($category['count'] ?? 0),
                    $exampleText
                );
            }
        }

        $mismatches = $report['mismatchComparisons'] ?? [];
        if (is_array($mismatches) && $mismatches !== []) {
            $lines[] = 'mismatchExamples:';
            foreach ($mismatches as $mismatch) {
                if (!is_array($mismatch)) {
                    continue;
                }
                $lines[] = '- ' . (string) ($mismatch['fixture'] ?? 'unknown')
                    . ': ' . (string) ($mismatch['firstDifference'] ?? 'unknown');
            }
        }

        $lines = self::appendOrderedRemainingGaps($lines, $report);

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredMappedParity(array $report, int $requiredPairCount): bool
    {
        if ($requiredPairCount < 0) {
            throw new \InvalidArgumentException('Required mapped parity count must not be negative');
        }

        return ($report['skipped'] ?? false) === false
            && ($report['status'] ?? null) === 'completed'
            && (int) ($report['totalPairCount'] ?? -1) === $requiredPairCount
            && (int) ($report['comparedPairCount'] ?? -1) === $requiredPairCount
            && (int) ($report['pptxParsedCount'] ?? -1) === $requiredPairCount
            && (int) ($report['nativeParsedCount'] ?? -1) === $requiredPairCount
            && (int) ($report['bothParsedCount'] ?? -1) === $requiredPairCount
            && self::hasSelectedCorpusCoverage($report)
            && (int) ($report['parseFailureCount'] ?? -1) === 0
            && (int) ($report['normalizedAstMatchCount'] ?? -1) === $requiredPairCount
            && (int) ($report['normalizedAstMismatchCount'] ?? -1) === 0
            && ($report['astParityStatus'] ?? null) === 'normalized-ast-implementation-equivalence-observed';
    }

    /**
     * @param array<string, mixed> $report
     */
    private static function hasSelectedCorpusCoverage(array $report): bool
    {
        if (array_key_exists('unpairedPptxCount', $report) && (int) $report['unpairedPptxCount'] !== 0) {
            return false;
        }
        if (array_key_exists('unpairedNativeCount', $report) && (int) $report['unpairedNativeCount'] !== 0) {
            return false;
        }

        $gaps = $report['orderedRemainingGaps'] ?? null;
        if (is_array($gaps)) {
            foreach ($gaps as $gap) {
                if (!is_array($gap) || ($gap['id'] ?? null) !== 'selected-pptx-native-fixture-corpus-coverage') {
                    continue;
                }

                return ($gap['status'] ?? null) === 'covered-by-current-selected-corpus-evidence';
            }

            return false;
        }

        return array_key_exists('unpairedPptxCount', $report)
            && array_key_exists('unpairedNativeCount', $report);
    }

    /**
     * @return array{type:string, attrs:array<string, mixed>, children:list<array<string, mixed>>}
     */
    public function normalizedDocument(AstNode $document): array
    {
        return $this->normalizedNode($document);
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizationPolicy(): array
    {
        return [
            'compares' => [
                'node type',
                'non-provenance node attributes',
                'child order and child count',
                'visible inline and block AST shape after local readers normalize native constructors and adjacent text runs',
                'image target, alt text, and title after local relationship provenance is removed',
                'SmartArt classes and layout attributes',
            ],
            'excludes' => [
                'local PPTX package review attrs',
                'local PPTX shape, cell, table-style, chart, relationship, and media provenance attrs',
                'derived text attrs on plain, paragraph, heading, and table_cell nodes',
                'raw image alt inline segmentation and line-break whitespace when the plain image alt attribute is compared',
                'empty/default table captions, table feet, row-head counts, and default column widths omitted by local PPTX AST nodes',
                'reader-specific adjacent Str/Space text-node segmentation',
            ],
            'doesNotAssert' => [
                'upstream Haskell/Cabal runner execution',
                'PPTX writer golden package parity',
                'byte-level PPTX package equality',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function filesByBasename(string $directory, string $extension): array
    {
        $files = [];
        foreach (glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.' . $extension) ?: [] as $path) {
            $files[basename($path, '.' . $extension)] = $path;
        }
        ksort($files, SORT_STRING);

        return $files;
    }

    /**
     * @return array{ok: bool, document: ?AstNode, error: ?string}
     */
    private function readPptx(string $path): array
    {
        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new \RuntimeException("Unable to read PPTX fixture '{$path}'.");
            }

            return ['ok' => true, 'document' => (new PptxReader())->read($bytes), 'error' => null];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'document' => null, 'error' => $exception::class . ': ' . $exception->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, document: ?AstNode, error: ?string}
     */
    private function readNative(string $path): array
    {
        try {
            $native = file_get_contents($path);
            if (!is_string($native)) {
                throw new \RuntimeException("Unable to read native fixture '{$path}'.");
            }

            return ['ok' => true, 'document' => (new NativeReader())->read($native), 'error' => null];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'document' => null, 'error' => $exception::class . ': ' . $exception->getMessage()];
        }
    }

    /**
     * @return array{type:string, attrs:array<string, mixed>, children:list<array<string, mixed>>}
     */
    private function normalizedNode(AstNode $node): array
    {
        $attrs = [];
        foreach ($node->attrs as $key => $value) {
            $key = (string) $key;
            if (self::isIgnoredAttrKey($key)) {
                continue;
            }
            if ($node->type === 'document' && in_array($key, ['meta'], true)) {
                continue;
            }
            if ($key === 'text' && in_array($node->type, ['plain', 'paragraph', 'heading', 'table_cell'], true)) {
                continue;
            }
            if ($key === 'caption' && (string) $value === '') {
                continue;
            }
            if ($node->type === 'table_body' && $key === 'rowHeadColumns' && (int) $value === 0) {
                continue;
            }
            if ($node->type === 'table_cell' && in_array($key, ['colspan', 'rowspan'], true) && (int) $value === 1) {
                continue;
            }
            if ($node->type === 'table_cell' && $key === 'align' && (string) $value === 'default') {
                continue;
            }
            if ($node->type === 'table' && $key === 'widths' && $this->isDefaultColumnWidths($value)) {
                continue;
            }
            if ($key === 'attributes' && $value === []) {
                continue;
            }

            $normalizedValue = $this->normalizedValue($value);
            if (in_array($key, ['attributes', 'htmlAttributes'], true) && $normalizedValue === []) {
                continue;
            }
            $attrs[$key] = $normalizedValue;
        }
        ksort($attrs, SORT_STRING);

        return [
            'type' => $node->type,
            'attrs' => $attrs,
            'children' => $node->type === 'image' ? $this->normalizedImageAltChildren($attrs) : $this->normalizedChildren($node->children),
        ];
    }

    /**
     * @param array<string, mixed> $attrs
     * @return list<array{type:string, attrs:array{text:string}, children:list<array<string, mixed>>}>
     */
    private function normalizedImageAltChildren(array $attrs): array
    {
        $alt = $attrs['alt'] ?? '';
        if (!is_string($alt) || trim($alt) === '') {
            return [];
        }

        $alt = trim(preg_replace('/[ \t\r\n\f\v]+/', ' ', $alt) ?? $alt);

        return [[
            'type' => 'text',
            'attrs' => ['text' => $alt],
            'children' => [],
        ]];
    }

    /**
     * @param list<AstNode> $children
     * @return list<array<string, mixed>>
     */
    private function normalizedChildren(array $children): array
    {
        $normalized = [];
        foreach ($children as $child) {
            $node = $this->normalizedNode($child);
            if ($this->isEmptyTableFootNode($node)) {
                continue;
            }
            $this->appendNormalizedChild($normalized, $node);
        }

        return $this->trimBoundaryWhitespaceText($normalized);
    }

    /**
     * @param list<array<string, mixed>> $normalized
     * @param array<string, mixed> $node
     */
    private function appendNormalizedChild(array &$normalized, array $node): void
    {
        $lastIndex = count($normalized) - 1;
        if ($lastIndex >= 0 && $this->isPlainTextNode($normalized[$lastIndex]) && $this->isPlainTextNode($node)) {
            $normalized[$lastIndex]['attrs']['text'] .= $node['attrs']['text'];
            return;
        }

        $normalized[] = $node;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isPlainTextNode(array $node): bool
    {
        $attrs = $node['attrs'] ?? null;

        return ($node['type'] ?? null) === 'text'
            && is_array($attrs)
            && array_keys($attrs) === ['text']
            && is_string($attrs['text'])
            && ($node['children'] ?? null) === [];
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return list<array<string, mixed>>
     */
    private function trimBoundaryWhitespaceText(array $nodes): array
    {
        while ($nodes !== [] && $this->isWhitespaceOnlyPlainTextNode($nodes[0])) {
            array_shift($nodes);
        }

        while ($nodes !== [] && $this->isWhitespaceOnlyPlainTextNode($nodes[count($nodes) - 1])) {
            array_pop($nodes);
        }

        return array_values($nodes);
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isWhitespaceOnlyPlainTextNode(array $node): bool
    {
        if (!$this->isPlainTextNode($node)) {
            return false;
        }

        return trim((string) $node['attrs']['text']) === '';
    }

    private function normalizedValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace("\t", ' ', $value);
        }
        if (is_float($value)) {
            return round($value, 12);
        }
        if ($value instanceof AstNode) {
            return $this->normalizedNode($value);
        }
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            if ($this->isAstNodeList($value)) {
                return $this->normalizedChildren($value);
            }

            return array_map(fn (mixed $item): mixed => $this->normalizedValue($item), $value);
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            if (self::isIgnoredAttrKey((string) $key)) {
                continue;
            }
            $normalized[(string) $key] = $this->normalizedValue($item);
        }
        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    /**
     * @param list<mixed> $value
     */
    private function isAstNodeList(array $value): bool
    {
        foreach ($value as $item) {
            if (!$item instanceof AstNode) {
                return false;
            }
        }

        return $value !== [];
    }

    private static function isIgnoredAttrKey(string $key): bool
    {
        return isset(self::IGNORED_ATTRS[$key])
            || str_starts_with($key, 'pptx')
            || self::isNativeProvenanceAttrKey($key);
    }

    private static function isNativeProvenanceAttrKey(string $key): bool
    {
        return str_starts_with($key, 'native')
            || str_ends_with($key, 'Native')
            || str_ends_with($key, 'Natives')
            || str_ends_with($key, 'Constructor')
            || str_ends_with($key, 'Constructors')
            || in_array($key, ['constructor', 'pandocApiVersion'], true);
    }

    private function isDefaultColumnWidths(mixed $value): bool
    {
        if (!is_array($value) || !array_is_list($value)) {
            return false;
        }

        foreach ($value as $width) {
            if ((float) $width !== 0.0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isEmptyTableFootNode(array $node): bool
    {
        return ($node['type'] ?? null) === 'table_foot'
            && ($node['attrs'] ?? null) === []
            && ($node['children'] ?? null) === [];
    }

    private function firstDifference(mixed $pptx, mixed $native, string $path = 'root'): ?string
    {
        if (gettype($pptx) !== gettype($native)) {
            return "{$path} type " . gettype($pptx) . ' vs ' . gettype($native);
        }
        if (!is_array($pptx)) {
            return $pptx === $native ? null : "{$path} value " . self::shortJson($pptx) . ' vs ' . self::shortJson($native);
        }

        $pptxKeys = array_keys($pptx);
        $nativeKeys = array_keys($native);
        if ($pptxKeys !== $nativeKeys) {
            return "{$path} keys " . self::shortJson($pptxKeys) . ' vs ' . self::shortJson($nativeKeys);
        }

        foreach ($pptxKeys as $key) {
            $difference = $this->firstDifference($pptx[$key], $native[$key], $path . '.' . $key);
            if ($difference !== null) {
                return $difference;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function mismatchCategories(string $difference): array
    {
        $lower = strtolower($difference);
        $categories = [];
        if (str_contains($lower, '.children keys')) {
            $categories[] = 'child-count-or-inline-granularity';
        }
        if (str_contains($lower, '.attrs keys') || str_contains($lower, '.attrs.')) {
            $categories[] = 'attribute-shape';
        }
        if (str_contains($lower, 'list') || str_contains($lower, 'bullet')) {
            $categories[] = 'list-shape';
        }
        if (str_contains($lower, 'table') || str_contains($lower, 'row') || str_contains($lower, 'cell')) {
            $categories[] = 'table-shape';
        }
        if (str_contains($lower, 'image') || str_contains($lower, 'url') || str_contains($lower, 'alt')) {
            $categories[] = 'image-shape';
        }
        if (str_contains($lower, 'smartart') || str_contains($lower, 'div')) {
            $categories[] = 'smartart-shape';
        }
        if (str_contains($lower, '.type')) {
            $categories[] = 'node-type';
        }
        if (str_contains($lower, ' value ')) {
            $categories[] = 'scalar-value';
        }
        if ($categories === []) {
            $categories[] = 'uncategorized-normalized-ast-drift';
        }

        return array_values(array_unique($categories));
    }

    /**
     * @return list<string>
     */
    private function topTypeSequence(AstNode $document): array
    {
        return array_map(static fn (AstNode $child): string => $child->type, $document->children);
    }

    /**
     * @param array<string, array{category: string, count: int, examples: list<string>}> $categoryCounts
     */
    private function addCategory(array &$categoryCounts, string $category, string $fixture, int $maxExamples): void
    {
        if (!isset($categoryCounts[$category])) {
            $categoryCounts[$category] = ['category' => $category, 'count' => 0, 'examples' => []];
        }

        ++$categoryCounts[$category]['count'];
        if (count($categoryCounts[$category]['examples']) < $maxExamples) {
            $categoryCounts[$category]['examples'][] = $fixture;
        }
    }

    private static function astParityStatus(int $parseFailureCount, int $mismatchCount, int $comparedPairCount): string
    {
        if ($comparedPairCount === 0) {
            return 'not-evaluated-no-paired-fixtures';
        }
        if ($parseFailureCount > 0) {
            return 'blocked-by-parse-failures';
        }
        if ($mismatchCount > 0) {
            return 'normalized-ast-mismatches-observed';
        }

        return 'normalized-ast-implementation-equivalence-observed';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function orderedRemainingGaps(
        bool $sourceDirectoryPresent,
        int $totalPairCount,
        int $comparedPairCount,
        int $parseFailureCount,
        int $matchCount,
        int $mismatchCount,
        int $unpairedPptxCount = 0,
        int $unpairedNativeCount = 0
    ): array {
        $sourceEvidence = $sourceDirectoryPresent
            ? "total pairs={$totalPairCount}; compared pairs={$comparedPairCount}; fixture rows={$comparedPairCount}; unpaired pptx={$unpairedPptxCount}; unpaired native={$unpairedNativeCount}; parse failures={$parseFailureCount}; normalized AST matches={$matchCount}; normalized AST mismatches={$mismatchCount}"
            : 'optional upstream PPTX cache absent; normalized AST comparison did not run';
        $selectedCorpusCovered = $sourceDirectoryPresent
            && $totalPairCount > 0
            && $comparedPairCount === $totalPairCount
            && $parseFailureCount === 0
            && $mismatchCount === 0
            && $matchCount === $totalPairCount
            && $unpairedPptxCount === 0
            && $unpairedNativeCount === 0;

        return [
            [
                'rank' => 1,
                'id' => 'normalized-pptx-native-ast-equality',
                'status' => !$sourceDirectoryPresent
                    ? 'not-evaluated'
                    : (($parseFailureCount === 0 && $mismatchCount === 0 && $comparedPairCount > 0) ? 'covered-by-current-normalized-ast-evidence' : 'open'),
                'currentEvidence' => $sourceEvidence,
                'evidenceRequired' => 'Keep parse failures and normalized AST mismatches at zero for every paired root-level upstream PPTX/native fixture.',
            ],
            [
                'rank' => 2,
                'id' => 'upstream-pptx-reader-runner-results',
                'status' => 'open',
                'currentEvidence' => 'Native-PHP per-fixture pass/fail rows are recorded, but no upstream Haskell/Cabal test-pandoc PPTX reader runner result is recorded by this AST lane.',
                'evidenceRequired' => 'Record reproducible upstream PPTX reader runner results and retain per-fixture pass/fail rows for every compared fixture.',
            ],
            [
                'rank' => 3,
                'id' => 'selected-pptx-native-fixture-corpus-coverage',
                'status' => !$sourceDirectoryPresent
                    ? 'not-evaluated'
                    : ($selectedCorpusCovered ? 'covered-by-current-selected-corpus-evidence' : 'open'),
                'currentEvidence' => $sourceEvidence,
                'evidenceRequired' => 'Run without a limit across the selected same-stem PPTX/native corpus, keep unpaired PPTX/native counts at zero, and keep parse failures and normalized AST mismatches at zero.',
            ],
        ];
    }

    /**
     * @param list<string> $lines
     * @param array<string, mixed> $report
     * @return list<string>
     */
    private static function appendOrderedRemainingGaps(array $lines, array $report): array
    {
        $gaps = $report['orderedRemainingGaps'] ?? [];
        if (!is_array($gaps) || $gaps === []) {
            return $lines;
        }

        $lines[] = 'orderedRemainingGaps:';
        foreach ($gaps as $gap) {
            if (!is_array($gap)) {
                continue;
            }
            $lines[] = sprintf(
                '%d. %s [%s] current=%s required=%s',
                (int) ($gap['rank'] ?? 0),
                (string) ($gap['id'] ?? 'unknown-gap'),
                (string) ($gap['status'] ?? 'unknown'),
                (string) ($gap['currentEvidence'] ?? ''),
                (string) ($gap['evidenceRequired'] ?? '')
            );
        }

        return $lines;
    }

    private static function percent(int $numerator, int $denominator): ?float
    {
        if ($denominator === 0) {
            return null;
        }

        return round(($numerator / $denominator) * 100, 2);
    }

    private static function formatPercent(mixed $value): string
    {
        if (!is_int($value) && !is_float($value)) {
            return 'n/a';
        }

        return number_format((float) $value, 2) . '%';
    }

    private static function shortJson(mixed $value): string
    {
        try {
            $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return gettype($value);
        }

        return strlen($json) > 180 ? substr($json, 0, 177) . '...' : $json;
    }
}
