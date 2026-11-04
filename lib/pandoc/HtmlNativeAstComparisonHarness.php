<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class HtmlNativeAstComparisonHarness
{
    private const DEFAULT_MAX_EXAMPLES = 12;
    private const VERDICT = 'normalized-ast-comparison-html-implementation-equivalence-observed';
    private const CLAIM = 'Compares local PHP TagSoup-backed Pandoc HTML reader output with paired .native fixtures by normalized AST shape; reader provenance, table review metadata, and NativeReader constructor provenance are excluded.';
    private const FIXTURE_INVENTORY_SIGNATURE_ALGORITHM = 'sha256-canonical-json-v1';
    private const CHECKED_IN_FIXTURE_INVENTORY_SIGNATURE_SHA256 = 'cf07296587514c4e15e7582701bcc337a3a19711feee7dc3c81ad68d1af16111';
    private const HTML_READER_OPTIONS_BY_BASENAME = [
        'upstream-html-cite-wbr-raw-inline' => ['htmlRawHtml' => true],
        'upstream-html-generic-raw-inline' => ['htmlRawHtml' => true],
        'upstream-html-raw-disabled-skip' => ['htmlRawHtml' => false],
        'upstream-html-script-raw-block' => ['htmlRawHtml' => true],
        'upstream-html-standalone-applet-inline' => ['htmlRawHtml' => true],
        'upstream-html-standalone-button-inline' => ['htmlRawHtml' => true],
        'upstream-html-standalone-inline-flow' => ['htmlRawHtml' => true],
        'upstream-html-standalone-map-inline' => ['htmlRawHtml' => true],
        'upstream-html-standalone-noscript-inline' => ['htmlRawHtml' => true],
        'upstream-html-standalone-object-embed-inline' => ['htmlRawHtml' => true],
        'upstream-html-standalone-svg-inline' => ['htmlRawHtml' => true],
        'upstream-html-standalone-void-inline' => ['htmlRawHtml' => true],
        'upstream-html-svg-disabled-raw-html' => ['htmlRawHtml' => true],
        'upstream-html-svg-raw-html' => ['htmlRawHtml' => true],
        'upstream-html-textarea-raw-block' => ['htmlRawHtml' => true],
    ];
    private const HTMLDOCUMENT_MAPPED_PAIR_EXCLUSIONS = [];

    /** @var array<string, true> */
    private const IGNORED_ATTRS = [
        'attrConstructor' => true,
        'attrNative' => true,
        'alignmentConstructor' => true,
        'alignmentNative' => true,
        'caption' => true,
        'captionBlocks' => true,
        'captionSource' => true,
        'colSpanConstructor' => true,
        'colSpanNative' => true,
        'columnDiagnostics' => true,
        'columnSources' => true,
        'columnSpecs' => true,
        'constructor' => true,
        'header' => true,
        'htmlAttributes' => true,
        'htmlLocalFragmentLinkRepair' => true,
        'htmlLocalFragmentLinkRepairCount' => true,
        'legacyTableCellBlocksNative' => true,
        'meta' => true,
        'native' => true,
        'nativeInlineConstructors' => true,
        'nativeInlineParts' => true,
        'rowSpanConstructor' => true,
        'rowSpanNative' => true,
        'sourceFormat' => true,
        'tableGeometry' => true,
        'widths' => true,
    ];

    /**
     * @param array{limit?: int, maxExamples?: int} $options
     * @return array<string, mixed>
     */
    public function run(string $htmlDirectory, array $options = []): array
    {
        $limit = max(0, (int) ($options['limit'] ?? 0));
        $maxExamples = max(0, (int) ($options['maxExamples'] ?? self::DEFAULT_MAX_EXAMPLES));

        if (!is_dir($htmlDirectory)) {
            return $this->skippedReport($htmlDirectory, 'html-native-fixture-directory-missing');
        }

        $htmlFiles = $this->filesByBasename($htmlDirectory, 'html');
        $nativeFiles = array_filter(
            $this->filesByBasename($htmlDirectory, 'native'),
            static fn (string $basename): bool => isset($htmlFiles[$basename]) || self::isHtmlReaderFixtureBasename($basename),
            ARRAY_FILTER_USE_KEY
        );
        $htmlFixtureNames = array_keys($htmlFiles);
        $nativeFixtureNames = array_keys($nativeFiles);
        $pairNames = array_values(array_intersect($htmlFixtureNames, $nativeFixtureNames));
        sort($pairNames, SORT_STRING);
        $excludedPairNames = array_values(array_intersect($pairNames, array_keys(self::HTMLDOCUMENT_MAPPED_PAIR_EXCLUSIONS)));
        sort($excludedPairNames, SORT_STRING);
        $comparisonPairNames = array_values(array_diff($pairNames, $excludedPairNames));
        $unpairedHtmlNames = array_values(array_diff($htmlFixtureNames, $pairNames));
        $unpairedNativeNames = array_values(array_diff($nativeFixtureNames, $pairNames));
        $unpairedHtmlFixtureNames = self::fixtureNamesWithExtension($unpairedHtmlNames, 'html', count($unpairedHtmlNames));
        $unpairedNativeFixtureNames = self::fixtureNamesWithExtension($unpairedNativeNames, 'native', count($unpairedNativeNames));
        $excludedPairs = array_map(
            static fn (string $name): array => [
                'fixture' => $name,
                'htmlFixture' => $name . '.html',
                'nativeFixture' => $name . '.native',
                'reason' => self::HTMLDOCUMENT_MAPPED_PAIR_EXCLUSIONS[$name],
            ],
            $excludedPairNames
        );

        $totalPairCount = count($pairNames);
        $fixtureInventorySignature = self::checkedInFixtureInventorySignature(
            $htmlFiles,
            $nativeFiles,
            $pairNames,
            $unpairedHtmlNames,
            $unpairedNativeNames
        );
        if ($limit > 0) {
            $comparisonPairNames = array_slice($comparisonPairNames, 0, $limit);
        }

        $htmlParsedCount = 0;
        $nativeParsedCount = 0;
        $bothParsedCount = 0;
        $matchCount = 0;
        $parseFailures = [];
        $mismatches = [];
        $categoryCounts = [];

        foreach ($comparisonPairNames as $pairName) {
            $htmlResult = $this->readHtml($htmlFiles[$pairName], $pairName);
            if ($htmlResult['ok']) {
                ++$htmlParsedCount;
            }

            $nativeResult = $this->readNative($nativeFiles[$pairName]);
            if ($nativeResult['ok']) {
                ++$nativeParsedCount;
            }

            if (!$htmlResult['ok'] || !$nativeResult['ok']) {
                $parseFailures[] = [
                    'fixture' => $pairName,
                    'htmlError' => $htmlResult['error'],
                    'nativeError' => $nativeResult['error'],
                ];
                $this->addCategory($categoryCounts, 'parse-failure', $pairName, $maxExamples);
                continue;
            }

            /** @var AstNode $htmlDocument */
            $htmlDocument = $htmlResult['document'];
            /** @var AstNode $nativeDocument */
            $nativeDocument = $nativeResult['document'];
            ++$bothParsedCount;

            $htmlAst = $this->normalizedNode($htmlDocument);
            $nativeAst = $this->normalizedNode($nativeDocument);
            if ($htmlAst === $nativeAst) {
                ++$matchCount;
                continue;
            }

            $difference = $this->firstDifference($htmlAst, $nativeAst) ?? 'unknown-normalized-ast-difference';
            $categories = $this->mismatchCategories($difference);
            foreach ($categories as $category) {
                $this->addCategory($categoryCounts, $category, $pairName, $maxExamples);
            }
            if (count($mismatches) < $maxExamples) {
                $mismatches[] = [
                    'fixture' => $pairName,
                    'firstDifference' => $difference,
                    'categories' => $categories,
                    'htmlTopTypes' => $this->topTypeSequence($htmlDocument),
                    'nativeTopTypes' => $this->topTypeSequence($nativeDocument),
                ];
            }
        }

        ksort($categoryCounts);
        $comparedPairCount = count($comparisonPairNames);
        $mismatchCount = $bothParsedCount - $matchCount;

        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-html-native-ast',
            'status' => 'completed',
            'skipped' => false,
            'reason' => null,
            'verdict' => self::VERDICT,
            'claim' => self::CLAIM,
            'evidenceKind' => 'html-native-normalized-ast-comparison',
            'upstreamHtmlDirectory' => $htmlDirectory,
            'normalizationPolicy' => self::normalizationPolicy(),
            'htmlReaderFixtureOptionOverrides' => self::htmlReaderFixtureOptionOverrides(),
            'checkedInFixtureInventorySignature' => $fixtureInventorySignature,
            'htmlFixtureCount' => count($htmlFiles),
            'nativeFixtureCount' => count($nativeFiles),
            'pairedFixtureCount' => $totalPairCount,
            'unpairedHtmlFixtureCount' => count($unpairedHtmlNames),
            'unpairedNativeFixtureCount' => count($unpairedNativeNames),
            'unpairedHtmlFixtureNames' => $unpairedHtmlFixtureNames,
            'unpairedNativeFixtureNames' => $unpairedNativeFixtureNames,
            'unpairedHtmlFixtureExamples' => self::fixtureNamesWithExtension($unpairedHtmlNames, 'html', $maxExamples),
            'unpairedNativeFixtureExamples' => self::fixtureNamesWithExtension($unpairedNativeNames, 'native', $maxExamples),
            'excludedMappedPairCount' => count($excludedPairs),
            'excludedMappedPairs' => $excludedPairs,
            'totalPairCount' => $totalPairCount,
            'comparedPairCount' => $comparedPairCount,
            'htmlParsedCount' => $htmlParsedCount,
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
                count($htmlFiles),
                count($nativeFiles),
                $totalPairCount,
                $comparedPairCount,
                count($parseFailures),
                $matchCount,
                $mismatchCount,
                count($unpairedHtmlNames),
                count($excludedPairs)
            ),
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public function formatReport(array $report): string
    {
        $lines = [
            'Pandoc HTML/native AST comparison: ' . (string) ($report['status'] ?? 'unknown'),
            'Verdict: ' . (string) ($report['verdict'] ?? self::VERDICT),
            'Claim: ' . (string) ($report['claim'] ?? self::CLAIM),
            'upstreamHtmlDirectory=' . (string) ($report['upstreamHtmlDirectory'] ?? ''),
        ];

        if (($report['skipped'] ?? false) === true) {
            $lines[] = 'reason=' . (string) ($report['reason'] ?? 'unknown');
            $lines = self::appendOrderedRemainingGaps($lines, $report);

            return implode(PHP_EOL, $lines) . PHP_EOL;
        }

        $lines[] = sprintf(
            'fixtureInventory: html=%d native=%d paired=%d unpairedHtml=%d unpairedNative=%d',
            (int) ($report['htmlFixtureCount'] ?? 0),
            (int) ($report['nativeFixtureCount'] ?? 0),
            (int) ($report['pairedFixtureCount'] ?? $report['totalPairCount'] ?? 0),
            (int) ($report['unpairedHtmlFixtureCount'] ?? 0),
            (int) ($report['unpairedNativeFixtureCount'] ?? 0)
        );
        $signature = is_array($report['checkedInFixtureInventorySignature'] ?? null)
            ? $report['checkedInFixtureInventorySignature']
            : [];
        $lines[] = sprintf(
            'fixtureInventorySignature: status=%s matchesExpected=%s sha256=%s expected=%s',
            (string) ($signature['status'] ?? 'unknown'),
            ($signature['matchesExpected'] ?? false) === true ? 'yes' : 'no',
            (string) ($signature['sha256'] ?? ''),
            (string) ($signature['expectedSha256'] ?? '')
        );
        $lines[] = sprintf(
            'pairs: total=%d compared=%d excluded=%d parsedBoth=%d parseFailures=%d',
            (int) ($report['totalPairCount'] ?? 0),
            (int) ($report['comparedPairCount'] ?? 0),
            (int) ($report['excludedMappedPairCount'] ?? 0),
            (int) ($report['bothParsedCount'] ?? 0),
            (int) ($report['parseFailureCount'] ?? 0)
        );
        $lines[] = sprintf(
            'normalizedAst: matches=%d (%s) mismatches=%d status=%s',
            (int) ($report['normalizedAstMatchCount'] ?? 0),
            self::formatPercent($report['normalizedAstMatchPercent'] ?? null),
            (int) ($report['normalizedAstMismatchCount'] ?? 0),
            (string) ($report['astParityStatus'] ?? 'unknown')
        );

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
            throw new \InvalidArgumentException('Required HTML mapped parity count must not be negative');
        }

        return ($report['skipped'] ?? false) === false
            && ($report['status'] ?? null) === 'completed'
            && self::hasValidCheckedInFixtureInventorySignature($report)
            && (int) ($report['comparedPairCount'] ?? -1) === $requiredPairCount
            && (int) ($report['htmlParsedCount'] ?? -1) === $requiredPairCount
            && (int) ($report['nativeParsedCount'] ?? -1) === $requiredPairCount
            && (int) ($report['bothParsedCount'] ?? -1) === $requiredPairCount
            && (int) ($report['parseFailureCount'] ?? -1) === 0
            && (int) ($report['normalizedAstMatchCount'] ?? -1) === $requiredPairCount
            && (int) ($report['normalizedAstMismatchCount'] ?? -1) === 0
            && ($report['astParityStatus'] ?? null) === 'normalized-ast-implementation-equivalence-observed';
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasValidCheckedInFixtureInventorySignature(array $report): bool
    {
        $signature = $report['checkedInFixtureInventorySignature'] ?? null;

        return is_array($signature)
            && ($signature['status'] ?? null) === 'valid-checked-in-html-fixture-inventory'
            && ($signature['matchesExpected'] ?? null) === true
            && ($signature['algorithm'] ?? null) === self::FIXTURE_INVENTORY_SIGNATURE_ALGORITHM
            && ($signature['sha256'] ?? null) === self::CHECKED_IN_FIXTURE_INVENTORY_SIGNATURE_SHA256;
    }

    /**
     * @return array<string, mixed>
     */
    private function skippedReport(string $htmlDirectory, string $reason): array
    {
        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-html-native-ast',
            'status' => 'skipped',
            'skipped' => true,
            'reason' => $reason,
            'verdict' => self::VERDICT,
            'claim' => self::CLAIM,
            'evidenceKind' => 'html-native-normalized-ast-comparison',
            'upstreamHtmlDirectory' => $htmlDirectory,
            'normalizationPolicy' => self::normalizationPolicy(),
            'htmlReaderFixtureOptionOverrides' => self::htmlReaderFixtureOptionOverrides(),
            'checkedInFixtureInventorySignature' => self::notEvaluatedFixtureInventorySignature(),
            'htmlFixtureCount' => 0,
            'nativeFixtureCount' => 0,
            'pairedFixtureCount' => 0,
            'unpairedHtmlFixtureCount' => 0,
            'unpairedNativeFixtureCount' => 0,
            'unpairedHtmlFixtureNames' => [],
            'unpairedNativeFixtureNames' => [],
            'unpairedHtmlFixtureExamples' => [],
            'unpairedNativeFixtureExamples' => [],
            'excludedMappedPairCount' => 0,
            'excludedMappedPairs' => [],
            'totalPairCount' => 0,
            'comparedPairCount' => 0,
            'htmlParsedCount' => 0,
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
            'orderedRemainingGaps' => self::orderedRemainingGaps(false, 0, 0, 0, 0, 0, 0, 0, 0, 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizationPolicy(): array
    {
        return [
            'compares' => [
                'node type after table-cell Plain wrappers are normalized',
                'non-provenance node attributes',
                'child order and child count',
                'visible inline text after adjacent text nodes are coalesced; block-level cached text attrs are excluded',
                'table head/body/body-row-header structure through rowHeadColumns',
            ],
            'excludes' => [
                'HTML reader document metadata and microdata review attrs',
                'NativeReader constructor provenance attrs',
                'table geometry review packets',
                'default table widths, zero rowHeadColumns, and empty captions/feet',
                'derived table-cell header flags when rowHeadColumns carries the semantic row-header contract',
                'default false HTML definition-list looseness sidecars when native text carries no equivalent flag',
                'reader-derived block cached text metadata, including paragraph/list-item text; task-list sidecars are normalized to Pandoc ballot-box text; block and inline shape remains compared',
                'source id/classes/key-value attrs on Pandoc inline constructors without native Attr tuples; inline constructor, text, and children remain compared',
                'local HTML fragment-link repair IDs and diagnostics; original link targets and visible link text remain compared',
                'redundant raw HTML format attrs and duplicate raw HTML text attrs; raw HTML payload remains compared',
            ],
            'doesNotAssert' => [
                'runtime Cabal/Tasty execution when no runner result artifact is supplied',
                'HTML writer parity',
                'byte-for-byte identity with Pandoc Haskell reader internals',
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

    public static function isHtmlReaderFixtureBasename(string $basename): bool
    {
        return str_starts_with($basename, 'upstream-html-')
            || str_starts_with($basename, 'upstream-native-html-');
    }

    /**
     * @return array<string, mixed>
     */
    public static function readerOptionsForFixtureBasename(string $basename): array
    {
        return self::HTML_READER_OPTIONS_BY_BASENAME[$basename] ?? [];
    }

    /**
     * @return array{ok: bool, document: ?AstNode, error: ?string}
     */
    private function readHtml(string $path, string $fixtureName): array
    {
        try {
            $bytes = file_get_contents($path);
            if (!is_string($bytes)) {
                throw new \RuntimeException("Unable to read HTML fixture '{$path}'.");
            }

            return [
                'ok' => true,
                'document' => (new HtmlReader(array_replace(
                    ['htmlReaderBackend' => HtmlReader::BACKEND_TAGSOUP_PANDOC_PORT],
                    self::HTML_READER_OPTIONS_BY_BASENAME[$fixtureName] ?? []
                )))->read($bytes),
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'document' => null, 'error' => $exception::class . ': ' . $exception->getMessage()];
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function htmlReaderFixtureOptionOverrides(): array
    {
        $overrides = [];
        foreach (self::HTML_READER_OPTIONS_BY_BASENAME as $basename => $options) {
            $overrides[$basename . '.html'] = $options;
        }

        return $overrides;
    }

    /**
     * @param array<string, string> $htmlFiles
     * @param array<string, string> $nativeFiles
     * @param list<string> $pairNames
     * @param list<string> $unpairedHtmlNames
     * @param list<string> $unpairedNativeNames
     * @return array<string, mixed>
     */
    private static function checkedInFixtureInventorySignature(
        array $htmlFiles,
        array $nativeFiles,
        array $pairNames,
        array $unpairedHtmlNames,
        array $unpairedNativeNames
    ): array {
        $records = [];
        foreach ($htmlFiles as $basename => $path) {
            $records[] = self::fixtureFileEvidence('html', $basename . '.html', $path);
        }
        foreach ($nativeFiles as $basename => $path) {
            $records[] = self::fixtureFileEvidence('native', $basename . '.native', $path);
        }

        usort(
            $records,
            static fn (array $left, array $right): int => strcmp(
                (string) ($left['fixture'] ?? ''),
                (string) ($right['fixture'] ?? '')
            ) ?: strcmp((string) ($left['kind'] ?? ''), (string) ($right['kind'] ?? ''))
        );

        $payload = [
            'htmlFixtureCount' => count($htmlFiles),
            'nativeFixtureCount' => count($nativeFiles),
            'pairedFixtureCount' => count($pairNames),
            'pairNames' => $pairNames,
            'unpairedHtmlFixtureNames' => self::fixtureNamesWithExtension($unpairedHtmlNames, 'html', count($unpairedHtmlNames)),
            'unpairedNativeFixtureNames' => self::fixtureNamesWithExtension($unpairedNativeNames, 'native', count($unpairedNativeNames)),
            'htmlReaderFixtureOptionOverrides' => self::htmlReaderFixtureOptionOverrides(),
            'records' => $records,
        ];
        $sha256 = hash('sha256', self::canonicalJson($payload));
        $matchesExpected = $sha256 === self::CHECKED_IN_FIXTURE_INVENTORY_SIGNATURE_SHA256;

        return [
            'algorithm' => self::FIXTURE_INVENTORY_SIGNATURE_ALGORITHM,
            'status' => $matchesExpected
                ? 'valid-checked-in-html-fixture-inventory'
                : 'checked-in-html-fixture-inventory-signature-mismatch',
            'matchesExpected' => $matchesExpected,
            'sha256' => $sha256,
            'expectedSha256' => self::CHECKED_IN_FIXTURE_INVENTORY_SIGNATURE_SHA256,
            'htmlFixtureCount' => count($htmlFiles),
            'nativeFixtureCount' => count($nativeFiles),
            'pairedFixtureCount' => count($pairNames),
            'recordCount' => count($records),
            'sampleRecords' => array_slice($records, 0, self::DEFAULT_MAX_EXAMPLES),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function notEvaluatedFixtureInventorySignature(): array
    {
        return [
            'algorithm' => self::FIXTURE_INVENTORY_SIGNATURE_ALGORITHM,
            'status' => 'not-evaluated',
            'matchesExpected' => false,
            'sha256' => null,
            'expectedSha256' => self::CHECKED_IN_FIXTURE_INVENTORY_SIGNATURE_SHA256,
            'htmlFixtureCount' => 0,
            'nativeFixtureCount' => 0,
            'pairedFixtureCount' => 0,
            'recordCount' => 0,
            'sampleRecords' => [],
        ];
    }

    /**
     * @return array{kind: string, fixture: string, sha256: string, bytes: int}
     */
    private static function fixtureFileEvidence(string $kind, string $fixture, string $path): array
    {
        return [
            'kind' => $kind,
            'fixture' => $fixture,
            'sha256' => hash_file('sha256', $path),
            'bytes' => (int) filesize($path),
        ];
    }

    private static function canonicalJson(mixed $value): string
    {
        return json_encode(self::canonicalValue($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function canonicalValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(self::canonicalValue(...), $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $child) {
            $value[$key] = self::canonicalValue($child);
        }

        return $value;
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
    public function normalizedDocument(AstNode $document): array
    {
        return $this->normalizedNode($document);
    }

    /**
     * @return array{type:string, attrs:array<string, mixed>, children:list<array<string, mixed>>}
     */
    private function normalizedNode(AstNode $node): array
    {
        $attrs = [];
        $taskChecked = null;
        foreach ($node->attrs as $key => $value) {
            $key = (string) $key;
            if (self::isIgnoredAttrKey($key)) {
                continue;
            }
            if ($key === 'text' && in_array($node->type, ['paragraph', 'plain', 'table_cell'], true)) {
                continue;
            }
            if ($key === 'text' && $node->type === 'list_item') {
                continue;
            }
            if ($key === 'text' && $node->type === 'term') {
                continue;
            }
            if ($key === 'term' && $node->type === 'definition_item') {
                continue;
            }
            if ($key === 'loose' && self::isListShapeMetadataNode($node)) {
                continue;
            }
            if ($key === 'loose' && $node->type === 'definition' && $value === false) {
                continue;
            }
            if ($key === 'taskChecked' && $node->type === 'list_item' && is_bool($value)) {
                $taskChecked = $value;
                continue;
            }
            if (self::isRedundantRawHtmlAttr($node, $key, $value)) {
                continue;
            }
            if (self::isNativeAttrlessInlineNode($node) && in_array($key, ['id', 'classes', 'attributes'], true)) {
                continue;
            }
            if ($key === 'id' && $node->attr('htmlLocalFragmentLinkRepair') === true) {
                continue;
            }
            if ($key === 'caption' && $value === '') {
                continue;
            }
            if ($key === 'rowHeadColumns' && (int) $value === 0) {
                continue;
            }
            if ($key === 'attributes' && $value === []) {
                continue;
            }

            $normalizedValue = $this->normalizedValue($value);
            if ($key === 'attributes' && is_array($normalizedValue)) {
                $normalizedValue = $this->normalizedAttributeMap($normalizedValue);
            }
            if ($normalizedValue === [] || $normalizedValue === null || $normalizedValue === '') {
                continue;
            }
            $attrs[$key] = $normalizedValue;
        }
        ksort($attrs, SORT_STRING);
        $children = $this->normalizedChildren($node->children);
        if ($node->type === 'list_item' && $taskChecked !== null) {
            $children = $this->withNormalizedTaskMarker($children, $taskChecked);
        }

        return [
            'type' => $node->type,
            'attrs' => $attrs,
            'children' => $children,
        ];
    }

    private static function isIgnoredAttrKey(string $key): bool
    {
        if (isset(self::IGNORED_ATTRS[$key]) && self::IGNORED_ATTRS[$key] === true) {
            return true;
        }

        return str_starts_with($key, 'native')
            || str_ends_with($key, 'Native')
            || str_ends_with($key, 'Natives')
            || str_ends_with($key, 'Constructor')
            || str_ends_with($key, 'Constructors');
    }

    private static function isListShapeMetadataNode(AstNode $node): bool
    {
        return in_array($node->type, ['bullet_list', 'ordered_list', 'definition_list', 'list_item'], true);
    }

    private static function isNativeAttrlessInlineNode(AstNode $node): bool
    {
        return in_array($node->type, [
            'emph',
            'small_caps',
            'strikeout',
            'strong',
            'subscript',
            'superscript',
            'underline',
        ], true);
    }

    private static function isRedundantRawHtmlAttr(AstNode $node, string $key, mixed $value): bool
    {
        if (!in_array($node->type, ['raw_html', 'raw_html_inline'], true)) {
            return false;
        }

        if ($key === 'format' && $value === 'html') {
            return true;
        }

        return $key === 'text'
            && is_string($value)
            && is_string($node->attr('html', null))
            && $value === $node->attr('html');
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
            if ($node['type'] === 'plain') {
                foreach ($node['children'] as $plainChild) {
                    $this->appendNormalizedChild($normalized, $plainChild);
                }
                continue;
            }
            $this->appendNormalizedChild($normalized, $node);
        }

        return $normalized;
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
     * @param list<array<string, mixed>> $children
     * @return list<array<string, mixed>>
     */
    private function withNormalizedTaskMarker(array $children, bool $checked): array
    {
        $marker = $checked ? "\u{2612}" : "\u{2610}";
        $prefix = $marker . ' ';
        if (isset($children[0]) && $this->isPlainTextNode($children[0])) {
            $text = $children[0]['attrs']['text'];
            if (str_starts_with($text, $marker)) {
                return $children;
            }
            $children[0]['attrs']['text'] = $prefix . $text;

            return $children;
        }

        array_unshift($children, [
            'type' => 'text',
            'attrs' => ['text' => rtrim($prefix)],
            'children' => [],
        ]);

        return $children;
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
     * @param array<string, mixed> $node
     */
    private function isEmptyTableFootNode(array $node): bool
    {
        return ($node['type'] ?? null) === 'table_foot'
            && ($node['attrs'] ?? []) === []
            && ($node['children'] ?? []) === [];
    }

    private function normalizedValue(mixed $value): mixed
    {
        if ($value instanceof AstNode) {
            return $this->normalizedNode($value);
        }
        if (!is_array($value)) {
            return is_float($value) ? round($value, 6) : $value;
        }

        $normalized = [];
        foreach ($value as $key => $child) {
            $normalized[$key] = $this->normalizedValue($child);
        }
        if (array_is_list($normalized)) {
            return $this->isNormalizedNodeList($normalized) ? $this->coalescedNormalizedNodeList($normalized) : $normalized;
        }
        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function normalizedAttributeMap(array $attributes): array
    {
        if (isset($attributes['style']) && is_string($attributes['style'])) {
            $attributes['style'] = $this->normalizedStyleAttribute($attributes['style']);
            if ($attributes['style'] === '') {
                unset($attributes['style']);
            }
        }

        return $attributes;
    }

    private function normalizedStyleAttribute(string $style): string
    {
        $declarations = [];
        foreach (preg_split('/;/', $style) ?: [] as $declaration) {
            $declaration = trim($declaration);
            if ($declaration !== '') {
                $declarations[] = $declaration;
            }
        }

        return implode('; ', $declarations);
    }

    /**
     * @param list<mixed> $items
     */
    private function isNormalizedNodeList(array $items): bool
    {
        if ($items === []) {
            return false;
        }

        foreach ($items as $item) {
            if (
                !is_array($item)
                || !is_string($item['type'] ?? null)
                || !is_array($item['attrs'] ?? null)
                || !is_array($item['children'] ?? null)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function coalescedNormalizedNodeList(array $items): array
    {
        $coalesced = [];
        foreach ($items as $item) {
            $this->appendNormalizedChild($coalesced, $item);
        }

        return $coalesced;
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    private function firstDifference(array $left, array $right, string $path = '$'): ?string
    {
        if ($left['type'] !== $right['type']) {
            return "{$path}.type html={$left['type']} native={$right['type']}";
        }
        if ($left['attrs'] !== $right['attrs']) {
            return "{$path}.attrs html=" . json_encode($left['attrs'], JSON_UNESCAPED_SLASHES)
                . ' native=' . json_encode($right['attrs'], JSON_UNESCAPED_SLASHES);
        }
        if (count($left['children']) !== count($right['children'])) {
            return "{$path}.children count html=" . count($left['children']) . ' native=' . count($right['children']);
        }
        foreach ($left['children'] as $index => $leftChild) {
            $difference = $this->firstDifference($leftChild, $right['children'][$index], $path . '.children[' . $index . ']');
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
        $categories = [];
        if (str_contains($difference, '.type')) {
            $categories[] = 'node-type-difference';
        }
        if (str_contains($difference, '.attrs')) {
            $categories[] = 'attribute-difference';
        }
        if (str_contains($difference, '.children count')) {
            $categories[] = 'child-count-difference';
        }

        return $categories === [] ? ['normalized-ast-difference'] : array_values(array_unique($categories));
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
            return 'not-evaluated-no-native-pairs';
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
        bool $directoryPresent,
        int $htmlFixtureCount,
        int $nativeFixtureCount,
        int $pairedFixtureCount,
        int $comparedPairCount,
        int $parseFailureCount,
        int $matchCount,
        int $mismatchCount,
        int $unpairedHtmlFixtureCount,
        int $excludedMappedPairCount
    ): array {
        $astEvidence = $directoryPresent
            ? "native mapped pairs={$comparedPairCount}; excluded mapped pairs={$excludedMappedPairCount}; parse failures={$parseFailureCount}; normalized matches={$matchCount}; normalized mismatches={$mismatchCount}"
            : 'HTML/native fixture directory absent; native AST comparison did not run';
        $astCovered = $directoryPresent
            && $comparedPairCount > 0
            && $parseFailureCount === 0
            && $mismatchCount === 0
            && $matchCount === $comparedPairCount;
        $tagSoupBacked = true;
        $tagSoupReaderCovered = $astCovered
            && $tagSoupBacked
            && $pairedFixtureCount === $htmlFixtureCount
            && $excludedMappedPairCount === 0;

        return [
            [
                'rank' => 1,
                'id' => 'checked-in-html-native-ast-equality',
                'status' => !$directoryPresent ? 'not-evaluated' : ($astCovered ? 'covered-by-current-normalized-ast-evidence' : 'open'),
                'currentEvidence' => $astEvidence,
                'evidenceRequired' => 'Compare every TagSoup-backed same-basename checked-in HTML/native fixture with zero parse failures and zero normalized AST mismatches.',
            ],
            [
                'rank' => 2,
                'id' => 'checked-in-html-fixtures-without-native-pairs',
                'status' => !$directoryPresent ? 'not-evaluated' : ($unpairedHtmlFixtureCount === 0 ? 'covered-by-current-normalized-ast-evidence' : 'open'),
                'currentEvidence' => $directoryPresent
                    ? "HTML fixtures={$htmlFixtureCount}; native fixtures={$nativeFixtureCount}; same-basename pairs={$pairedFixtureCount}; HTML fixtures without native pairs={$unpairedHtmlFixtureCount}"
                    : 'HTML/native fixture directory absent; unpaired fixture inventory did not run',
                'evidenceRequired' => 'Add same-basename checked-in .native expectations only when normalized AST parity has been demonstrated, and keep unpaired fixtures scoped as corpus-only evidence until then.',
            ],
            [
                'rank' => 3,
                'id' => 'upstream-html-reader-runner-results',
                'status' => 'open',
                'currentEvidence' => 'This harness compares checked-in HTML/native fixtures but does not run upstream Haskell/Tasty HTML reader tests.',
                'evidenceRequired' => 'Record reproducible upstream Tests.Readers.HTML runner results when a Haskell runner is available.',
            ],
            [
                'rank' => 4,
                'id' => 'tagsoup-pandoc-html-reader-backend',
                'status' => !$directoryPresent ? 'not-evaluated' : ($tagSoupReaderCovered ? 'covered-by-current-tagsoup-backend' : 'open'),
                'currentEvidence' => $directoryPresent
                    ? "TagSoup backend=tagsoup-pandoc-port; checked-in gate covers {$comparedPairCount} TagSoup-backed paired fixture(s) out of {$htmlFixtureCount} HTML fixture(s); {$excludedMappedPairCount} source-preservation fixture(s) are tracked but excluded from the mapped gate."
                    : 'HTML/native fixture directory absent; fixture coverage did not run.',
                'evidenceRequired' => 'Use the TagSoup-backed Pandoc HTML reader port and keep every checked-in HTML/native mapped fixture at zero parse failures and zero normalized AST mismatches.',
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
                '%d. %s [%s]: %s',
                (int) ($gap['rank'] ?? 0),
                (string) ($gap['id'] ?? 'unknown'),
                (string) ($gap['status'] ?? 'unknown'),
                (string) ($gap['currentEvidence'] ?? '')
            );
        }

        return $lines;
    }

    private static function percent(int $count, int $total): ?float
    {
        return $total === 0 ? null : round(($count / $total) * 100, 2);
    }

    private static function formatPercent(mixed $percent): string
    {
        return is_float($percent) || is_int($percent) ? number_format((float) $percent, 2) . '%' : 'n/a';
    }

    /**
     * @param list<string> $basenames
     * @return list<string>
     */
    private static function fixtureNamesWithExtension(array $basenames, string $extension, int $maxExamples): array
    {
        return array_map(
            static fn (string $basename): string => $basename . '.' . $extension,
            array_slice($basenames, 0, $maxExamples)
        );
    }
}
