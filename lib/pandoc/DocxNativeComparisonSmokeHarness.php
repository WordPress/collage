<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DocxNativeComparisonSmokeHarness
{
    private const DEFAULT_MAX_EXAMPLES = 5;
    private const VERDICT = 'smoke-only-not-full-docx-parity';
    private const CLAIM = 'Compares local PHP DOCX reader output with paired upstream .native fixtures by plain text and top-level node type sequence only; no full AST equality, upstream runner, or writer golden parity is asserted.';

    /** @var list<string> */
    private const TEXT_NODE_TYPES = [
        'text',
        'code',
        'code_block',
        'math',
        'raw_block',
        'raw_html',
        'raw_markdown',
        'raw_tex',
        'raw_inline',
        'raw_html_inline',
        'raw_tex_inline',
    ];

    /** @var list<string> */
    private const BLOCKISH_NODE_TYPES = [
        'blockquote',
        'bullet_list',
        'definition',
        'definition_item',
        'definition_list',
        'div',
        'figure',
        'heading',
        'line',
        'line_block',
        'list_item',
        'ordered_list',
        'paragraph',
        'plain',
        'table',
        'table_body',
        'table_cell',
        'table_foot',
        'table_head',
        'table_row',
        'term',
    ];

    /**
     * @param array{limit?: int, maxExamples?: int} $options
     * @return array<string, mixed>
     */
    public function run(string $docxDirectory, array $options = []): array
    {
        $limit = max(0, (int) ($options['limit'] ?? 0));
        $maxExamples = max(0, (int) ($options['maxExamples'] ?? self::DEFAULT_MAX_EXAMPLES));

        if (!is_dir($docxDirectory)) {
            return [
                'schemaVersion' => 1,
                'tool' => 'pandoc-docx-native-smoke',
                'status' => 'skipped',
                'skipped' => true,
                'reason' => 'upstream-cache-missing',
                'verdict' => self::VERDICT,
                'claim' => self::CLAIM,
                'evidenceKind' => 'docx-native-reader-smoke-comparison',
                'upstreamDocxDirectory' => $docxDirectory,
                'docxArtifactCount' => 0,
                'nativeArtifactCount' => 0,
                'totalPairCount' => 0,
                'comparedPairCount' => 0,
                'docxParsedCount' => 0,
                'nativeParsedCount' => 0,
                'bothParsedCount' => 0,
                'parseFailureCount' => 0,
                'sameTextCount' => 0,
                'sameTopTypeSequenceCount' => 0,
                'sameTextPercent' => null,
                'sameTopTypeSequencePercent' => null,
                'semanticGapPairCount' => 0,
                'semanticParityStatus' => 'not-evaluated-source-directory-unavailable',
                'knownSemanticGapCategories' => [],
                'parseFailures' => [],
                'comparisons' => [],
                'semanticGapComparisons' => [],
                'orderedRemainingGaps' => self::orderedRemainingGaps(false, 0, 0, 0, 0, 0),
            ];
        }

        $docxFiles = $this->filesByBasename($docxDirectory, 'docx');
        $nativeFiles = $this->filesByBasename($docxDirectory, 'native');
        $pairNames = array_values(array_intersect(array_keys($docxFiles), array_keys($nativeFiles)));
        sort($pairNames);

        $totalPairCount = count($pairNames);
        if ($limit > 0) {
            $pairNames = array_slice($pairNames, 0, $limit);
        }

        $docxParsedCount = 0;
        $nativeParsedCount = 0;
        $bothParsedCount = 0;
        $sameTextCount = 0;
        $sameTopTypeSequenceCount = 0;
        $semanticGapPairCount = 0;
        $parseFailures = [];
        $comparisons = [];
        $semanticGapComparisons = [];
        $categoryCounts = [];

        foreach ($pairNames as $pairName) {
            $docxResult = $this->readDocx($docxFiles[$pairName]);
            if ($docxResult['ok']) {
                ++$docxParsedCount;
            }

            $nativeResult = $this->readNative($nativeFiles[$pairName]);
            if ($nativeResult['ok']) {
                ++$nativeParsedCount;
            }

            if (!$docxResult['ok'] || !$nativeResult['ok']) {
                $failure = [
                    'fixture' => $pairName,
                    'docxError' => $docxResult['error'],
                    'nativeError' => $nativeResult['error'],
                ];
                $parseFailures[] = $failure;
                $this->addCategory($categoryCounts, 'parse-failure', $pairName, $maxExamples);
                continue;
            }

            /** @var AstNode $docxDocument */
            $docxDocument = $docxResult['document'];
            /** @var AstNode $nativeDocument */
            $nativeDocument = $nativeResult['document'];
            ++$bothParsedCount;

            $docxText = $this->plainText($docxDocument);
            $nativeText = $this->plainText($nativeDocument);
            $docxTopTypes = $this->topTypeSequence($docxDocument);
            $nativeTopTypes = $this->topTypeSequence($nativeDocument);
            $sameText = $docxText === $nativeText;
            $sameTopTypes = $docxTopTypes === $nativeTopTypes;

            if ($sameText) {
                ++$sameTextCount;
            }
            if ($sameTopTypes) {
                ++$sameTopTypeSequenceCount;
            }

            $categories = [];
            if (!$sameText || !$sameTopTypes) {
                ++$semanticGapPairCount;
                $categories = $this->semanticGapCategories($pairName, $docxText, $sameText, $sameTopTypes);
                foreach ($categories as $category) {
                    $this->addCategory($categoryCounts, $category, $pairName, $maxExamples);
                }
                $semanticGapComparisons[] = [
                    'fixture' => $pairName,
                    'sameText' => $sameText,
                    'sameTopTypeSequence' => $sameTopTypes,
                    'docxTopTypes' => $docxTopTypes,
                    'nativeTopTypes' => $nativeTopTypes,
                    'categories' => $categories,
                ];
            }

            if (count($comparisons) < $maxExamples) {
                $comparisons[] = [
                    'fixture' => $pairName,
                    'sameText' => $sameText,
                    'sameTopTypeSequence' => $sameTopTypes,
                    'docxTopTypes' => $docxTopTypes,
                    'nativeTopTypes' => $nativeTopTypes,
                    'categories' => $categories,
                ];
            }
        }

        ksort($categoryCounts);

        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-docx-native-smoke',
            'status' => 'completed',
            'skipped' => false,
            'reason' => null,
            'verdict' => self::VERDICT,
            'claim' => self::CLAIM,
            'evidenceKind' => 'docx-native-reader-smoke-comparison',
            'upstreamDocxDirectory' => $docxDirectory,
            'docxArtifactCount' => count($docxFiles),
            'nativeArtifactCount' => count($nativeFiles),
            'totalPairCount' => $totalPairCount,
            'comparedPairCount' => count($pairNames),
            'docxParsedCount' => $docxParsedCount,
            'nativeParsedCount' => $nativeParsedCount,
            'bothParsedCount' => $bothParsedCount,
            'parseFailureCount' => count($parseFailures),
            'sameTextCount' => $sameTextCount,
            'sameTopTypeSequenceCount' => $sameTopTypeSequenceCount,
            'sameTextPercent' => self::percent($sameTextCount, count($pairNames)),
            'sameTopTypeSequencePercent' => self::percent($sameTopTypeSequenceCount, count($pairNames)),
            'semanticGapPairCount' => $semanticGapPairCount,
            'semanticParityStatus' => self::semanticParityStatus(count($parseFailures), $semanticGapPairCount, count($pairNames)),
            'knownSemanticGapCategories' => array_values($categoryCounts),
            'parseFailures' => array_slice($parseFailures, 0, $maxExamples),
            'comparisons' => $comparisons,
            'semanticGapComparisons' => $semanticGapComparisons,
            'orderedRemainingGaps' => self::orderedRemainingGaps(
                true,
                count($pairNames),
                count($parseFailures),
                $semanticGapPairCount,
                $sameTextCount,
                $sameTopTypeSequenceCount
            ),
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public function formatReport(array $report): string
    {
        $lines = [
            'Pandoc DOCX/native smoke: ' . (string) $report['status'],
            'Verdict: ' . (string) ($report['verdict'] ?? self::VERDICT),
            'Claim: ' . (string) ($report['claim'] ?? self::CLAIM),
            'upstreamDocxDirectory=' . (string) $report['upstreamDocxDirectory'],
        ];

        if (($report['status'] ?? '') === 'skipped') {
            $lines[] = 'reason=' . (string) ($report['reason'] ?? 'unknown');
            $lines = self::appendOrderedRemainingGaps($lines, $report);

            return implode("\n", $lines) . "\n";
        }

        $lines[] = sprintf(
            'artifacts: docx=%d native=%d totalPairs=%d comparedPairs=%d',
            (int) $report['docxArtifactCount'],
            (int) $report['nativeArtifactCount'],
            (int) $report['totalPairCount'],
            (int) $report['comparedPairCount'],
        );
        $lines[] = sprintf(
            'parsed: docx=%d native=%d both=%d parseFailures=%d',
            (int) $report['docxParsedCount'],
            (int) $report['nativeParsedCount'],
            (int) $report['bothParsedCount'],
            (int) $report['parseFailureCount'],
        );
        $lines[] = sprintf(
            'same: text=%d (%s) topTypeSequence=%d (%s) semanticGapPairs=%d semanticParityStatus=%s',
            (int) $report['sameTextCount'],
            self::formatPercent($report['sameTextPercent'] ?? null),
            (int) $report['sameTopTypeSequenceCount'],
            self::formatPercent($report['sameTopTypeSequencePercent'] ?? null),
            (int) $report['semanticGapPairCount'],
            (string) ($report['semanticParityStatus'] ?? 'unknown'),
        );

        $categories = $report['knownSemanticGapCategories'] ?? [];
        if (is_array($categories) && $categories !== []) {
            $lines[] = 'knownSemanticGapCategories:';
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
                    $exampleText,
                );
            }
        }

        $lines = self::appendOrderedRemainingGaps($lines, $report);

        return implode("\n", $lines) . "\n";
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
        ksort($files);

        return $files;
    }

    /**
     * @return array{ok: bool, document: ?AstNode, error: ?string}
     */
    private function readDocx(string $path): array
    {
        try {
            return ['ok' => true, 'document' => (new DocxReader())->readDocxFile($path), 'error' => null];
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

    private function plainText(AstNode $document): string
    {
        $parts = [];
        $this->appendPlainText($document, $parts);

        return trim(preg_replace('/\s+/u', ' ', implode('', $parts)) ?? '');
    }

    /**
     * @param list<string> $parts
     */
    private function appendPlainText(AstNode $node, array &$parts): void
    {
        if ($this->isMetadataOnlyOpenXmlBookmarkInline($node)) {
            return;
        }

        if (in_array($node->type, self::TEXT_NODE_TYPES, true)) {
            $text = $node->attr('text', '');
            if (is_string($text) && $text !== '') {
                $parts[] = $text;
            }

            return;
        }

        if (in_array($node->type, ['linebreak', 'softbreak', 'space'], true)) {
            $parts[] = ' ';

            return;
        }

        $before = count($parts);
        foreach ($node->children as $child) {
            $this->appendPlainText($child, $parts);
        }

        if (count($parts) > $before && in_array($node->type, self::BLOCKISH_NODE_TYPES, true)) {
            $parts[] = "\n";
        }

        if (count($parts) === $before && $node->children === []) {
            $text = $node->attr('text', '');
            if (is_string($text) && $text !== '') {
                $parts[] = $text;
            }
        }
    }

    private function isMetadataOnlyOpenXmlBookmarkInline(AstNode $node): bool
    {
        if ($node->type !== 'raw_inline' || $node->attr('format') !== 'openxml') {
            return false;
        }

        return preg_match('/^<w:bookmark(?:Start|End)\b/u', trim((string) $node->attr('text', ''))) === 1;
    }

    /**
     * @return list<string>
     */
    private function topTypeSequence(AstNode $document): array
    {
        return array_map(static fn (AstNode $child): string => $child->type, $document->children);
    }

    /**
     * @return list<string>
     */
    private function semanticGapCategories(string $fixture, string $docxText, bool $sameText, bool $sameTopTypes): array
    {
        $fixtureLower = strtolower($fixture);
        $categories = [];

        if (!$sameText && preg_match('/<\/?(?:w|wp|a|r|v|o):/i', $docxText) === 1) {
            $categories[] = 'raw-openxml-field-or-bookmark-markup';
        }

        $keywordCategories = [
            'comment-or-annotation-order' => ['comment'],
            'drawing-image-object-placeholders' => ['diagram', 'drawing', 'image', 'textbox', 'vml'],
            'field-bookmark-cross-reference-resolution' => ['anchor', 'bookmark', 'cross_reference', 'empty_field', 'field', 'instrtext', 'link', 'pageref'],
            'list-numbering-and-item-boundaries' => ['dummy_item', 'list', 'task_list'],
            'metadata-core-properties-normalization' => ['document-properties', 'metadata'],
            'section-header-footer-boundaries' => ['0_level_headers', 'header', 'footer', 'section'],
            'style-whitespace-normalization' => ['char_style', 'drop_cap', 'formatting', 'indentation', 'normalize', 'punctuation', 'style', 'tabs', 'unicode', 'verbatim'],
            'table-grid-caption-span' => ['table'],
            'tracked-change-revision-policy' => ['deletion', 'insertion', 'move', 'paragraph_insertion', 'scrubbed', 'track_changes'],
        ];

        foreach ($keywordCategories as $category => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($fixtureLower, $needle)) {
                    $categories[] = $category;
                    break;
                }
            }
        }

        if (!$sameTopTypes) {
            $categories[] = 'block-structure-boundary';
        }
        if (!$sameText) {
            $categories[] = 'text-normalization';
        }

        $categories = array_values(array_unique($categories));
        if ($categories === []) {
            $categories[] = 'uncategorized-semantic-drift';
        }

        return $categories;
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

    private static function semanticParityStatus(int $parseFailureCount, int $semanticGapPairCount, int $comparedPairCount): string
    {
        if ($comparedPairCount === 0) {
            return 'not-evaluated-no-paired-fixtures';
        }
        if ($parseFailureCount > 0) {
            return 'blocked-by-parse-failures';
        }
        if ($semanticGapPairCount > 0) {
            return 'semantic-gaps-observed';
        }

        return 'smoke-text-and-top-types-match-not-full-parity';
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

    /**
     * @return list<array<string, mixed>>
     */
    private static function orderedRemainingGaps(
        bool $sourceDirectoryPresent,
        int $comparedPairCount,
        int $parseFailureCount,
        int $semanticGapPairCount,
        int $sameTextCount,
        int $sameTopTypeSequenceCount
    ): array {
        $sourceEvidence = $sourceDirectoryPresent
            ? "compared pairs={$comparedPairCount}; parse failures={$parseFailureCount}; semantic gap pairs={$semanticGapPairCount}; same text={$sameTextCount}; same top-type sequence={$sameTopTypeSequenceCount}"
            : 'optional upstream DOCX cache absent; smoke comparison did not run';

        return [
            [
                'rank' => 1,
                'id' => 'upstream-docx-runner-results',
                'status' => 'open',
                'currentEvidence' => 'No upstream Haskell/Cabal test-pandoc DOCX reader or writer runner result is recorded by this smoke lane.',
                'evidenceRequired' => 'Record reproducible upstream DOCX reader/writer runner results or a native-PHP equivalent denominator with per-fixture pass/fail rows.',
            ],
            [
                'rank' => 2,
                'id' => 'full-ast-equality',
                'status' => 'open',
                'currentEvidence' => $sourceEvidence . '; the smoke compares only plain text and top-level node type sequences.',
                'evidenceRequired' => 'Compare full AST structure, attributes, inline nodes, block nodes, metadata, notes, tables, and raw OpenXML payload handling against upstream .native expectations.',
            ],
            [
                'rank' => 3,
                'id' => 'writer-golden-docx-package-parity',
                'status' => 'open',
                'currentEvidence' => 'The smoke harness reads paired DOCX/native fixtures only; it does not generate DOCX or compare golden writer packages.',
                'evidenceRequired' => 'Generate DOCX output for upstream writer golden cases and compare package parts, relationships, content types, and document XML semantics.',
            ],
            [
                'rank' => 4,
                'id' => 'semantic-gap-zero-tolerance',
                'status' => !$sourceDirectoryPresent
                    ? 'not-evaluated'
                    : (($parseFailureCount === 0 && $semanticGapPairCount === 0)
                        ? 'not-observed-in-smoke'
                        : 'open'),
                'currentEvidence' => $sourceEvidence,
                'evidenceRequired' => 'Keep parse failures and semantic smoke gaps at zero across the full paired corpus before treating text/top-type smoke evidence as current.',
            ],
        ];
    }
}
