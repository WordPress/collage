<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class XlsxNativeAstComparisonHarness
{
    private const DEFAULT_MAX_EXAMPLES = 12;
    private const VERDICT = 'normalized-ast-comparison-not-full-xlsx-parity';
    private const CLAIM = 'Compares local PHP XLSX reader output with paired upstream .native fixtures by normalized AST shape; local XLSX review/provenance attrs and derived text caches are excluded, but no upstream Haskell runner or writer parity is asserted.';

    /** @var array<string, true> */
    private const IGNORED_ATTRS = [
        'columnWidths' => true,
        'header' => true,
        'nativeColumnCount' => true,
        'relationshipAttribute' => true,
        'relationshipId' => true,
        'singleStrAlt' => true,
        'sourceCell' => true,
        'sourceColumn' => true,
        'sourceFormat' => true,
        'src' => true,
    ];

    /**
     * @param array{limit?: int, maxExamples?: int} $options
     * @return array<string, mixed>
     */
    public function run(string $xlsxDirectory, array $options = []): array
    {
        $limit = max(0, (int) ($options['limit'] ?? 0));
        $maxExamples = max(0, (int) ($options['maxExamples'] ?? self::DEFAULT_MAX_EXAMPLES));

        if (!is_dir($xlsxDirectory)) {
            return [
                'schemaVersion' => 1,
                'tool' => 'pandoc-xlsx-native-ast',
                'status' => 'skipped',
                'skipped' => true,
                'reason' => 'upstream-cache-missing',
                'verdict' => self::VERDICT,
                'claim' => self::CLAIM,
                'evidenceKind' => 'xlsx-native-normalized-ast-comparison',
                'upstreamXlsxDirectory' => $xlsxDirectory,
                'normalizationPolicy' => self::normalizationPolicy(),
                'totalPairCount' => 0,
                'comparedPairCount' => 0,
                'xlsxParsedCount' => 0,
                'nativeParsedCount' => 0,
                'bothParsedCount' => 0,
                'parseFailureCount' => 0,
                'normalizedAstMatchCount' => 0,
                'normalizedAstMismatchCount' => 0,
                'normalizedAstMatchPercent' => null,
                'astParityStatus' => 'not-evaluated-source-directory-unavailable',
                'parseFailures' => [],
                'mismatchComparisons' => [],
                'mismatchCategories' => [],
                'orderedRemainingGaps' => self::orderedRemainingGaps(false, 0, 0, 0, 0),
            ];
        }

        $xlsxFiles = $this->filesByBasename($xlsxDirectory, 'xlsx');
        $nativeFiles = $this->filesByBasename($xlsxDirectory, 'native');
        $pairNames = array_values(array_intersect(array_keys($xlsxFiles), array_keys($nativeFiles)));
        sort($pairNames, SORT_STRING);

        $totalPairCount = count($pairNames);
        if ($limit > 0) {
            $pairNames = array_slice($pairNames, 0, $limit);
        }

        $xlsxParsedCount = 0;
        $nativeParsedCount = 0;
        $bothParsedCount = 0;
        $matchCount = 0;
        $parseFailures = [];
        $mismatches = [];
        $categoryCounts = [];

        foreach ($pairNames as $pairName) {
            $xlsxResult = $this->readXlsx($xlsxFiles[$pairName]);
            if ($xlsxResult['ok']) {
                ++$xlsxParsedCount;
            }

            $nativeResult = $this->readNative($nativeFiles[$pairName]);
            if ($nativeResult['ok']) {
                ++$nativeParsedCount;
            }

            if (!$xlsxResult['ok'] || !$nativeResult['ok']) {
                $parseFailures[] = [
                    'fixture' => $pairName,
                    'xlsxError' => $xlsxResult['error'],
                    'nativeError' => $nativeResult['error'],
                ];
                $this->addCategory($categoryCounts, 'parse-failure', $pairName, $maxExamples);
                continue;
            }

            /** @var AstNode $xlsxDocument */
            $xlsxDocument = $xlsxResult['document'];
            /** @var AstNode $nativeDocument */
            $nativeDocument = $nativeResult['document'];
            ++$bothParsedCount;

            $xlsxAst = $this->normalizedNode($xlsxDocument);
            $nativeAst = $this->normalizedNode($nativeDocument);
            if ($xlsxAst === $nativeAst) {
                ++$matchCount;
                continue;
            }

            $difference = $this->firstDifference($xlsxAst, $nativeAst) ?? 'unknown-normalized-ast-difference';
            $categories = $this->mismatchCategories($difference);
            foreach ($categories as $category) {
                $this->addCategory($categoryCounts, $category, $pairName, $maxExamples);
            }

            if (count($mismatches) < $maxExamples) {
                $mismatches[] = [
                    'fixture' => $pairName,
                    'firstDifference' => $difference,
                    'categories' => $categories,
                    'xlsxTopTypes' => $this->topTypeSequence($xlsxDocument),
                    'nativeTopTypes' => $this->topTypeSequence($nativeDocument),
                ];
            }
        }

        ksort($categoryCounts);
        $comparedPairCount = count($pairNames);
        $mismatchCount = $bothParsedCount - $matchCount;

        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-xlsx-native-ast',
            'status' => 'completed',
            'skipped' => false,
            'reason' => null,
            'verdict' => self::VERDICT,
            'claim' => self::CLAIM,
            'evidenceKind' => 'xlsx-native-normalized-ast-comparison',
            'upstreamXlsxDirectory' => $xlsxDirectory,
            'normalizationPolicy' => self::normalizationPolicy(),
            'totalPairCount' => $totalPairCount,
            'comparedPairCount' => $comparedPairCount,
            'xlsxParsedCount' => $xlsxParsedCount,
            'nativeParsedCount' => $nativeParsedCount,
            'bothParsedCount' => $bothParsedCount,
            'parseFailureCount' => count($parseFailures),
            'normalizedAstMatchCount' => $matchCount,
            'normalizedAstMismatchCount' => $mismatchCount,
            'normalizedAstMatchPercent' => self::percent($matchCount, $comparedPairCount),
            'astParityStatus' => self::astParityStatus(count($parseFailures), $mismatchCount, $comparedPairCount),
            'parseFailures' => array_slice($parseFailures, 0, $maxExamples),
            'mismatchComparisons' => $mismatches,
            'mismatchCategories' => array_values($categoryCounts),
            'orderedRemainingGaps' => self::orderedRemainingGaps(
                true,
                $comparedPairCount,
                count($parseFailures),
                $matchCount,
                $mismatchCount
            ),
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public function formatReport(array $report): string
    {
        $lines = [
            'Pandoc XLSX/native AST comparison: ' . (string) ($report['status'] ?? 'unknown'),
            'Verdict: ' . (string) ($report['verdict'] ?? self::VERDICT),
            'Claim: ' . (string) ($report['claim'] ?? self::CLAIM),
            'upstreamXlsxDirectory=' . (string) ($report['upstreamXlsxDirectory'] ?? ''),
        ];

        if (($report['skipped'] ?? false) === true) {
            $lines[] = 'reason=' . (string) ($report['reason'] ?? 'unknown');
            $lines = self::appendOrderedRemainingGaps($lines, $report);

            return implode(PHP_EOL, $lines) . PHP_EOL;
        }

        $lines[] = sprintf(
            'pairs: total=%d compared=%d parsedBoth=%d parseFailures=%d',
            (int) ($report['totalPairCount'] ?? 0),
            (int) ($report['comparedPairCount'] ?? 0),
            (int) ($report['bothParsedCount'] ?? 0),
            (int) ($report['parseFailureCount'] ?? 0),
        );
        $lines[] = sprintf(
            'normalizedAst: matches=%d (%s) mismatches=%d status=%s',
            (int) ($report['normalizedAstMatchCount'] ?? 0),
            self::formatPercent($report['normalizedAstMatchPercent'] ?? null),
            (int) ($report['normalizedAstMismatchCount'] ?? 0),
            (string) ($report['astParityStatus'] ?? 'unknown'),
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
            && (int) ($report['xlsxParsedCount'] ?? -1) === $requiredPairCount
            && (int) ($report['nativeParsedCount'] ?? -1) === $requiredPairCount
            && (int) ($report['bothParsedCount'] ?? -1) === $requiredPairCount
            && (int) ($report['parseFailureCount'] ?? -1) === 0
            && (int) ($report['normalizedAstMatchCount'] ?? -1) === $requiredPairCount
            && (int) ($report['normalizedAstMismatchCount'] ?? -1) === 0
            && ($report['astParityStatus'] ?? null) === 'normalized-ast-equality-observed-not-runner-parity';
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
                'sheet heading order',
                'table head, body, row, cell, and visible inline structure',
                'cell text after reader-specific Str/Space segmentation is normalized',
            ],
            'excludes' => [
                'local XLSX package review attrs',
                'local XLSX workbook, sheet, cell coordinate, table-style, cell alignment, chart, relationship, and media provenance attrs',
                'derived text attrs on plain, paragraph, heading, and table_cell nodes',
                'empty/default table captions, table feet, row-head counts, and default column widths omitted by local XLSX AST nodes',
                'reader-specific adjacent Str/Space text-node segmentation',
            ],
            'doesNotAssert' => [
                'upstream Haskell/Cabal runner execution',
                'XLSX writer golden package parity',
                'byte-level XLSX package equality',
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
    private function readXlsx(string $path): array
    {
        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new \RuntimeException("Unable to read XLSX fixture '{$path}'.");
            }

            return ['ok' => true, 'document' => (new XlsxReader())->read($bytes), 'error' => null];
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
            if ($node->type === 'table_cell' && $key === 'align') {
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
            'children' => $this->normalizedChildren($node->children),
        ];
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
            || str_starts_with($key, 'xlsx')
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

    private function firstDifference(mixed $xlsx, mixed $native, string $path = 'root'): ?string
    {
        if (gettype($xlsx) !== gettype($native)) {
            return "{$path} type " . gettype($xlsx) . ' vs ' . gettype($native);
        }
        if (!is_array($xlsx)) {
            return $xlsx === $native ? null : "{$path} value " . self::shortJson($xlsx) . ' vs ' . self::shortJson($native);
        }

        $xlsxKeys = array_keys($xlsx);
        $nativeKeys = array_keys($native);
        if ($xlsxKeys !== $nativeKeys) {
            return "{$path} keys " . self::shortJson($xlsxKeys) . ' vs ' . self::shortJson($nativeKeys);
        }

        foreach ($xlsxKeys as $key) {
            $difference = $this->firstDifference($xlsx[$key], $native[$key], $path . '.' . $key);
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

        return 'normalized-ast-equality-observed-not-runner-parity';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function orderedRemainingGaps(
        bool $sourceDirectoryPresent,
        int $comparedPairCount,
        int $parseFailureCount,
        int $matchCount,
        int $mismatchCount
    ): array {
        $sourceEvidence = $sourceDirectoryPresent
            ? "compared pairs={$comparedPairCount}; parse failures={$parseFailureCount}; normalized AST matches={$matchCount}; normalized AST mismatches={$mismatchCount}"
            : 'optional upstream XLSX cache absent; normalized AST comparison did not run';

        return [
            [
                'rank' => 1,
                'id' => 'normalized-xlsx-native-ast-equality',
                'status' => !$sourceDirectoryPresent
                    ? 'not-evaluated'
                    : (($parseFailureCount === 0 && $mismatchCount === 0 && $comparedPairCount > 0) ? 'covered-by-current-normalized-ast-evidence' : 'open'),
                'currentEvidence' => $sourceEvidence,
                'evidenceRequired' => 'Keep parse failures and normalized AST mismatches at zero for every paired root-level upstream XLSX/native fixture.',
            ],
            [
                'rank' => 2,
                'id' => 'upstream-xlsx-reader-runner-results',
                'status' => 'open',
                'currentEvidence' => 'No upstream Haskell/Cabal test-pandoc XLSX reader runner result is recorded by this AST lane.',
                'evidenceRequired' => 'Record reproducible upstream XLSX reader runner results or a native-PHP equivalent denominator with per-fixture pass/fail rows.',
            ],
            [
                'rank' => 3,
                'id' => 'upstream-xlsx-fixture-corpus-coverage',
                'status' => 'open',
                'currentEvidence' => 'The harness compares only paired XLSX/native fixtures present in the selected directory.',
                'evidenceRequired' => 'Populate and compare every upstream XLSX reader fixture, plus any generated current-upstream edge fixtures used as parity pins.',
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
